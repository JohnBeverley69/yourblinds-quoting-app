<?php
declare(strict_types=1);

/**
 * Migration: global (installation-wide) key/value settings.
 *
 * Adds app_settings — a tiny store for flags that aren't per-tenant. First
 * use: email_paused ("testing mode" — stop ALL outgoing email site-wide while
 * a QA tester is poking the app, so they can't email a real supplier/customer).
 *
 * Idempotent. Run via /migrate_app_settings.php (super-admin), then delete.
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

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS app_settings (
        setting_key   VARCHAR(64) NOT NULL,
        setting_value TEXT NULL,
        updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "app_settings table: ensured\n";

echo "\nDone. Delete this file from the server once you're happy.\n";
