<?php
declare(strict_types=1);

/**
 * Disconnect an accounting provider for this tenant.
 * POST /admin/accounting/disconnect.php   (provider=quickbooks, _csrf)
 *
 * Best-effort revokes the token at the provider, then removes the stored
 * connection so a re-connect starts clean.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';
require __DIR__ . '/../../_partials/accounting.php';

requireAdmin();
$user     = current_user();
$for      = (string) ($_POST['for'] ?? $_GET['for'] ?? '') === 'factory' ? 'factory' : '';
$clientId = ac_target_client_id($for);   // for=factory: the platform's own books (super-admin)
$back     = ac_return_url($for);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $back);
    exit;
}
csrf_check();

$providerKey = (string) ($_POST['provider'] ?? 'quickbooks');
$prov = ac_provider($providerKey);

if ($prov) {
    $conn = ac_connection_plain($clientId, $providerKey);
    if ($conn) {
        $prov->disconnect($conn);   // never throws
    }
    ac_delete_connection($clientId, $providerKey);
    ac_log($clientId, $providerKey, 'disconnect', 'ok', 'Disconnected by ' . (string) ($user['full_name'] ?? 'an admin'));
    $_SESSION['flash_success'] = 'Disconnected from ' . $prov->label() . '.';
} else {
    $_SESSION['flash_error'] = 'Unknown accounting provider.';
}

header('Location: ' . $back);
exit;
