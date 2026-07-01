<?php
declare(strict_types=1);

/**
 * Seed the Arena CURTAINS guided journey (Phase 2) onto "Arena Curtains"
 * (#5757), exact mirror of the live CRT configurator.
 *
 * Heading style (Pencil/Deep Pencil/Double Pinch/Triple Pinch/Eyelet/Wave)
 * is priced on separate base grids = our 6 SYSTEMS (already built), so it is
 * NOT an option here. Arena's remaining curtain fields:
 *   - Lining Type: Standard (£0) / Blackout (+10%) / Thermal (+20%) — PERCENT
 *     surcharge (price list p193-195). price_percent.
 *   - Lining Colour: Ivory / White (£0).
 *   - Pair or Single (£0 — base grids are per PAIR; Single pricing factor TBC,
 *     flagged for review).
 * No Exact/Recess or Fit Height for curtains.
 *
 * DRY-RUN unless ?apply=1; idempotent rebuild. Super-admin only.
 *   Preview: /seed_arena_curtain_options.php   Apply: &apply=1
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors','1'); error_reporting(E_ALL); @set_time_limit(120);

$user=current_user(); $clientId=(int)$user['client_id'];
$pdo=db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$PRODUCT_NAME='Arena Curtains'; $apply=(($_GET['apply']??'')==='1');

$p=$pdo->prepare('SELECT id FROM products WHERE client_id=? AND name=? LIMIT 1'); $p->execute([$clientId,$PRODUCT_NAME]);
$productId=(int)($p->fetchColumn()?:0);
if(!$productId){ echo "Product \"$PRODUCT_NAME\" not found.\n"; exit(1); }

$mk=static fn($label,$delta=0.0,$pct=0.0,$def=false)=>['label'=>$label,'delta'=>$delta,'pct'=>$pct,'default'=>$def];
$colour=static function(array $L) use($mk){ $o=[]; foreach($L as $i=>$l)$o[]=$mk($l,0,0,$i===0); return $o; };

$J=[
 ['key'=>'lining','name'=>'Lining Type','req'=>true,'gate'=>null,'choices'=>[
     $mk('Standard',0,0,true), $mk('Blackout (+10%)',0,10.0), $mk('Thermal (+20%)',0,20.0)]],
 ['key'=>'liningcol','name'=>'Lining Colour','req'=>false,'gate'=>null,'choices'=>$colour(['Ivory','White'])],
 ['key'=>'pairsingle','name'=>'Pair or Single','req'=>true,'gate'=>null,'choices'=>$colour(['Pair','Single'])],
];

echo ($apply?"APPLY":"DRY RUN (preview only)")." — Arena Curtains options (exact CRT mirror) onto #{$productId}\n".str_repeat('=',60)."\n\n";
$nx=0;$nc=0;
if($apply)$pdo->beginTransaction();
try {
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
    $insChoice=$pdo->prepare('INSERT INTO product_extra_choices (product_extra_id,system_id,label,image_path,price_delta,price_percent,price_per_metre,is_default,sort_order,active) VALUES (?,NULL,?,NULL,?,?,0,?,?,1)');
    $so=0;
    foreach($J as $ex){
        echo sprintf("  %-16s %s\n",$ex['name'],$ex['req']?'(req)':'');
        $extraId=null;
        if($apply){ $insExtra->execute([$clientId,$productId,$ex['name'],$ex['req']?1:0,$so++]); $extraId=(int)$pdo->lastInsertId(); }
        $nx++;$cs=0;
        foreach($ex['choices'] as $c){ $tag=($c['delta']?" +£{$c['delta']}":'').($c['pct']?" +{$c['pct']}%":'');
            echo "        - {$c['label']}{$tag}\n";
            if($apply)$insChoice->execute([$extraId,$c['label'],$c['delta'],$c['pct'],$c['default']?1:0,$cs++]);
            $nc++; }
    }
    if($apply)$pdo->commit();
} catch(Throwable $e){ if($apply&&$pdo->inTransaction())$pdo->rollBack(); echo "\nFAILED: ".$e->getMessage()."\n"; exit(1); }
echo "\n".str_repeat('-',60)."\n".($apply?"Created":"Would create").": {$nx} options, {$nc} choices.\n";
if(!$apply) echo "\nPREVIEW ONLY — re-run with ?apply=1.\n";
