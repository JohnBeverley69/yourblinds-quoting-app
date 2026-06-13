<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

csrf_check();

$user     = current_user();
$clientId = (int) $user['client_id'];
$quoteId  = (int) ($_POST['quote_id'] ?? 0);
$quote    = qb_load_quote_or_404($quoteId, $clientId);

// Deleting a whole quote/order is a back-office action — gate on admin or
// quote-creation rights so a fitter merely assigned to the job can't destroy
// it. 404 (not 403) so we don't confirm the quote exists to a guesser.
$perms = current_user_permissions();
if (($user['role'] ?? '') !== 'admin' && empty($perms['can_create_quotes'])) {
    http_response_code(404);
    exit('Quote not found.');
}

// Refuse if payments are recorded against it. The payments FK is ON DELETE
// SET NULL, so deleting would orphan those rows — they'd keep counting toward
// Accounts totals with no order to reconcile against. Mirrors the guard in
// quote-history/bulk_delete.php (single-delete previously bypassed it).
try {
    $payChk = db()->prepare('SELECT COUNT(*) FROM payments WHERE quote_id = ? AND client_id = ?');
    $payChk->execute([$quoteId, $clientId]);
    if ((int) $payChk->fetchColumn() > 0) {
        qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'error',
            'This order has payment(s) recorded against it — remove those first, then delete.');
    }
} catch (Throwable $e) { /* payments table absent (feature off) — nothing to orphan */ }

// Tenant-scoped delete. ON DELETE CASCADE on quote_items + quote_item_extras
// cleans up the children automatically.
db()->prepare('DELETE FROM quotes WHERE id = ? AND client_id = ?')
    ->execute([$quoteId, $clientId]);

$_SESSION['flash_success'] = 'Quote ' . $quote['quote_number'] . ' deleted.';
header('Location: /orders/index.php');
exit;
