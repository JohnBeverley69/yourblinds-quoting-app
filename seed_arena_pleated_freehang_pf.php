<?php
declare(strict_types=1);

/**
 * Pleated family split — WAVE 1 (exact PLT mirror):
 *   - Rebuild #5758 "Arena Pleated" -> "Arena Pleated Freehang" (Arena type
 *     PYS): systems Standard Cord Freehang + Tab Freehang (PYS_TYPE =
 *     Freehang / Tab control). Drops the non-freehang systems (Roof x3,
 *     Perfect Fit x2 — they move to their own products).
 *   - Create "Arena Pleated Perfect Fit" (Arena type PYE): systems Perfect
 *     Fit + Perfect Fit Golden Oak (frame colour scoped per system).
 *
 * Base grids + fabrics from recovered seed_data/arena_pleated_{prices,
 * fabrics}.csv. Option journeys are the exact fields captured by driving the
 * live PLT form; all £0 spec choices (no surcharges on these two types).
 * Wave 2 = Roof, Roof Shape, Shape Freehang, Top Down Bottom Up, Night & Day.
 *
 * DRY-RUN unless ?apply=1; idempotent. Super-admin only.
 *   Preview: /seed_arena_pleated_freehang_pf.php   Apply: &apply=1
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

$mk=static fn($label,$sys=null,$delta=0.0,$pct=0.0,$def=false)=>['label'=>$label,'sys'=>$sys,'delta'=>$delta,'pct'=>$pct,'width'=>null,'default'=>$def];
$colour=static function(array $L) use($mk){ $o=[]; foreach($L as $i=>$l)$o[]=$mk($l,null,0,0,$i===0); return $o; };
$sysColour=static function(string $s,array $L,bool $fd) use($mk){ $o=[]; foreach($L as $i=>$l)$o[]=$mk($l,$s,0,0,$fd&&$i===0); return $o; };
$ctrlLen=array_merge(['Standard'], array_map(fn($n)=>$n.' cm', range(10,500,10)));

// product config: name, rename-from-id (or null=create), systems(local=>csvSystem), journey builder
$PRODUCTS=[
 ['name'=>'Arena Pleated Freehang','renameFromId'=>5758,'systems'=>['Standard Cord Freehang'=>'Standard Cord Freehang','Tab Freehang'=>'Tab Freehang'],
  'journey'=>function() use($mk,$colour,$ctrlLen){ return [
     ['key'=>'exact','name'=>'Exact or Recess','req'=>true,'gate'=>null,'choices'=>$colour(['Exact','Recess'])],
     ['key'=>'side','name'=>'Control Side','req'=>false,'gate'=>null,'choices'=>$colour(['Left','Right'])],
     ['key'=>'controlslen','name'=>'Controls Length','req'=>false,'gate'=>null,'choices'=>$colour($ctrlLen)],
     ['key'=>'brackets','name'=>'Headrail Brackets','req'=>false,'gate'=>null,'choices'=>$colour(['Face','Top fix','Both Top fix and Face','Top fix and Universal Extention'])],
     ['key'=>'hrcolour','name'=>'Headrail Colour','req'=>false,'gate'=>null,'choices'=>$colour(['White','Anthracite','Brown'])],
     ['key'=>'fitheight','name'=>'Fit Height','req'=>false,'gate'=>null,'lenLabel'=>'mm','choices'=>[$mk('Fit Height',null,0,0,true)]],
  ]; }],
 ['name'=>'Arena Pleated Perfect Fit','renameFromId'=>null,'systems'=>['Perfect Fit'=>'Perfect Fit','Perfect Fit Golden Oak'=>'Perfect Fit Golden Oak'],
  'journey'=>function() use($mk,$colour,$sysColour){ return [
     ['key'=>'profile','name'=>'Profile Type','req'=>true,'gate'=>null,'choices'=>$colour(['Gen 2 (New Frame)'])],
     ['key'=>'pfbrac','name'=>'PF Bracket Size','req'=>false,'gate'=>null,'choices'=>$colour(['18 mm','20 mm','22 mm','24 mm','26 mm','28 mm','30 mm','32 mm','38 mm'])],
     ['key'=>'pfframe','name'=>'PF Frame Colour','req'=>false,'gate'=>null,'choices'=>array_merge($sysColour('Perfect Fit',['White','Black','Brown','Anthracite'],true),$sysColour('Perfect Fit Golden Oak',['Golden Oak'],false))],
     ['key'=>'pfhandle','name'=>'PF Handle Location','req'=>false,'gate'=>null,'choices'=>$colour(['Bottom','Left','Right','None'])],
     ['key'=>'packing','name'=>'Packing Piece','req'=>false,'gate'=>null,'choices'=>$colour(['None','2 mm','6 mm','2mm and 6mm'])],
  ]; }],
];

echo ($apply?"APPLY":"DRY RUN (preview only)")." — Pleated Wave 1 (Freehang + Perfect Fit)\n".str_repeat('=',66)."\n\n";
if($apply)$pdo->beginTransaction();
try {
    $hasLen=false; try{$pdo->query('SELECT length_input_label FROM product_extras LIMIT 1');$hasLen=true;}catch(Throwable $e){}
    foreach($PRODUCTS as $cfg){
        $pname=$cfg['name']; echo "\n### {$pname} ###\n";
        // resolve product id (rename existing, or find/create by name)
        $productId=0;
        if($cfg['renameFromId']){
            $productId=(int)$cfg['renameFromId'];
            if($apply) $pdo->prepare('UPDATE products SET name=? WHERE id=? AND client_id=?')->execute([$pname,$productId,$clientId]);
            echo "  (rename #{$productId} -> {$pname})\n";
        } else {
            $f=$pdo->prepare('SELECT id FROM products WHERE client_id=? AND name=?');$f->execute([$clientId,$pname]);$productId=(int)($f->fetchColumn()?:0);
            if($apply && $productId===0){ $ss=$pdo->prepare('SELECT COALESCE(MAX(sort_order),-1)+1 FROM products WHERE client_id=?');$ss->execute([$clientId]);$ns=(int)$ss->fetchColumn();
                try{$pdo->prepare('INSERT INTO products (client_id,name,option_label,show_colour_field,sort_order,active) VALUES (?,?,?,1,?,1)')->execute([$clientId,$pname,'Fabric',$ns]);}
                catch(Throwable $e){$pdo->prepare('INSERT INTO products (client_id,name,option_label,sort_order,active) VALUES (?,?,?,?,1)')->execute([$clientId,$pname,'Fabric',$ns]);}
                $productId=(int)$pdo->lastInsertId(); }
            echo "  product #".($productId?:'(new)')."\n";
        }

        // systems: rebuild to exactly the configured set (drop others)
        $SYS=[]; $i=0;
        if($apply){
            // drop systems not in the configured set (and their price tables)
            $keep=array_keys($cfg['systems']);
            $cur=$pdo->prepare('SELECT id,name FROM product_systems WHERE client_id=? AND product_id=?'); $cur->execute([$clientId,$productId]);
            foreach($cur->fetchAll() as $r){ if(!in_array($r['name'],$keep,true)){ $sid=(int)$r['id'];
                $tt=$pdo->prepare('SELECT id FROM price_tables WHERE client_id=? AND product_id=? AND system_id=?'); $tt->execute([$clientId,$productId,$sid]); $tids=$tt->fetchAll(PDO::FETCH_COLUMN);
                if($tids){$in=implode(',',array_fill(0,count($tids),'?'));$pdo->prepare("DELETE FROM price_table_rows WHERE price_table_id IN ($in)")->execute($tids);$pdo->prepare("DELETE FROM price_tables WHERE id IN ($in)")->execute($tids);}
                $pdo->prepare('DELETE FROM product_systems WHERE id=?')->execute([$sid]); } }
        }
        foreach($cfg['systems'] as $local=>$csvSys){
            if($apply){ $q=$pdo->prepare('SELECT id FROM product_systems WHERE client_id=? AND product_id=? AND name=?');$q->execute([$clientId,$productId,$local]);$sid=(int)($q->fetchColumn()?:0);
                if($sid===0){$pdo->prepare('INSERT INTO product_systems (client_id,product_id,name,sort_order,active,is_default) VALUES (?,?,?,?,1,?)')->execute([$clientId,$productId,$local,$i,$i===0?1:0]);$sid=(int)$pdo->lastInsertId();}
                else{$pdo->prepare('UPDATE product_systems SET active=1,is_default=?,sort_order=? WHERE id=?')->execute([$i===0?1:0,$i,$sid]);}
                $SYS[$local]=$sid; } else $SYS[$local]=0;
            $i++; }
        echo "  systems: ".implode(', ',array_keys($cfg['systems']))."\n";

        // base tables + fabrics
        $cells=0;$tbls=0;
        if($apply){
            $tblIns=$pdo->prepare('INSERT INTO price_tables (client_id,product_id,system_id,band_code,name,active) VALUES (?,?,?,?,?,1)');
            $rowIns=$pdo->prepare('INSERT INTO price_table_rows (price_table_id,width_mm,drop_mm,price) VALUES (?,?,?,?)');
            foreach($cfg['systems'] as $local=>$csvSys){
                // clear existing tables for this system then rebuild
                $tt=$pdo->prepare('SELECT id FROM price_tables WHERE client_id=? AND product_id=? AND system_id=?'); $tt->execute([$clientId,$productId,$SYS[$local]]); $old=$tt->fetchAll(PDO::FETCH_COLUMN);
                if($old){$in=implode(',',array_fill(0,count($old),'?'));$pdo->prepare("DELETE FROM price_table_rows WHERE price_table_id IN ($in)")->execute($old);$pdo->prepare("DELETE FROM price_tables WHERE id IN ($in)")->execute($old);}
                foreach(['Elements','A','B','C','D','E'] as $band){
                    $rows=array_values(array_filter($PRICES,fn($r)=>$r['system']===$csvSys&&$r['band']===$band));
                    if(!$rows)continue; $tblIns->execute([$clientId,$productId,$SYS[$local],$band,"Arena Band $band"]);$tid=(int)$pdo->lastInsertId();
                    foreach($rows as $c){$rowIns->execute([$tid,(int)$c['width_mm'],(int)$c['drop_mm'],(float)$c['price']]);$cells++;} $tbls++;
                }
            }
        } else { foreach($cfg['systems'] as $local=>$csvSys) foreach(['Elements','A','B','C','D','E'] as $band){ $n=count(array_filter($PRICES,fn($r)=>$r['system']===$csvSys&&$r['band']===$band)); if($n){$cells+=$n;$tbls++;} } }
        echo "  base: {$tbls} tables, {$cells} cells\n";
        // fabrics
        $fc=0;
        if($apply){ $pdo->prepare('DELETE FROM product_options WHERE client_id=? AND product_id=?')->execute([$clientId,$productId]);
            $oi=$pdo->prepare('INSERT INTO product_options (client_id,product_id,system_id,band_code,supplier_name,name,colour,code,sort_order,active) VALUES (?,?,NULL,?,?,?,?,?,?,?)');
            $sort=0;$seen=[];
            foreach($FABRICS as $fr){ $band=trim((string)$fr['band']);$banded=isset($VALID[$band]);$bc=$banded?$band:'TBC';$name=(string)$fr['name'];$col=(string)($fr['colour']??'');
                $k=$bc.'|'.mb_strtolower($name).'|'.mb_strtolower($col); if(isset($seen[$k]))continue;$seen[$k]=1;
                $oi->execute([$clientId,$productId,$bc,'Arena',$name,$col,'',$sort++,$banded?1:0]);$fc++; } } else $fc=count($FABRICS);
        echo "  fabrics: {$fc}\n";

        // options
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
        $insExtra=$pdo->prepare('INSERT INTO product_extras (client_id,product_id,parent_choice_id,name,is_required,sort_order,active,parent_match_all) VALUES (?,?,NULL,?,?,?,1,0)');
        $insExtraLen=$hasLen?$pdo->prepare('INSERT INTO product_extras (client_id,product_id,parent_choice_id,name,is_required,sort_order,active,length_input_label) VALUES (?,?,NULL,?,?,?,1,?)'):null;
        $insChoice=$pdo->prepare('INSERT INTO product_extra_choices (product_extra_id,system_id,label,image_path,price_delta,price_percent,price_per_metre,is_default,sort_order,active) VALUES (?,?,?,NULL,?,?,0,?,?,1)');
        $so=0;$nx=0;$nc=0;
        foreach($J as $ex){
            echo "    - {$ex['name']} (".count($ex['choices'])." choices)\n";
            $extraId=null;
            if($apply){ if(!empty($ex['lenLabel'])&&$insExtraLen)$insExtraLen->execute([$clientId,$productId,$ex['name'],$ex['req']?1:0,$so++,$ex['lenLabel']]);
                else $insExtra->execute([$clientId,$productId,$ex['name'],$ex['req']?1:0,$so++]);
                $extraId=(int)$pdo->lastInsertId(); }
            $nx++;$cs=0;
            foreach($ex['choices'] as $c){ $sid=$c['sys']!==null?($SYS[$c['sys']]??null):null;
                if($apply)$insChoice->execute([$extraId,$sid,$c['label'],$c['delta'],$c['pct'],$c['default']?1:0,$cs++]); $nc++; }
        }
        echo "  options: {$nx} extras, {$nc} choices\n";
    }
    if($apply)$pdo->commit();
} catch(Throwable $e){ if($apply&&$pdo->inTransaction())$pdo->rollBack(); echo "\nFAILED: ".$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n"; exit(1); }
echo "\n".str_repeat('=',66)."\n".($apply?"BUILT":"WOULD BUILD")." Pleated Wave 1.\n";
if(!$apply) echo "\nPREVIEW ONLY — re-run with ?apply=1.\n";
