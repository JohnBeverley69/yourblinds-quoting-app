<?php
declare(strict_types=1);

/**
 * Migration: worksheet_templates — configurable production worksheet/label
 * layouts, per product.
 *
 *   worksheet_templates(id, product_id, name, is_default, layout_json,
 *                       created_at, updated_at)
 *
 * Replaces Blind Matrix's fixed, un-editable worksheet (the "huge bugbear").
 * layout_json describes what prints and where — an order HEADER block plus a
 * per-line-item set of labels (e.g. a cutting label + a fabric label), each
 * an ordered list of fields. A field names its source (a build variable, an
 * order/line detail, free text, or a barcode), the caption to print, and a
 * show-when rule. Shape:
 *
 *   {
 *     "stock": "a4-diecut",
 *     "header": { "fields": [ {source, caption, show}, ... ] },
 *     "labels": [
 *        { "title": "Cutting label", "fields": [ {source, caption, show}, ... ] },
 *        { "title": "Fabric label",  "fields": [ ... ] }
 *     ]
 *   }
 *
 * source is "var:<name>" | "order:<key>" | "text" | "barcode:<key>".
 * Edited in the factory Worksheets screen; one template per product is the
 * default used for printing.
 *
 * Idempotent. Run via web: /migrate_worksheet_templates.php (super-admin).
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

echo "Migrating: worksheet_templates table…\n\n";

$exists = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'worksheet_templates'"
)->fetchColumn();

if (!$exists) {
    $pdo->exec(
        "CREATE TABLE worksheet_templates (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            product_id  INT NOT NULL,
            name        VARCHAR(120) NOT NULL,
            is_default  TINYINT(1) NOT NULL DEFAULT 0,
            layout_json LONGTEXT NOT NULL,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_ws_tpl_product (product_id, is_default)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created table worksheet_templates.';
} else {
    $ops[] = 'Table worksheet_templates already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nWorksheet layouts can now be built per product in the factory Worksheets screen.\n";
