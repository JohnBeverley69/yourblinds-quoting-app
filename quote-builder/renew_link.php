<?php
declare(strict_types=1);

/**
 * "Renew for 30 days" — restart a sent quote's acceptance window from today
 * (see _partials/quote_expiry.php). For quotes shared by WhatsApp / copied
 * link rather than emailed (re-sending by email renews automatically).
 * POST + CSRF; same access gate as the rest of the quote builder.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../_partials/quote_expiry.php';

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

$quote = qb_load_quote_or_404($quoteId, $clientId);
qb_require_quote_access($quote, $user, current_user_permissions());

$back = '/quote-builder/edit.php?id=' . $quoteId;
if ((string) $quote['status'] !== 'sent') {
    qb_flash_redirect($back, 'error', 'Only a sent quote that is awaiting the customer can be renewed.');
}

db()->prepare('UPDATE quotes SET sent_at = NOW() WHERE id = ? AND client_id = ?')
    ->execute([$quoteId, $clientId]);

qb_flash_redirect($back, 'success', 'Renewed — the customer can accept this quote for another '
    . QUOTE_ACCEPT_DAYS . ' days (until ' . date('j F Y', time() + QUOTE_ACCEPT_DAYS * 86400) . ').');
