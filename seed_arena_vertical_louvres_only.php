<?php
declare(strict_types=1);

/**
 * Seeder: Arena Vertical Louvres Only — replacement vertical slats, priced
 * PER SLAT (products.price_per_slat = 1): the price table is a drop →
 * price-per-slat list (rows stored width_mm 0, drop_mm = drop, price = per
 * slat). The engine looks the per-slat price up by drop and multiplies by
 * the quote line quantity (= number of louvres ordered).
 *
 * Systems = louvre width (89mm / 127mm). Bands = Elements + A–E (the louvre
 * fabric's band sets the per-slat rate). Fabrics are the standard vertical
 * fabrics, collapsed to one row per (name,colour,band) — system_id NULL when
 * the fabric is on both widths, else the single width. Unbanded '?' → 'TBC'
 * inactive.
 *
 * Data: seed_data/arena_vertical_louvres_only_prices.csv (system,band,drop_mm,price_per_slat)
 *       seed_data/arena_vertical_fabrics.csv             (system,name,colour,band)
 * Idempotent by name, transactional. Run via web: /seed_arena_vertical_louvres_only.php
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

$PRODUCT_NAME = 'Arena Vertical Louvres Only';
$SUPPLIER     = 'Arena';
$SYSTEMS      = ['89mm', '127mm'];
$VALID        = ['Elements'=>1,'A'=>1,'B'=>1,'C'=>1,'D'=>1,'E'=>1];
$DATA_DIR     = __DIR__ . '/seed_data';
$PRICES_CSV   = $DATA_DIR . '/arena_vertical_louvres_only_prices.csv';
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
$fabricRows = $readCsv($FABRICS_CSV);
$ops[] = sprintf('Read %d per-slat rates, %d fabrics.', count($priceRows), count($fabricRows));
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

    // Price-per-slat mode.
    $pdo->prepare('UPDATE products SET price_per_slat = 1 WHERE id = ?')->execute([$productId]);
    $ops[] = 'Set price_per_slat = 1.'; echo end($ops) . "\n";

    $sysId = [];
    foreach ($SYSTEMS as $i => $sysName) {
        $fs = $pdo->prepare('SELECT id FROM product_systems WHERE client_id=? AND product_id=? AND name=?'); $fs->execute([$clientId,$productId,$sysName]);
        $id = (int) ($fs->fetchColumn() ?: 0);
        if ($id === 0) { $pdo->prepare('INSERT INTO product_systems (client_id,product_id,name,sort_order,active,is_default) VALUES (?,?,?,?,1,?)')->execute([$clientId,$productId,$sysName,$i,$i===0?1:0]); $id=(int)$pdo->lastInsertId(); }
        else { $pdo->prepare('UPDATE product_systems SET active=1,is_default=?,sort_order=? WHERE id=?')->execute([$i===0?1:0,$i,$id]); }
        $sysId[$sysName] = $id;
    }
    $ops[] = 'Systems: ' . implode(', ', array_map(fn($n)=>"{$n}=#{$sysId[$n]}",$SYSTEMS)) . '.'; echo end($ops) . "\n";

    $pdo->prepare('DELETE r FROM price_table_rows r JOIN price_tables t ON t.id=r.price_table_id WHERE t.client_id=? AND t.product_id=?')->execute([$clientId,$productId]);
    $pdo->prepare('DELETE FROM price_tables WHERE client_id=? AND product_id=?')->execute([$clientId,$productId]);
    $pdo->prepare('DELETE FROM product_options WHERE client_id=? AND product_id=?')->execute([$clientId,$productId]);
    $ops[] = 'Cleared existing tables + options.'; echo end($ops) . "\n";

    // Per-slat price tables: one per (system, band); rows = (width 0, drop, per-slat price).
    $grouped = [];
    foreach ($priceRows as $pr) $grouped[$pr['system'] . '|' . $pr['band']][] = $pr;
    $tblIns = $pdo->prepare('INSERT INTO price_tables (client_id,product_id,system_id,band_code,name,active) VALUES (?,?,?,?,?,1)');
    $rowIns = $pdo->prepare('INSERT INTO price_table_rows (price_table_id,width_mm,drop_mm,price) VALUES (?,0,?,?)');
    $tc=0; $cc=0;
    foreach ($grouped as $key => $cells) {
        [$sysName,$band] = explode('|',$key,2);
        if (!isset($sysId[$sysName])) continue;
        $tblIns->execute([$clientId,$productId,$sysId[$sysName],$band,"Arena Band {$band}"]); $tid=(int)$pdo->lastInsertId();
        foreach ($cells as $c) { $rowIns->execute([$tid,(int)$c['drop_mm'],(float)$c['price_per_slat']]); $cc++; }
        $tc++;
    }
    $ops[] = "Built {$tc} per-slat price tables ({$cc} rates)."; echo end($ops) . "\n";

    // Fabrics — collapse by (name,colour,band); system_id NULL if on both widths.
    $byKey = [];
    foreach ($fabricRows as $f) {
        $sysName=(string)$f['system']; if(!isset($sysId[$sysName])) continue;
        $band=trim((string)$f['band']); $band=($band===''||$band==='?')?'TBC':$band;
        $name=(string)$f['name']; $colour=(string)($f['colour']??'');
        $k=$name.'|'.$colour.'|'.$band;
        if(!isset($byKey[$k])) $byKey[$k]=['name'=>$name,'colour'=>$colour,'band'=>$band,'sys'=>[]];
        $byKey[$k]['sys'][$sysName]=true;
    }
    $optIns = $pdo->prepare('INSERT INTO product_options (client_id,product_id,system_id,band_code,supplier_name,name,colour,code,sort_order,active) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $added=0; $inactive=0; $sort=0;
    foreach ($byKey as $o) {
        $banded = isset($VALID[$o['band']]);
        $sysv = count($o['sys'])>=count($SYSTEMS) ? null : $sysId[array_key_first($o['sys'])];
        $optIns->execute([$clientId,$productId,$sysv,$o['band'],$SUPPLIER,$o['name'],$o['colour'],'',$sort++,$banded?1:0]);
        $banded?$added++:$inactive++;
    }
    $ops[] = "Imported ".($added+$inactive)." fabrics: {$added} active, {$inactive} inactive (TBC)."; echo end($ops) . "\n";
    $pdo->commit();
} catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }

echo "\n" . str_repeat('=', 60) . "\nSeed complete.\n\nSteps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nNote: this is priced PER SLAT — the quote line quantity = number of louvres.\n";
