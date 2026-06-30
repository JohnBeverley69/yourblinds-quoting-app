<?php
declare(strict_types=1);

/**
 * Seeder: Arena Curved Vertical (89mm) — curved-track vertical blind. Standard
 * width×drop pricing, one "89mm" system, bands Elements + A–D (no Band E —
 * curved tops out at D). Fabrics are the 89mm vertical fabrics: band in
 * {Elements,A,B,C,D} → active; Band E / unbanded → 'TBC' inactive (E can't
 * price on curved).
 *
 * Data: seed_data/arena_curved_vertical_prices.csv (system,band,w,d,price)
 *       seed_data/arena_vertical_fabrics.csv        (system,name,colour,band) — 89mm rows used
 * Idempotent by name, transactional. Run via web: /seed_arena_curved_vertical.php
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1'); error_reporting(E_ALL); @set_time_limit(300); @ini_set('memory_limit','512M');
$pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$ops = [];
set_exception_handler(function (Throwable $e) use (&$ops) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) header('Content-Type: text/plain; charset=utf-8');
    echo "Seed FAILED: " . $e->getMessage() . "\n\nSteps:\n";
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

$PRODUCT_NAME = 'Arena Curved Vertical';
$SUPPLIER     = 'Arena';
$SYSTEMS      = ['89mm'];
$VALID        = ['Elements'=>1,'A'=>1,'B'=>1,'C'=>1,'D'=>1];  // no E on curved
$DATA_DIR     = __DIR__ . '/seed_data';
$PRICES_CSV   = $DATA_DIR . '/arena_curved_vertical_prices.csv';
$FABRICS_CSV  = $DATA_DIR . '/arena_vertical_fabrics.csv';

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
$fabricRows = array_values(array_filter($readCsv($FABRICS_CSV), fn($f)=>($f['system']??'')==='89mm'));
$ops[] = sprintf('Read %d price cells, %d (89mm) fabrics.', count($priceRows), count($fabricRows));
echo end($ops) . "\n";

$pdo->beginTransaction();
try {
    $find = $pdo->prepare('SELECT id FROM products WHERE client_id = ? AND name = ?');
    $find->execute([$clientId, $PRODUCT_NAME]);
    $productId = (int) ($find->fetchColumn() ?: 0);
    if ($productId === 0) {
        $ss = $pdo->prepare('SELECT COALESCE(MAX(sort_order),-1)+1 FROM products WHERE client_id = ?'); $ss->execute([$clientId]);
        $ns = (int) $ss->fetchColumn();
        try { $pdo->prepare('INSERT INTO products (client_id,name,option_label,show_colour_field,sort_order,active) VALUES (?,?,?,1,?,1)')->execute([$clientId,$PRODUCT_NAME,'Fabric',$ns]); }
        catch (Throwable $e) { $pdo->prepare('INSERT INTO products (client_id,name,option_label,sort_order,active) VALUES (?,?,?,?,1)')->execute([$clientId,$PRODUCT_NAME,'Fabric',$ns]); }
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
    foreach ($priceRows as $pr) $grouped[$pr['system'].'|'.$pr['band']][] = $pr;
    $tblIns = $pdo->prepare('INSERT INTO price_tables (client_id,product_id,system_id,band_code,name,active) VALUES (?,?,?,?,?,1)');
    $rowIns = $pdo->prepare('INSERT INTO price_table_rows (price_table_id,width_mm,drop_mm,price) VALUES (?,?,?,?)');
    $tc=0; $cc=0;
    foreach ($grouped as $key => $cells) {
        [$sysName,$band] = explode('|',$key,2);
        if (!isset($sysId[$sysName])) continue;
        $tblIns->execute([$clientId,$productId,$sysId[$sysName],$band,"Arena Band {$band}"]); $tid=(int)$pdo->lastInsertId();
        foreach ($cells as $c) { $rowIns->execute([$tid,(int)$c['width_mm'],(int)$c['drop_mm'],(float)$c['price']]); $cc++; }
        $tc++;
    }
    $ops[] = "Built {$tc} price tables ({$cc} cells)."; echo end($ops) . "\n";

    // Fabrics (89mm only, one system) — collapse by (name,colour,band); band in VALID -> active.
    $optIns = $pdo->prepare('INSERT INTO product_options (client_id,product_id,system_id,band_code,supplier_name,name,colour,code,sort_order,active) VALUES (?,?,NULL,?,?,?,?,?,?,?)');
    $added=0; $inactive=0; $sort=0; $seen=[];
    foreach ($fabricRows as $f) {
        $band=trim((string)$f['band']); $banded=isset($VALID[$band]); $bc=$banded?$band:'TBC';
        $name=(string)$f['name']; $colour=(string)($f['colour']??'');
        $k=$bc.'|'.mb_strtolower($name).'|'.mb_strtolower($colour);
        if(isset($seen[$k])) continue; $seen[$k]=true;
        $optIns->execute([$clientId,$productId,$bc,$SUPPLIER,$name,$colour,'',$sort++,$banded?1:0]);
        $banded?$added++:$inactive++;
    }
    $ops[] = "Imported ".($added+$inactive)." fabrics: {$added} active (A–D+Elements), {$inactive} inactive (Band E / no band)."; echo end($ops) . "\n";
    $pdo->commit();
} catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }

echo "\n" . str_repeat('=', 60) . "\nSeed complete.\n\nSteps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
