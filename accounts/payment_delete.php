<?php
declare(strict_types=1);

/**
 * Delete a payment. POST + CSRF + tenant-scoped.
 *
 * Posted fields:
 *   id          required
 *   return_to   optional — URL to redirect back to
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../quote-builder/_helpers.php';   // qb_settle_if_paid

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

csrf_check();

$user     = current_user();
$clientId = (int) $user['client_id'];

// Paid add-on — gate at the handler too, not just the UI.
acct_require_feature($clientId);

$id       = (int) ($_POST['id']        ?? 0);
$returnTo = safe_local_redirect(
    (string) ($_POST['return_to'] ?? ''), '/accounts/index.php'
);

if ($id <= 0) {
    $_SESSION['flash_error'] = 'No payment id supplied.';
    header('Location: ' . $returnTo);
    exit;
}

// Grab the linked quote before deleting so we can re-settle its paid status
// (removing money may drop a "paid" order back below its total).
$qSt = db()->prepare('SELECT quote_id FROM payments WHERE id = ? AND client_id = ? LIMIT 1');
$qSt->execute([$id, $clientId]);
$linkedQuoteId = (int) ($qSt->fetchColumn() ?: 0);

$st = db()->prepare(
    'DELETE FROM payments WHERE id = ? AND client_id = ?'
);
$st->execute([$id, $clientId]);

if ($st->rowCount() === 1 && $linkedQuoteId > 0) {
    qb_settle_if_paid(db(), $linkedQuoteId, $clientId);
}

$_SESSION['flash_success'] = $st->rowCount() === 1
    ? 'Payment deleted.'
    : 'Payment not found.';
header('Location: ' . $returnTo);
exit;
