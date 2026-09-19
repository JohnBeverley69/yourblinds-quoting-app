<?php
declare(strict_types=1);

/**
 * Super-admin: accounting-integration health/diagnostic.
 *
 * Read-only. Answers "why isn't QuickBooks switched on?" WITHOUT ever printing
 * secret values — it reports only whether each config key is PRESENT, the
 * computed (non-secret) redirect URI + environment, where the app reads .env
 * from, and whether the connections table exists. Safe to leave in place.
 *
 * /master-admin/accounting-health.php
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/accounting.php';

requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');

function present(?string $v): string { return ($v !== null && $v !== '') ? 'PRESENT' : 'MISSING'; }

$envFile = APP_ROOT . '/.env';

echo "=== Accounting integration health ===\n\n";

echo "APP_ROOT                : " . APP_ROOT . "\n";
echo ".env expected at        : " . $envFile . "\n";
echo ".env file exists?       : " . (is_readable($envFile) ? 'YES' : 'NO — app is not reading a .env at this path') . "\n";
if (is_readable($envFile)) {
    echo ".env last modified      : " . date('Y-m-d H:i:s', (int) @filemtime($envFile)) . "\n";
    echo ".env size (bytes)       : " . (int) @filesize($envFile) . "\n";
    // KEY NAMES ONLY (never values) actually present in the file the app reads.
    $names = [];
    foreach ((array) @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        $ln = trim((string) $ln);
        if ($ln === '' || $ln[0] === '#') continue;
        $eq = strpos($ln, '=');
        if ($eq === false) continue;
        $k = trim(substr($ln, 0, $eq));
        if ($k !== '' && preg_match('/^[A-Z_][A-Z0-9_]*$/i', $k)) $names[] = $k;
    }
    echo ".env keys present       : " . ($names ? implode(', ', $names) : '(none parsed)') . "\n";
}
echo "APP_URL                 : " . (string) (env('APP_URL', '(unset)')) . "\n";
echo "APP_ENCRYPTION_KEY      : " . present(env('APP_ENCRYPTION_KEY', '')) . "\n\n";

echo "--- QuickBooks env vars (values never shown) ---\n";
echo "QUICKBOOKS_ENV          : " . (string) (env('QUICKBOOKS_ENV', '(unset)')) . "\n";
echo "QUICKBOOKS_CLIENT_ID    : " . present(env('QUICKBOOKS_CLIENT_ID', '')) . "\n";
echo "QUICKBOOKS_CLIENT_SECRET: " . present(env('QUICKBOOKS_CLIENT_SECRET', '')) . "\n";
echo "QUICKBOOKS_REDIRECT_URI : " . (string) (env('QUICKBOOKS_REDIRECT_URI', '(unset — will derive from APP_URL)')) . "\n\n";

$qbo = ac_provider('quickbooks');
echo "--- Provider view ---\n";
echo "environment()           : " . $qbo->environment() . "\n";
echo "isConfigured()          : " . ($qbo->isConfigured() ? 'YES ✓' : 'NO ✗') . "\n";
echo "redirectUri()           : " . $qbo->redirectUri() . "\n";
echo "  (this MUST match the Redirect URI registered on the Intuit app EXACTLY)\n\n";

echo "connections table       : " . (ac_table_exists() ? 'exists' : 'MISSING — run /migrate_accounting_connections.php') . "\n";

echo "\nDone.\n";
