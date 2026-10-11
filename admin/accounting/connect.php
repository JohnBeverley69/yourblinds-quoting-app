<?php
declare(strict_types=1);

/**
 * Start the OAuth connect flow for an accounting provider.
 * GET /admin/accounting/connect.php?provider=quickbooks
 *
 * Stashes an anti-CSRF `state` + the chosen provider in the session, then
 * redirects the admin to the provider's authorize page. The provider bounces
 * back to callback.php.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';
require __DIR__ . '/../../_partials/accounting.php';

requireAdmin();
$user     = current_user();
$for      = (string) ($_GET['for'] ?? '') === 'factory' ? 'factory' : '';
$clientId = ac_target_client_id($for);   // for=factory: the platform's own books (super-admin)
$back     = ac_return_url($for);

$providerKey = (string) ($_GET['provider'] ?? 'quickbooks');
$prov = ac_provider($providerKey);

if (!$prov) {
    $_SESSION['flash_error'] = 'Unknown accounting provider.';
    header('Location: ' . $back);
    exit;
}
if (!$prov->isConfigured()) {
    $_SESSION['flash_error'] = $prov->label() . ' is not set up yet — the app keys are missing. '
        . 'Add QUICKBOOKS_CLIENT_ID / QUICKBOOKS_CLIENT_SECRET to the server config first.';
    header('Location: ' . $back);
    exit;
}

$state = bin2hex(random_bytes(16));
$_SESSION['ac_oauth'] = [
    'state'     => $state,
    'provider'  => $providerKey,
    'client_id' => $clientId,
    'for'       => $for,
    'ts'        => time(),
];

header('Location: ' . $prov->authorizeUrl($state));
exit;
