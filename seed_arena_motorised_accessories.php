<?php
declare(strict_types=1);

/**
 * "Arena Motorised Accessories" (Arena product type MTR) — the Somfy motor /
 * remote / charger / accessory catalogue that Arena adds as separate order
 * lines (not inline on the blind). Modelled as a NO-FABRIC product
 * (requires_option=0, width_only=1 with a single £0 base row) whose cost comes
 * entirely from priced option choices. Pick Motor Supply (RTS/Zigbee) → then
 * Motor / Remote / Accessory, each carrying its surcharge from
 * seed_data/arena_roller_surcharge_flat.csv (RTS + Zigbee rows, p262-263).
 *
 * DRY-RUN unless ?apply=1; idempotent. Super-admin only.
 *   Preview: /seed_arena_motorised_accessories.php   Apply: &apply=1
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors','1'); error_reporting(E_ALL); @set_time_limit(300);

$user=current_user(); $clientId=(int)$user['client_id'];
$pdo=db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$PRODUCT_NAME='Arena Motorised Accessories'; $apply=(($_GET['apply']??'')==='1');

// RTS + Zigbee rows from the shared flat CSV
$flatByKey=[]; $csv=__DIR__.'/seed_data/arena_roller_surcharge_flat.csv';
if(!is_file($csv)) { echo "Missing $csv\n"; exit(1); }
$fh=fopen($csv,'r');$h=fgetcsv($fh);
while(($r=fgetcsv($fh))!==false){ if($r===[null]||$r===false)continue; $row=array_combine($h,$r);
    if(in_array($row['system'],['RTS','Zigbee'],true)) $flatByKey[$row['system'].'|'.$row['group']][]=$row; }
fclose($fh);

$mk=static fn($label,$delta=0.0,$def=false)=>['label'=>$label,'delta'=>$delta,'default'=>$def];
$cat=function(string $sys,string $grp,string $none) use($flatByKey,$mk){ $o=[$mk($none,0,true)];
    foreach(($flatByKey[$sys.'|'.$grp]??[]) as $r) $o[]=$mk($r['choice'],(float)$r['price']); return $o; };

$J=[
 ['key'=>'supply','name'=>'Motor Supply','req'=>true,'gate'=>null,'choices'=>[$mk('Somfy RTS',0,true),$mk('Somfy Zigbee')]],
 ['key'=>'motorrts','name'=>'Motor (RTS)','req'=>false,'gate'=>[['supply','Somfy RTS']],'choices'=>$cat('RTS','Motor','— Select —')],
 ['key'=>'remoterts','name'=>'Remote (RTS)','req'=>false,'gate'=>[['supply','Somfy RTS']],'choices'=>$cat('RTS','Remote','None')],
 ['key'=>'accrts','name'=>'Accessory (RTS)','req'=>false,'gate'=>[['supply','Somfy RTS']],'choices'=>$cat('RTS','Accessory','None')],
 ['key'=>'motorzig','name'=>'Motor (Zigbee)','req'=>false,'gate'=>[['supply','Somfy Zigbee']],'choices'=>$cat('Zigbee','Motor','— Select —')],
 ['key'=>'remotezig','name'=>'Remote (Zigbee)','req'=>false,'gate'=>[['supply','Somfy Zigbee']],'choices'=>$cat('Zigbee','Remote','None')],
 ['key'=>'acczig','name'=>'Accessory (Zigbee)','req'=>false,'gate'=>[['supply','Somfy Zigbee']],'choices'=>$cat('Zigbee','Accessory','None')],
];

echo ($apply?"APPLY":"DRY RUN (preview only)")." — {$PRODUCT_NAME}\n".str_repeat('=',60)."\n\n";
$nx=0;$nc=0;
if($apply)$pdo->beginTransaction();
try {
    $f=$pdo->prepare('SELECT id FROM products WHERE client_id=? AND name=?');$f->execute([$clientId,$PRODUCT_NAME]);
    $productId=(int)($f->fetchColumn()?:0);
    if($apply){
        if($productId===0){ $ss=$pdo->prepare('SELECT COALESCE(MAX(sort_order),-1)+1 FROM products WHERE client_id=?');$ss->execute([$clientId]);$ns=(int)$ss->fetchColumn();
            $pdo->prepare('INSERT INTO products (client_id,name,option_label,sort_order,active) VALUES (?,?,?,?,1)')->execute([$clientId,$PRODUCT_NAME,'Accessory',$ns]);
            $productId=(int)$pdo->lastInsertId(); }
        $pdo->prepare('UPDATE products SET requires_option=0, width_only=1 WHERE id=?')->execute([$productId]);
        // one system + a single £0 base row (covers any width)
        $q=$pdo->prepare('SELECT id FROM product_systems WHERE client_id=? AND product_id=? AND name=?');$q->execute([$clientId,$productId,'Standard']);$sid=(int)($q->fetchColumn()?:0);
        if($sid===0){$pdo->prepare('INSERT INTO product_systems (client_id,product_id,name,sort_order,active,is_default) VALUES (?,?,?,0,1,1)')->execute([$clientId,$productId,'Standard']);$sid=(int)$pdo->lastInsertId();}
        $pdo->prepare('DELETE r FROM price_table_rows r JOIN price_tables t ON t.id=r.price_table_id WHERE t.client_id=? AND t.product_id=?')->execute([$clientId,$productId]);
        $pdo->prepare('DELETE FROM price_tables WHERE client_id=? AND product_id=?')->execute([$clientId,$productId]);
        $pdo->prepare("INSERT INTO price_tables (client_id,product_id,system_id,band_code,name,active) VALUES (?,?,?,'STD','Base',1)")->execute([$clientId,$productId,$sid]);
        $tid=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO price_table_rows (price_table_id,width_mm,drop_mm,price) VALUES (?,99999,0,0)')->execute([$tid]);
    }
    echo "  product #".($productId?:'(new)')." (no-fabric, width-only £0 base)\n\n";

    // rebuild option tree
    $hasLen=false; try{$pdo->query('SELECT length_input_label FROM product_extras LIMIT 1');$hasLen=true;}catch(Throwable $e){}
    if($apply){
        $ids=$pdo->prepare('SELECT id FROM product_extras WHERE client_id=? AND product_id=?');$ids->execute([$clientId,$productId]);$exIds=$ids->fetchAll(PDO::FETCH_COLUMN);
        if($exIds){$in=implode(',',array_fill(0,count($exIds),'?'));
            $ch=$pdo->prepare("SELECT id FROM product_extra_choices WHERE product_extra_id IN ($in)");$ch->execute($exIds);$cIds=$ch->fetchAll(PDO::FETCH_COLUMN);
            if($cIds){$cin=implode(',',array_fill(0,count($cIds),'?'));$pdo->prepare("DELETE FROM extra_choice_price_rows WHERE product_extra_choice_id IN ($cin)")->execute($cIds);}
            $pdo->prepare("DELETE FROM product_extra_parent_choices WHERE product_extra_id IN ($in)")->execute($exIds);
            $pdo->prepare("DELETE FROM product_extra_choices WHERE product_extra_id IN ($in)")->execute($exIds);
            $pdo->prepare("DELETE FROM product_extras WHERE id IN ($in)")->execute($exIds);}
    }
    $insExtra=$pdo->prepare('INSERT INTO product_extras (client_id,product_id,parent_choice_id,name,is_required,sort_order,active,parent_match_all) VALUES (?,?,?,?,?,?,1,0)');
    $insChoice=$pdo->prepare('INSERT INTO product_extra_choices (product_extra_id,system_id,label,image_path,price_delta,price_percent,price_per_metre,is_default,sort_order,active) VALUES (?,NULL,?,NULL,?,0,0,?,?,1)');
    $insJ=$pdo->prepare('INSERT INTO product_extra_parent_choices (product_extra_id,product_extra_choice_id) VALUES (?,?)');
    $cidMap=[];$so=0;
    foreach($J as $ex){
        $pids=[]; if(!empty($ex['gate'])) foreach($ex['gate'] as [$pk,$pl]) foreach(($cidMap[$pk.'|'.$pl]??[]) as $cid)$pids[]=$cid;
        $primary=$pids[0]??null;
        echo sprintf("  %-22s %s — %d choices\n",$ex['name'],$ex['gate']?'[gate '.$ex['gate'][0][1].']':'',count($ex['choices']));
        $extraId=null;
        if($apply){ $insExtra->execute([$clientId,$productId,$primary,$ex['name'],$ex['req']?1:0,$so++]); $extraId=(int)$pdo->lastInsertId(); foreach($pids as $pid)$insJ->execute([$extraId,$pid]); }
        $nx++;$cs=0;
        foreach($ex['choices'] as $c){ $tag=$c['delta']?" +£{$c['delta']}":'';
            if($apply){$insChoice->execute([$extraId,$c['label'],$c['delta'],$c['default']?1:0,$cs++]);$cidMap[$ex['key'].'|'.$c['label']][]=(int)$pdo->lastInsertId();}
            else $cidMap[$ex['key'].'|'.$c['label']][]=0;
            $nc++; }
    }
    if($apply)$pdo->commit();
} catch(Throwable $e){ if($apply&&$pdo->inTransaction())$pdo->rollBack(); echo "\nFAILED: ".$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n"; exit(1); }
echo "\n".str_repeat('-',60)."\n".($apply?"Built":"Would build").": {$nx} options, {$nc} choices.\n";
if(!$apply) echo "\nPREVIEW ONLY — re-run with ?apply=1.\n";
