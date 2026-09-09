<?php
declare(strict_types=1);

/**
 * Save the per-quote agreed-price override (the negotiated final INC-VAT total),
 * then recompute so net/VAT are derived backwards from it.
 *
 *   POST quote_id, price_override   (blank/empty = clear the override)
 *
 * Gated by can_create_quotes + an editable quote. The gap between the natural
 * line prices and the agreed price is shown to the customer as a "Discount"
 * line; clearing restores the natural line-item totals.
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
$isAdmin  = ($user['role'] ?? '') === 'admin';
$_perms   = current_user_permissions();
$clientId = (int) $user['client_id'];
$quoteId  = (int) ($_POST['quote_id'] ?? 0);
$quote    = qb_load_quote_or_404($quoteId, $clientId);
$backUrl  = '/quote-builder/edit.php?id=' . $quoteId;

qb_require_quote_access($quote, $user, current_user_permissions());

if (!$isAdmin && empty($_perms['can_create_quotes'])) {
    qb_flash_redirect($backUrl, 'error', 'You don\'t have permission to set this.');
}

if (!qb_is_editable($quote)) {
    qb_flash_redirect($backUrl, 'error', 'Quote is locked — reopen it to edit.');
}

// Blank input clears the override (revert to natural totals). Otherwise it's a
// non-negative money figure, bounded to avoid silly typos.
$raw = trim((string) ($_POST['price_override'] ?? ''));
$clear = ($raw === '');
$override = null;
if (!$clear) {
    $override = (float) $raw;
    $override = max(0.0, min(9999999.99, round($override, 2)));
}

try {
    if ($clear) {
        db()->prepare('UPDATE quotes SET price_override = NULL WHERE id = ? AND client_id = ?')
            ->execute([$quoteId, $clientId]);
    } else {
        db()->prepare('UPDATE quotes SET price_override = ? WHERE id = ? AND client_id = ?')
            ->execute([$override, $quoteId, $clientId]);
    }
} catch (Throwable $e) {
    qb_flash_redirect($backUrl, 'error', 'Could not save — has migrate_quote_price_override.php been run? ' . $e->getMessage());
}

qb_recompute_totals($quoteId);

qb_flash_redirect($backUrl, 'success', $clear
    ? 'Price override cleared — totals back to the calculated price.'
    : 'Price set to ' . qb_fmt_money((float) $override) . ' + VAT — the difference shows as a discount.');
