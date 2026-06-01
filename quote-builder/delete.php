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

// Tenant-scoped delete. ON DELETE CASCADE on quote_items + quote_item_extras
// cleans up the children automatically.
db()->prepare('DELETE FROM quotes WHERE id = ? AND client_id = ?')
    ->execute([$quoteId, $clientId]);

$_SESSION['flash_success'] = 'Quote ' . $quote['quote_number'] . ' deleted.';
header('Location: /orders/index.php');
exit;
