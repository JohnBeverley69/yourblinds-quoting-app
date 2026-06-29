<?php
declare(strict_types=1);

/**
 * Seeder: Arena Pleated Blinds — Phase 1. Mirrors seed_arena_roller.php.
 *
 * One product "Arena Pleated" with seven systems, each with Elements + A–E
 * band grids (from the May-2026 list, p204-222). Band grids legitimately
 * vary in width range per band (Arena size-limits the lower bands), and
 * grids are ragged — that's expected, not an error.
 *   Standard Cord Freehang, Tab Freehang, Rectangular Roof, Shaped Roof,
 *   Fixed Roof, Perfect Fit, Perfect Fit Golden Oak
 * (Night & Day pleated is priced differently — deferred.)
 *
 * Fabrics are collapsed to base fabric (the Freehang/Roof/Trilite/PF
 * suffixes are SYSTEMS, not fabrics) and shared across systems (system_id
 * NULL). Banded from the spec index / model matrix (p225-244). The Duette/
 * Hive cellular ranges + some "Shape" variants aren't on the standard A–E
 * scheme → band_code 'TBC' + inactive (visible, flagged, fill later).
 *
 * Data: seed_data/arena_pleated_prices.csv, seed_data/arena_pleated_fabrics.csv
 * Prices = Arena TRADE cost. Idempotent by name, transactional.
 * Run via web (super-admin): /seed_arena_pleated.php
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}

ini_set('display_errors', '1');
error_reporting(E_ALL);
@set_time_limit(300);
@ini_set('memory_limit', '512M');

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$ops = [];
set_exception_handler(function (Throwable $e) use (&$ops) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) header('Content-Type: text/plain; charset=utf-8');
    echo "Seed FAILED: " . $e->getMessage() . "\n\nSteps before failure (rolled back if mid-transaction):\n";
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

$PRODUCT_NAME = 'Arena Pleated';
$OPTION_LABEL = 'Fabric';
$SUPPLIER     = 'Arena';
$SYSTEMS      = ['Standard Cord Freehang', 'Tab Freehang', 'Rectangular Roof', 'Shaped Roof', 'Fixed Roof', 'Perfect Fit', 'Perfect Fit Golden Oak'];
$VALID_BANDS  = ['Elements' => 1, 'A' => 1, 'B' => 1, 'C' => 1, 'D' => 1, 'E' => 1];
$DATA_DIR     = __DIR__ . '/seed_data';
$PRICES_CSV   = $DATA_DIR . '/arena_pleated_prices.csv';
$FABRICS_CSV  = $DATA_DIR . '/arena_pleated_fabrics.csv';

$user     = current_user();
$clientId = (int) ($user['client_id'] ?? 0);
if ($clientId <= 0) throw new RuntimeException('Could not determine your client_id — are you logged in?');

echo "Seeding \"{$PRODUCT_NAME}\" into client_id {$clientId}\n" . str_repeat('=', 60) . "\n\n";

$readCsv = static function (string $path): array {
    if (!is_file($path)) throw new RuntimeException("Missing data file: {$path}");
    $fh = fopen($path, 'r');
    if (!$fh) throw new RuntimeException("Cannot open: {$path}");
    $header = fgetcsv($fh);
    $rows = [];
    while (($r = fgetcsv($fh)) !== false) {
        if ($r === [null] || $r === false) continue;
        $rows[] = array_combine($header, $r);
    }
    fclose($fh);
    return $rows;
};

$priceRows  = $readCsv($PRICES_CSV);
$fabricRows = $readCsv($FABRICS_CSV);
$ops[] = sprintf('Read %d price cells, %d fabrics from seed_data/.', count($priceRows), count($fabricRows));
echo end($ops) . "\n";

$pdo->beginTransaction();
try {
    $find = $pdo->prepare('SELECT id FROM products WHERE client_id = ? AND name = ?');
    $find->execute([$clientId, $PRODUCT_NAME]);
    $productId = (int) ($find->fetchColumn() ?: 0);
    if ($productId === 0) {
        $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM products WHERE client_id = ?');
        $sortStmt->execute([$clientId]);
        $nextSort = (int) $sortStmt->fetchColumn();
        try {
            $pdo->prepare('INSERT INTO products (client_id, name, option_label, show_colour_field, sort_order, active) VALUES (?, ?, ?, 1, ?, 1)')
                ->execute([$clientId, $PRODUCT_NAME, $OPTION_LABEL, $nextSort]);
        } catch (Throwable $e) {
            $pdo->prepare('INSERT INTO products (client_id, name, option_label, sort_order, active) VALUES (?, ?, ?, ?, 1)')
                ->execute([$clientId, $PRODUCT_NAME, $OPTION_LABEL, $nextSort]);
        }
        $productId = (int) $pdo->lastInsertId();
        $ops[] = "Created product #{$productId} \"{$PRODUCT_NAME}\".";
    } else {
        $ops[] = "Reusing existing product #{$productId} \"{$PRODUCT_NAME}\".";
    }
    echo end($ops) . "\n";

    $sysId = [];
    foreach ($SYSTEMS as $i => $sysName) {
        $fs = $pdo->prepare('SELECT id FROM product_systems WHERE client_id = ? AND product_id = ? AND name = ?');
        $fs->execute([$clientId, $productId, $sysName]);
        $id = (int) ($fs->fetchColumn() ?: 0);
        if ($id === 0) {
            $pdo->prepare('INSERT INTO product_systems (client_id, product_id, name, sort_order, active, is_default) VALUES (?, ?, ?, ?, 1, ?)')
                ->execute([$clientId, $productId, $sysName, $i, $i === 0 ? 1 : 0]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE product_systems SET active = 1, is_default = ?, sort_order = ? WHERE id = ?')
                ->execute([$i === 0 ? 1 : 0, $i, $id]);
        }
        $sysId[$sysName] = $id;
    }
    $ops[] = 'Systems: ' . implode(', ', array_keys($sysId)) . '.';
    echo end($ops) . "\n";

    $pdo->prepare('DELETE r FROM price_table_rows r JOIN price_tables t ON t.id = r.price_table_id WHERE t.client_id = ? AND t.product_id = ?')
        ->execute([$clientId, $productId]);
    $pdo->prepare('DELETE FROM price_tables   WHERE client_id = ? AND product_id = ?')->execute([$clientId, $productId]);
    $pdo->prepare('DELETE FROM product_options WHERE client_id = ? AND product_id = ?')->execute([$clientId, $productId]);
    $ops[] = 'Cleared existing price tables + fabric options for a fresh rebuild.';
    echo end($ops) . "\n";

    $grouped = [];
    foreach ($priceRows as $pr) $grouped[$pr['system'] . '|' . $pr['band']][] = $pr;
    $tblIns = $pdo->prepare('INSERT INTO price_tables (client_id, product_id, system_id, band_code, name, active) VALUES (?, ?, ?, ?, ?, 1)');
    $rowIns = $pdo->prepare('INSERT INTO price_table_rows (price_table_id, width_mm, drop_mm, price) VALUES (?, ?, ?, ?)');
    $tableCount = 0; $cellCount = 0;
    foreach ($grouped as $key => $cells) {
        [$sysName, $band] = explode('|', $key, 2);
        if (!isset($sysId[$sysName])) continue;
        $tblIns->execute([$clientId, $productId, $sysId[$sysName], $band, "Arena Band {$band}"]);
        $tableId = (int) $pdo->lastInsertId();
        foreach ($cells as $c) { $rowIns->execute([$tableId, (int) $c['width_mm'], (int) $c['drop_mm'], (float) $c['price']]); $cellCount++; }
        $tableCount++;
    }
    $ops[] = "Built {$tableCount} price tables ({$cellCount} cells).";
    echo end($ops) . "\n";

    $optIns = $pdo->prepare(
        'INSERT INTO product_options
            (client_id, product_id, system_id, band_code, supplier_name, name, colour, code, sort_order, active)
         VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?)'
    );
    $added = 0; $inactive = 0; $sort = 0; $seen = [];
    foreach ($fabricRows as $f) {
        $band = trim((string) ($f['band'] ?? ''));
        $banded = isset($VALID_BANDS[$band]);
        $bandCode = $banded ? $band : 'TBC';
        $name = (string) $f['name']; $colour = (string) ($f['colour'] ?? '');
        $key = $bandCode . '|' . mb_strtolower($name) . '|' . mb_strtolower($colour);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $optIns->execute([$clientId, $productId, $bandCode, $SUPPLIER, $name, $colour, '', $sort++, $banded ? 1 : 0]);
        if ($banded) $added++; else $inactive++;
    }
    $ops[] = "Imported " . ($added + $inactive) . " fabric options: {$added} active (banded), {$inactive} inactive (Duette/cellular + Shape variants — fill bands later).";
    echo end($ops) . "\n";

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

echo "\n" . str_repeat('=', 60) . "\nSeed complete.\n\nSteps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
