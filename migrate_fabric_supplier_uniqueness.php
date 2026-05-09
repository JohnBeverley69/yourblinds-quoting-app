<?php
declare(strict_types=1);

/**
 * Migration: include supplier_name in the product_options uniqueness check.
 *
 * The original index `uniq_option_per_product` covered
 *   (product_id, band_code, name, colour)
 * which meant two suppliers selling fabrics with the same name + colour
 * (e.g. Eclipse "Opus / Beige" and Marketplace "Opus / Beige") collided
 * on import — the second was skipped as a duplicate even though it's a
 * legitimately different SKU at a different price.
 *
 * Idempotent — safe to re-run. Reads INFORMATION_SCHEMA first so it
 * skips when the index is already on the supplier-aware shape.
 *
 * Run via CLI:   php migrate_fabric_supplier_uniqueness.php
 * Run via web:   /migrate_fabric_supplier_uniqueness.php   (super-admin login required)
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

set_exception_handler(function (Throwable $e) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "MIGRATION FAILED\n================\n\n";
    echo 'Error: ' . $e->getMessage() . "\n";
    echo 'In:    ' . $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
});

// Look up which columns the existing unique index covers, in order.
$st = $pdo->prepare(
    "SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
       FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'product_options'
        AND INDEX_NAME   = 'uniq_option_per_product'
      GROUP BY INDEX_NAME"
);
$st->execute();
$existingCols = $st->fetchColumn();

if ($existingCols === false) {
    echo "No existing 'uniq_option_per_product' index found. Creating supplier-aware version...\n";
    $pdo->exec(
        'CREATE UNIQUE INDEX uniq_option_per_product
            ON product_options (product_id, band_code, supplier_name, name, colour)'
    );
    echo "  Done.\n";
} elseif (strpos((string) $existingCols, 'supplier_name') !== false) {
    echo "Index already includes supplier_name — nothing to do.\n";
    echo "  Current columns: $existingCols\n";
} else {
    echo "Existing index covers: $existingCols\n";
    echo "Dropping and re-creating with supplier_name included...\n";
    $pdo->exec('ALTER TABLE product_options DROP INDEX uniq_option_per_product');
    $pdo->exec(
        'CREATE UNIQUE INDEX uniq_option_per_product
            ON product_options (product_id, band_code, supplier_name, name, colour)'
    );
    echo "  Done.\n";
}

echo "\nFinal index columns:\n";
$st = $pdo->prepare(
    "SELECT COLUMN_NAME, SEQ_IN_INDEX
       FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'product_options'
        AND INDEX_NAME   = 'uniq_option_per_product'
      ORDER BY SEQ_IN_INDEX"
);
$st->execute();
foreach ($st->fetchAll() as $r) {
    echo '  ' . $r['SEQ_IN_INDEX'] . '. ' . $r['COLUMN_NAME'] . "\n";
}

echo "\nNext step: re-run any failed Marketplace import — those rows should now insert.\n";
echo "Existing fabrics already in the DB are untouched.\n";
echo "When you're done, you can delete this file from the server.\n";
