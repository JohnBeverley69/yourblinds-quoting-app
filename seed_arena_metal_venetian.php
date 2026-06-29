<?php
declare(strict_types=1);

/**
 * Seeder: Arena Metal Venetian Blinds — Phase 1. A DIFFERENT shape to the
 * banded products (metal vs wood are separate products per the boss).
 *
 * Priced by SLAT SIZE (the systems), not fabric band. Two price tiers per
 * size: Standard and Special Effects (SE colours cost more):
 *   15mm, 25mm (both STD+SE), 35mm, 50mm (STD only),
 *   25mm Perfect Fit, 25mm PF Golden Oak, Tabbed 25mm (STD+SE)
 *
 * The "fabric" is the SLAT COLOUR (option_label "Colour"), system-scoped
 * (each slat size has its own colour set). A colour's price tier comes from
 * its Special-Effects flag (Yes → SE, else STD).
 *
 * band_code is keyed per system+tier (e.g. "25mm STD", "Tabbed 25mm SE")
 * because the product_options UNIQUE key excludes system_id and the same
 * colour name recurs across slat sizes — a system-specific band keeps each
 * size's colours + grid self-contained and collision-free. The pricing
 * engine matches the quote's chosen system + the colour's band_code.
 *
 * Data: seed_data/arena_metal_venetian_prices.csv  (system,band[STD|SE],w,d,price)
 *       seed_data/arena_metal_venetian_colours.csv (system,name,special_effects)
 * Idempotent by name, transactional. Run via web: /seed_arena_metal_venetian.php
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

$PRODUCT_NAME = 'Arena Metal Venetian';
$OPTION_LABEL = 'Colour';
$SUPPLIER     = 'Arena';
$SYSTEMS      = ['15mm', '25mm', '35mm', '50mm', '25mm Perfect Fit', '25mm PF Golden Oak', 'Tabbed 25mm'];
$DATA_DIR     = __DIR__ . '/seed_data';
$PRICES_CSV   = $DATA_DIR . '/arena_metal_venetian_prices.csv';
$COLOURS_CSV  = $DATA_DIR . '/arena_metal_venetian_colours.csv';

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
$colourRows = $readCsv($COLOURS_CSV);
$ops[] = sprintf('Read %d price cells, %d colours from seed_data/.', count($priceRows), count($colourRows));
echo end($ops) . "\n";

$pdo->beginTransaction();
try {
    // Product (option_label "Colour", no separate colour field).
    $find = $pdo->prepare('SELECT id FROM products WHERE client_id = ? AND name = ?');
    $find->execute([$clientId, $PRODUCT_NAME]);
    $productId = (int) ($find->fetchColumn() ?: 0);
    if ($productId === 0) {
        $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM products WHERE client_id = ?');
        $sortStmt->execute([$clientId]);
        $nextSort = (int) $sortStmt->fetchColumn();
        try {
            $pdo->prepare('INSERT INTO products (client_id, name, option_label, show_colour_field, sort_order, active) VALUES (?, ?, ?, 0, ?, 1)')
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
    $ops[] = 'Cleared existing price tables + colour options for a fresh rebuild.';
    echo end($ops) . "\n";

    // Price tables: one per (system, tier). band_code = "<system> <tier>".
    $grouped = [];
    foreach ($priceRows as $pr) $grouped[$pr['system'] . '|' . $pr['band']][] = $pr;
    $tblIns = $pdo->prepare('INSERT INTO price_tables (client_id, product_id, system_id, band_code, name, active) VALUES (?, ?, ?, ?, ?, 1)');
    $rowIns = $pdo->prepare('INSERT INTO price_table_rows (price_table_id, width_mm, drop_mm, price) VALUES (?, ?, ?, ?)');
    $tableCount = 0; $cellCount = 0; $haveBand = [];
    foreach ($grouped as $key => $cells) {
        [$sysName, $tier] = explode('|', $key, 2);
        if (!isset($sysId[$sysName])) continue;
        $bandCode = $sysName . ' ' . $tier;
        $tblIns->execute([$clientId, $productId, $sysId[$sysName], $bandCode, "Arena {$bandCode}"]);
        $tableId = (int) $pdo->lastInsertId();
        $haveBand[$sysName . '|' . $bandCode] = true;
        // Dedupe (width,drop) within a table — the Tabbed 25mm grid has a drop
        // row that collapses onto another (price_table_rows has a uniq_cell key).
        $cellSeen = [];
        foreach ($cells as $c) {
            $ck = (int) $c['width_mm'] . 'x' . (int) $c['drop_mm'];
            if (isset($cellSeen[$ck])) continue;
            $cellSeen[$ck] = true;
            $rowIns->execute([$tableId, (int) $c['width_mm'], (int) $c['drop_mm'], (float) $c['price']]);
            $cellCount++;
        }
        $tableCount++;
    }
    $ops[] = "Built {$tableCount} price tables ({$cellCount} cells).";
    echo end($ops) . "\n";

    // Colour options: system-scoped, band_code = "<system> <tier>" (SE if the
    // colour is a Special Effect and an SE table exists for that size, else STD).
    $optIns = $pdo->prepare(
        'INSERT INTO product_options
            (client_id, product_id, system_id, band_code, supplier_name, name, colour, code, sort_order, active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
    );
    $added = 0; $sort = 0; $seen = [];
    foreach ($colourRows as $c) {
        $sysName = (string) $c['system'];
        if (!isset($sysId[$sysName])) continue;
        $se   = strcasecmp(trim((string) ($c['special_effects'] ?? '')), 'Yes') === 0;
        $tier = $se ? 'SE' : 'STD';
        $bandCode = $sysName . ' ' . $tier;
        if (!isset($haveBand[$sysName . '|' . $bandCode])) $bandCode = $sysName . ' STD';  // fallback
        $name = (string) $c['name'];
        $key  = $bandCode . '|' . mb_strtolower($name);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $optIns->execute([$clientId, $productId, $sysId[$sysName], $bandCode, $SUPPLIER, $name, '', '', $sort++]);
        $added++;
    }
    $ops[] = "Imported {$added} slat-colour options (system-scoped, STD/SE tiers).";
    echo end($ops) . "\n";

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

echo "\n" . str_repeat('=', 60) . "\nSeed complete.\n\nSteps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
