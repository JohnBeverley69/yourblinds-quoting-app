<?php
declare(strict_types=1);

/**
 * Seeder: Arena Vertical Headrail Only — replacement vertical headrail/track,
 * a NO-FABRIC product (requires_option = 0) priced on WIDTH alone
 * (width_only = 1). Two headrail types as systems: "D6 Standard" and
 * "Senses & Vogue". One price table per system (band 'STD'), rows stored
 * width_mm / drop_mm 0 / price; the engine resolves it via the no-fabric
 * path and looks up by width (round-up).
 *
 * Data: seed_data/arena_vertical_headrail_only_prices.csv (system,width_mm,price)
 * Idempotent by name, transactional. Run via web: /seed_arena_vertical_headrail_only.php
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

$PRODUCT_NAME = 'Arena Vertical Headrail Only';
$SYSTEMS      = ['D6 Standard', 'Senses & Vogue'];
$PRICES_CSV   = __DIR__ . '/seed_data/arena_vertical_headrail_only_prices.csv';

$user = current_user(); $clientId = (int) ($user['client_id'] ?? 0);
if ($clientId <= 0) throw new RuntimeException('Could not determine your client_id.');
echo "Seeding \"{$PRODUCT_NAME}\" into client_id {$clientId}\n" . str_repeat('=', 60) . "\n\n";

$readCsv = static function (string $path): array {
    if (!is_file($path)) throw new RuntimeException("Missing: {$path}");
    $fh = fopen($path, 'r'); $h = fgetcsv($fh); $rows = [];
    while (($r = fgetcsv($fh)) !== false) { if ($r === [null] || $r === false) continue; $rows[] = array_combine($h, $r); }
    fclose($fh); return $rows;
};
$priceRows = $readCsv($PRICES_CSV);
$ops[] = sprintf('Read %d width rates.', count($priceRows)); echo end($ops) . "\n";

$pdo->beginTransaction();
try {
    $find = $pdo->prepare('SELECT id FROM products WHERE client_id = ? AND name = ?');
    $find->execute([$clientId, $PRODUCT_NAME]);
    $productId = (int) ($find->fetchColumn() ?: 0);
    if ($productId === 0) {
        $ss = $pdo->prepare('SELECT COALESCE(MAX(sort_order),-1)+1 FROM products WHERE client_id = ?'); $ss->execute([$clientId]);
        $ns = (int) $ss->fetchColumn();
        $pdo->prepare('INSERT INTO products (client_id,name,option_label,sort_order,active) VALUES (?,?,?,?,1)')->execute([$clientId,$PRODUCT_NAME,'Headrail',$ns]);
        $productId = (int) $pdo->lastInsertId(); $ops[] = "Created product #{$productId}.";
    } else { $ops[] = "Reusing product #{$productId}."; }
    echo end($ops) . "\n";

    // No-fabric, width-only.
    $pdo->prepare('UPDATE products SET width_only = 1, requires_option = 0 WHERE id = ?')->execute([$productId]);
    $ops[] = 'Set width_only = 1, requires_option = 0 (no-fabric).'; echo end($ops) . "\n";

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
    $ops[] = 'Cleared existing tables.'; echo end($ops) . "\n";

    // One price table per system (band 'STD'); width-only rows (drop 0).
    $grouped = [];
    foreach ($priceRows as $pr) $grouped[$pr['system']][] = $pr;
    $tblIns = $pdo->prepare("INSERT INTO price_tables (client_id,product_id,system_id,band_code,name,active) VALUES (?,?,?,'STD',?,1)");
    $rowIns = $pdo->prepare('INSERT INTO price_table_rows (price_table_id,width_mm,drop_mm,price) VALUES (?,?,0,?)');
    $tc=0; $cc=0;
    foreach ($grouped as $sysName => $cells) {
        if (!isset($sysId[$sysName])) continue;
        $tblIns->execute([$clientId,$productId,$sysId[$sysName],"Arena {$sysName} Headrail"]); $tid=(int)$pdo->lastInsertId();
        $wseen=[];
        foreach ($cells as $c) { $w=(int)$c['width_mm']; if(isset($wseen[$w]))continue; $wseen[$w]=1; $rowIns->execute([$tid,$w,(float)$c['price']]); $cc++; }
        $tc++;
    }
    $ops[] = "Built {$tc} width-only price tables ({$cc} widths)."; echo end($ops) . "\n";
    $pdo->commit();
} catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }

echo "\n" . str_repeat('=', 60) . "\nSeed complete.\n\nSteps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nNote: no-fabric, priced on headrail width alone.\n";
