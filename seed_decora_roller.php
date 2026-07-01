<?php
declare(strict_types=1);

/**
 * Seeder: DECORA ROLLER (EDI blind type "R" / "Roller") — FULL EDI-driven
 * template. First fully-built Decora product; the pattern for the rest.
 *
 * SOURCES (Decora is a 3-source assembly):
 *   - Base price grids  : seed_data/decora_roller_prices.csv (from Decora Excel
 *       trade book "FB Roller", the price grid for blind type R). CLEAN LIST —
 *       ex-VAT, PRE-discount (portal auto-applies our discount+VAT; we ignore
 *       both). Bands A–E. Stored zero discount / zero markup.
 *   - Fabrics + options : seed_data/decora_roller_{fabrics,options}.csv (from
 *       the Decora EDI dataset Roller.csv) — 845 fabrics with EDI codes + band
 *       (Price Group); 28 option groups / 595 choices with EDI codes + default.
 *   - Option £ surcharge: Decora trade book. Wired here: Bracket Cover Cap
 *       "Yes" = £0.30/blind (FB Roller sheet Additional Info). Other priced
 *       options (Motor / Pull / Remote — trade-book accessory & motor sheets)
 *       left £0, FLAGGED for the option-pricing pass.
 *
 * NOTES / KNOWN REFINEMENTS:
 *   - Fabric EDI codes stored in product_options.code. Option-choice EDI codes
 *     have no schema field yet → appended to the label as " [CODE]" so they're
 *     preserved for future EDI ordering (drop once a code column exists).
 *   - Fabric bands A3/AA (17 fabrics) have no A–E price grid → stored TBC +
 *     inactive (flag: need their grids).
 *   - Options are flat (no gating) per the EDI's flat structure; gating (e.g.
 *     Motor only when motorised) is a later refinement.
 *
 * Idempotent by name, transactional. Run: /seed_decora_roller.php
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors','1'); error_reporting(E_ALL); @set_time_limit(600); @ini_set('memory_limit','512M');
$pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$ops=[];
set_exception_handler(function(Throwable $e) use (&$ops){ if(PHP_SAPI!=='cli'&&!headers_sent())header('Content-Type: text/plain; charset=utf-8'); echo "Seed FAILED: ".$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n\nSteps:\n"; foreach($ops as $i=>$o)echo sprintf("  %2d. %s\n",$i+1,$o); exit(1); });

$PRODUCT_NAME='Decora Roller'; $SUPPLIER='Decora'; $BLIND_CODE='R'; $SYSTEMS=['Standard']; $VALID=['A'=>1,'B'=>1,'C'=>1,'D'=>1,'E'=>1];
$DATA=__DIR__.'/seed_data';
$PRICES="$DATA/decora_roller_prices.csv"; $FABRICS="$DATA/decora_roller_fabrics.csv"; $OPTIONS="$DATA/decora_roller_options.csv";
$user=current_user(); $clientId=(int)($user['client_id']??0);
if($clientId<=0) throw new RuntimeException('No client_id.');
echo "Seeding \"{$PRODUCT_NAME}\" (EDI {$BLIND_CODE}) into client_id {$clientId}\n".str_repeat('=',60)."\n\n";

$readCsv=static function(string $p):array{ if(!is_file($p))throw new RuntimeException("Missing: $p"); $fh=fopen($p,'r');$h=fgetcsv($fh);$o=[]; while(($r=fgetcsv($fh))!==false){if($r===[null]||$r===false)continue;$o[]=array_combine($h,$r);} fclose($fh); return $o; };
$priceRows=$readCsv($PRICES); $fabricRows=$readCsv($FABRICS); $optionRows=$readCsv($OPTIONS);
$ops[]=sprintf('Read %d price cells, %d fabrics, %d option choices.',count($priceRows),count($fabricRows),count($optionRows)); echo end($ops)."\n";

// Option surcharges wired from the trade book (Additional Info). Keyed by option group + choice label.
$OPT_PRICE=[ 'Bracket Cover Cap|Yes' => 0.30 ];

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

    // wipe existing tables/fabrics/options for a clean rebuild
    $pdo->prepare('DELETE r FROM price_table_rows r JOIN price_tables t ON t.id=r.price_table_id WHERE t.client_id=? AND t.product_id=?')->execute([$clientId,$productId]);
    $pdo->prepare('DELETE FROM price_tables WHERE client_id=? AND product_id=?')->execute([$clientId,$productId]);
    $pdo->prepare('DELETE FROM product_options WHERE client_id=? AND product_id=?')->execute([$clientId,$productId]);
    $ex=$pdo->prepare('SELECT id FROM product_extras WHERE client_id=? AND product_id=?'); $ex->execute([$clientId,$productId]); $exIds=$ex->fetchAll(PDO::FETCH_COLUMN);
    if($exIds){$in=implode(',',array_fill(0,count($exIds),'?'));
        $ch=$pdo->prepare("SELECT id FROM product_extra_choices WHERE product_extra_id IN ($in)");$ch->execute($exIds);$cIds=$ch->fetchAll(PDO::FETCH_COLUMN);
        if($cIds){$cin=implode(',',array_fill(0,count($cIds),'?'));$pdo->prepare("DELETE FROM extra_choice_price_rows WHERE product_extra_choice_id IN ($cin)")->execute($cIds);}
        $pdo->prepare("DELETE FROM product_extra_parent_choices WHERE product_extra_id IN ($in)")->execute($exIds);
        $pdo->prepare("DELETE FROM product_extra_choices WHERE product_extra_id IN ($in)")->execute($exIds);
        $pdo->prepare("DELETE FROM product_extras WHERE id IN ($in)")->execute($exIds); }
    $ops[]='Cleared existing tables + options + extras.'; echo end($ops)."\n";

    // base price tables (bands A–E)
    $grp=[]; foreach($priceRows as $p)$grp[$p['system'].'|'.$p['band']][]=$p;
    $ti=$pdo->prepare('INSERT INTO price_tables (client_id,product_id,system_id,band_code,name,active) VALUES (?,?,?,?,?,1)');
    $ri=$pdo->prepare('INSERT INTO price_table_rows (price_table_id,width_mm,drop_mm,price) VALUES (?,?,?,?)');
    $tc=0;$cc=0;
    foreach($grp as $k=>$cells){ [$s,$b]=explode('|',$k,2); if(!isset($sysId[$s]))continue; $ti->execute([$clientId,$productId,$sysId[$s],$b,"Decora Band $b"]);$tid=(int)$pdo->lastInsertId();
        foreach($cells as $c){$ri->execute([$tid,(int)$c['width_mm'],(int)$c['drop_mm'],(float)$c['price']]);$cc++;} $tc++; }
    $ops[]="Built {$tc} price tables ({$cc} cells)."; echo end($ops)."\n";

    // fabrics (EDI codes in product_options.code; band in A–E active, else TBC inactive)
    $oi=$pdo->prepare('INSERT INTO product_options (client_id,product_id,system_id,band_code,supplier_name,name,colour,code,sort_order,active) VALUES (?,?,NULL,?,?,?,?,?,?,?)');
    $a=0;$t=0;$sort=0;$seen=[];
    foreach($fabricRows as $fr){ $band=trim((string)$fr['band']);$banded=isset($VALID[$band]);$bc=$banded?$band:'TBC';$name=(string)$fr['name'];$colour=(string)($fr['colour']??'');$code=(string)($fr['code']??'');
        $k=$bc.'|'.mb_strtolower($name).'|'.mb_strtolower($colour); if(isset($seen[$k]))continue;$seen[$k]=1;
        $oi->execute([$clientId,$productId,$bc,$SUPPLIER,$name,$colour,$code,$sort++,$banded?1:0]); $banded?$a++:$t++; }
    $ops[]="Imported ".($a+$t)." fabrics: {$a} active, {$t} inactive (A3/AA no grid)."; echo end($ops)."\n";

    // options: one extra per EDI group (first-appearance order); choices with default; code appended to label
    $insExtra=$pdo->prepare('INSERT INTO product_extras (client_id,product_id,parent_choice_id,name,is_required,sort_order,active) VALUES (?,?,NULL,?,0,?,1)');
    $insChoice=$pdo->prepare('INSERT INTO product_extra_choices (product_extra_id,system_id,label,image_path,price_delta,price_percent,price_per_metre,is_default,sort_order,active) VALUES (?,NULL,?,NULL,?,0,0,?,?,1)');
    $order=[]; $byGroup=[];
    foreach($optionRows as $r){ $g=trim((string)$r['group']); if($g==='')continue; if(!isset($byGroup[$g])){$byGroup[$g]=[];$order[]=$g;} $byGroup[$g][]=$r; }
    $nx=0;$nc=0;$so=0;$priced=0;
    foreach($order as $g){ $insExtra->execute([$clientId,$productId,$g,$so++]); $eid=(int)$pdo->lastInsertId(); $nx++; $cs=0;
        foreach($byGroup[$g] as $r){ $label=(string)$r['label']; $code=trim((string)($r['code']??'')); $lbl=$code!==''?($label.' ['.$code.']'):$label;
            $delta=(float)($OPT_PRICE[$g.'|'.$label]??0); if($delta>0)$priced++;
            $insChoice->execute([$eid,$lbl,$delta,(trim((string)$r['is_default'])!==''?1:0),$cs++]); $nc++; }
    }
    $ops[]="Built {$nx} option groups, {$nc} choices ({$priced} priced)."; echo end($ops)."\n";
    $pdo->commit();
} catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
echo "\n".str_repeat('=',60)."\nSeed complete.\n\nSteps:\n"; foreach($ops as $i=>$o)echo sprintf("  %2d. %s\n",$i+1,$o);
echo "\nDecora LIST prices (ex-VAT, zero discount/markup). Options mostly £0 pending option-pricing pass.\n";
