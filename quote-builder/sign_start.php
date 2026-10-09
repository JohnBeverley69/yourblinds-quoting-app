<?php
declare(strict_types=1);

/**
 * "Customer signs here" — open the customer's own quote page on THIS device in
 * in-person mode, so a customer without email/WhatsApp can sign on the
 * salesperson's tablet or phone. The page shows exactly what the customer sees
 * (no costs) plus a finger-signature box; accepting runs the normal customer
 * accept (quote-history/accept.php), which records the drawn signature.
 *
 * The public page only accepts a SENT quote, so a draft is marked sent first
 * (sent_at stamped) — it is being presented to the customer right now.
 */

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
$back     = '/quote-builder/edit.php?id=' . $quoteId;

$quote = qb_load_quote_or_404($quoteId, $clientId);
qb_require_quote_access($quote, $user, current_user_permissions());

$isAdmin = ($user['role'] ?? '') === 'admin';
if (!qb_user_can_change_to($isAdmin, current_user_permissions(), 'accepted')) {
    qb_flash_redirect($back, 'error', 'You don\'t have permission to take a customer\'s acceptance.');
}

$status = (string) $quote['status'];
if (!in_array($status, ['draft', 'sent'], true)) {
    qb_flash_redirect($back, 'error', 'This quote has already been ' . $status . '.');
}

$pdo = db();
$ic  = $pdo->prepare('SELECT COUNT(*) FROM quote_items WHERE quote_id = ?');
$ic->execute([$quoteId]);
if ((int) $ic->fetchColumn() === 0) {
    qb_flash_redirect($back, 'error', 'Add at least one blind before the customer signs.');
}

if ($status === 'draft') {
    $pdo->prepare(
        "UPDATE quotes SET status = 'sent', sent_at = COALESCE(sent_at, NOW())
          WHERE id = ? AND client_id = ? AND status = 'draft'"
    )->execute([$quoteId, $clientId]);
    require_once __DIR__ . '/../_partials/order_stage.php';
    recompute_order_stage($pdo, $quoteId);
}

require_once __DIR__ . '/../_partials/quote_expiry.php';
$fresh = qb_load_quote_or_404($quoteId, $clientId);
if (quote_is_expired($fresh)) {
    qb_flash_redirect($back, 'error', 'This quote has expired — renew it before the customer signs.');
}

header('Location: /quote-history/public.php?token=' . urlencode((string) $quote['public_token']) . '&in_person=1');
exit;
