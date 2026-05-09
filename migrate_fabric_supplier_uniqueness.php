<?php
declare(strict_types=1);

/**
 * Migration: include supplier_name + band_code in product_options uniqueness.
 *
 * The original index `uniq_option_per_product` covered just
 *   (client_id, product_id, name, colour)
 * which had two problems:
 *   1. Two suppliers selling fabrics with the same name+colour collided
 *      on import (e.g. Eclipse "Opus / Beige" vs Marketplace "Opus / Beige").
 *   2. Same name+colour with different bands also collided.
 *
 * The new shape is
 *   (client_id, product_id, band_code, supplier_name, name, colour)
 * so each genuine SKU is unique per tenant.
 *
 * MySQL gymnastics: the old index's leftmost column (client_id) is what's
 * holding up the FK from product_options.client_id → clients.id, so we
 * can't drop it before there's another index covering client_id. We work
 * around that by:
 *   1. ADD the new unique under a temporary name (also starts with
 *      client_id, so it supports the FK).
 *   2. DROP the old.
 *   3. RENAME the new one to the canonical name.
 *
 * Idempotent — re-runnable. Safe.
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

/**
 * Return the comma-separated, sequence-ordered column list of an index, or
 * null if no such index exists on product_options.
 */
function pe_index_columns(PDO $pdo, string $indexName): ?string
{
    $st = $pdo->prepare(
        "SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
           FROM INFORMATION_SCHEMA.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'product_options'
            AND INDEX_NAME   = ?
          GROUP BY INDEX_NAME"
    );
    $st->execute([$indexName]);
    $cols = $st->fetchColumn();
    return $cols === false ? null : (string) $cols;
}

$DESIRED = '(client_id, product_id, band_code, supplier_name, name, colour)';

$current = pe_index_columns($pdo, 'uniq_option_per_product');
echo $current === null
    ? "No 'uniq_option_per_product' index found yet.\n"
    : "Current 'uniq_option_per_product' columns: $current\n";

if ($current !== null
    && strpos($current, 'band_code')     !== false
    && strpos($current, 'supplier_name') !== false
) {
    echo "Index already covers band_code + supplier_name — nothing to do.\n";
    exit(0);
}

// Clean up any half-applied state from a previous failed run.
if (pe_index_columns($pdo, 'uniq_option_per_product_new') !== null) {
    echo "Removing leftover 'uniq_option_per_product_new' from a previous run...\n";
    $pdo->exec('ALTER TABLE product_options DROP INDEX uniq_option_per_product_new');
}

// Step 1: add the new unique index. Starts with client_id, so it provides
// the FK coverage that the old index was holding.
echo "Step 1/3: adding new unique index (under temporary name)...\n";
$pdo->exec(
    'ALTER TABLE product_options
        ADD UNIQUE INDEX uniq_option_per_product_new
        (client_id, product_id, band_code, supplier_name, name, colour)'
);

// Step 2: drop the old now that the FK has fallback coverage.
if ($current !== null) {
    echo "Step 2/3: dropping old 'uniq_option_per_product'...\n";
    $pdo->exec('ALTER TABLE product_options DROP INDEX uniq_option_per_product');
} else {
    echo "Step 2/3: skipped — no old index to drop.\n";
}

// Step 3: rename the new one to the canonical name (so existing import
// code matching 'uniq_option_per_product' in error messages still works).
echo "Step 3/3: renaming new index to 'uniq_option_per_product'...\n";
$pdo->exec(
    'ALTER TABLE product_options
        RENAME INDEX uniq_option_per_product_new TO uniq_option_per_product'
);

echo "\nDone. Final 'uniq_option_per_product' columns:\n";
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

echo "\nNext step: re-run the Marketplace import — those rows should now insert.\n";
echo "Existing fabrics already in the DB are untouched.\n";
echo "When you're done, you can delete this file from the server.\n";
