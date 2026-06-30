<?php
declare(strict_types=1);

/**
 * Seeder: Arena Diffused (Vertical Diffused Shade) — width×drop, one
 * "Standard" system, bands A & B (price list p79-80). Fabrics from the
 * Diffused Specification (p81): Sense colours = band A, Splendour/Splendour
 * FR = band B. All banded.
 *
 * Data: seed_data/arena_diffused_prices.csv, seed_data/arena_diffused_fabrics.csv
 * Idempotent by name, transactional. Run via web: /seed_arena_diffused.php
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors','1'); error_reporting(E_ALL); @set_time_limit(300);
$pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$ops=[];
set_exception_handler(function(Throwable $e) use (&$ops){ if(PHP_SAPI!=='cli'&&!headers_sent())header('Content-Type: text/plain; charset=utf-8'); echo "Seed FAILED: ".$e->getMessage()."\n\nSteps:\n"; foreach($ops as $i=>$o)echo sprintf("  %2d. %s\n",$i+1,$o); exit(1); });

$PRODUCT_NAME='Arena Diffused'; $SYSTEMS=['Standard']; $VALID=['A'=>1,'B'=>1];
$DATA=__DIR__.'/seed_data'; $PRICES="$DATA/arena_diffused_prices.csv"; $FABRICS="$DATA/arena_diffused_fabrics.csv";
$user=current_user(); $clientId=(int)($user['client_id']??0);
if($clientId<=0) throw new RuntimeException('No client_id.');
echo "Seeding \"{$PRODUCT_NAME}\" into client_id {$clientId}\n".str_repeat('=',60)."\n\n";

$readCsv=static function(string $p):array{ if(!is_file($p))throw new RuntimeException("Missing: $p"); $fh=fopen($p,'r');$h=fgetcsv($fh);$o=[]; while(($r=fgetcsv($fh))!==false){if($r===[null]||$r===false)continue;$o[]=array_combine($h,$r);} fclose($fh); return $o; };
$priceRows=$readCsv($PRICES); $fabricRows=$readCsv($FABRICS);
$ops[]=sprintf('Read %d price cells, %d fabrics.',count($priceRows),count($fabricRows)); echo end($ops)."\n";

$pdo->beginTransaction();
try {
    $f=$pdo->prepare('SELECT id FROM products WHERE client_id=? AND name=?'); $f->execute([$clientId,$PRODUCT_NAME]);
    $productId=(int)($f->fetchColumn()?:0);
    if($productId===0){ $ss=$pdo->prepare('SELECT COALESCE(MAX(sort_order),-1)+1 FROM products WHERE client_id=?');$ss->execute([$clientId]);$ns=(int)$ss->fetchColumn();
        try{$pdo->prepare('INSERT INTO products (client_id,name,option_label,show_colour_field,sort_order,active) VALUES (?,?,?,1,?,1)')->execute([$clientId,$PRODUCT_NAME,'Fabric',$ns]);}
        catch(Throwable $e){$pdo->prepare('INSERT INTO products (client_id,name,option_label,sort_order,active) VALUES (?,?,?,?,1)')->execute([$clientId,$PRODUCT_NAME,'Fabric',$ns]);}
        $productId=(int)$pdo->lastInsertId(); $ops[]="Created product #{$productId}.";
    } else { $ops[]="Reusing product #{$productId}."; } echo end($ops)."\n";

    $sysId=[];
    foreach($SYSTEMS as $i=>$s){ $q=$pdo->prepare('SELECT id FROM product_systems WHERE client_id=? AND product_id=? AND name=?');$q->execute([$clientId,$productId,$s]);$id=(int)($q->fetchColumn()?:0);
        if($id===0){$pdo->prepare('INSERT INTO product_systems (client_id,product_id,name,sort_order,active,is_default) VALUES (?,?,?,?,1,?)')->execute([$clientId,$productId,$s,$i,$i===0?1:0]);$id=(int)$pdo->lastInsertId();}
        else{$pdo->prepare('UPDATE product_systems SET active=1,is_default=?,sort_order=? WHERE id=?')->execute([$i===0?1:0,$i,$id]);}
        $sysId[$s]=$id; }
    $ops[]='Systems: '.implode(', ',array_keys($sysId)).'.'; echo end($ops)."\n";

    $pdo->prepare('DELETE r FROM price_table_rows r JOIN price_tables t ON t.id=r.price_table_id WHERE t.client_id=? AND t.product_id=?')->execute([$clientId,$productId]);
    $pdo->prepare('DELETE FROM price_tables WHERE client_id=? AND product_id=?')->execute([$clientId,$productId]);
    $pdo->prepare('DELETE FROM product_options WHERE client_id=? AND product_id=?')->execute([$clientId,$productId]);
    $ops[]='Cleared existing tables + options.'; echo end($ops)."\n";

    $grp=[]; foreach($priceRows as $p)$grp[$p['system'].'|'.$p['band']][]=$p;
    $ti=$pdo->prepare('INSERT INTO price_tables (client_id,product_id,system_id,band_code,name,active) VALUES (?,?,?,?,?,1)');
    $ri=$pdo->prepare('INSERT INTO price_table_rows (price_table_id,width_mm,drop_mm,price) VALUES (?,?,?,?)');
    $tc=0;$cc=0;
    foreach($grp as $k=>$cells){ [$s,$b]=explode('|',$k,2); if(!isset($sysId[$s]))continue; $ti->execute([$clientId,$productId,$sysId[$s],$b,"Arena Band $b"]);$tid=(int)$pdo->lastInsertId();
        foreach($cells as $c){$ri->execute([$tid,(int)$c['width_mm'],(int)$c['drop_mm'],(float)$c['price']]);$cc++;} $tc++; }
    $ops[]="Built {$tc} price tables ({$cc} cells)."; echo end($ops)."\n";

    $oi=$pdo->prepare('INSERT INTO product_options (client_id,product_id,system_id,band_code,supplier_name,name,colour,code,sort_order,active) VALUES (?,?,NULL,?,?,?,?,?,?,?)');
    $a=0;$t=0;$sort=0;$seen=[];
    foreach($fabricRows as $fr){ $band=trim((string)$fr['band']);$banded=isset($VALID[$band]);$bc=$banded?$band:'TBC';$name=(string)$fr['name'];$colour=(string)($fr['colour']??'');
        $k=$bc.'|'.mb_strtolower($name).'|'.mb_strtolower($colour); if(isset($seen[$k]))continue;$seen[$k]=1;
        $oi->execute([$clientId,$productId,$bc,'Arena',$name,$colour,'',$sort++,$banded?1:0]); $banded?$a++:$t++; }
    $ops[]="Imported ".($a+$t)." fabrics: {$a} active, {$t} inactive."; echo end($ops)."\n";
    $pdo->commit();
} catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
echo "\n".str_repeat('=',60)."\nSeed complete.\n\nSteps:\n"; foreach($ops as $i=>$o)echo sprintf("  %2d. %s\n",$i+1,$o);
