<?php
declare(strict_types=1);

/**
 * Migration: price_tables.sort_order.
 *
 * Price tables (= bands) on a system have always been ordered
 * alphabetically with a small hardcoded AAA/AA/A bias at the top.
 * That ranks "50mm Gloss String" before "50mm String" which doesn't
 * match the way tenants think about their bands (basic → premium).
 *
 * Adds a sort_order column + drag-handle reordering via the generic
 * reorder.php endpoint (same pattern as systems / options / choices).
 *
 * Back-fill: existing rows get sort_order = their row id, so the
 * initial order on each system reflects creation order — close
 * enough to the historical view that nothing jumps around
 * surprisingly, and the admin can drag from there.
 *
 * Idempotent — re-runnable. Detects existing column and skips.
 *
 * Run via web: /migrate_price_tables_sort_order.php (super-admin).
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

echo "Migrating: price_tables.sort_order…\n\n";

$hasColStmt = $pdo->query(
    "SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'price_tables'
        AND COLUMN_NAME  = 'sort_order'"
);
$hasCol = (bool) $hasColStmt->fetchColumn();

if (!$hasCol) {
    $pdo->exec(
        "ALTER TABLE price_tables
         ADD COLUMN sort_order INT NOT NULL DEFAULT 0
         AFTER active"
    );
    $ops[] = 'Added column price_tables.sort_order (INT, DEFAULT 0).';

    $upd = $pdo->prepare(
        'UPDATE price_tables SET sort_order = id'
    );
    $upd->execute();
    $changed = $upd->rowCount();
    $ops[] = "Back-filled sort_order = id on $changed existing row(s).";
} else {
    $ops[] = 'price_tables.sort_order already exists — skipped.';
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) {
    echo sprintf("  %2d. %s\n", $i + 1, $op);
}
echo "\nPrice tables can now be reordered by dragging on the\n";
echo "system's Price tables page. Order persists per system.\n";
