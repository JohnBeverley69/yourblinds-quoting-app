<?php
declare(strict_types=1);

/**
 * Migration: width-only pricing.
 *
 * Some products are priced on WIDTH alone — there's no drop. The classic
 * case is a vertical-blind headrail only (a rail across the top, no
 * height), or a track. The normal model prices on a width × drop grid
 * and requires a drop at quote time.
 *
 * products.width_only = 1 flips a product into 1-D pricing: the engine
 * matches the price-table row by width only, the quote builder + InstaPrice
 * hide the Drop field, and saved lines store drop 0.
 *
 * Independent of requires_option (no-fabric) — a product can be either,
 * both, or neither — though a headrail is typically both.
 *
 * Idempotent. Run via web: /migrate_width_only.php (super-admin login).
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
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

echo "Migrating: width-only pricing…\n\n";

$hasCol = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'products'
        AND COLUMN_NAME  = 'width_only'"
)->fetchColumn();

if (!$hasCol) {
    // Place it after requires_option when that column exists, else just
    // append it (keeps the ALTER valid on either schema state).
    $afterRO = (bool) $pdo->query(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'products'
            AND COLUMN_NAME  = 'requires_option'"
    )->fetchColumn();
    $pos = $afterRO ? ' AFTER requires_option' : '';
    $pdo->exec(
        "ALTER TABLE products
         ADD COLUMN width_only TINYINT(1) NOT NULL DEFAULT 0" . $pos
    );
    $ops[] = 'Added column products.width_only (TINYINT(1) NOT NULL DEFAULT 0).';
} else {
    $ops[] = 'products.width_only already exists — skipped.';
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nTick 'priced by width only' on the product edit page (or the setup\n";
echo "wizard) for headrail / track lines. Use the width-only price importer\n";
echo "to load a width → price list per system.\n";
