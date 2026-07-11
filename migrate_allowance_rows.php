<?php
declare(strict_types=1);

/**
 * Migration: allowance_rows — named multi-key lookup tables for the build engine.
 *
 *   allowance_rows(id, table_name, key_norm, keys_display, value, seq,
 *                  created_at, updated_at)
 *
 * A "table" is all rows sharing a table_name (e.g. "vertical_headrail").
 * key_norm is the lower-cased "|"-joined key columns (the lookup key);
 * keys_display is the readable version. Referenced from build-rule formulas
 * via LOOKUP("table", key1, key2, ...) so a shared deduction (e.g. a headrail
 * cut allowance) is one lookup edited in one place, not a duplicated IF.
 *
 * Idempotent. Run via web: /migrate_allowance_rows.php (super-admin).
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

$ops = [];
set_exception_handler(function (Throwable $e) use (&$ops) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Migration FAILED: " . $e->getMessage() . "\n\n";
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

echo "Migrating: allowance_rows table…\n\n";

$exists = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'allowance_rows'"
)->fetchColumn();

if (!$exists) {
    $pdo->exec(
        "CREATE TABLE allowance_rows (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            table_name   VARCHAR(64)  NOT NULL,
            key_norm     VARCHAR(255) NOT NULL,
            keys_display VARCHAR(500) NOT NULL,
            value        DOUBLE       NOT NULL,
            seq          INT          NOT NULL DEFAULT 0,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_allowance (table_name, key_norm),
            KEY idx_allowance_table (table_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created table allowance_rows.';
} else {
    $ops[] = 'Table allowance_rows already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nBuild-rule formulas can now use LOOKUP(\"table\", keys…). Seed the vertical\n";
echo "headrail allowances with /seed_vertical_allowances.php.\n";
