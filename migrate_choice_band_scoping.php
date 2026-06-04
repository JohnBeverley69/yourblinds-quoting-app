<?php
declare(strict_types=1);

/**
 * Migration: per-choice band scoping (product_extra_choice_bands).
 *
 * Until now, choices on an extra (e.g. "Tape colour") applied to
 * every fabric on the product, optionally narrowed by system. But
 * tape colour availability often varies by BAND — a narrow slat
 * band might only support 2 tape colours, a wider one supports 6.
 * No way to express that previously, so admins had to either over-
 * list (showing customers options that don't apply) or under-list
 * (hiding options that do).
 *
 * Adds a junction table linking choices to the bands they apply to:
 *
 *   CREATE TABLE product_extra_choice_bands (
 *     choice_id  INT NOT NULL,
 *     band_code  VARCHAR(20) NOT NULL,
 *     ...
 *   );
 *
 * No rows for a choice = "applies to all bands" (the existing
 * behaviour). At least one row = "applies only to the listed
 * bands". Filtered case-insensitively to match how price_tables
 * lookups work elsewhere.
 *
 * Idempotent — re-running detects the existing table and skips.
 *
 * Run via web: /migrate_choice_band_scoping.php (super-admin login).
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
    echo "Steps completed before failure:\n";
    foreach ($ops as $i => $op) {
        echo sprintf("  %2d. %s\n", $i + 1, $op);
    }
    exit(1);
});

echo "Migrating: product_extra_choice_bands junction table…\n\n";

// 1. Check if the table exists already.
$hasTblStmt = $pdo->query(
    "SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'product_extra_choice_bands'"
);
$hasTbl = (bool) $hasTblStmt->fetchColumn();

if (!$hasTbl) {
    $pdo->exec(
        "CREATE TABLE product_extra_choice_bands (
            choice_id INT          NOT NULL,
            band_code VARCHAR(20)  NOT NULL,
            PRIMARY KEY (choice_id, band_code),
            KEY ix_pecb_band (band_code),
            CONSTRAINT fk_pecb_choice FOREIGN KEY (choice_id)
                REFERENCES product_extra_choices(id) ON DELETE CASCADE
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $ops[] = 'Created table product_extra_choice_bands.';
} else {
    $ops[] = 'product_extra_choice_bands already exists — skipped.';
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) {
    echo sprintf("  %2d. %s\n", $i + 1, $op);
}
echo "\nChoice band-scoping is now available. Each choice with no\n";
echo "matching band rows applies to every band (the previous default).\n";
echo "Tag a choice with bands on the Edit choice page to restrict it.\n";
