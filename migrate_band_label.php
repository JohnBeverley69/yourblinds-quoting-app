<?php
declare(strict_types=1);

/**
 * Migration: products.band_label.
 *
 * Per-product wording for the "band" axis in the quote builder + live
 * preview. Bands are the price-table tiers; for most products "Band" is
 * right (A/B/C price tiers), but for some the band axis is really
 * something else — e.g. a wood venetian where each band is a headrail
 * type ("Tape / String"). NULL / empty = fall back to "Band".
 *
 * Idempotent — re-runnable. Detects the existing column and skips.
 *
 * Run via web: /migrate_band_label.php  (super-admin login).
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

echo "Migrating products.band_label…\n\n";

$hasCol = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'products'
        AND COLUMN_NAME  = 'band_label'"
)->fetchColumn();

if (!$hasCol) {
    $pdo->exec(
        "ALTER TABLE products
         ADD COLUMN band_label VARCHAR(60) NULL
         AFTER option_label"
    );
    $ops[] = 'Added column products.band_label (VARCHAR(60) NULL).';
} else {
    $ops[] = 'products.band_label already exists — skipped.';
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) {
    echo sprintf("  %2d. %s\n", $i + 1, $op);
}
echo "\nSet a per-product band label on the product edit page (e.g.\n";
echo "\"Tape / String\"). Empty = the builder shows \"Band\" as before.\n";
