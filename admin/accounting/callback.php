<?php
declare(strict_types=1);

/**
 * OAuth redirect target for accounting providers.
 * GET /admin/accounting/callback.php?code=...&state=...&realmId=...
 *
 * This exact URL must be registered as the app's Redirect URI at the provider
 * (Intuit): https://yourblinds.uk/admin/accounting/callback.php
 *
 * Verifies the `state` we stashed in connect.php (anti-CSRF), exchanges the code
 * for tokens via the provider, and stores the per-tenant connection.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';
require __DIR__ . '/../../_partials/accounting.php';

requireAdmin();
$user     = current_user();
$clientId = (int) $user['client_id'];

$sess = $_SESSION['ac_oauth'] ?? null;
unset($_SESSION['ac_oauth']);   // one-shot

$err = (string) ($_GET['error'] ?? '');
if ($err !== '') {
    $_SESSION['flash_error'] = 'Connection cancelled or refused by the provider (' . $err . ').';
    header('Location: /admin/settings.php#accounting');
    exit;
}

$state = (string) ($_GET['state'] ?? '');
if (!$sess || !hash_equals((string) ($sess['state'] ?? ''), $state) || $state === '') {
    $_SESSION['flash_error'] = 'Security check failed on the accounting connection (state mismatch). Please try connecting again.';
    header('Location: /admin/settings.php#accounting');
    exit;
}
// The connection must finish on the same account that started it.
if ((int) ($sess['client_id'] ?? 0) !== $clientId) {
    $_SESSION['flash_error'] = 'Accounting connection was started on a different account. Please try again.';
    header('Location: /admin/settings.php#accounting');
    exit;
}
// Guard against a stale handshake (authorize codes are short-lived anyway).
if ((int) ($sess['ts'] ?? 0) < time() - 900) {
    $_SESSION['flash_error'] = 'The connection took too long and expired. Please try connecting again.';
    header('Location: /admin/settings.php#accounting');
    exit;
}

$providerKey = (string) ($sess['provider'] ?? 'quickbooks');
$prov = ac_provider($providerKey);
if (!$prov) {
    $_SESSION['flash_error'] = 'Unknown accounting provider.';
    header('Location: /admin/settings.php#accounting');
    exit;
}

try {
    $fields = $prov->handleCallback($_GET);
    ac_save_connection($clientId, $providerKey, $fields);
    $co = $fields['company_name'] ?? $prov->label();
    $_SESSION['flash_success'] = 'Connected to ' . $prov->label() . ' (' . $co . ').';
} catch (Throwable $e) {
    error_log('Accounting connect failed (client ' . $clientId . ', ' . $providerKey . '): ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Could not complete the ' . $prov->label() . ' connection: ' . $e->getMessage();
}

header('Location: /admin/settings.php#accounting');
exit;
