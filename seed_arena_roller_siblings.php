<?php
declare(strict_types=1);

/**
 * Build the 3 Arena roller SIBLING products that Arena treats as their own
 * product types (split out from the merged 5-system Roller for 1:1 EDI):
 *   - "Arena Grip Fit Roller"      (ROG)  — 1 system: Grip Fit
 *   - "Arena Roller Perfect Fit"   (RPF)  — 2 systems: Perfect Fit, Golden Oak
 *   - "Arena Cassette Roller"      (ROV)  — 2 systems: Louvolite, Senses
 *
 * Base grids + fabrics come from the recovered Phase-1 roller data
 * (seed_data/arena_roller_prices.csv = system,band,w,d,price ; arena_roller_
 * fabrics.csv = name,colour,band). Option journeys mirror each Arena product
 * type's configurator fields/order/values, priced from arena_roller_surcharge_
 * {width,flat}.csv. (Motorised Accessories = separate seeder.)
 *
 * DRY-RUN unless ?apply=1. Idempotent: per product, rebuilds systems + price
 * tables + fabrics + option tree. Super-admin only.
 *   Preview: /seed_arena_roller_siblings.php   Apply: &apply=1
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors','1'); error_reporting(E_ALL); @set_time_limit(600); @ini_set('memory_limit','512M');

$user=current_user(); $clientId=(int)$user['client_id'];
$pdo=db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$apply=(($_GET['apply']??'')==='1');

$readCsv=static function(string $path):array{ if(!is_file($path))throw new RuntimeException("Missing: $path"); $fh=fopen($path,'r');$h=fgetcsv($fh);$o=[]; while(($r=fgetcsv($fh))!==false){if($r===[null]||$r===false)continue;$o[]=array_combine($h,$r);} fclose($fh); return $o; };
$PRICES=$readCsv(__DIR__.'/seed_data/arena_roller_prices.csv');
$FABRICS=$readCsv(__DIR__.'/seed_data/arena_roller_fabrics.csv');
$WIDTH=$readCsv(__DIR__.'/seed_data/arena_roller_surcharge_width.csv');
$FLAT=$readCsv(__DIR__.'/seed_data/arena_roller_surcharge_flat.csv');
$widthByChoice=[]; foreach($WIDTH as $r) $widthByChoice[$r['system'].'|'.$r['group'].'|'.$r['choice']][]=[(int)$r['width_mm'],(float)$r['price']];
$flatByGroup=[]; foreach($FLAT as $r) $flatByGroup[$r['system'].'|'.$r['group']][]=$r;
$flatCat=function(string $sys,string $grp,string $needle) use($flatByGroup){ foreach(($flatByGroup[$sys.'|'.$grp]??[]) as $r){ if(stripos($r['choice'],$needle)!==false) return (float)$r['price']; } return 0.0; };
$VALID=['A'=>1,'B'=>1,'C'=>1,'D'=>1,'E'=>1];

$mk=static fn($label,$sys=null,$delta=0.0,$pct=0.0,$width=null,$def=false)=>['label'=>$label,'sys'=>$sys,'delta'=>$delta,'pct'=>$pct,'width'=>$width,'default'=>$def];
$colour=static function(array $L) use($mk){ $o=[]; foreach($L as $i=>$l)$o[]=$mk($l,null,0,0,null,$i===0); return $o; };
$sysColour=static function(string $s,array $L,bool $fd) use($mk){ $o=[]; foreach($L as $i=>$l)$o[]=$mk($l,$s,0,0,null,$fd&&$i===0); return $o; };

// shared shape/trim/pull mappings (same as open-roller rebuild)
$G1KEY='Shapes 2/4/Classic/Ritz/Coronet/Provence/Colonial'; $G2KEY='Shapes 5/6/Castille/Parapet/Temple/Colonade/Arch/Apex/Quad';
$SHAPE_G1=['Classic','Ritz','Coronet','Provence','Colonial'];
$shapeLabels=['Shape 1','Classic','Ritz','Coronet','Provence','Colonial','Castille','Parapet','Temple','Colonade','Arch','Apex','Quad'];
$shapeChoices=function(array $systems) use($mk,$shapeLabels,$SHAPE_G1,$widthByChoice,$G1KEY,$G2KEY){
    $o=[$mk('Shape 1',null,0,0,null,true)];
    foreach($systems as $s) foreach($shapeLabels as $sl){ if($sl==='Shape 1')continue;
        $grp=in_array($sl,$SHAPE_G1,true)?$G1KEY:$G2KEY; $o[]=$mk($sl,$s,0,0,$widthByChoice[$s.'|Shape|'.$grp]??null,false); }
    return $o;
};
$TRIM=['Antique Beige','Antique Ecru','Antique Ivory','Antique White','Cable Angora','Cable Fleece','Cable Fudge','Cable Ice White','Cable Taupe','Chrome Circular Eyelets (Louvolite)','Chrome Square Eyelets (Louvolite)','Crystal Dewdrop','Crystal Grey','Crystal Snowdrop','Glitz Black','Glitz Cream','Glitz Grey','Melody Black','Melody Grey','Melody Mocha','Melody Pearl','Melody Silver','Melody White','Metallic Copper','Metallic Gold','Metallic Silver','Nickel Circular Eyelets','Trio Camel','Vogue Ivory','Vogue Natural','Vogue Platinum','Vogue Steel'];
$trimPrice=function(string $sys,string $v) use($flatCat){ $l=strtolower($v);
    if(strpos($l,'square eyelet')!==false)return $flatCat($sys,'Eyelets','Square');
    if(strpos($l,'circular eyelet')!==false)return $flatCat($sys,'Eyelets','Circular');
    if(strpos($l,'cable')!==false||strpos($l,'trio')!==false||strpos($l,'antique')!==false)return $flatCat($sys,'Braid','Cable');
    if(strpos($l,'melody')!==false||strpos($l,'vogue')!==false)return $flatCat($sys,'Braid','Melody');
    if(strpos($l,'glitz')!==false)return $flatCat($sys,'Braid','Glitz');
    if(strpos($l,'crystal')!==false)return $flatCat($sys,'Braid','Crystal');
    if(strpos($l,'metallic')!==false)return $flatCat($sys,'Braid','Metallic'); return 0.0; };
$trimChoices=function(array $systems) use($mk,$TRIM,$trimPrice){ $o=[$mk('No Trim',null,0,0,null,true)];
    foreach($systems as $s) foreach($TRIM as $v) $o[]=$mk($v,$s,$trimPrice($s,$v),0,null,false); return $o; };
$PULL=['Classic Acorn Brass','Classic Acorn Chrome','Classic Acorn Pine','Classic Barrel Alder','Classic Barrel American Holly','Classic Barrel Cherry','Classic Barrel Driftwood','Classic Barrel Ebony','Classic Barrel Highland Ash','Classic Barrel Jacobean','Classic Barrel Lava','Classic Barrel Merbau','Classic Barrel White Fir','Premier Crystal Clear Ball','Premier Cylinder Black Perspex','Premier Cylinder Brushed Steel','Premier Cylinder Chrome','Premier Cylinder Clear Perspex','Premier Jute Ball','Premier Pear Black','Premier Pear Grey','Premier Pear Mocha','Premier Pear Pearl','Premier Pear Silver'];
$pullPrice=function(string $sys,string $v) use($flatCat){ $l=strtolower($v);
    if(strpos($l,'classic')!==false)return $flatCat($sys,'Pull','Classic Barrel');
    if(strpos($l,'premier crystal')!==false)return $flatCat($sys,'Pull','Premier Crystal');
    if(strpos($l,'premier cylinder')!==false)return $flatCat($sys,'Pull','Premier Cylinder');
    if(strpos($l,'premier jute')!==false)return $flatCat($sys,'Pull','Jute');
    if(strpos($l,'premier pear')!==false)return $flatCat($sys,'Pull','Premier Pear'); return 0.0; };
$pullChoices=function(array $systems) use($mk,$PULL,$pullPrice){ $o=[$mk('No Pull',null,0,0,null,true)];
    foreach($systems as $s) foreach($PULL as $v) $o[]=$mk($v,$s,$pullPrice($s,$v),0,null,false); return $o; };
$ctrlLen=array_merge(['Standard'], array_map(fn($n)=>$n.' cm', range(50,300,10)));

// ---- Per-product configs ------------------------------------------------
// csvSystems = which arena_roller_prices.csv 'system' rows feed this product (local name => csv system)
$PRODUCTS=[
 'Arena Grip Fit Roller'=>[
    'systems'=>['Grip Fit'=>'Grip Fit'],
    'journey'=>function() use($mk,$colour,$shapeChoices,$trimChoices,$widthByChoice){
        return [
         ['key'=>'ctrl','name'=>'Control Type','req'=>true,'gate'=>null,'choices'=>$colour(['Manual','Motorised'])],
         ['key'=>'barrel','name'=>'Barrel Type','req'=>true,'gate'=>null,'choices'=>$colour(['Easy Fit Left','Easy Fit Right'])],
         ['key'=>'motorsupply','name'=>'Motor Supply','req'=>false,'gate'=>[['ctrl','Motorised']],'choices'=>$colour(['Somfy RTS','Somfy Zigbee'])],
         ['key'=>'cassette','name'=>'Cassette Type','req'=>false,'gate'=>null,'choices'=>$colour(['70mm Fascia (S7)'])],
         ['key'=>'cassettecol','name'=>'Cassette Colour','req'=>false,'gate'=>null,'choices'=>$colour(['Anthracite','Black','White'])],
         ['key'=>'valance','name'=>'Valance','req'=>false,'gate'=>null,'choices'=>$colour(['No','Yes'])],
         ['key'=>'exact','name'=>'Exact or Recess','req'=>true,'gate'=>null,'choices'=>$colour(['Exact','Recess'])],
         ['key'=>'barreldia','name'=>'Barrel Diameter','req'=>false,'gate'=>null,'choices'=>$colour(['32mm'])],
         ['key'=>'chaintype','name'=>'Chain Type','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>$colour(['Breakaway'])],
         ['key'=>'chaincol','name'=>'Chain Colour','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>[$mk('Anthracite',null,0,0,null,true),$mk('Antique',null,0,5.0),$mk('Black Metal',null,0,5.0),$mk('Chrome',null,0,5.0)]],
         ['key'=>'controlslen','name'=>'Controls Length','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>$colour(array_merge(['Standard'],array_map(fn($n)=>$n.' cm',range(10,180,10))))],
         ['key'=>'reverse','name'=>'Reverse Roll','req'=>false,'gate'=>null,'choices'=>$colour(['No','Yes'])],
         ['key'=>'shape','name'=>'Shape','req'=>false,'gate'=>null,'choices'=>$shapeChoices(['Grip Fit'])],
         ['key'=>'bottombar','name'=>'Bottom Bar Type','req'=>false,'gate'=>null,'choices'=>[$mk('Standard',null,0,0,null,true),$mk('Wraparound','Grip Fit',0,0,$widthByChoice['Grip Fit|Bottom Bar|Wraparound Bottom Bar']??null),$mk('Decorative','Grip Fit',0,0,$widthByChoice['Grip Fit|Bottom Bar|Decorative Bottom Bar']??null)]],
         ['key'=>'trim','name'=>'Trim','req'=>false,'gate'=>null,'choices'=>$trimChoices(['Grip Fit'])],
         ['key'=>'fitheight','name'=>'Fit Height','req'=>false,'gate'=>null,'lenLabel'=>'mm','choices'=>[$mk('Fit Height',null,0,0,null,true)]],
        ];
    },
 ],
 'Arena Roller Perfect Fit'=>[
    'systems'=>['Perfect Fit'=>'Perfect Fit','Perfect Fit Golden Oak'=>'Perfect Fit Golden Oak'],
    'journey'=>function() use($mk,$colour,$sysColour){
        return [
         ['key'=>'profile','name'=>'Profile Type','req'=>true,'gate'=>null,'choices'=>$colour(['Gen 2 (New Frame)'])],
         ['key'=>'barrel','name'=>'Barrel Type','req'=>true,'gate'=>null,'choices'=>$colour(['Easy Fit Spring'])],
         ['key'=>'exact','name'=>'Exact or Recess','req'=>true,'gate'=>null,'choices'=>$colour(['Exact','Recess'])],
         ['key'=>'pfbrac','name'=>'PF Bracket Size','req'=>false,'gate'=>null,'choices'=>$colour(['18 mm','20 mm','22 mm','24 mm','26 mm','28 mm','30 mm','32 mm','38 mm'])],
         ['key'=>'pfframe','name'=>'PF Frame Colour','req'=>false,'gate'=>null,'choices'=>array_merge($sysColour('Perfect Fit',['White','Black','Brown','Anthracite'],true),$sysColour('Perfect Fit Golden Oak',['Golden Oak'],false))],
         ['key'=>'pfhandle','name'=>'PF Handle Location','req'=>false,'gate'=>null,'choices'=>$colour(['Bottom','None'])],
         ['key'=>'packing','name'=>'Packing Piece','req'=>false,'gate'=>null,'choices'=>$colour(['None','2 mm','6 mm','2mm and 6mm'])],
         ['key'=>'fitheight','name'=>'Fit Height','req'=>false,'gate'=>null,'lenLabel'=>'mm','choices'=>[$mk('Fit Height',null,0,0,null,true)]],
        ];
    },
 ],
 'Arena Cassette Roller'=>[
    'systems'=>['Louvolite'=>'Louvolite','Senses'=>'Senses'],
    'journey'=>function() use($mk,$colour,$sysColour,$shapeChoices,$trimChoices,$pullChoices,$widthByChoice,$ctrlLen){
        // cassette type required, system-scoped width surcharge
        $cass=[];
        foreach([['Louvolite','Semi','Semi Cassette 40mm'],['Louvolite','70mm Fascia','Semi Cassette 70mm'],['Louvolite','Full','Full Cassette'],
                 ['Senses','Mounting Profile','Mounting Profile'],['Senses','Full Fascia','Full Fascia']] as [$s,$label,$csv])
            $cass[]=$mk($label,$s,0,0,$widthByChoice[$s.'|Cassette|'.$csv]??null,false);
        return [
         ['key'=>'ctrl','name'=>'Control Type','req'=>true,'gate'=>null,'choices'=>$colour(['Manual','Motorised'])],
         ['key'=>'barrel','name'=>'Barrel Type','req'=>true,'gate'=>null,'choices'=>[$mk('Easy Fit Left',null,0,0,null,true),$mk('Easy Fit Right'),$mk('Motorised Left'),$mk('Motorised Right')]],
         ['key'=>'motorsupply','name'=>'Motor Supply','req'=>false,'gate'=>[['ctrl','Motorised']],'choices'=>$colour(['Somfy RTS','Somfy Zigbee'])],
         ['key'=>'cassette','name'=>'Cassette Type','req'=>true,'gate'=>null,'choices'=>$cass],
         ['key'=>'cassettecol','name'=>'Cassette Colour','req'=>false,'gate'=>null,'choices'=>$colour(['Anthracite','Black','White'])],
         ['key'=>'cassetteend','name'=>'Cassette Endcap Colour','req'=>false,'gate'=>null,'choices'=>$colour(['White','Chrome','Black Chrome','Anthracite'])],
         ['key'=>'valance','name'=>'Valance','req'=>false,'gate'=>null,'choices'=>$colour(['No','Yes'])],
         ['key'=>'exact','name'=>'Exact or Recess','req'=>true,'gate'=>null,'choices'=>$colour(['Exact','Recess'])],
         ['key'=>'barreldia','name'=>'Barrel Diameter','req'=>false,'gate'=>null,'choices'=>$colour(['32mm','40mm','45mm','55mm'])],
         ['key'=>'chaintype','name'=>'Chain Type','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>$colour(['Breakaway','Tensioned'])],
         ['key'=>'chaincol','name'=>'Chain Colour','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>[$mk('Black Plastic',null,0,0,null,true),$mk('Antique',null,0,5.0),$mk('Black Metal',null,0,5.0),$mk('Chrome',null,0,5.0)]],
         ['key'=>'controlslen','name'=>'Controls Length','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>$colour($ctrlLen)],
         ['key'=>'reverse','name'=>'Reverse Roll','req'=>false,'gate'=>null,'choices'=>$colour(['No','Yes'])],
         ['key'=>'shape','name'=>'Shape','req'=>false,'gate'=>null,'choices'=>$shapeChoices(['Louvolite','Senses'])],
         ['key'=>'bottombar','name'=>'Bottom Bar Type','req'=>false,'gate'=>null,'choices'=>array_merge([$mk('Standard',null,0,0,null,true)],
                [$mk('Round','Louvolite',0,0,$widthByChoice['Louvolite|Bottom Bar|Round Bottom Bar']??null),$mk('Wraparound','Louvolite',0,0,$widthByChoice['Louvolite|Bottom Bar|Wraparound Bottom Bar']??null),
                 $mk('Covered Aluminium Bar','Senses',0,0,$widthByChoice['Senses|Bottom Bar|Fabric Covered Aluminium']??null),$mk('Exposed Aluminium Bar','Senses',0,0,$widthByChoice['Senses|Bottom Bar|Exposed Aluminium']??null)])],
         ['key'=>'trim','name'=>'Trim','req'=>false,'gate'=>null,'choices'=>$trimChoices(['Louvolite','Senses'])],
         ['key'=>'polepull','name'=>'Pole Pull Type','req'=>false,'gate'=>null,'choices'=>$pullChoices(['Louvolite','Senses'])],
         ['key'=>'fitheight','name'=>'Fit Height','req'=>false,'gate'=>null,'lenLabel'=>'mm','choices'=>[$mk('Fit Height',null,0,0,null,true)]],
        ];
    },
 ],
];

echo ($apply?"APPLY":"DRY RUN (preview only)")." — Arena roller sibling products\n".str_repeat('=',70)."\n\n";
$summary=[];
if($apply) $pdo->beginTransaction();
try {
    $hasLen=false; try{$pdo->query('SELECT length_input_label FROM product_extras LIMIT 1');$hasLen=true;}catch(Throwable $e){}
    foreach($PRODUCTS as $pname=>$cfg){
        echo "\n### {$pname} ###\n";
        // ----- product -----
        $f=$pdo->prepare('SELECT id FROM products WHERE client_id=? AND name=?'); $f->execute([$clientId,$pname]);
        $productId=(int)($f->fetchColumn()?:0);
        if($apply){
            if($productId===0){ $ss=$pdo->prepare('SELECT COALESCE(MAX(sort_order),-1)+1 FROM products WHERE client_id=?');$ss->execute([$clientId]);$ns=(int)$ss->fetchColumn();
                try{$pdo->prepare('INSERT INTO products (client_id,name,option_label,show_colour_field,sort_order,active) VALUES (?,?,?,1,?,1)')->execute([$clientId,$pname,'Fabric',$ns]);}
                catch(Throwable $e){$pdo->prepare('INSERT INTO products (client_id,name,option_label,sort_order,active) VALUES (?,?,?,?,1)')->execute([$clientId,$pname,'Fabric',$ns]);}
                $productId=(int)$pdo->lastInsertId();
            }
        }
        echo "  product #".($productId?:'(new)')."\n";

        // ----- systems -----
        $SYS=[]; $i=0;
        foreach($cfg['systems'] as $local=>$csvSys){
            if($apply){
                $q=$pdo->prepare('SELECT id FROM product_systems WHERE client_id=? AND product_id=? AND name=?');$q->execute([$clientId,$productId,$local]);$sid=(int)($q->fetchColumn()?:0);
                if($sid===0){$pdo->prepare('INSERT INTO product_systems (client_id,product_id,name,sort_order,active,is_default) VALUES (?,?,?,?,1,?)')->execute([$clientId,$productId,$local,$i,$i===0?1:0]);$sid=(int)$pdo->lastInsertId();}
                else{$pdo->prepare('UPDATE product_systems SET active=1,is_default=?,sort_order=? WHERE id=?')->execute([$i===0?1:0,$i,$sid]);}
                $SYS[$local]=$sid;
            } else $SYS[$local]=0;
            $i++;
        }
        echo "  systems: ".implode(', ',array_keys($cfg['systems']))."\n";

        // ----- base price tables + fabrics -----
        if($apply){
            $pdo->prepare('DELETE r FROM price_table_rows r JOIN price_tables t ON t.id=r.price_table_id WHERE t.client_id=? AND t.product_id=?')->execute([$clientId,$productId]);
            $pdo->prepare('DELETE FROM price_tables WHERE client_id=? AND product_id=?')->execute([$clientId,$productId]);
            $pdo->prepare('DELETE FROM product_options WHERE client_id=? AND product_id=?')->execute([$clientId,$productId]);
        }
        $tblIns=$pdo->prepare('INSERT INTO price_tables (client_id,product_id,system_id,band_code,name,active) VALUES (?,?,?,?,?,1)');
        $rowIns=$pdo->prepare('INSERT INTO price_table_rows (price_table_id,width_mm,drop_mm,price) VALUES (?,?,?,?)');
        $cells=0;$tbls=0;
        foreach($cfg['systems'] as $local=>$csvSys){
            foreach(['A','B','C','D','E'] as $band){
                $rows=array_filter($PRICES,fn($r)=>$r['system']===$csvSys&&$r['band']===$band);
                if(!$rows)continue;
                if($apply){ $tblIns->execute([$clientId,$productId,$SYS[$local],$band,"Arena Band $band"]); $tid=(int)$pdo->lastInsertId();
                    foreach($rows as $c){ $rowIns->execute([$tid,(int)$c['width_mm'],(int)$c['drop_mm'],(float)$c['price']]); $cells++; } }
                else $cells+=count($rows);
                $tbls++;
            }
        }
        echo "  base: {$tbls} price tables, {$cells} cells\n";
        // fabrics (band-keyed, shared)
        $fcount=0;
        if($apply){
            $oi=$pdo->prepare('INSERT INTO product_options (client_id,product_id,system_id,band_code,supplier_name,name,colour,code,sort_order,active) VALUES (?,?,NULL,?,?,?,?,?,?,?)');
            $sort=0;$seen=[];
            foreach($FABRICS as $fr){ $band=trim((string)$fr['band']);$banded=isset($VALID[$band]);$bc=$banded?$band:'TBC';$name=(string)$fr['name'];$col=(string)($fr['colour']??'');
                $k=$bc.'|'.mb_strtolower($name).'|'.mb_strtolower($col); if(isset($seen[$k]))continue;$seen[$k]=1;
                $oi->execute([$clientId,$productId,$bc,'Arena',$name,$col,'',$sort++,$banded?1:0]); $fcount++; }
        } else $fcount=count($FABRICS);
        echo "  fabrics: {$fcount}\n";

        // ----- option journey -----
        $J=$cfg['journey']();
        if($apply){
            $ids=$pdo->prepare('SELECT id FROM product_extras WHERE client_id=? AND product_id=?');$ids->execute([$clientId,$productId]);$exIds=$ids->fetchAll(PDO::FETCH_COLUMN);
            if($exIds){$in=implode(',',array_fill(0,count($exIds),'?'));
                $ch=$pdo->prepare("SELECT id FROM product_extra_choices WHERE product_extra_id IN ($in)");$ch->execute($exIds);$cIds=$ch->fetchAll(PDO::FETCH_COLUMN);
                if($cIds){$cin=implode(',',array_fill(0,count($cIds),'?'));$pdo->prepare("DELETE FROM extra_choice_price_rows WHERE product_extra_choice_id IN ($cin)")->execute($cIds);}
                $pdo->prepare("DELETE FROM product_extra_parent_choices WHERE product_extra_id IN ($in)")->execute($exIds);
                $pdo->prepare("DELETE FROM product_extra_choices WHERE product_extra_id IN ($in)")->execute($exIds);
                $pdo->prepare("DELETE FROM product_extras WHERE id IN ($in)")->execute($exIds);}
        }
        $insExtra=$pdo->prepare('INSERT INTO product_extras (client_id,product_id,parent_choice_id,name,is_required,sort_order,active,parent_match_all) VALUES (?,?,?,?,?,?,1,?)');
        $insExtraLen=$hasLen?$pdo->prepare('INSERT INTO product_extras (client_id,product_id,parent_choice_id,name,is_required,sort_order,active,length_input_label) VALUES (?,?,?,?,?,?,1,?)'):null;
        $insChoice=$pdo->prepare('INSERT INTO product_extra_choices (product_extra_id,system_id,label,image_path,price_delta,price_percent,price_per_metre,is_default,sort_order,active) VALUES (?,?,?,NULL,?,?,0,?,?,1)');
        $insJ=$pdo->prepare('INSERT INTO product_extra_parent_choices (product_extra_id,product_extra_choice_id) VALUES (?,?)');
        $insW=$pdo->prepare('INSERT INTO extra_choice_price_rows (product_extra_choice_id,width_mm,price) VALUES (?,?,?)');
        $cidMap=[];$so=0;$nx=0;$nc=0;$nw=0;
        foreach($J as $ex){
            $pids=[]; if(!empty($ex['gate'])) foreach($ex['gate'] as [$pk,$pl]) foreach(($cidMap[$pk.'|'.$pl]??[]) as $cid)$pids[]=$cid;
            $pids=array_values(array_unique($pids)); $and=(!empty($ex['gate'])&&count($ex['gate'])>1)?1:0; $primary=$pids[0]??null;
            $extraId=null;
            if($apply){ if(!empty($ex['lenLabel'])&&$insExtraLen)$insExtraLen->execute([$clientId,$productId,$primary,$ex['name'],$ex['req']?1:0,$so++,$ex['lenLabel']]);
                else $insExtra->execute([$clientId,$productId,$primary,$ex['name'],$ex['req']?1:0,$so++,$and]);
                $extraId=(int)$pdo->lastInsertId(); foreach($pids as $pid){$insJ->execute([$extraId,$pid]);} }
            $nx++; $cs=0;
            foreach($ex['choices'] as $c){ $sid=$c['sys']!==null?($SYS[$c['sys']]??null):null;
                if($apply){ $insChoice->execute([$extraId,$sid,$c['label'],$c['delta'],$c['pct'],$c['default']?1:0,$cs++]); $cid=(int)$pdo->lastInsertId(); $cidMap[$ex['key'].'|'.$c['label']][]=$cid;
                    if(!empty($c['width'])){foreach($c['width'] as [$w,$pr]){$insW->execute([$cid,$w,$pr]);$nw++;}} }
                else $cidMap[$ex['key'].'|'.$c['label']][]=0;
                $nc++; }
        }
        echo "  options: {$nx} extras, {$nc} choices, {$nw} width rows\n";
        $summary[$pname]=compact('cells','fcount','nx','nc','nw');
    }
    if($apply)$pdo->commit();
} catch(Throwable $e){ if($apply&&$pdo->inTransaction())$pdo->rollBack(); echo "\nFAILED: ".$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n"; exit(1); }

echo "\n".str_repeat('=',70)."\n".($apply?"BUILT":"WOULD BUILD")." ".count($PRODUCTS)." products.\n";
if(!$apply) echo "\nPREVIEW ONLY — re-run with ?apply=1.\n";
