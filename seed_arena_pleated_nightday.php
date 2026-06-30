<?php
declare(strict_types=1);

/**
 * Seeder: Arena Pleated Night & Day (BlindScreen Dual / Double-Track) —
 * width×drop, NO fabric band (one 'STD' table per system). Three frame
 * systems from the price list p223: Freehang, Perfect Fit, Perfect Fit
 * Golden Oak. Fabrics = the four BlindScreen ranges (Breeze In, Light Seal,
 * Scene Set, Sheer Intimacy) + colours, harvested from the PLT configurator
 * (PDS/PDT BlindScreen). NB this is a dual-layer (day + night screen) product;
 * we model a single fabric/colour pick — price is flat per frame type, so the
 * two-screen pairing is an Arena order-time detail that doesn't affect price.
 *
 * Data: seed_data/arena_pleated_nightday_prices.csv  (system,band,w,d,price)
 *       seed_data/arena_pleated_nightday_fabrics.csv (name,colour,band)
 * Idempotent by name, transactional. Run via web: /seed_arena_pleated_nightday.php
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors','1'); error_reporting(E_ALL); @set_time_limit(300);
$pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$ops=[];
set_exception_handler(function(Throwable $e) use (&$ops){ if(PHP_SAPI!=='cli'&&!headers_sent())header('Content-Type: text/plain; charset=utf-8'); echo "Seed FAILED: ".$e->getMessage()."\n\nSteps:\n"; foreach($ops as $i=>$o)echo sprintf("  %2d. %s\n",$i+1,$o); exit(1); });

$PRODUCT_NAME='Arena Pleated Night & Day'; $SYSTEMS=['Freehang','Perfect Fit','Perfect Fit Golden Oak']; $VALID=['STD'=>1];
$DATA=__DIR__.'/seed_data'; $PRICES="$DATA/arena_pleated_nightday_prices.csv"; $FABRICS="$DATA/arena_pleated_nightday_fabrics.csv";
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
    foreach($grp as $k=>$cells){ [$s,$b]=explode('|',$k,2); if(!isset($sysId[$s]))continue; $ti->execute([$clientId,$productId,$sysId[$s],$b,"Arena $s"]);$tid=(int)$pdo->lastInsertId();
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
