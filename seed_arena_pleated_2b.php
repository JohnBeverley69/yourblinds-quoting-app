<?php
declare(strict_types=1);

/**
 * Pleated family — WAVE 2b (the dual-layer tail):
 *   A) FIX "Arena Pleated Night & Day" #5770 (Arena type PYN). Base = p223
 *      grids (Freehang/Perfect Fit/PF Golden Oak, flat STD). Night & Day
 *      Pleated ("Transition") is dual-layer using the STANDARD pleated fabric
 *      ranges (Duette/Hive/etc.), NOT BlindScreen fabrics — so fabrics swap to
 *      arena_pleated_fabrics.csv (single-pick simplification; all forced STD
 *      since base is flat).
 *   B) CREATE "Arena BlindScreen" (Arena type PDS/PDT/PLB). Base = p248-256
 *      RRP matrices → 6 systems (Model 01/02/03 × Single/Dual/Double-Track),
 *      flat STD. Fabrics = Breeze In/Light Seal/Scene Set/Sheer Intimacy
 *      (arena_pleated_nightday_fabrics.csv, single-pick; dual-layer combo is an
 *      order-time detail). Options: Frame Colour, Control Type, Low Rail Type,
 *      Bunch Side, Exact/Recess. FLAGGED for UI: Sella Trim £2.20/sqm,
 *      Vanoseal £4.75/pack (per-sqm/pack surcharges, left off).
 *
 * DRY-RUN unless ?apply=1; idempotent. Super-admin only.
 *   Preview: /seed_arena_pleated_2b.php   Apply: &apply=1
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors','1'); error_reporting(E_ALL); @set_time_limit(600); @ini_set('memory_limit','512M');

$user=current_user(); $clientId=(int)$user['client_id'];
$pdo=db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$apply=(($_GET['apply']??'')==='1');

$readCsv=static function(string $p):array{ if(!is_file($p))throw new RuntimeException("Missing: $p"); $fh=fopen($p,'r');$h=fgetcsv($fh);$o=[]; while(($r=fgetcsv($fh))!==false){if($r===[null]||$r===false)continue;$o[]=array_combine($h,$r);} fclose($fh); return $o; };
$mk=static fn($label,$def=false)=>['label'=>$label,'default'=>$def];
$colour=static function(array $L) use($mk){ $o=[]; foreach($L as $i=>$l)$o[]=$mk($l,$i===0); return $o; };

$PRODUCTS=[
 ['name'=>'Arena Pleated Night & Day','pricesCsv'=>'arena_pleated_nightday_prices.csv','fabricsCsv'=>'arena_pleated_fabrics.csv','journey'=>[
    ['key'=>'exact','name'=>'Exact or Recess','req'=>true,'choices'=>$colour(['Exact','Recess'])],
    ['key'=>'brackets','name'=>'Headrail Brackets','req'=>false,'choices'=>$colour(['Face Fix','Top Fix','Both Top Fix and Face','Top Fix and Universal Extension'])],
    ['key'=>'hrcolour','name'=>'Headrail Colour','req'=>false,'choices'=>$colour(['White','Anthracite','Brown'])],
    ['key'=>'fitheight','name'=>'Fit Height','req'=>false,'lenLabel'=>'mm','choices'=>[$mk('Fit Height',true)]],
 ]],
 ['name'=>'Arena BlindScreen','pricesCsv'=>'arena_blindscreen_prices.csv','fabricsCsv'=>'arena_pleated_nightday_fabrics.csv','journey'=>[
    ['key'=>'exact','name'=>'Exact or Recess','req'=>true,'choices'=>$colour(['Exact','Recess'])],
    ['key'=>'framecol','name'=>'Frame Colour','req'=>true,'choices'=>$colour(['White','Black','Grey'])],
    ['key'=>'ctrl','name'=>'Control Type','req'=>true,'choices'=>$colour(['Manual'])],
    ['key'=>'lowrail','name'=>'Low Rail Type','req'=>false,'choices'=>$colour(['Standard','Single Bull Nose','Double Bull Nose'])],
    ['key'=>'bunch','name'=>'Bunch Side','req'=>false,'choices'=>$colour(['Both Sides (centre)'])],
    ['key'=>'fitheight','name'=>'Fit Height','req'=>false,'lenLabel'=>'mm','choices'=>[$mk('Fit Height',true)]],
 ]],
];

echo ($apply?"APPLY":"DRY RUN (preview only)")." — Pleated Wave 2b (Night & Day fix + BlindScreen)\n".str_repeat('=',66)."\n\n";
if($apply)$pdo->beginTransaction();
try {
    $hasLen=false; try{$pdo->query('SELECT length_input_label FROM product_extras LIMIT 1');$hasLen=true;}catch(Throwable $e){}
    foreach($PRODUCTS as $cfg){
        $pname=$cfg['name']; echo "\n### {$pname} ###\n";
        $PRICES=$readCsv(__DIR__.'/seed_data/'.$cfg['pricesCsv']);
        $FABRICS=$readCsv(__DIR__.'/seed_data/'.$cfg['fabricsCsv']);
        $f=$pdo->prepare('SELECT id FROM products WHERE client_id=? AND name=?');$f->execute([$clientId,$pname]);$productId=(int)($f->fetchColumn()?:0);
        if($apply && $productId===0){ $ss=$pdo->prepare('SELECT COALESCE(MAX(sort_order),-1)+1 FROM products WHERE client_id=?');$ss->execute([$clientId]);$ns=(int)$ss->fetchColumn();
            try{$pdo->prepare('INSERT INTO products (client_id,name,option_label,show_colour_field,sort_order,active) VALUES (?,?,?,1,?,1)')->execute([$clientId,$pname,'Fabric',$ns]);}
            catch(Throwable $e){$pdo->prepare('INSERT INTO products (client_id,name,option_label,sort_order,active) VALUES (?,?,?,?,1)')->execute([$clientId,$pname,'Fabric',$ns]);}
            $productId=(int)$pdo->lastInsertId(); }
        echo "  product #".($productId?:'(new)')."\n";

        // systems from price CSV (STD band), rebuilt
        $sysNames=[]; foreach($PRICES as $r) if(!in_array($r['system'],$sysNames,true)) $sysNames[]=$r['system'];
        $SYS=[]; $i=0;
        if($apply){
            // drop stale systems + tables
            $pdo->prepare('DELETE r FROM price_table_rows r JOIN price_tables t ON t.id=r.price_table_id WHERE t.client_id=? AND t.product_id=?')->execute([$clientId,$productId]);
            $pdo->prepare('DELETE FROM price_tables WHERE client_id=? AND product_id=?')->execute([$clientId,$productId]);
            $cur=$pdo->prepare('SELECT id,name FROM product_systems WHERE client_id=? AND product_id=?'); $cur->execute([$clientId,$productId]);
            foreach($cur->fetchAll() as $r){ if(!in_array($r['name'],$sysNames,true)) $pdo->prepare('DELETE FROM product_systems WHERE id=?')->execute([$r['id']]); }
        }
        foreach($sysNames as $sn){ if($apply){ $q=$pdo->prepare('SELECT id FROM product_systems WHERE client_id=? AND product_id=? AND name=?');$q->execute([$clientId,$productId,$sn]);$sid=(int)($q->fetchColumn()?:0);
            if($sid===0){$pdo->prepare('INSERT INTO product_systems (client_id,product_id,name,sort_order,active,is_default) VALUES (?,?,?,?,1,?)')->execute([$clientId,$productId,$sn,$i,$i===0?1:0]);$sid=(int)$pdo->lastInsertId();}
            else{$pdo->prepare('UPDATE product_systems SET active=1,is_default=?,sort_order=? WHERE id=?')->execute([$i===0?1:0,$i,$sid]);} $SYS[$sn]=$sid; } else $SYS[$sn]=0; $i++; }
        echo "  systems (".count($sysNames)."): ".implode(', ',$sysNames)."\n";

        // base tables (STD per system)
        $cells=0;
        if($apply){
            $tblIns=$pdo->prepare("INSERT INTO price_tables (client_id,product_id,system_id,band_code,name,active) VALUES (?,?,?,'STD','Base',1)");
            $rowIns=$pdo->prepare('INSERT INTO price_table_rows (price_table_id,width_mm,drop_mm,price) VALUES (?,?,?,?)');
            $grp=[]; foreach($PRICES as $r)$grp[$r['system']][]=$r;
            foreach($grp as $sn=>$rows){ $tblIns->execute([$clientId,$productId,$SYS[$sn]]); $tid=(int)$pdo->lastInsertId();
                foreach($rows as $c){$rowIns->execute([$tid,(int)$c['width_mm'],(int)$c['drop_mm'],(float)$c['price']]);$cells++;} }
        } else $cells=count($PRICES);
        echo "  base: {$cells} cells\n";

        // fabrics — all forced STD active (flat-priced products)
        $fc=0;
        if($apply){ $pdo->prepare('DELETE FROM product_options WHERE client_id=? AND product_id=?')->execute([$clientId,$productId]);
            $oi=$pdo->prepare("INSERT INTO product_options (client_id,product_id,system_id,band_code,supplier_name,name,colour,code,sort_order,active) VALUES (?,?,NULL,'STD','Arena',?,?,'',?,1)");
            $sort=0;$seen=[]; foreach($FABRICS as $fr){ $name=(string)$fr['name'];$col=(string)($fr['colour']??'');
                $k=mb_strtolower($name).'|'.mb_strtolower($col); if(isset($seen[$k]))continue;$seen[$k]=1;
                $oi->execute([$clientId,$productId,$name,$col,$sort++]);$fc++; } } else $fc=count($FABRICS);
        echo "  fabrics: {$fc} (all STD)\n";

        // options
        if($apply){
            $ids=$pdo->prepare('SELECT id FROM product_extras WHERE client_id=? AND product_id=?');$ids->execute([$clientId,$productId]);$exIds=$ids->fetchAll(PDO::FETCH_COLUMN);
            if($exIds){$in=implode(',',array_fill(0,count($exIds),'?'));
                $ch=$pdo->prepare("SELECT id FROM product_extra_choices WHERE product_extra_id IN ($in)");$ch->execute($exIds);$cIds=$ch->fetchAll(PDO::FETCH_COLUMN);
                if($cIds){$cin=implode(',',array_fill(0,count($cIds),'?'));$pdo->prepare("DELETE FROM extra_choice_price_rows WHERE product_extra_choice_id IN ($cin)")->execute($cIds);}
                $pdo->prepare("DELETE FROM product_extra_parent_choices WHERE product_extra_id IN ($in)")->execute($exIds);
                $pdo->prepare("DELETE FROM product_extra_choices WHERE product_extra_id IN ($in)")->execute($exIds);
                $pdo->prepare("DELETE FROM product_extras WHERE id IN ($in)")->execute($exIds);}
        }
        $insExtra=$pdo->prepare('INSERT INTO product_extras (client_id,product_id,parent_choice_id,name,is_required,sort_order,active,parent_match_all) VALUES (?,?,NULL,?,?,?,1,0)');
        $insExtraLen=$hasLen?$pdo->prepare('INSERT INTO product_extras (client_id,product_id,parent_choice_id,name,is_required,sort_order,active,length_input_label) VALUES (?,?,NULL,?,?,?,1,?)'):null;
        $insChoice=$pdo->prepare('INSERT INTO product_extra_choices (product_extra_id,system_id,label,image_path,price_delta,price_percent,price_per_metre,is_default,sort_order,active) VALUES (?,NULL,?,NULL,0,0,0,?,?,1)');
        $so=0;$nx=0;$nc=0;
        foreach($cfg['journey'] as $ex){ echo "  - {$ex['name']} (".count($ex['choices'])." choices)\n"; $extraId=null;
            if($apply){ if(!empty($ex['lenLabel'])&&$insExtraLen)$insExtraLen->execute([$clientId,$productId,$ex['name'],$ex['req']?1:0,$so++,$ex['lenLabel']]);
                else $insExtra->execute([$clientId,$productId,$ex['name'],$ex['req']?1:0,$so++]); $extraId=(int)$pdo->lastInsertId(); }
            $nx++;$cs=0; foreach($ex['choices'] as $c){ if($apply)$insChoice->execute([$extraId,$c['label'],$c['default']?1:0,$cs++]); $nc++; } }
        echo "  options: {$nx} extras, {$nc} choices\n";
    }
    if($apply)$pdo->commit();
} catch(Throwable $e){ if($apply&&$pdo->inTransaction())$pdo->rollBack(); echo "\nFAILED: ".$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n"; exit(1); }
echo "\n".str_repeat('=',66)."\n".($apply?"BUILT":"WOULD BUILD")." Pleated Wave 2b.\n";
if(!$apply) echo "\nPREVIEW ONLY — re-run with ?apply=1.\n";
