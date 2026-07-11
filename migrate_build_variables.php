<?php
declare(strict_types=1);

/**
 * Migration: build_variables — the decision-table build rules, per product.
 *
 *   build_variables(id, product_id, name, seq, columns_json, rows_json,
 *                   created_at, updated_at)
 *
 * One row per named build VARIABLE for a Beverley master product (client #3).
 * A variable (H_Cut, Control_Length, ...) is the item that prints on the
 * worksheet/label; its calculation is a small decision table:
 *
 *   columns_json = the question-columns, bound to the product's own options —
 *     [{"label":"System","ref":"system"},
 *      {"label":"Control Type","ref":"extra:42"}, ...]
 *     ref is "system" (the product_systems axis) or "extra:<product_extras.id>".
 *
 *   rows_json = the scenarios, first-match-wins top to bottom —
 *     [{"cells":["Nova","Corded","","Recess"],"result":"Width - 25"}, ...]
 *     a cell is the chosen option label, or "" meaning "— any —" (wildcard).
 *     result is a figure or an Excel-style formula (see formula_engine.php).
 *
 * Supersedes the earlier raw-formula `build_rules` table. Idempotent.
 * Run via web: /migrate_build_variables.php (super-admin).
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

echo "Migrating: build_variables table…\n\n";

$exists = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'build_variables'"
)->fetchColumn();

if (!$exists) {
    $pdo->exec(
        "CREATE TABLE build_variables (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            product_id   INT NOT NULL,
            name         VARCHAR(64) NOT NULL,
            seq          INT NOT NULL DEFAULT 0,
            columns_json TEXT NOT NULL,
            rows_json    LONGTEXT NOT NULL,
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_build_var (product_id, name),
            KEY idx_build_var_product (product_id, seq)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created table build_variables.';
} else {
    $ops[] = 'Table build_variables already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nBuild rules can now be edited as decision tables in the factory Build Rules screen.\n";
