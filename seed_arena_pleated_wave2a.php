<?php
declare(strict_types=1);

/**
 * Pleated family split — WAVE 2a (exact PLT mirror). Four products, all from
 * the recovered pleated base grids (seed_data/arena_pleated_{prices,fabrics}.csv):
 *   - "Arena Pleated Roof"        (PYR): systems Rectangular Roof + Fixed Roof;
 *        motorised; roof sub-type (Lantern/Skylight/Standard) as £0 spec.
 *   - "Arena Pleated Roof Shape"  (PYT): system Shaped Roof; motorised.
 *   - "Arena Pleated Shape Freehang" (PYF): reuse Freehang base (Standard Cord
 *        + Tab); no shape surcharge in the price list (prices = Freehang).
 *   - "Arena Top Down Bottom Up"  (TDBU): reuse Freehang base + £18.60/blind
 *        surcharge baked into every base cell (price list p208/212).
 *
 * £0 spec option journeys captured from the live PLT form. DRY-RUN unless
 * ?apply=1; idempotent. Super-admin only.
 *   Preview: /seed_arena_pleated_wave2a.php   Apply: &apply=1
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
$PRICES=$readCsv(__DIR__.'/seed_data/arena_pleated_prices.csv');
$FABRICS=$readCsv(__DIR__.'/seed_data/arena_pleated_fabrics.csv');
$VALID=['Elements'=>1,'A'=>1,'B'=>1,'C'=>1,'D'=>1,'E'=>1];

$mk=static fn($label,$sys=null,$def=false)=>['label'=>$label,'sys'=>$sys,'default'=>$def];
$colour=static function(array $L) use($mk){ $o=[]; foreach($L as $i=>$l)$o[]=$mk($l,null,$i===0); return $o; };
$ctrlLen=array_merge(['Standard'], array_map(fn($n)=>$n.' cm', range(10,500,10)));

$roofJourney=function($withProdType) use($mk,$colour){ $j=[];
    if($withProdType) $j[]=['key'=>'rooftype','name'=>'Roof Type','req'=>true,'choices'=>$colour(['Standard Roof Rectangle','Lantern Rectangle','Skylight Rectangle'])];
    $j[]=['key'=>'ctrlsys','name'=>'Control System','req'=>true,'choices'=>$colour(['Motorised'])];
    $j[]=['key'=>'motorsupply','name'=>'Motor Supply','req'=>false,'choices'=>$colour(['Somfy RTS','Somfy Zigbee'])];
    $j[]=['key'=>'toprail','name'=>'Top Rail Brackets','req'=>false,'choices'=>$colour(['Face Fix','Top Fix'])];
    $j[]=['key'=>'botrail','name'=>'Bottom Rail Brackets','req'=>false,'choices'=>$colour(['Face Fix','Top Fix'])];
    $j[]=['key'=>'hrcolour','name'=>'Headrail Colour','req'=>false,'choices'=>$colour(['White','Anthracite','Brown'])];
    $j[]=['key'=>'exact','name'=>'Exact or Recess','req'=>true,'choices'=>$colour(['Exact','Recess'])];
    return $j; };
$freehangJourney=function() use($mk,$colour,$ctrlLen){ return [
    ['key'=>'exact','name'=>'Exact or Recess','req'=>true,'choices'=>$colour(['Exact','Recess'])],
    ['key'=>'side','name'=>'Control Side','req'=>false,'choices'=>$colour(['Left','Right'])],
    ['key'=>'controlslen','name'=>'Controls Length','req'=>false,'choices'=>$colour($ctrlLen)],
    ['key'=>'brackets','name'=>'Headrail Brackets','req'=>false,'choices'=>$colour(['Face Fix','Top Fix','Both Top Fix and Face','Top Fix and Universal Extension'])],
    ['key'=>'hrcolour','name'=>'Headrail Colour','req'=>false,'choices'=>$colour(['White','Anthracite','Brown'])],
    ['key'=>'fitheight','name'=>'Fit Height','req'=>false,'lenLabel'=>'mm','choices'=>[$mk('Fit Height')]],
]; };

$PRODUCTS=[
 ['name'=>'Arena Pleated Roof','systems'=>['Rectangular Roof'=>'Rectangular Roof','Fixed Roof'=>'Fixed Roof'],'priceAdd'=>0.0,'journey'=>$roofJourney(true)],
 ['name'=>'Arena Pleated Roof Shape','systems'=>['Shaped Roof'=>'Shaped Roof'],'priceAdd'=>0.0,'journey'=>$roofJourney(false)],
 ['name'=>'Arena Pleated Shape Freehang','systems'=>['Standard Cord Freehang'=>'Standard Cord Freehang','Tab Freehang'=>'Tab Freehang'],'priceAdd'=>0.0,'journey'=>$freehangJourney()],
 ['name'=>'Arena Top Down Bottom Up','systems'=>['Standard Cord Freehang'=>'Standard Cord Freehang','Tab Freehang'=>'Tab Freehang'],'priceAdd'=>18.60,'journey'=>$freehangJourney()],
];

echo ($apply?"APPLY":"DRY RUN (preview only)")." — Pleated Wave 2a (Roof, Roof Shape, Shape Freehang, TDBU)\n".str_repeat('=',68)."\n\n";
if($apply)$pdo->beginTransaction();
try {
    $hasLen=false; try{$pdo->query('SELECT length_input_label FROM product_extras LIMIT 1');$hasLen=true;}catch(Throwable $e){}
    foreach($PRODUCTS as $cfg){
        $pname=$cfg['name']; echo "\n### {$pname} ###\n";
        $f=$pdo->prepare('SELECT id FROM products WHERE client_id=? AND name=?');$f->execute([$clientId,$pname]);$productId=(int)($f->fetchColumn()?:0);
        if($apply && $productId===0){ $ss=$pdo->prepare('SELECT COALESCE(MAX(sort_order),-1)+1 FROM products WHERE client_id=?');$ss->execute([$clientId]);$ns=(int)$ss->fetchColumn();
            try{$pdo->prepare('INSERT INTO products (client_id,name,option_label,show_colour_field,sort_order,active) VALUES (?,?,?,1,?,1)')->execute([$clientId,$pname,'Fabric',$ns]);}
            catch(Throwable $e){$pdo->prepare('INSERT INTO products (client_id,name,option_label,sort_order,active) VALUES (?,?,?,?,1)')->execute([$clientId,$pname,'Fabric',$ns]);}
            $productId=(int)$pdo->lastInsertId(); }
        echo "  product #".($productId?:'(new)').($cfg['priceAdd']?" (+£{$cfg['priceAdd']}/cell)":'')."\n";

        $SYS=[]; $i=0;
        foreach($cfg['systems'] as $local=>$csvSys){
            if($apply){ $q=$pdo->prepare('SELECT id FROM product_systems WHERE client_id=? AND product_id=? AND name=?');$q->execute([$clientId,$productId,$local]);$sid=(int)($q->fetchColumn()?:0);
                if($sid===0){$pdo->prepare('INSERT INTO product_systems (client_id,product_id,name,sort_order,active,is_default) VALUES (?,?,?,?,1,?)')->execute([$clientId,$productId,$local,$i,$i===0?1:0]);$sid=(int)$pdo->lastInsertId();}
                else{$pdo->prepare('UPDATE product_systems SET active=1,is_default=?,sort_order=? WHERE id=?')->execute([$i===0?1:0,$i,$sid]);}
                $SYS[$local]=$sid; } else $SYS[$local]=0; $i++; }
        echo "  systems: ".implode(', ',array_keys($cfg['systems']))."\n";

        $cells=0;$tbls=0;
        if($apply){
            $pdo->prepare('DELETE r FROM price_table_rows r JOIN price_tables t ON t.id=r.price_table_id WHERE t.client_id=? AND t.product_id=?')->execute([$clientId,$productId]);
            $pdo->prepare('DELETE FROM price_tables WHERE client_id=? AND product_id=?')->execute([$clientId,$productId]);
            $tblIns=$pdo->prepare('INSERT INTO price_tables (client_id,product_id,system_id,band_code,name,active) VALUES (?,?,?,?,?,1)');
            $rowIns=$pdo->prepare('INSERT INTO price_table_rows (price_table_id,width_mm,drop_mm,price) VALUES (?,?,?,?)');
            foreach($cfg['systems'] as $local=>$csvSys) foreach(['Elements','A','B','C','D','E'] as $band){
                $rows=array_values(array_filter($PRICES,fn($r)=>$r['system']===$csvSys&&$r['band']===$band)); if(!$rows)continue;
                $tblIns->execute([$clientId,$productId,$SYS[$local],$band,"Arena Band $band"]);$tid=(int)$pdo->lastInsertId();
                foreach($rows as $c){$rowIns->execute([$tid,(int)$c['width_mm'],(int)$c['drop_mm'],(float)$c['price']+$cfg['priceAdd']]);$cells++;} $tbls++; }
        } else { foreach($cfg['systems'] as $local=>$csvSys) foreach(['Elements','A','B','C','D','E'] as $band){ $n=count(array_filter($PRICES,fn($r)=>$r['system']===$csvSys&&$r['band']===$band)); if($n){$cells+=$n;$tbls++;} } }
        echo "  base: {$tbls} tables, {$cells} cells\n";

        $fc=0;
        if($apply){ $pdo->prepare('DELETE FROM product_options WHERE client_id=? AND product_id=?')->execute([$clientId,$productId]);
            $oi=$pdo->prepare('INSERT INTO product_options (client_id,product_id,system_id,band_code,supplier_name,name,colour,code,sort_order,active) VALUES (?,?,NULL,?,?,?,?,?,?,?)');
            $sort=0;$seen=[]; foreach($FABRICS as $fr){ $band=trim((string)$fr['band']);$banded=isset($VALID[$band]);$bc=$banded?$band:'TBC';$name=(string)$fr['name'];$col=(string)($fr['colour']??'');
                $k=$bc.'|'.mb_strtolower($name).'|'.mb_strtolower($col); if(isset($seen[$k]))continue;$seen[$k]=1;
                $oi->execute([$clientId,$productId,$bc,'Arena',$name,$col,'',$sort++,$banded?1:0]);$fc++; } } else $fc=count($FABRICS);
        echo "  fabrics: {$fc}\n";

        $J=$cfg['journey'];
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
        foreach($J as $ex){ echo "    - {$ex['name']} (".count($ex['choices'])." choices)\n"; $extraId=null;
            if($apply){ if(!empty($ex['lenLabel'])&&$insExtraLen)$insExtraLen->execute([$clientId,$productId,$ex['name'],$ex['req']?1:0,$so++,$ex['lenLabel']]);
                else $insExtra->execute([$clientId,$productId,$ex['name'],$ex['req']?1:0,$so++]); $extraId=(int)$pdo->lastInsertId(); }
            $nx++;$cs=0; foreach($ex['choices'] as $c){ if($apply)$insChoice->execute([$extraId,$c['label'],$c['default']?1:0,$cs++]); $nc++; } }
        echo "  options: {$nx} extras, {$nc} choices\n";
    }
    if($apply)$pdo->commit();
} catch(Throwable $e){ if($apply&&$pdo->inTransaction())$pdo->rollBack(); echo "\nFAILED: ".$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n"; exit(1); }
echo "\n".str_repeat('=',68)."\n".($apply?"BUILT":"WOULD BUILD")." Pleated Wave 2a (4 products).\n";
if(!$apply) echo "\nPREVIEW ONLY — re-run with ?apply=1.\n";
