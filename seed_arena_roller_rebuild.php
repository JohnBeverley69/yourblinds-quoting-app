<?php
declare(strict_types=1);

/**
 * RESTRUCTURE "Arena Roller" (#5755) to mirror Arena's ROB "Roller Blinds"
 * product type EXACTLY (for 1:1 EDI field mapping):
 *   - Reduce systems to LOUVOLITE + SENSES (open roller). Grip Fit, Perfect
 *     Fit and PF Golden Oak move to their OWN products (separate Arena types
 *     ROG / RPF) — built by sibling seeders. This drops those 3 systems +
 *     their price tables from #5755.
 *   - Rebuild the option journey to Arena's exact ROB field set, names and
 *     order, with per-system value scoping and surcharges:
 *       Control Type, Barrel Type (Softrise +£22), Motor Supply (RTS/Zigbee,
 *       motorised), Exact/Recess, Brackets Colour, Bracket Covers, Barrel
 *       Diameter, Bracket Size, Chain Type/Colour (metal +5%), Controls
 *       Length, Reverse Roll, Shape (width), Bottom Bar Type (width) +
 *       Colour/Endcap, Trim (flat), Pole Pull Type (flat), Fit Height.
 *   - Cassette + Motorised Accessories are SEPARATE products (Arena ROV/MTR).
 *
 * Pricing from seed_data/arena_roller_surcharge_{width,flat}.csv (Arena
 * figures, stored as-is — engine never marks up extras). DRY-RUN unless
 * ?apply=1; idempotent (rebuilds this product's option tree + trims systems).
 * Super-admin only.   Preview: /seed_arena_roller_rebuild.php   Apply: &apply=1
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors','1'); error_reporting(E_ALL); @set_time_limit(300);

$user=current_user(); $clientId=(int)$user['client_id'];
$pdo=db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$PRODUCT_NAME='Arena Roller'; $apply=(($_GET['apply']??'')==='1');
$KEEP_SYSTEMS=['Louvolite','Senses']; $DROP_SYSTEMS=['Grip Fit','Perfect Fit','Perfect Fit Golden Oak'];

$p=$pdo->prepare('SELECT id FROM products WHERE client_id=? AND name=? LIMIT 1'); $p->execute([$clientId,$PRODUCT_NAME]);
$productId=(int)($p->fetchColumn()?:0);
if(!$productId){ echo "Product \"$PRODUCT_NAME\" not found.\n"; exit(1); }

// System name->id
$sysRows=$pdo->prepare('SELECT id,name FROM product_systems WHERE client_id=? AND product_id=?'); $sysRows->execute([$clientId,$productId]);
$SYS=[]; foreach($sysRows->fetchAll() as $r) $SYS[$r['name']]=(int)$r['id'];

// ---- surcharge CSVs ----
$readCsv=static function(string $path):array{ if(!is_file($path))throw new RuntimeException("Missing: $path"); $fh=fopen($path,'r');$h=fgetcsv($fh);$o=[]; while(($r=fgetcsv($fh))!==false){if($r===[null]||$r===false)continue;$o[]=array_combine($h,$r);} fclose($fh); return $o; };
$WIDTH=$readCsv(__DIR__.'/seed_data/arena_roller_surcharge_width.csv');
$FLAT =$readCsv(__DIR__.'/seed_data/arena_roller_surcharge_flat.csv');
$widthByChoice=[]; foreach($WIDTH as $r) $widthByChoice[$r['system'].'|'.$r['group'].'|'.$r['choice']][]=[(int)$r['width_mm'],(float)$r['price']];
$flatByGroup=[]; foreach($FLAT as $r) $flatByGroup[$r['system'].'|'.$r['group']][]=$r;
$flatCat=function(string $system,string $group,string $needle) use($flatByGroup){
    foreach(($flatByGroup[$system.'|'.$group]??[]) as $r){ if(stripos($r['choice'],$needle)!==false) return (float)$r['price']; } return 0.0; };

$mk=static fn($label,$sys=null,$delta=0.0,$pct=0.0,$width=null,$def=false)=>
    ['label'=>$label,'sys'=>$sys,'delta'=>$delta,'pct'=>$pct,'width'=>$width,'default'=>$def];
$colour=static function(array $labels) use($mk){ $o=[]; foreach($labels as $i=>$l)$o[]=$mk($l,null,0,0,null,$i===0); return $o; };
// system-scoped colour list
$sysColour=static function(string $sys,array $labels,bool $firstDefault) use($mk){ $o=[]; foreach($labels as $i=>$l)$o[]=$mk($l,$sys,0,0,null,$firstDefault&&$i===0); return $o; };

// ---- Shape: map label -> price group (per system width table) ----
$SHAPE_G1=['Classic','Ritz','Coronet','Provence','Colonial'];           // = Shapes 2/4 group
$SHAPE_G2=['Castille','Parapet','Temple','Colonade','Arch','Apex','Quad']; // = Shapes 5/6 group
$shapeLabels=['Shape 1','Classic','Ritz','Coronet','Provence','Colonial','Castille','Parapet','Temple','Colonade','Arch','Apex','Quad'];
$G1KEY='Shapes 2/4/Classic/Ritz/Coronet/Provence/Colonial'; $G2KEY='Shapes 5/6/Castille/Parapet/Temple/Colonade/Arch/Apex/Quad';
$shapeChoices=function() use($mk,$shapeLabels,$SHAPE_G1,$SHAPE_G2,$widthByChoice,$G1KEY,$G2KEY,$KEEP_SYSTEMS){
    $o=[$mk('Shape 1',null,0,0,null,true)];
    foreach($KEEP_SYSTEMS as $s){
        foreach($shapeLabels as $sl){ if($sl==='Shape 1')continue;
            $grp=in_array($sl,$SHAPE_G1,true)?$G1KEY:$G2KEY;
            $rows=$widthByChoice[$s.'|Shape|'.$grp]??null;
            $o[]=$mk($sl,$s,0,0,$rows,false);
        }
    }
    return $o;
};
// ---- Bottom Bar: per-system choices + width tables ----
$bottomBarChoices=function() use($mk,$widthByChoice,$KEEP_SYSTEMS){
    $o=[$mk('Standard',null,0,0,null,true)];
    // Louvolite: Round, Wraparound ; Senses: Covered Aluminium Bar(=Fabric Covered Aluminium), Exposed Aluminium Bar(=Exposed Aluminium)
    $map=['Louvolite'=>[['Round','Round Bottom Bar'],['Wraparound','Wraparound Bottom Bar']],
          'Senses'=>[['Covered Aluminium Bar','Fabric Covered Aluminium'],['Exposed Aluminium Bar','Exposed Aluminium']]];
    foreach($KEEP_SYSTEMS as $s) foreach(($map[$s]??[]) as [$label,$csvChoice]){
        $rows=$widthByChoice[$s.'|Bottom Bar|'.$csvChoice]??null; $o[]=$mk($label,$s,0,0,$rows,false);
    }
    return $o;
};
// ---- Trim: Arena values priced by braid/eyelet category (per system) ----
$TRIM_VALUES=['No Trim','Antique Beige','Antique Ecru','Antique Ivory','Antique White','Cable Angora','Cable Fleece','Cable Fudge','Cable Ice White','Cable Taupe','Chrome Circular Eyelets (Louvolite)','Chrome Square Eyelets (Louvolite)','Crystal Dewdrop','Crystal Grey','Crystal Snowdrop','Glitz Black','Glitz Cream','Glitz Grey','Melody Black','Melody Grey','Melody Mocha','Melody Pearl','Melody Silver','Melody White','Metallic Copper','Metallic Gold','Metallic Silver','Nickel Circular Eyelets','Trio Camel','Vogue Ivory','Vogue Natural','Vogue Platinum','Vogue Steel'];
$trimPrice=function(string $system,string $v) use($flatCat){
    $l=strtolower($v);
    if($l==='no trim') return 0.0;
    if(strpos($l,'square eyelet')!==false) return $flatCat($system,'Eyelets','Square');
    if(strpos($l,'circular eyelet')!==false) return $flatCat($system,'Eyelets','Circular');
    if(strpos($l,'cable')!==false||strpos($l,'trio')!==false||strpos($l,'antique')!==false) return $flatCat($system,'Braid','Cable');
    if(strpos($l,'melody')!==false||strpos($l,'vogue')!==false) return $flatCat($system,'Braid','Melody');
    if(strpos($l,'glitz')!==false) return $flatCat($system,'Braid','Glitz');
    if(strpos($l,'crystal')!==false) return $flatCat($system,'Braid','Crystal');
    if(strpos($l,'metallic')!==false) return $flatCat($system,'Braid','Metallic');
    return 0.0;
};
$trimChoices=function() use($mk,$TRIM_VALUES,$trimPrice,$KEEP_SYSTEMS){
    $o=[$mk('No Trim',null,0,0,null,true)];
    foreach($KEEP_SYSTEMS as $s) foreach($TRIM_VALUES as $v){ if($v==='No Trim')continue;
        $o[]=$mk($v,$s,$trimPrice($s,$v),0,null,false); }
    return $o;
};
// ---- Pole Pull: Arena values priced by pull category ----
$PULL_VALUES=['No Pull','Classic Acorn Brass','Classic Acorn Chrome','Classic Acorn Pine','Classic Barrel Alder','Classic Barrel American Holly','Classic Barrel Cherry','Classic Barrel Driftwood','Classic Barrel Ebony','Classic Barrel Highland Ash','Classic Barrel Jacobean','Classic Barrel Lava','Classic Barrel Merbau','Classic Barrel White Fir','Premier Crystal Clear Ball','Premier Cylinder Black Perspex','Premier Cylinder Brushed Steel','Premier Cylinder Chrome','Premier Cylinder Clear Perspex','Premier Jute Ball','Premier Pear Black','Premier Pear Grey','Premier Pear Mocha','Premier Pear Pearl','Premier Pear Silver'];
$pullPrice=function(string $system,string $v) use($flatCat){
    $l=strtolower($v); if($l==='no pull')return 0.0;
    if(strpos($l,'classic acorn')!==false||strpos($l,'classic barrel')!==false) return $flatCat($system,'Pull','Classic Barrel');
    if(strpos($l,'premier crystal')!==false) return $flatCat($system,'Pull','Premier Crystal');
    if(strpos($l,'premier cylinder')!==false) return $flatCat($system,'Pull','Premier Cylinder');
    if(strpos($l,'premier jute')!==false) return $flatCat($system,'Pull','Jute');
    if(strpos($l,'premier pear')!==false) return $flatCat($system,'Pull','Premier Pear');
    return 0.0;
};
$pullChoices=function() use($mk,$PULL_VALUES,$pullPrice,$KEEP_SYSTEMS){
    $o=[$mk('No Pull',null,0,0,null,true)];
    foreach($KEEP_SYSTEMS as $s) foreach($PULL_VALUES as $v){ if($v==='No Pull')continue;
        $o[]=$mk($v,$s,$pullPrice($s,$v),0,null,false); }
    return $o;
};
$softriseDelta=$flatCat('Louvolite','Roller Option','Soft Rise'); // 22.0

// ---- Journey (Arena ROB order) ----
$J=[];
$J[]=['key'=>'ctrl','name'=>'Control Type','req'=>true,'gate'=>null,'choices'=>$colour(['Manual','Motorised'])];
$J[]=['key'=>'barrel','name'=>'Barrel Type','req'=>true,'gate'=>null,'choices'=>[
        $mk('Easy Fit Left',null,0,0,null,true),$mk('Easy Fit Right'),$mk('Easy Fit Spring'),
        $mk('Softrise',null,$softriseDelta),$mk('Motorised Left'),$mk('Motorised Right')]];
$J[]=['key'=>'motorsupply','name'=>'Motor Supply','req'=>false,'gate'=>[['ctrl','Motorised']],'choices'=>$colour(['Somfy RTS','Somfy Zigbee'])];
$J[]=['key'=>'exact','name'=>'Exact or Recess','req'=>true,'gate'=>null,'choices'=>$colour(['Exact','Recess'])];
$J[]=['key'=>'brackcol','name'=>'Brackets Colour','req'=>false,'gate'=>null,'choices'=>array_merge(
        $sysColour('Louvolite',['Anthracite','Black','White'],true), $sysColour('Senses',['Black','Silver','White'],false))];
$J[]=['key'=>'brackcov','name'=>'Bracket Covers','req'=>false,'gate'=>null,'choices'=>$colour(['No','Yes'])];
$J[]=['key'=>'barreldia','name'=>'Barrel Diameter','req'=>false,'gate'=>null,'choices'=>$colour(['32mm','40mm','45mm','55mm'])];
$J[]=['key'=>'bracksize','name'=>'Bracket Size','req'=>false,'gate'=>null,'choices'=>array_merge(
        $sysColour('Louvolite',['32mm','32mm extension'],true), $sysColour('Senses',['Medium'],false))];
$J[]=['key'=>'chaintype','name'=>'Chain Type','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>$colour(['Breakaway','Tensioned'])];
$J[]=['key'=>'chaincol','name'=>'Chain Colour','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>[
        $mk('Black Plastic',null,0,0,null,true),$mk('Antique',null,0,5.0),$mk('Black Metal',null,0,5.0),$mk('Chrome',null,0,5.0)]];
$J[]=['key'=>'controlslen','name'=>'Controls Length','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>$colour(
        array_merge(['Standard'], array_map(fn($n)=>($n).' cm', range(50,300,10))))];
$J[]=['key'=>'reverse','name'=>'Reverse Roll','req'=>false,'gate'=>null,'choices'=>$colour(['No','Yes'])];
$J[]=['key'=>'shape','name'=>'Shape','req'=>false,'gate'=>null,'choices'=>$shapeChoices()];
$J[]=['key'=>'bottombar','name'=>'Bottom Bar Type','req'=>false,'gate'=>null,'choices'=>$bottomBarChoices()];
$J[]=['key'=>'bottombarcol','name'=>'Bottom Bar Endcap Colour','req'=>false,'gate'=>null,'choices'=>$colour(['White','Chrome','Black Chrome','Anthracite','Cream'])];
$J[]=['key'=>'trim','name'=>'Trim','req'=>false,'gate'=>null,'choices'=>$trimChoices()];
$J[]=['key'=>'polepull','name'=>'Pole Pull Type','req'=>false,'gate'=>null,'choices'=>$pullChoices()];
$J[]=['key'=>'fitheight','name'=>'Fit Height','req'=>false,'gate'=>null,'lenLabel'=>'mm','choices'=>[$mk('Fit Height',null,0,0,null,true)]];

// =========================================================================
echo ($apply?"APPLY":"DRY RUN (preview only)")." — Rebuild Arena Roller #{$productId} (open ROB: Louvolite+Senses)\n".str_repeat('=',70)."\n\n";

$nExtra=0;$nChoice=0;$nWidth=0;$nGate=0;$droppedSys=0;$droppedTbl=0;
if($apply) $pdo->beginTransaction();
try {
    // 1) Drop non-open systems + their price tables/rows
    foreach($DROP_SYSTEMS as $sname){ if(!isset($SYS[$sname]))continue; $sid=$SYS[$sname];
        echo "  drop system: {$sname} (#{$sid})\n";
        if($apply){
            $tids=$pdo->prepare('SELECT id FROM price_tables WHERE client_id=? AND product_id=? AND system_id=?'); $tids->execute([$clientId,$productId,$sid]);
            $ids=$tids->fetchAll(PDO::FETCH_COLUMN);
            if($ids){ $in=implode(',',array_fill(0,count($ids),'?'));
                $pdo->prepare("DELETE FROM price_table_rows WHERE price_table_id IN ($in)")->execute($ids);
                $pdo->prepare("DELETE FROM price_tables WHERE id IN ($in)")->execute($ids); $droppedTbl+=count($ids); }
            $pdo->prepare('DELETE FROM product_systems WHERE id=?')->execute([$sid]);
        }
        $droppedSys++;
    }

    // 2) Wipe + rebuild option tree
    if($apply){
        $ids=$pdo->prepare('SELECT id FROM product_extras WHERE client_id=? AND product_id=?'); $ids->execute([$clientId,$productId]);
        $exIds=$ids->fetchAll(PDO::FETCH_COLUMN);
        if($exIds){ $in=implode(',',array_fill(0,count($exIds),'?'));
            $chIds=$pdo->prepare("SELECT id FROM product_extra_choices WHERE product_extra_id IN ($in)"); $chIds->execute($exIds);
            $cIds=$chIds->fetchAll(PDO::FETCH_COLUMN);
            if($cIds){ $cin=implode(',',array_fill(0,count($cIds),'?'));
                $pdo->prepare("DELETE FROM extra_choice_price_rows WHERE product_extra_choice_id IN ($cin)")->execute($cIds); }
            $pdo->prepare("DELETE FROM product_extra_parent_choices WHERE product_extra_id IN ($in)")->execute($exIds);
            $pdo->prepare("DELETE FROM product_extra_choices WHERE product_extra_id IN ($in)")->execute($exIds);
            $pdo->prepare("DELETE FROM product_extras WHERE id IN ($in)")->execute($exIds);
        }
    }

    $hasLen=false; try{$pdo->query('SELECT length_input_label FROM product_extras LIMIT 1');$hasLen=true;}catch(Throwable $e){}
    $insExtra=$pdo->prepare('INSERT INTO product_extras (client_id,product_id,parent_choice_id,name,is_required,sort_order,active,parent_match_all) VALUES (?,?,?,?,?,?,1,?)');
    $insExtraLen=$hasLen?$pdo->prepare('INSERT INTO product_extras (client_id,product_id,parent_choice_id,name,is_required,sort_order,active,length_input_label) VALUES (?,?,?,?,?,?,1,?)'):null;
    $insChoice=$pdo->prepare('INSERT INTO product_extra_choices (product_extra_id,system_id,label,image_path,price_delta,price_percent,price_per_metre,is_default,sort_order,active) VALUES (?,?,?,NULL,?,?,0,?,?,1)');
    $insJunction=$pdo->prepare('INSERT INTO product_extra_parent_choices (product_extra_id,product_extra_choice_id) VALUES (?,?)');
    $insWidth=$pdo->prepare('INSERT INTO extra_choice_price_rows (product_extra_choice_id,width_mm,price) VALUES (?,?,?)');

    $choiceIds=[]; $sort=0;
    foreach($J as $ex){
        $parentIds=[];
        if(!empty($ex['gate'])) foreach($ex['gate'] as [$pk,$pl]) foreach(($choiceIds[$pk.'|'.$pl]??[]) as $cid) $parentIds[]=$cid;
        $parentIds=array_values(array_unique($parentIds));
        $andGate=(!empty($ex['gate'])&&count($ex['gate'])>1)?1:0; $primary=$parentIds[0]??null;
        $gd=$ex['gate']?(' [gate: '.implode($andGate?' AND ':' OR ',array_map(fn($g)=>$g[0].'='.$g[1],$ex['gate'])).']'):'';
        echo sprintf("  %-24s %s%s — %d choices\n",$ex['name'],$ex['req']?'(req)':'',$gd,count($ex['choices']));
        $extraId=null;
        if($apply){
            if(!empty($ex['lenLabel'])&&$insExtraLen) $insExtraLen->execute([$clientId,$productId,$primary,$ex['name'],$ex['req']?1:0,$sort++,$ex['lenLabel']]);
            else $insExtra->execute([$clientId,$productId,$primary,$ex['name'],$ex['req']?1:0,$sort++,$andGate]);
            $extraId=(int)$pdo->lastInsertId();
            foreach($parentIds as $pid){ $insJunction->execute([$extraId,$pid]); $nGate++; }
        }
        $nExtra++; $cSort=0;
        foreach($ex['choices'] as $c){
            $sysId=$c['sys']!==null?($SYS[$c['sys']]??null):null;
            if($apply){
                $insChoice->execute([$extraId,$sysId,$c['label'],$c['delta'],$c['pct'],$c['default']?1:0,$cSort++]);
                $cid=(int)$pdo->lastInsertId(); $choiceIds[$ex['key'].'|'.$c['label']][]=$cid;
                if(!empty($c['width'])){ foreach($c['width'] as [$w,$pr]){ $insWidth->execute([$cid,$w,$pr]); $nWidth++; } }
            } else { $choiceIds[$ex['key'].'|'.$c['label']][]=0; }
            $nChoice++;
        }
    }
    if($apply) $pdo->commit();
} catch(Throwable $e){ if($apply&&$pdo->inTransaction())$pdo->rollBack(); echo "\nFAILED: ".$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n"; exit(1); }

echo "\n".str_repeat('-',70)."\n";
echo ($apply?"Done":"Would")." — dropped {$droppedSys} systems ({$droppedTbl} price tables); ".($apply?"built":"would build")." {$nExtra} options, {$nChoice} choices, {$nWidth} width rows, {$nGate} gates.\n";
if(!$apply) echo "\nPREVIEW ONLY — re-run with ?apply=1.\n";
