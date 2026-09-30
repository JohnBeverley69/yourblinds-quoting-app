<?php
declare(strict_types=1);

/**
 * Factory action: move an incoming order through the production flow.
 *
 * POST: quote_id, status (target: new|received|in_production|made|dispatched),
 *       _csrf. Factory staff only; the order must carry Beverley lines.
 *
 * 'new' removes the factory_jobs row (order drops back to unactioned). Any
 * other target upserts the row with the new status + who/when.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/blind_jobs.php';

requireFactoryOffice();

$backTo = '/factory/incoming-orders.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $backTo);
    exit;
}
csrf_check();

/** The production flow, in order. 'new' = no factory_jobs row. */
const FACTORY_STAGES = ['new', 'received', 'in_production', 'made', 'dispatched'];
const FACTORY_STAGE_LABELS = [
    'new'           => 'new',
    'received'      => 'received',
    'in_production' => 'in production',
    'made'          => 'made',
    'dispatched'    => 'dispatched',
];

$pdo     = db();
$MASTER  = current_factory_id();
$quoteId = (int) ($_POST['quote_id'] ?? 0);
$target  = (string) ($_POST['status'] ?? '');
$user    = current_user();
$userId  = (int) ($user['user_id'] ?? 0);

if (!in_array($target, FACTORY_STAGES, true)) {
    $_SESSION['flash_error'] = 'Unknown production stage.';
    header('Location: ' . $backTo);
    exit;
}

// Guard: only orders that actually carry Beverley lines can be actioned.
$isBev = false;
if ($quoteId > 0) {
    $chk = $pdo->prepare(
        "SELECT 1 FROM quote_items qi
           JOIN products p ON p.id = qi.product_id
          WHERE qi.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ? LIMIT 1"
    );
    $chk->execute([$quoteId, $MASTER]);
    $isBev = (bool) $chk->fetchColumn();
}

if ($quoteId <= 0 || !$isBev) {
    $_SESSION['flash_error'] = 'That order could not be actioned.';
    header('Location: ' . $backTo);
    exit;
}

// Guard: only a PLACED order is the factory's to action. A tenant's draft /
// sent / accepted quote carries factory lines too, but it hasn't been ordered —
// the factory must not start (or dispatch) work on it by typing its id.
$stq = $pdo->prepare('SELECT status FROM quotes WHERE id = ? LIMIT 1');
$stq->execute([$quoteId]);
if (!in_array((string) ($stq->fetchColumn() ?: ''), ['ordered', 'fitted', 'invoiced', 'paid'], true)) {
    $_SESSION['flash_error'] = "That order hasn't been placed yet, so it can't be actioned.";
    header('Location: ' . $backTo);
    exit;
}

// "Mark made" is normally the floor's job (the last blind finishing nudges the
// order to made). When the office marks it made while blinds are still open on
// the floor, it must be a deliberate override (force_made, confirmed on the
// button) — and then the remaining blinds are completed explicitly so the floor
// count and the order status agree, rather than silently disagreeing.
$forcedBlinds = 0;
if ($target === 'made' && bj_tables_ready($pdo)) {
    $prog = bj_order_progress($pdo, [$quoteId])[$quoteId] ?? ['total' => 0, 'done' => 0];
    if ((int) $prog['total'] > 0 && (int) $prog['done'] < (int) $prog['total']) {
        if (empty($_POST['force_made'])) {
            $_SESSION['flash_error'] = 'The floor still has ' . ((int) $prog['total'] - (int) $prog['done']) . ' of '
                . (int) $prog['total'] . ' blinds to finish, so the order was not marked made. '
                . 'Finish them on the floor, or confirm "Mark made" to complete them.';
            header('Location: ' . $backTo);
            exit;
        }
        try {
            $forcedBlinds = bj_force_complete_order($pdo, $quoteId, $userId ?: null);
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not complete the blinds on the floor: ' . $e->getMessage();
            header('Location: ' . $backTo);
            exit;
        }
    }
}

// Gate dispatch on Ready: every in-house blind made AND every bought-in line
// received (Phase 1 — previously only the bought-in half was enforced, so an
// order could ship with blinds still unfinished on the floor).
if ($target === 'dispatched') {
    require_once __DIR__ . '/../_partials/order_stage.php';
    if (!os_is_ready($pdo, $quoteId, $MASTER)) {
        $_SESSION['flash_error'] = "Can't dispatch yet — the order isn't ready. Every blind must be made and every bought-in item received first.";
        header('Location: ' . $backTo);
        exit;
    }
}

try {
    if ($target === 'new') {
        $pdo->prepare("DELETE FROM factory_jobs WHERE quote_id = ?")->execute([$quoteId]);
        // Reset pulls the order's blinds back off the floor too.
        if (bj_tables_ready($pdo)) bj_clear_order($pdo, $quoteId);
        $_SESSION['flash_success'] = 'Order moved back to new.';
    } else {
        $recvAt = $target === 'received' ? date('Y-m-d H:i:s') : null;
        $recvBy = $target === 'received' ? ($userId ?: null)   : null;
        $pdo->prepare(
            "INSERT INTO factory_jobs (quote_id, status, status_at, status_by, received_at, received_by)
             VALUES (?, ?, NOW(), ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 status      = VALUES(status),
                 status_at   = NOW(),
                 status_by   = VALUES(status_by),
                 received_at = COALESCE(received_at, VALUES(received_at)),
                 received_by = COALESCE(received_by, VALUES(received_by))"
        )->execute([$quoteId, $target, $userId ?: null, $recvAt, $recvBy]);

        // Moving into production releases the order's Beverley blinds onto the
        // floor (idempotent — re-entering production won't duplicate them).
        $released = '';
        if ($target === 'in_production' && bj_tables_ready($pdo)) {
            $n = bj_release_order($pdo, $quoteId, $MASTER);
            if ($n > 0) $released = " {$n} blind" . ($n === 1 ? '' : 's') . ' released to the floor.';
        }
        if ($forcedBlinds > 0) {
            $released .= " {$forcedBlinds} unfinished blind" . ($forcedBlinds === 1 ? ' was' : 's were') . ' marked complete on the floor.';
        }
        $_SESSION['flash_success'] = 'Order moved to ' . (FACTORY_STAGE_LABELS[$target] ?? $target) . '.' . $released;
    }
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Could not update the order: ' . $e->getMessage()
        . ' — have the factory_jobs migrations been run?';
}

// Phase 1: dispatching on the floor issues the delivery note too — one dispatch,
// one delivery note, whichever path is used.
require_once __DIR__ . '/../_partials/order_stage.php';
if ($target === 'dispatched') {
    os_mark_dispatched_dn($pdo, $quoteId, $MASTER, $userId ?: null);
    // Phase 2: auto-invoice on dispatch, only if the setting is on (default off).
    os_auto_invoice_on_dispatch($pdo, $quoteId, $MASTER, $userId ?: null);
}
// Phase 0: roll the single fulfilment stage up from this floor status change.
recompute_order_stage($pdo, $quoteId);

header('Location: ' . $backTo);
exit;
