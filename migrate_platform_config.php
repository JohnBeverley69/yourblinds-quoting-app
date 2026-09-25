<?php
declare(strict_types=1);

/**
 * platform_config — a tiny super-admin key/value store for PLATFORM-level
 * settings that would otherwise live in .env, so they can be set from the admin
 * UI instead of editing files on the server (which is painful on managed hosts).
 *
 * Used first for the QuickBooks app credentials (one Intuit app serves every
 * tenant) and the token-encryption key. Values here take precedence over .env
 * in the accounting layer, with .env as the fallback.
 *
 * Idempotent, super-admin, web-runnable: /migrate_platform_config.php
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$exists = $pdo->query("SHOW TABLES LIKE 'platform_config'")->fetchColumn();
if ($exists) {
    echo "platform_config already exists — skipped.\n";
} else {
    $pdo->exec(
        "CREATE TABLE platform_config (
            name        VARCHAR(64) NOT NULL PRIMARY KEY,
            value       TEXT NULL,
            updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "Created platform_config.\n";
}

echo "\nDone. Set the QuickBooks keys in Settings → Accounting (super-admin).\n";
