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
$itemId   = (int) ($_POST['item_id']  ?? 0);
$quote    = qb_load_quote_or_404($quoteId, $clientId);

if (!qb_is_editable($quote)) {
    qb_flash_redirect(
        '/quote-builder/edit.php?id=' . $quoteId,
        'error',
        'Quote is locked. Reopen it to remove blinds.'
    );
}

// Ownership check: the item must belong to this quote.
$st = db()->prepare(
    'DELETE FROM quote_items WHERE id = ? AND quote_id = ?'
);
$st->execute([$itemId, $quoteId]);

qb_recompute_totals($quoteId);

qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'success', 'Blind removed.');
