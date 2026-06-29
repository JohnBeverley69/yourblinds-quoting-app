<?php
declare(strict_types=1);

/**
 * Seeder: Arena Wood Venetian (50mm) — Phase 1. Real-wood venetian, separate
 * from metal and faux (boss's call). "50mm Wood" umbrellas Bamboo + Everglade.
 *
 * One product, one "50mm" system, with the RANGE acting as the band (each has
 * its own single price grid, no sub-bands):
 *   band "Bamboo"   (FSC Bamboo, price list p37)
 *   band "Everglade"(Everglade wood, price list p30)
 * The "fabric" is the wood COLOUR (option_label "Colour"), banded to its range.
 * (Sherwood is a separate product — handled elsewhere.)
 *
 * Data: seed_data/arena_wood_venetian_prices.csv  (system,band,w,d,price)
 *       seed_data/arena_wood_venetian_colours.csv (system,name,band)
 * Idempotent by name, transactional. Run via web: /seed_arena_wood_venetian.php
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1'); error_reporting(E_ALL); @set_time_limit(300);
$pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$ops = [];
set_exception_handler(function (Throwable $e) use (&$ops) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) header('Content-Type: text/plain; charset=utf-8');
    echo "Seed FAILED: " . $e->getMessage() . "\n\nSteps:\n";
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

$PRODUCT_NAME = 'Arena Wood Venetian';
$SYSTEMS      = ['50mm'];
$DATA_DIR     = __DIR__ . '/seed_data';
$PRICES_CSV   = $DATA_DIR . '/arena_wood_venetian_prices.csv';
$COLOURS_CSV  = $DATA_DIR . '/arena_wood_venetian_colours.csv';

$user = current_user(); $clientId = (int) ($user['client_id'] ?? 0);
if ($clientId <= 0) throw new RuntimeException('Could not determine your client_id.');
echo "Seeding \"{$PRODUCT_NAME}\" into client_id {$clientId}\n" . str_repeat('=', 60) . "\n\n";

$readCsv = static function (string $path): array {
    if (!is_file($path)) throw new RuntimeException("Missing: {$path}");
    $fh = fopen($path, 'r'); $h = fgetcsv($fh); $rows = [];
    while (($r = fgetcsv($fh)) !== false) { if ($r === [null] || $r === false) continue; $rows[] = array_combine($h, $r); }
    fclose($fh); return $rows;
};
$priceRows  = $readCsv($PRICES_CSV);
$colourRows = $readCsv($COLOURS_CSV);
$ops[] = sprintf('Read %d price cells, %d colours.', count($priceRows), count($colourRows));
echo end($ops) . "\n";

$pdo->beginTransaction();
try {
    $find = $pdo->prepare('SELECT id FROM products WHERE client_id = ? AND name = ?');
    $find->execute([$clientId, $PRODUCT_NAME]);
    $productId = (int) ($find->fetchColumn() ?: 0);
    if ($productId === 0) {
        $ss = $pdo->prepare('SELECT COALESCE(MAX(sort_order),-1)+1 FROM products WHERE client_id = ?'); $ss->execute([$clientId]);
        $ns = (int) $ss->fetchColumn();
        try { $pdo->prepare('INSERT INTO products (client_id,name,option_label,show_colour_field,sort_order,active) VALUES (?,?,?,0,?,1)')->execute([$clientId,$PRODUCT_NAME,'Colour',$ns]); }
        catch (Throwable $e) { $pdo->prepare('INSERT INTO products (client_id,name,option_label,sort_order,active) VALUES (?,?,?,?,1)')->execute([$clientId,$PRODUCT_NAME,'Colour',$ns]); }
        $productId = (int) $pdo->lastInsertId(); $ops[] = "Created product #{$productId}.";
    } else { $ops[] = "Reusing product #{$productId}."; }
    echo end($ops) . "\n";

    $sysId = [];
    foreach ($SYSTEMS as $i => $sysName) {
        $fs = $pdo->prepare('SELECT id FROM product_systems WHERE client_id=? AND product_id=? AND name=?'); $fs->execute([$clientId,$productId,$sysName]);
        $id = (int) ($fs->fetchColumn() ?: 0);
        if ($id === 0) { $pdo->prepare('INSERT INTO product_systems (client_id,product_id,name,sort_order,active,is_default) VALUES (?,?,?,?,1,?)')->execute([$clientId,$productId,$sysName,$i,$i===0?1:0]); $id=(int)$pdo->lastInsertId(); }
        else { $pdo->prepare('UPDATE product_systems SET active=1,is_default=?,sort_order=? WHERE id=?')->execute([$i===0?1:0,$i,$id]); }
        $sysId[$sysName] = $id;
    }
    $ops[] = 'Systems: ' . implode(', ', array_keys($sysId)) . '.'; echo end($ops) . "\n";

    $pdo->prepare('DELETE r FROM price_table_rows r JOIN price_tables t ON t.id=r.price_table_id WHERE t.client_id=? AND t.product_id=?')->execute([$clientId,$productId]);
    $pdo->prepare('DELETE FROM price_tables WHERE client_id=? AND product_id=?')->execute([$clientId,$productId]);
    $pdo->prepare('DELETE FROM product_options WHERE client_id=? AND product_id=?')->execute([$clientId,$productId]);
    $ops[] = 'Cleared existing tables + options.'; echo end($ops) . "\n";

    $grouped = [];
    foreach ($priceRows as $pr) $grouped[$pr['system'] . '|' . $pr['band']][] = $pr;
    $tblIns = $pdo->prepare('INSERT INTO price_tables (client_id,product_id,system_id,band_code,name,active) VALUES (?,?,?,?,?,1)');
    $rowIns = $pdo->prepare('INSERT INTO price_table_rows (price_table_id,width_mm,drop_mm,price) VALUES (?,?,?,?)');
    $tc = 0; $cc = 0;
    foreach ($grouped as $key => $cells) {
        [$sysName, $band] = explode('|', $key, 2);
        if (!isset($sysId[$sysName])) continue;
        $tblIns->execute([$clientId,$productId,$sysId[$sysName],$band,"Arena {$band}"]); $tid=(int)$pdo->lastInsertId();
        $cseen=[];
        foreach ($cells as $c) { $ck=(int)$c['width_mm'].'x'.(int)$c['drop_mm']; if(isset($cseen[$ck]))continue; $cseen[$ck]=1; $rowIns->execute([$tid,(int)$c['width_mm'],(int)$c['drop_mm'],(float)$c['price']]); $cc++; }
        $tc++;
    }
    $ops[] = "Built {$tc} price tables ({$cc} cells)."; echo end($ops) . "\n";

    $optIns = $pdo->prepare('INSERT INTO product_options (client_id,product_id,system_id,band_code,supplier_name,name,colour,code,sort_order,active) VALUES (?,?,NULL,?,?,?,?,?,?,1)');
    $added = 0; $sort = 0; $seen = [];
    foreach ($colourRows as $c) {
        $band = trim((string) $c['band']); $name = (string) $c['name'];
        $key = $band . '|' . mb_strtolower($name);
        if (isset($seen[$key])) continue; $seen[$key] = true;
        $optIns->execute([$clientId,$productId,$band,'Arena',$name,'','',$sort++]); $added++;
    }
    $ops[] = "Imported {$added} colour options (banded by range)."; echo end($ops) . "\n";
    $pdo->commit();
} catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }

echo "\n" . str_repeat('=', 60) . "\nSeed complete.\n\nSteps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
