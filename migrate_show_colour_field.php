<?php
declare(strict_types=1);

/**
 * Migration: products.show_colour_field flag.
 *
 * Controls whether the dedicated "Colour" sub-field renders next to
 * the fabric name on the inline single-add forms (options.php,
 * option-edit.php, edit.php Fabrics section). Some products genuinely
 * need both Name + Colour (rollers / verticals — Polaris fabric in
 * Cream / Stone / Black variants); others need just Name (wood and
 * metal venetians, where the slat colour IS the identifier).
 *
 * Smart default at migration time, applied only to existing rows:
 *   - LOWER(option_label) matches 'colou?r'   → 0  (hide the field)
 *   - everything else                          → 1  (show the field)
 *
 * New products default to 1 at the DB level. The wizard / create
 * paths derive a more appropriate initial value from the typed
 * option_label after this migration ships.
 *
 * Idempotent — re-runnable. Detects existing column and skips.
 *
 * Run via web: /migrate_show_colour_field.php  (super-admin login).
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

echo "Migrating products.show_colour_field flag…\n\n";

// 1. Check if the column exists already.
$hasColStmt = $pdo->query(
    "SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'products'
        AND COLUMN_NAME  = 'show_colour_field'"
);
$hasCol = (bool) $hasColStmt->fetchColumn();

if (!$hasCol) {
    $pdo->exec(
        "ALTER TABLE products
         ADD COLUMN show_colour_field TINYINT(1) NOT NULL DEFAULT 1
         AFTER option_label"
    );
    $ops[] = 'Added column products.show_colour_field (TINYINT(1), DEFAULT 1).';

    // Set smart default on existing rows. REGEXP 'colou?r' matches
    // both British and American spellings, anywhere in the label.
    $upd = $pdo->prepare(
        "UPDATE products
            SET show_colour_field = 0
          WHERE LOWER(option_label) REGEXP 'colou?r'"
    );
    $upd->execute();
    $changed = $upd->rowCount();
    $ops[] = "Set show_colour_field = 0 on $changed existing colour-labelled products.";
} else {
    $ops[] = 'products.show_colour_field already exists — skipped.';
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) {
    echo sprintf("  %2d. %s\n", $i + 1, $op);
}
echo "\nproducts.show_colour_field now drives the fabric forms' Colour\n";
echo "field visibility. Toggle on the product edit page.\n";
