<?php
declare(strict_types=1);

/**
 * Save a blind's fascia-group tag, then reconcile the fascia across the group
 * and recompute totals.
 *
 *   POST quote_id, item_id, fascia_group  (blank = ungroup)
 *
 * Blinds sharing the same tag share one continuous fascia: one carrier line
 * carries it at the group's total width, the others become "No Fascia". See
 * qb_reconcile_fascia_groups().
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';
require __DIR__ . '/../_partials/pricing_engine.php';
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
$isAdmin  = ($user['role'] ?? '') === 'admin';
$_perms   = current_user_permissions();
$clientId = (int) $user['client_id'];
$quoteId  = (int) ($_POST['quote_id'] ?? 0);
$itemId   = (int) ($_POST['item_id']  ?? 0);
$quote    = qb_load_quote_or_404($quoteId, $clientId);
$backUrl  = '/quote-builder/edit.php?id=' . $quoteId;

qb_require_quote_access($quote, $user, current_user_permissions());

if (!$isAdmin && empty($_perms['can_create_quotes'])) {
    qb_flash_redirect($backUrl, 'error', 'You don\'t have permission to set this.');
}
if (!qb_is_editable($quote)) {
    qb_flash_redirect($backUrl, 'error', 'Quote is locked — reopen it to edit.');
}

// Tag: blank (ungroup) or a short uppercased token (A / B / 1 …). Kept tiny.
$tag = strtoupper(trim((string) ($_POST['fascia_group'] ?? '')));
$tag = preg_replace('/[^A-Z0-9]/', '', $tag);
$tag = substr((string) $tag, 0, 8);
$store = $tag === '' ? null : $tag;

$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE quote_items SET fascia_group = ? WHERE id = ? AND quote_id = ?')
        ->execute([$store, $itemId, $quoteId]);
    qb_reconcile_fascia_groups($pdo, $quoteId, $clientId, (int) ($quote['account_client_id'] ?? 0));
    qb_recompute_totals($quoteId);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    qb_flash_redirect($backUrl, 'error', 'Could not set the fascia group: ' . $e->getMessage()
        . ' — has migrate_fascia_group.php been run?');
}

qb_flash_redirect($backUrl, 'success', $store !== null
    ? 'Fascia group ' . $store . ' set — shared fascia reconciled across the group.'
    : 'Removed from its fascia group. If it was a member, re-pick its fascia if needed.');
