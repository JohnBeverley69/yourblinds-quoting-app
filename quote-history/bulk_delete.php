<?php
declare(strict_types=1);

/**
 * Bulk-delete quotes from /quote-history/index.php. POST-only.
 *
 * Body shape:
 *   csrf_token
 *   quote_ids[] = N, M, ...
 *
 * Tenant-scoped: the DELETE has WHERE client_id = ? so a crafted form
 * can't reach into another tenant's quotes even with a valid CSRF.
 * ON DELETE CASCADE on quote_items + quote_item_extras + appointments
 * handles the children.
 *
 * Redirects back to /quote-history/index.php with a flash message
 * stating how many were removed. Idempotent w.r.t. already-gone rows
 * (DELETE just returns 0 rowCount for them).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

csrf_check();

$user     = current_user();
$clientId = (int) $user['client_id'];

$ids = $_POST['quote_ids'] ?? [];
if (!is_array($ids)) $ids = [];
$ids = array_values(array_unique(array_filter(
    array_map('intval', $ids),
    static fn ($n) => $n > 0
)));

// Preserve the filter / search the user was on so the redirect lands
// them back on the same view — useful when they're cleaning up a
// "drafts only" list and want to keep filtering after the delete.
$status = trim((string) ($_POST['return_status'] ?? ''));
$q      = trim((string) ($_POST['return_q'] ?? ''));
$qs     = [];
if ($status !== '') $qs[] = 'status=' . urlencode($status);
if ($q      !== '') $qs[] = 'q='      . urlencode($q);
$back   = '/quote-history/index.php' . ($qs ? '?' . implode('&', $qs) : '');

if (!$ids) {
    $_SESSION['flash_error'] = 'No quotes selected.';
    header('Location: ' . $back);
    exit;
}

$pdo = db();
$ph  = implode(',', array_fill(0, count($ids), '?'));

// Refuse to silently bin quotes that have payment rows attached —
// payments.quote_id is ON DELETE SET NULL, so the rows would become
// orphans in /accounts (linked to no order). Surface them to the
// user so they can decide: delete the payments first, OR keep the
// quote for the audit trail.
//
// Defensive: if the payments table doesn't exist yet (migration not
// run), skip this check entirely. The DELETE below works either way.
$blocked = [];
try {
    $payStmt = $pdo->prepare(
        "SELECT q.id, q.quote_number, COUNT(p.id) AS n_payments
           FROM quotes q
           JOIN payments p ON p.quote_id = q.id
          WHERE q.id IN ($ph) AND q.client_id = ?
       GROUP BY q.id, q.quote_number"
    );
    $payStmt->execute(array_merge($ids, [$clientId]));
    foreach ($payStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $blocked[(int) $r['id']] = (string) $r['quote_number'];
    }
} catch (Throwable $e) {
    // payments table missing — skip the check, proceed with all.
}

$deletable = array_values(array_diff(
    array_map('intval', $ids), array_keys($blocked)
));

$deleted = 0;
if ($deletable) {
    $delPh  = implode(',', array_fill(0, count($deletable), '?'));
    $stmt   = $pdo->prepare(
        "DELETE FROM quotes WHERE id IN ($delPh) AND client_id = ?"
    );
    $stmt->execute(array_merge($deletable, [$clientId]));
    $deleted = $stmt->rowCount();
}

$msgs = [];
if ($deleted > 0) {
    $msgs[] = ($deleted === 1 ? '1 quote' : "$deleted quotes") . ' deleted.';
}
if ($blocked) {
    $list = implode(', ', $blocked);
    $msgs[] = (count($blocked) === 1
        ? '1 quote was kept because it has payment(s) recorded against it: '
        : count($blocked) . ' quotes were kept because they have payments recorded against them: ')
        . $list
        . '. Delete the payments first if you really want to remove these.';
}

if (!$msgs) {
    $_SESSION['flash_error'] = 'No quotes deleted.';
} elseif ($blocked) {
    // Use error styling so the "kept" message is conspicuous; the
    // success bit is included in the same line so it doesn't get lost.
    $_SESSION['flash_error'] = implode(' ', $msgs);
} else {
    $_SESSION['flash_success'] = implode(' ', $msgs);
}

header('Location: ' . $back);
exit;
