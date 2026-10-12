<?php
declare(strict_types=1);

/**
 * Factory action: Process order (see _partials/process_order.php).
 *
 * POST quote_id, back, _csrf            — take the order in, put its blinds on
 *                                         the floor, email every bought-in
 *                                         supplier its purchase order.
 * POST quote_id, back, supplier, _csrf  — on a processed order: send that one
 *                                         supplier what's outstanding (Retry
 *                                         after a failure, or a new item).
 *
 * Scoped to the acting factory (current_factory_id): its lines, its suppliers,
 * its sender details. Nothing here reads or writes another factory's records.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/blind_jobs.php';
require __DIR__ . '/../_partials/bought_in.php';
require __DIR__ . '/../_partials/factory_boughtin.php';
require __DIR__ . '/../_partials/order_tracks.php';
require __DIR__ . '/../_partials/order_stage.php';

requireFactoryOffice();

$backTo = ot_back_url($_POST['back'] ?? null);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . $backTo); exit; }
csrf_check();

$pdo       = db();
$factoryId = current_factory_id();
$quoteId   = (int) ($_POST['quote_id'] ?? 0);
$supplier  = trim((string) ($_POST['supplier'] ?? ''));
$userId    = (int) (current_user()['user_id'] ?? 0);

$fail = static function (string $msg) use ($backTo): void {
    $_SESSION['flash_error'] = $msg;
    header('Location: ' . $backTo);
    exit;
};

// This factory makes or supplies a line on it, and it's a placed order.
$own = $pdo->prepare(
    'SELECT 1 FROM quote_items qi JOIN products p ON p.id = qi.product_id
      WHERE qi.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ? LIMIT 1'
);
$own->execute([$quoteId, $factoryId]);
if ($quoteId <= 0 || !$own->fetchColumn()) $fail('That order could not be processed.');

$q = $pdo->prepare('SELECT quote_number, status FROM quotes WHERE id = ? LIMIT 1');
$q->execute([$quoteId]);
$quote = $q->fetch(PDO::FETCH_ASSOC) ?: [];
if (!in_array((string) ($quote['status'] ?? ''), os_placed_statuses(), true)) {
    $fail("That order hasn't been placed yet, so it can't be processed.");
}
$ref = (string) ($quote['quote_number'] ?? ('#' . $quoteId));

// One at a time per order: two quick clicks (or two people) must not email a
// supplier twice. The second waits, then finds nothing outstanding.
$lockName = 'yb_process_order_' . $quoteId;
try { $pdo->query("SELECT GET_LOCK(" . $pdo->quote($lockName) . ", 15)"); } catch (Throwable $e) {}

$fjStatus = '';
try {
    $s = $pdo->prepare('SELECT status FROM factory_jobs WHERE quote_id = ? LIMIT 1');
    $s->execute([$quoteId]);
    $fjStatus = (string) ($s->fetchColumn() ?: '');
} catch (Throwable $e) { $fail('Could not read the order — have the factory_jobs migrations been run?'); }

$said = [];
if ($supplier === '') {
    if ($fjStatus !== '') {
        try { $pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote($lockName) . ")"); } catch (Throwable $e) {}
        $fail($ref . ' has already been processed.');
    }
    // 1–2. Take it in; anything made here goes on the floor. All bought in:
    // taken in, and the POs below move it on.
    $mk = $pdo->prepare(
        'SELECT 1 FROM quote_items qi JOIN products p ON p.id = qi.product_id '
        . bought_in_master_join('p', 'mp') . '
          WHERE qi.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
            AND ' . bought_in_inhouse_predicate('mp') . ' LIMIT 1'
    );
    $mk->execute([$quoteId, $factoryId]);
    $target = $mk->fetchColumn() ? 'in_production' : 'received';
    try {
        $pdo->prepare(
            "INSERT INTO factory_jobs (quote_id, status, status_at, status_by, received_at, received_by)
             VALUES (?, ?, NOW(), ?, NOW(), ?)
             ON DUPLICATE KEY UPDATE
                 status      = VALUES(status),
                 status_at   = NOW(),
                 status_by   = VALUES(status_by),
                 received_at = COALESCE(received_at, VALUES(received_at)),
                 received_by = COALESCE(received_by, VALUES(received_by))"
        )->execute([$quoteId, $target, $userId ?: null, $userId ?: null]);
    } catch (Throwable $e) {
        $fail('Could not process the order: ' . $e->getMessage() . ' — have the factory_jobs migrations been run?');
    }
    if ($target === 'in_production' && bj_tables_ready($pdo)) {
        $n = bj_release_order($pdo, $quoteId, $factoryId);
        if ($n > 0) $said[] = $n . ' blind' . ($n === 1 ? '' : 's') . ' on the floor';
    }
} elseif ($fjStatus === '') {
    // A supplier send on its own is for a processed order; a new one goes
    // through Process so it's taken in too.
    try { $pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote($lockName) . ")"); } catch (Throwable $e) {}
    $fail('Process ' . $ref . ' first — that sends its purchase orders too.');
}

// 3. The purchase orders: every supplier still to order, or just the one asked for.
$sent = []; $failed = [];
foreach (factory_send_outstanding($pdo, $quoteId, $factoryId, $userId, $supplier !== '' ? $supplier : null) as $sup => $r) {
    $items = (int) $r['items'] . ' ' . ($r['addition'] ? 'new item' : 'item') . ((int) $r['items'] === 1 ? '' : 's');
    if ($r['ok']) $sent[] = $sup . ' (' . $items . ')';
    else          $failed[] = "Couldn't order from " . $sup . ': ' . $r['reason'];
}
if ($supplier === '' && !$sent && !$failed) factory_boughtin_restamp($pdo, $quoteId, $factoryId);

recompute_order_stage($pdo, $quoteId);
try { $pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote($lockName) . ")"); } catch (Throwable $e) {}

if ($sent) $said[] = 'ordered from ' . implode(', ', $sent);
if ($supplier === '') {
    $_SESSION['flash_success'] = 'Processed ' . $ref . ($said ? ': ' . implode(' · ', $said) : '') . '.';
} elseif ($sent) {
    $_SESSION['flash_success'] = $ref . ': ' . implode(' · ', $said) . '.';
} elseif (!$failed) {
    $_SESSION['flash_success'] = 'Nothing left to order from ' . $supplier . ' on ' . $ref . '.';
}
if ($failed) {
    $_SESSION['flash_error'] = implode(' ', $failed) . ' It stays in "Couldn\'t order" — fix it, then press Retry.';
}
header('Location: ' . $backTo);
exit;
