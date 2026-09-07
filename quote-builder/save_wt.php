<?php
declare(strict_types=1);

/**
 * Save the per-quote WT charge (internal-only surcharge), then recompute
 * the quote totals so the WT folds into subtotal/VAT/total.
 *
 *   POST quote_id, wt_amount
 *
 * Gated by the tenant's feature_wt setting + can_create_quotes. Never exposed
 * to customers — the WT only lives on the builder.
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

// Feature must be enabled for this tenant.
$enabled = false;
try {
    $fs = db()->prepare('SELECT COALESCE(feature_wt, 0) FROM client_settings WHERE client_id = ? LIMIT 1');
    $fs->execute([$clientId]);
    $enabled = ((int) $fs->fetchColumn()) === 1;
} catch (Throwable $e) { /* column missing → disabled */ }
if (!$enabled) {
    qb_flash_redirect($backUrl, 'error', 'WT is not enabled — turn it on in Settings → Quoting first.');
}

// Non-negative money, bounded to avoid silly typos.
$wt = (float) ($_POST['wt_amount'] ?? 0);
$wt = max(0.0, min(99999.99, round($wt, 2)));

try {
    db()->prepare('UPDATE quotes SET wt_amount = ? WHERE id = ? AND client_id = ?')
        ->execute([$wt, $quoteId, $clientId]);
} catch (Throwable $e) {
    qb_flash_redirect($backUrl, 'error', 'Could not save — has migrate_wt_charge.php been run? ' . $e->getMessage());
}

qb_recompute_totals($quoteId);

qb_flash_redirect($backUrl, 'success', $wt > 0
    ? 'WT set to ' . qb_fmt_money($wt) . ' (internal — not shown to the customer).'
    : 'WT cleared.');
