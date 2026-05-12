<?php
declare(strict_types=1);

/**
 * Migration: per-tenant feature flag for the Accounts (payments) module.
 *
 * Adds client_settings.feature_accounts (TINYINT, default 0 = disabled).
 * Master admin can toggle this on per tenant via /master-admin/index.php
 * (it picks up automatically because _partials/feature_flags.php is the
 * registry that page reads from).
 *
 * Gating behaviour:
 *   - Sidebar "Accounts" link hidden when off.
 *   - /accounts/* pages 403 when off (server-side enforcement, not just
 *     UI hiding).
 *   - Quote-edit Payments panel hidden when off.
 *   - Orders page Outstanding column hidden when off.
 *
 * Deposits are NOT gated — they're a core "order management" feature,
 * not part of the paid accounts add-on.
 *
 * Idempotent. Run via /migrate_feature_accounts.php (super-admin).
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}

ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

set_exception_handler(function (Throwable $e) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "MIGRATION FAILED\n================\n\n";
    echo 'Error: ' . $e->getMessage() . "\n";
    echo 'In:    ' . $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
});

$st = $pdo->prepare(
    'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
);
$st->execute(['client_settings', 'feature_accounts']);

if ($st->fetchColumn() === false) {
    $pdo->exec(
        'ALTER TABLE client_settings
            ADD COLUMN feature_accounts TINYINT(1) NOT NULL DEFAULT 0
            AFTER feature_postcode_lookup'
    );
    echo "client_settings.feature_accounts: added (default 0 = disabled)\n";
} else {
    echo "client_settings.feature_accounts: already present (skipped)\n";
}

echo "\n";
echo "Feature flag wired. Visit /master-admin/index.php to enable\n";
echo "Accounts for specific tenants. Until you tick the box for a\n";
echo "tenant, they won't see the Accounts sidebar entry, the Payments\n";
echo "panel on quote-edit, or the Outstanding column on Orders.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
