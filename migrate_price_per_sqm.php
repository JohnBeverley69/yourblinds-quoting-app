<?php
declare(strict_types=1);

/**
 * Migration: per-square-metre pricing (priced by area).
 *
 * For products like plantation SHUTTERS, the price is driven by area:
 *
 *     base = rate_per_m2(material, louvre) × max(area, MIN_AREA)
 *     area = (width_mm / 1000) × (drop_mm / 1000)
 *
 * products.price_per_sqm = 1 switches the engine into this mode. BOTH width
 * and drop (height) are required. The price "table" for a (system, band)
 * holds a single £/m² rate (one row, width_mm 0 / drop_mm 0 / price = rate)
 * — there's no size grid, the rate is constant and multiplied by the area.
 *
 * products.min_area_m2 is an optional per-product minimum billable area
 * (e.g. a supplier won't price below 0.5 m²). Default 0 = no minimum.
 *
 * Mirrors width_only / price_per_slat — a product is exactly one of:
 * normal grid, width_only, price_per_slat, or price_per_sqm. Idempotent.
 *
 * Run via web: /migrate_price_per_sqm.php (super-admin).
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

$colExists = static function (string $col) use ($pdo): bool {
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = ?"
    );
    $st->execute([$col]);
    return (bool) $st->fetchColumn();
};

echo "Migrating: per-square-metre pricing…\n\n";

// 1. price_per_sqm flag.
if ($colExists('price_per_sqm')) {
    $ops[] = 'products.price_per_sqm already exists — skipped.';
} else {
    // Place it after price_per_slat when that column exists, else append.
    $afterPPS = $colExists('price_per_slat');
    $pos = $afterPPS ? ' AFTER price_per_slat' : '';
    $pdo->exec(
        "ALTER TABLE products
         ADD COLUMN price_per_sqm TINYINT(1) NOT NULL DEFAULT 0" . $pos
    );
    $ops[] = 'Added products.price_per_sqm (TINYINT(1) NOT NULL DEFAULT 0).';
}

// 2. min_area_m2 (optional minimum billable area; 0 = no minimum).
if ($colExists('min_area_m2')) {
    $ops[] = 'products.min_area_m2 already exists — skipped.';
} else {
    $afterPSQ = $colExists('price_per_sqm');
    $pos = $afterPSQ ? ' AFTER price_per_sqm' : '';
    $pdo->exec(
        "ALTER TABLE products
         ADD COLUMN min_area_m2 DECIMAL(6,3) NOT NULL DEFAULT 0" . $pos
    );
    $ops[] = 'Added products.min_area_m2 (DECIMAL(6,3) NOT NULL DEFAULT 0).';
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nTick 'priced per square metre' on the product edit page (coming in the\n";
echo "next step). The price list becomes a single £/m² rate per (system, band);\n";
echo "the line price is that rate × area (width × height), with an optional\n";
echo "minimum billable area. Both width and height are required.\n";
