<?php
declare(strict_types=1);

/**
 * Save a direct order's "Sold for" price — what the client's own customer is
 * paying (inc or ex VAT). Only for the client's own figures (Dashboard sales
 * and profit); it never reaches the factory. Allowed at any status, because
 * the price is often agreed after the order is placed.
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
$quote    = qb_load_quote_or_404($quoteId, $clientId);
$perms    = current_user_permissions();
qb_require_quote_access($quote, $user, $perms);

$back = '/quote-builder/edit.php?id=' . $quoteId;
if (!qb_is_direct_order($quote)) {
    qb_flash_redirect($back, 'error', 'Sold for is only on direct orders.');
}
if (($user['role'] ?? '') !== 'admin' && empty($perms['can_create_orders']) && empty($perms['can_create_quotes'])) {
    qb_flash_redirect($back, 'error', 'You don\'t have permission to change this order.');
}

try {
    qb_save_sold_for(db(), $quoteId, $clientId, (string) ($_POST['sold_for'] ?? ''), !empty($_POST['sold_for_inc_vat']));
} catch (Throwable $e) {
    qb_flash_redirect($back, 'error', 'Could not save the price — run /setup/migrations/migrate_direct_orders.php. (' . $e->getMessage() . ')');
}
// The message has to describe what was actually STORED, not what was typed.
// qb_save_sold_for() (_helpers.php:147) treats anything non-numeric or <= 0 the
// same as blank and NULLs all four sold_for_* columns, so typing 0 — a free
// replacement, or a slip while clearing the field — cleared the price while the
// page said "this order now counts in your sales and profit figures". It
// doesn't: the Dashboard keeps it out of revenue and profit and still lists it
// under "Direct orders — no selling price", which filters on
// sold_for_net IS NULL.
$sfRaw   = trim(str_replace([',', '£'], '', (string) ($_POST['sold_for'] ?? '')));
$sfKept  = $sfRaw !== '' && is_numeric($sfRaw) && (float) $sfRaw > 0;
qb_flash_redirect($back, 'success', $sfKept
    ? 'Sold for price saved — this order now counts in your sales and profit figures.'
    : 'Sold for price cleared — this order won’t count towards sales or profit until you enter what you sold it for.');
