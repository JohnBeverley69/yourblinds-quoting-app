<?php
declare(strict_types=1);

/**
 * Migration: build_rules — the manufacturing build formulas, per product.
 *
 *   build_rules(id, product_id, variable, formula, seq, active,
 *               created_at, updated_at)
 *
 * Each row is one named formula for a Beverley master product (client #3):
 * an output/intermediate variable (H_Cut, Vanes, Width_conversion...) and its
 * Excel-style expression, evaluated in `seq` order so a rule can use earlier
 * rules' outputs. Fed by _partials/formula_engine.php and edited in the
 * factory Build Rules screen.
 *
 * Idempotent. Run via web: /migrate_build_rules.php (super-admin).
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

echo "Migrating: build_rules table…\n\n";

$exists = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'build_rules'"
)->fetchColumn();

if (!$exists) {
    $pdo->exec(
        "CREATE TABLE build_rules (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            product_id  INT NOT NULL,
            variable    VARCHAR(64) NOT NULL,
            formula     TEXT NOT NULL,
            seq         INT NOT NULL DEFAULT 0,
            active      TINYINT(1) NOT NULL DEFAULT 1,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_build_rules_product (product_id, seq)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created table build_rules.';
} else {
    $ops[] = 'Table build_rules already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nBuild formulas can now be edited per product in the factory Build Rules screen.\n";
