<?php
declare(strict_types=1);

/**
 * Migration: cost-price tracking on Products → true gross profit.
 *
 * Adds cost_price columns to the product hierarchy plus snapshot
 * columns on quote lines, so the Dashboard can compute real gross
 * profit instead of the rough markup-based "estimated margin".
 *
 * The model:
 *   products.cost_price             — default wholesale cost per blind
 *                                     for this product, before fabric.
 *   product_options.cost_price      — fabric/option cost; ADDS to the
 *                                     product cost (premium fabric →
 *                                     higher cost). NULL = no add.
 *   product_extra_choices.cost_price — flat wholesale cost per use of
 *                                     this extra/option choice.
 *
 * On each quote line we snapshot:
 *   quote_items.cost_price_snapshot   — total per-blind cost at save
 *                                       (product + fabric, frozen).
 *   quote_items.extras_cost_snapshot  — sum of extras' cost on this line.
 *   quote_item_extras.cost_snapshot   — per-extra cost at save.
 *
 * Snapshots mean historic gross profit stays correct even when
 * products/fabrics get edited or deleted later — same pattern as the
 * existing product_name_snapshot, base_price fields.
 *
 * NULL costs are treated as 0 by the engine, so this migration is
 * non-breaking: existing quotes show as "full sell price = profit"
 * until tenants fill in cost data on their products.
 *
 * Idempotent. Run via /migrate_product_costs.php (super-admin).
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

function pc_column_exists(PDO $pdo, string $table, string $col): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = ?
            AND COLUMN_NAME  = ? LIMIT 1'
    );
    $st->execute([$table, $col]);
    return $st->fetchColumn() !== false;
}

function pc_add_column(PDO $pdo, string $table, string $col, string $ddlAfterClause, array &$ops): void
{
    if (pc_column_exists($pdo, $table, $col)) {
        $ops[] = "$table.$col already present";
        return;
    }
    $pdo->exec("ALTER TABLE $table ADD COLUMN $col DECIMAL(10,2) NULL $ddlAfterClause");
    $ops[] = "Added $table.$col";
}

$ops = [];

// ---- Cost columns on the Products hierarchy --------------------------
// "AFTER" clauses keep the new column visually near the existing price
// columns when browsing the schema in phpMyAdmin — purely cosmetic.
pc_add_column($pdo, 'products',              'cost_price', '',                $ops);
pc_add_column($pdo, 'product_options',       'cost_price', '',                $ops);
pc_add_column($pdo, 'product_extra_choices', 'cost_price', 'AFTER price_delta', $ops);

// ---- Snapshot columns on quote lines ---------------------------------
pc_add_column($pdo, 'quote_items',       'cost_price_snapshot',   'AFTER base_price',          $ops);
pc_add_column($pdo, 'quote_items',       'extras_cost_snapshot',  'AFTER cost_price_snapshot', $ops);
pc_add_column($pdo, 'quote_item_extras', 'cost_snapshot',         'AFTER amount_applied',      $ops);

echo "Migration complete:\n";
foreach ($ops as $op) echo '  - ' . $op . "\n";
echo "\n";
echo "Cost fields are now available on:\n";
echo "  - Each Product (admin/products/edit.php)\n";
echo "  - Each Fabric/option (admin/products/option-edit.php)\n";
echo "  - Each Extra choice (admin/products/extra-choice-edit.php)\n";
echo "\n";
echo "New quote lines snapshot the cost at save-time, so editing or\n";
echo "deleting a product later won't disturb historic gross-profit\n";
echo "figures. Existing quotes show full sell price as profit until\n";
echo "you populate the cost fields and create new lines.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
