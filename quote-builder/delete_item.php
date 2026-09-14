<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';
require __DIR__ . '/../_partials/pricing_engine.php';     // qb_reconcile_fascia_groups re-prices
require __DIR__ . '/../_partials/price_table_parser.php';
require __DIR__ . '/../_partials/units.php';

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
$itemId   = (int) ($_POST['item_id']  ?? 0);
$quote    = qb_load_quote_or_404($quoteId, $clientId);
qb_require_quote_access($quote, $user, current_user_permissions());

if (!qb_is_editable($quote)) {
    qb_flash_redirect(
        '/quote-builder/edit.php?id=' . $quoteId,
        'error',
        'Quote is locked. Reopen it to remove blinds.'
    );
}

// Ownership check: the item must belong to this quote. Deleting a blind can
// change a fascia group (e.g. remove the carrier), so reconcile + recompute
// afterwards, in one transaction.
$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->prepare('DELETE FROM quote_items WHERE id = ? AND quote_id = ?')->execute([$itemId, $quoteId]);
    qb_reconcile_fascia_groups($pdo, $quoteId, $clientId, (int) ($quote['account_client_id'] ?? 0));
    qb_recompute_totals($quoteId);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'error', 'Could not remove the blind: ' . $e->getMessage());
}

qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'success', 'Blind removed.');
