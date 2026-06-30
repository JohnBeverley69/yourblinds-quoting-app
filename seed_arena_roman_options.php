<?php
declare(strict_types=1);

/**
 * Seed the Arena ROMAN guided journey (Phase 2) onto "Arena Roman" (#5756),
 * EXACT mirror of the live ROM configurator (captured by driving the form).
 *
 * Romans / Blackout Roman / Interlined Roman share one identical field set
 * (verified) — so this is ONE product with a "Lining Type" selector, priced
 * as a PERCENT surcharge (Blackout +10%, Interlined +20%; p183). Motorised
 * accessories are a SEPARATE product (Arena MTR = "Arena Motorised
 * Accessories"), so there is NO inline motor tree here.
 *
 * Arena's exact field set:
 *   Manual    : Lining Type, Control Type, Chain Type, Rail Type (Deluxe),
 *               Lining Colour, Chain Colour, + Exact/Recess, Closed Loop,
 *               Draw Side.
 *   Motorised : Lining Type, Control Type, Rail Type, Lining Colour,
 *               Exact/Recess (no chain / draw side / closed loop).
 * (ROM's live form shows no Installation Height field, so no Fit Height.)
 *
 * Control/chain = £0 ("Zero charge for breakaway chain", p190). DRY-RUN
 * unless ?apply=1; idempotent rebuild. Super-admin only.
 *   Preview: /seed_arena_roman_options.php   Apply: &apply=1
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors','1'); error_reporting(E_ALL); @set_time_limit(300);

$user=current_user(); $clientId=(int)$user['client_id'];
$pdo=db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$PRODUCT_NAME='Arena Roman'; $apply=(($_GET['apply']??'')==='1');

$p=$pdo->prepare('SELECT id FROM products WHERE client_id=? AND name=? LIMIT 1'); $p->execute([$clientId,$PRODUCT_NAME]);
$productId=(int)($p->fetchColumn()?:0);
if(!$productId){ echo "Product \"$PRODUCT_NAME\" not found.\n"; exit(1); }

$mk=static fn($label,$delta=0.0,$pct=0.0,$def=false)=>['label'=>$label,'delta'=>$delta,'pct'=>$pct,'default'=>$def];
$colour=static function(array $L) use($mk){ $o=[]; foreach($L as $i=>$l)$o[]=$mk($l,0,0,$i===0); return $o; };

// Exact ROM journey (Arena order: selects then manual-only radios)
$J=[
 ['key'=>'lining','name'=>'Lining Type','req'=>true,'gate'=>null,'choices'=>[
     $mk('Romans',0,0,true), $mk('Blackout Roman (+10%)',0,10.0), $mk('Interlined Roman (+20%)',0,20.0)]],
 ['key'=>'ctrl','name'=>'Control Type','req'=>true,'gate'=>null,'choices'=>$colour(['Manual','Motorised'])],
 ['key'=>'chaintype','name'=>'Chain Type','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>$colour(['Breakaway','Tensioned'])],
 ['key'=>'railtype','name'=>'Rail Type','req'=>true,'gate'=>null,'choices'=>$colour(['Deluxe'])],
 ['key'=>'liningcol','name'=>'Lining Colour','req'=>false,'gate'=>null,'choices'=>$colour(['Ivory','White'])],
 ['key'=>'chaincol','name'=>'Chain Colour','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>$colour(['Antique','Black','Chrome','White'])],
 ['key'=>'exact','name'=>'Exact or Recess','req'=>true,'gate'=>null,'choices'=>$colour(['Exact','Recess'])],
 ['key'=>'closedloop','name'=>'Closed Loop','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>$colour(['No','Yes'])],
 ['key'=>'drawside','name'=>'Draw Side','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>$colour(['Left','Right'])],
];

echo ($apply?"APPLY":"DRY RUN (preview only)")." — Arena Roman options (exact ROM mirror) onto #{$productId}\n".str_repeat('=',60)."\n\n";
$nx=0;$nc=0;$ng=0;
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
    $insExtra=$pdo->prepare('INSERT INTO product_extras (client_id,product_id,parent_choice_id,name,is_required,sort_order,active,parent_match_all) VALUES (?,?,?,?,?,?,1,0)');
    $insChoice=$pdo->prepare('INSERT INTO product_extra_choices (product_extra_id,system_id,label,image_path,price_delta,price_percent,price_per_metre,is_default,sort_order,active) VALUES (?,NULL,?,NULL,?,?,0,?,?,1)');
    $insJ=$pdo->prepare('INSERT INTO product_extra_parent_choices (product_extra_id,product_extra_choice_id) VALUES (?,?)');
    $cidMap=[];$so=0;
    foreach($J as $ex){
        $pids=[]; if(!empty($ex['gate'])) foreach($ex['gate'] as [$pk,$pl]) foreach(($cidMap[$pk.'|'.$pl]??[]) as $cid)$pids[]=$cid;
        $primary=$pids[0]??null;
        $gd=$ex['gate']?(' [gate: '.implode(' AND ',array_map(fn($g)=>$g[0].'='.$g[1],$ex['gate'])).']'):'';
        echo sprintf("  %-18s %s%s\n",$ex['name'],$ex['req']?'(req)':'',$gd);
        $extraId=null;
        if($apply){ $insExtra->execute([$clientId,$productId,$primary,$ex['name'],$ex['req']?1:0,$so++]); $extraId=(int)$pdo->lastInsertId(); foreach($pids as $pid){$insJ->execute([$extraId,$pid]);$ng++;} }
        $nx++;$cs=0;
        foreach($ex['choices'] as $c){ $tag=($c['delta']?" +£{$c['delta']}":'').($c['pct']?" +{$c['pct']}%":'');
            echo "        - {$c['label']}{$tag}\n";
            if($apply){$insChoice->execute([$extraId,$c['label'],$c['delta'],$c['pct'],$c['default']?1:0,$cs++]);$cidMap[$ex['key'].'|'.$c['label']][]=(int)$pdo->lastInsertId();}
            else $cidMap[$ex['key'].'|'.$c['label']][]=0;
            $nc++; }
    }
    if($apply)$pdo->commit();
} catch(Throwable $e){ if($apply&&$pdo->inTransaction())$pdo->rollBack(); echo "\nFAILED: ".$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n"; exit(1); }
echo "\n".str_repeat('-',60)."\n".($apply?"Created":"Would create").": {$nx} options, {$nc} choices, {$ng} gates.\n";
if(!$apply) echo "\nPREVIEW ONLY — re-run with ?apply=1.\n";
