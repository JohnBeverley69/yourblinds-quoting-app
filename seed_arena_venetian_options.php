<?php
declare(strict_types=1);

/**
 * Venetian Phase 2 (exact VEN mirror) — options-only (bases already built).
 * Four products, each mirroring its live-form field set:
 *   - "Arena Metal Venetian" #5760: Exact/Recess, Control Side, Tilt Side,
 *     Wand Length, Controls Length, UEB Colour, Door Stops, Fit Height.
 *   - "Arena Faux Wood Venetian" #5761: Exact/Recess, Controls Length,
 *     Cord/Tape (Tape +20%), Bracket Type, Valance, Valance Returns, Fit Height.
 *   - "Arena Wood Venetian" #5762 & "Arena Sherwood" #5763: Exact/Recess,
 *     Control Type, Control Side, Controls Length, Cord/Tape (Standard tape
 *     +20% / Decorative +25%), Pull Type, Bracket Type, Valance, Valance
 *     Returns, Fit Height.
 *
 * Surcharges: tape = price_percent (p23/p37/p44). FLAGGED for UI (choice/
 * range-specific, left £0 here): metal cord pulls +£5; Expressions no-valance
 * -£1.50. DRY-RUN unless ?apply=1; idempotent. Super-admin only.
 *   Preview: /seed_arena_venetian_options.php   Apply: &apply=1
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors','1'); error_reporting(E_ALL); @set_time_limit(300);

$user=current_user(); $clientId=(int)$user['client_id'];
$pdo=db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$apply=(($_GET['apply']??'')==='1');

$mk=static fn($label,$pct=0.0,$def=false)=>['label'=>$label,'pct'=>$pct,'default'=>$def];
$colour=static function(array $L) use($mk){ $o=[]; foreach($L as $i=>$l)$o[]=$mk($l,0,$i===0); return $o; };
$ctrlLen=array_merge(['Default'], array_map(fn($n)=>$n.' cm', range(20,300,10)));
$woodJourney=function() use($mk,$colour,$ctrlLen){ return [
    ['key'=>'exact','name'=>'Exact or Recess','req'=>true,'choices'=>$colour(['Exact','Recess'])],
    ['key'=>'ctrltype','name'=>'Control Type','req'=>true,'choices'=>$colour(['Manual'])],
    ['key'=>'side','name'=>'Control Side','req'=>false,'choices'=>$colour(['Left','Right'])],
    ['key'=>'controlslen','name'=>'Controls Length','req'=>false,'choices'=>$colour($ctrlLen)],
    ['key'=>'cordtape','name'=>'Cord or Tape','req'=>true,'choices'=>[$mk('Cord',0,true),$mk('Standard Tape (+20%)',20.0),$mk('Decorative Tape (+25%)',25.0)]],
    ['key'=>'pull','name'=>'Pull Type','req'=>false,'choices'=>$colour(['A - Bell - Nickel','B - Bell - Matt Nickel','C - Bell - Matt Brass','D - Bell - Black','E - Barrel - Chrome','F - Barrel - Matt Nickel','G - Barrel - Brass','H - Barrel - Gunmetal','X - Colour-matched'])],
    ['key'=>'bracket','name'=>'Bracket Type','req'=>false,'choices'=>$colour(['Box','Top Fix'])],
    ['key'=>'valance','name'=>'Valance','req'=>false,'choices'=>$colour(['Custom','Supply Uncut','Valance Cut To Size'])],
    ['key'=>'valrtns','name'=>'Valance Returns','req'=>false,'choices'=>$colour(['No','Yes'])],
    ['key'=>'fitheight','name'=>'Fit Height','req'=>false,'lenLabel'=>'mm','choices'=>[$mk('Fit Height')]],
]; };

$PRODUCTS=[
 ['name'=>'Arena Metal Venetian','journey'=>[
    ['key'=>'exact','name'=>'Exact or Recess','req'=>true,'choices'=>$colour(['Exact','Recess'])],
    ['key'=>'side','name'=>'Control Side','req'=>false,'choices'=>$colour(['Left','Right'])],
    ['key'=>'tilt','name'=>'Tilt Side','req'=>false,'choices'=>$colour(['Left','Right'])],
    ['key'=>'wandlen','name'=>'Wand Length','req'=>false,'choices'=>$colour(array_merge(['Default'],array_map(fn($n)=>$n.' cm',range(20,300,10))))],
    ['key'=>'controlslen','name'=>'Controls Length','req'=>false,'choices'=>$colour(array_merge(['Default'],array_map(fn($n)=>$n.' cm',range(20,300,10))))],
    ['key'=>'ueb','name'=>'UEB Colour','req'=>false,'choices'=>$colour(['White','Brown','Silver','None'])],
    ['key'=>'doorstops','name'=>'Door Stops','req'=>false,'choices'=>$colour(['No','Yes'])],
    ['key'=>'fitheight','name'=>'Fit Height','req'=>false,'lenLabel'=>'mm','choices'=>[$mk('Fit Height')]],
 ]],
 ['name'=>'Arena Faux Wood Venetian','journey'=>[
    ['key'=>'exact','name'=>'Exact or Recess','req'=>true,'choices'=>$colour(['Exact','Recess'])],
    ['key'=>'controlslen','name'=>'Controls Length','req'=>false,'choices'=>$colour($ctrlLen)],
    ['key'=>'cordtape','name'=>'Cord or Tape','req'=>true,'choices'=>[$mk('Cord',0,true),$mk('Standard Tape (+20%)',20.0)]],
    ['key'=>'bracket','name'=>'Bracket Type','req'=>false,'choices'=>$colour(['Box','Top Fix'])],
    ['key'=>'valance','name'=>'Valance','req'=>false,'choices'=>$colour(['Custom','Supply Uncut','Valance Cut To Size'])],
    ['key'=>'valrtns','name'=>'Valance Returns','req'=>false,'choices'=>$colour(['No','Yes'])],
    ['key'=>'fitheight','name'=>'Fit Height','req'=>false,'lenLabel'=>'mm','choices'=>[$mk('Fit Height')]],
 ]],
 ['name'=>'Arena Wood Venetian','journey'=>$woodJourney()],
 ['name'=>'Arena Sherwood Venetian','journey'=>$woodJourney()],
];

echo ($apply?"APPLY":"DRY RUN (preview only)")." — Venetian Phase 2 options\n".str_repeat('=',60)."\n\n";
if($apply)$pdo->beginTransaction();
try {
    $hasLen=false; try{$pdo->query('SELECT length_input_label FROM product_extras LIMIT 1');$hasLen=true;}catch(Throwable $e){}
    foreach($PRODUCTS as $cfg){
        $pname=$cfg['name'];
        $f=$pdo->prepare('SELECT id FROM products WHERE client_id=? AND name=?');$f->execute([$clientId,$pname]);$productId=(int)($f->fetchColumn()?:0);
        echo "### {$pname} #".($productId?:'?')." ###\n";
        if(!$productId){ echo "  NOT FOUND — skipped\n\n"; continue; }
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
        $insChoice=$pdo->prepare('INSERT INTO product_extra_choices (product_extra_id,system_id,label,image_path,price_delta,price_percent,price_per_metre,is_default,sort_order,active) VALUES (?,NULL,?,NULL,0,?,0,?,?,1)');
        $so=0;$nx=0;$nc=0;
        foreach($cfg['journey'] as $ex){ $tag=''; foreach($ex['choices'] as $c)if(!empty($c['pct']))$tag=' (has +%)';
            echo "  - {$ex['name']} (".count($ex['choices'])." choices){$tag}\n"; $extraId=null;
            if($apply){ if(!empty($ex['lenLabel'])&&$insExtraLen)$insExtraLen->execute([$clientId,$productId,$ex['name'],$ex['req']?1:0,$so++,$ex['lenLabel']]);
                else $insExtra->execute([$clientId,$productId,$ex['name'],$ex['req']?1:0,$so++]); $extraId=(int)$pdo->lastInsertId(); }
            $nx++;$cs=0; foreach($ex['choices'] as $c){ if($apply)$insChoice->execute([$extraId,$c['label'],$c['pct'],$c['default']?1:0,$cs++]); $nc++; } }
        echo "  => {$nx} extras, {$nc} choices\n\n";
    }
    if($apply)$pdo->commit();
} catch(Throwable $e){ if($apply&&$pdo->inTransaction())$pdo->rollBack(); echo "\nFAILED: ".$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n"; exit(1); }
echo str_repeat('=',60)."\n".($apply?"BUILT":"WOULD BUILD")." venetian options (4 products).\n";
if(!$apply) echo "\nPREVIEW ONLY — re-run with ?apply=1.\n";
