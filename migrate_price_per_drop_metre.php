<?php
declare(strict_types=1);

/**
 * Migration: per-metre-of-drop pricing.
 *
 * Some products price as a RATE × the drop, where the rate depends on the
 * width band and the fabric band — e.g. "Vertical Fabric Only", where the
 * price list gives £/metre-of-drop for each (width, band) and the customer
 * price is that rate × the drop.
 *
 * products.price_per_drop_metre = 1 switches the engine into this mode:
 *   - the price table is a width -> rate list (rows stored with drop_mm 0,
 *     the rate in the price column);
 *   - base price = rate(width, rounded up) × (drop_mm / 1000).
 *
 * It's a normal fabric product otherwise (fabric -> band -> price table,
 * width AND drop entered). Independent of width_only / requires_option.
 *
 * Idempotent. Run via web: /migrate_price_per_drop_metre.php (super-admin).
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

echo "Migrating: per-metre-of-drop pricing…\n\n";

$hasCol = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'products'
        AND COLUMN_NAME  = 'price_per_drop_metre'"
)->fetchColumn();

if (!$hasCol) {
    // Place after width_only when present, else append.
    $afterWO = (bool) $pdo->query(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'products'
            AND COLUMN_NAME  = 'width_only'"
    )->fetchColumn();
    $pos = $afterWO ? ' AFTER width_only' : '';
    $pdo->exec(
        "ALTER TABLE products
         ADD COLUMN price_per_drop_metre TINYINT(1) NOT NULL DEFAULT 0" . $pos
    );
    $ops[] = 'Added products.price_per_drop_metre (TINYINT(1) NOT NULL DEFAULT 0).';
} else {
    $ops[] = 'products.price_per_drop_metre already exists — skipped.';
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nTick 'priced per metre of drop' on the product edit page. Price tables\n";
echo "become a width -> rate list; the engine multiplies the rate by the drop.\n";
