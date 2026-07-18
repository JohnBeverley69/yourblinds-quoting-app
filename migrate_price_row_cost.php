<?php
declare(strict_types=1);

/**
 * Migration: a cost alongside each sell price.
 *
 * In-house (manufactured) blinds had no cost the system could see — the flat
 * products.cost_price was disabled and couldn't vary by size anyway. But a made
 * blind's cost DOES vary by size, exactly like its sell price. John's cost lives
 * in a size grid in Excel, cell-for-cell parallel to his sell grid.
 *
 * So cost belongs where the price already is: one more column on the price cell.
 *   price_table_rows.cost  — the cost to make this size, £. Imported from the
 *                            cost sheet; margin = price − cost, exact per size.
 *
 * DECIMAL(10,4) to keep the precision the cost sheet carries (£5.6634…). NULL
 * where no cost has been imported yet, so nothing changes for a product that
 * only has sell prices.
 *
 * Run via web: /migrate_price_row_cost.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colExists = static function (string $t, string $c) use ($pdo): bool {
    try { $pdo->query("SELECT `$c` FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

if (!$colExists('price_table_rows', 'cost')) {
    $pdo->exec("ALTER TABLE price_table_rows ADD COLUMN cost DECIMAL(10,4) NULL AFTER price");
    echo "  Added price_table_rows.cost.\n";
} else {
    echo "  price_table_rows.cost already exists — skipped.\n";
}

echo "\nDone. Import a product's cost grid on its price screen — the same file\n";
echo "format as the sell prices, just the cost sheet. margin = price − cost.\n";
