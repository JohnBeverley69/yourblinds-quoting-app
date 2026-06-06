<?php
declare(strict_types=1);

/**
 * Migration: performance indexes for the large catalogue tables.
 *
 * As the catalogue grows (product_options ~30k rows, price_table_rows
 * ~60k rows) the hot lookups want covering indexes:
 *   - product_options(product_id, active)         — fabric search + lists
 *   - price_table_rows(price_table_id, width_mm, drop_mm)
 *                                                 — the matrix cell lookup
 *                                                   (range scan + ORDER BY)
 *   - product_extra_choices(product_extra_id, active)
 *                                                 — choices grid
 *
 * Idempotent — adds each index only if a same-named index isn't already
 * present, so it's safe to re-run. Adding indexes on these row counts is
 * sub-second.
 *
 * Run via web: /migrate_perf_indexes.php  (super-admin login).
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
    echo "Migration FAILED: " . $e->getMessage() . "\n\nDone before failure:\n";
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

echo "Adding performance indexes…\n\n";

/** Does an index of this name exist on the table? */
$indexExists = function (string $table, string $index) use ($pdo): bool {
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = ?
            AND INDEX_NAME   = ? LIMIT 1"
    );
    $st->execute([$table, $index]);
    return (bool) $st->fetchColumn();
};

/** Does the table exist? (defensive on older/odd schemas) */
$tableExists = function (string $table) use ($pdo): bool {
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1"
    );
    $st->execute([$table]);
    return (bool) $st->fetchColumn();
};

$targets = [
    ['product_options',       'idx_po_product_active',  '(product_id, active)'],
    ['price_table_rows',      'idx_ptr_table_dims',     '(price_table_id, width_mm, drop_mm)'],
    ['product_extra_choices', 'idx_pec_extra_active',   '(product_extra_id, active)'],
];

foreach ($targets as [$table, $index, $cols]) {
    if (!$tableExists($table)) {
        $ops[] = "Skipped $table.$index — table not present.";
        continue;
    }
    if ($indexExists($table, $index)) {
        $ops[] = "$table.$index already exists — skipped.";
        continue;
    }
    $t = microtime(true);
    $pdo->exec("ALTER TABLE `$table` ADD INDEX `$index` $cols");
    $ops[] = sprintf("Added %s.%s %s  (%.0f ms)", $table, $index, $cols, (microtime(true) - $t) * 1000);
}

echo "Complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nThese speed up the fabric typeahead, the matrix-cell price lookup,\n";
echo "and the choices grid as the catalogue grows.\n";
