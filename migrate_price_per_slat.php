<?php
declare(strict_types=1);

/**
 * Migration: per-slat pricing (priced by drop).
 *
 * For products like "Vertical Fabric 89mm Only": the customer gives a DROP
 * and a NUMBER OF SLATS (no width). The price list is a drop -> price-per-
 * slat table per (system, band); the line price is:
 *
 *     per_slat_rate(drop, rounded up) × number of slats
 *
 * products.price_per_slat = 1 switches the engine into this mode. It's a
 * normal fabric product otherwise (fabric -> band -> table). The quote
 * line's quantity IS the slat count; width is not used.
 *
 * Supersedes the earlier price_per_drop_metre flag (which had the wrong
 * shape). If that column exists it's renamed; otherwise the new column is
 * added. Idempotent.
 *
 * Run via web: /migrate_price_per_slat.php (super-admin).
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

echo "Migrating: per-slat pricing…\n\n";

if ($colExists('price_per_slat')) {
    $ops[] = 'products.price_per_slat already exists — skipped.';
    // If the old column is also still around, drop it (dormant, wrong shape).
    if ($colExists('price_per_drop_metre')) {
        $pdo->exec("ALTER TABLE products DROP COLUMN price_per_drop_metre");
        $ops[] = 'Dropped the obsolete products.price_per_drop_metre column.';
    }
} elseif ($colExists('price_per_drop_metre')) {
    // Rename the earlier (wrong-shape) flag to the correct name. Any value
    // already set carries over — both default 0 so that's harmless.
    $pdo->exec(
        "ALTER TABLE products
         CHANGE COLUMN price_per_drop_metre price_per_slat TINYINT(1) NOT NULL DEFAULT 0"
    );
    $ops[] = 'Renamed products.price_per_drop_metre → price_per_slat.';
} else {
    $afterWO = $colExists('width_only');
    $pos = $afterWO ? ' AFTER width_only' : '';
    $pdo->exec(
        "ALTER TABLE products
         ADD COLUMN price_per_slat TINYINT(1) NOT NULL DEFAULT 0" . $pos
    );
    $ops[] = 'Added products.price_per_slat (TINYINT(1) NOT NULL DEFAULT 0).';
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nTick 'priced per slat (by drop)' on the product edit page. Price tables\n";
echo "become a drop → price-per-slat list; the line price is that rate ×\n";
echo "the number of slats (the line quantity). Width is not used.\n";
