<?php
declare(strict_types=1);

/**
 * Seed the Arena ROMAN guided journey (Phase 2 options) onto "Arena Roman"
 * (#5756), one "Standard" system, mirroring the ROM configurator.
 *
 * Pricing (Arena figures, stored as-is — engine never marks up extras):
 *   - Lining Type = PERCENT surcharge (price list p183): Blackout +10%,
 *     Interlined +20% (Lined = £0). price_percent.
 *   - Control/chain options = £0 ("Zero charge for breakaway chain", p190).
 *   - Motorised = Somfy RTS only (Situo/Telis); motor + remote + accessory
 *     surcharges reused from seed_data/arena_roller_surcharge_flat.csv (RTS
 *     rows — the motorisation table p262 is shared across products).
 *   - No width-based surcharges for Roman (cushion covers p184 are a separate
 *     orderable, not a blind option).
 *
 * Exact-or-Recess + Installation Height (Fit Height) BOTH present in ROM.
 * Gating: parent_choice_id (primary) + product_extra_parent_choices (all) +
 * parent_match_all (1=AND). DRY-RUN unless ?apply=1; idempotent rebuild of
 * THIS product's option tree. Super-admin only.
 *
 *   Preview : /seed_arena_roman_options.php
 *   Apply   : /seed_arena_roman_options.php?apply=1
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1'); error_reporting(E_ALL); @set_time_limit(300);

$user     = current_user();
$clientId = (int) $user['client_id'];
$pdo      = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$PRODUCT_NAME = 'Arena Roman';
$apply = (($_GET['apply'] ?? '') === '1');

$p = $pdo->prepare('SELECT id, name FROM products WHERE client_id = ? AND name = ? LIMIT 1');
$p->execute([$clientId, $PRODUCT_NAME]);
$product = $p->fetch();
if (!$product) { echo "Product \"$PRODUCT_NAME\" not found for client $clientId.\n"; exit(1); }
$productId = (int) $product['id'];

// RTS motorisation rows from the shared roller flat CSV.
$flatByGroup = [];
$csv = __DIR__ . '/seed_data/arena_roller_surcharge_flat.csv';
if (is_file($csv)) {
    $fh = fopen($csv, 'r'); $h = fgetcsv($fh);
    while (($r = fgetcsv($fh)) !== false) { if ($r === [null] || $r === false) continue; $row = array_combine($h, $r);
        if ($row['system'] === 'RTS') $flatByGroup[$row['group']][] = $row; }
    fclose($fh);
}

$mk = static fn($label,$delta=0.0,$pct=0.0,$def=false,$lenLabel=null)=>
    ['label'=>$label,'delta'=>$delta,'pct'=>$pct,'default'=>$def];
$colour = static function(array $labels) use ($mk){ $o=[]; foreach($labels as $i=>$l) $o[]=$mk($l,0,0,$i===0); return $o; };
$motorChoices = function(string $group, string $none) use ($flatByGroup,$mk){
    $o=[$mk($none,0,0,true)];
    foreach (($flatByGroup[$group] ?? []) as $r) $o[] = $mk($r['choice'], (float)$r['price']);
    return $o;
};

// ---- Journey (ordered). gate = null | [[parentKey, choiceLabel], ...] -----
$J = [];
$J[] = ['key'=>'exact','name'=>'Exact or Recess','req'=>true,'gate'=>null,'choices'=>$colour(['Exact','Recess'])];
$J[] = ['key'=>'lining','name'=>'Lining Type','req'=>true,'gate'=>null,'choices'=>[
        $mk('Lined',0,0,true), $mk('Blackout (+10%)',0,10.0), $mk('Interlined (+20%)',0,20.0)]];
$J[] = ['key'=>'construction','name'=>'Construction','req'=>false,'gate'=>null,'choices'=>$colour(['Standard','Deluxe'])];
$J[] = ['key'=>'liningcol','name'=>'Lining Colour','req'=>false,'gate'=>null,'choices'=>$colour(['Ivory','White'])];
$J[] = ['key'=>'centreseam','name'=>'Centre Seam','req'=>false,'gate'=>null,'choices'=>$colour(['No','Yes'])];
$J[] = ['key'=>'ctrl','name'=>'Control Type','req'=>true,'gate'=>null,'choices'=>$colour(['Manual','Motorised'])];
// Manual branch
$J[] = ['key'=>'side','name'=>'Raise/Lower Control Side','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>$colour(['Left','Right'])];
$J[] = ['key'=>'chaintype','name'=>'Chain Type','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>$colour(['Breakaway','Tensioned'])];
$J[] = ['key'=>'chaincol','name'=>'Chain Colour','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>$colour(['White','Black','Chrome','Antique'])];
$J[] = ['key'=>'loop','name'=>'Chain Loop Length','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>$colour(['Standard','30 cm','45 cm','60 cm','90 cm','120 cm'])];
$J[] = ['key'=>'cleat','name'=>'Cleat Type','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>$colour(['Chrome','Black','Brass'])];
// Hardware
$J[] = ['key'=>'extbrackets','name'=>'Universal Extension Brackets','req'=>false,'gate'=>null,'choices'=>$colour(['No','Yes'])];
// Motorised branch (Somfy RTS)
$J[] = ['key'=>'motorrts','name'=>'Motor (Somfy RTS)','req'=>false,'gate'=>[['ctrl','Motorised']],'choices'=>$motorChoices('Motor','— Select —')];
$J[] = ['key'=>'remoterts','name'=>'Remote (Somfy RTS)','req'=>false,'gate'=>[['ctrl','Motorised']],'choices'=>$motorChoices('Remote','None')];
$J[] = ['key'=>'accrts','name'=>'Motor Accessories (Somfy RTS)','req'=>false,'gate'=>[['ctrl','Motorised']],'choices'=>$motorChoices('Accessory','None')];
// Tail
$J[] = ['key'=>'fitheight','name'=>'Fit Height','req'=>false,'gate'=>null,'lenLabel'=>'mm','choices'=>[$mk('Fit Height',0,0,true)]];

// =========================================================================
echo ($apply ? "APPLY" : "DRY RUN (preview only)") . " — Arena Roman options onto #{$productId}\n" . str_repeat('=', 60) . "\n\n";

$nExtra=0; $nChoice=0; $nGate=0;
if ($apply) $pdo->beginTransaction();
try {
    if ($apply) {
        $ids = $pdo->prepare('SELECT id FROM product_extras WHERE client_id=? AND product_id=?');
        $ids->execute([$clientId,$productId]);
        $exIds = $ids->fetchAll(PDO::FETCH_COLUMN);
        if ($exIds) {
            $in = implode(',', array_fill(0, count($exIds), '?'));
            $chIds = $pdo->prepare("SELECT id FROM product_extra_choices WHERE product_extra_id IN ($in)");
            $chIds->execute($exIds); $cIds = $chIds->fetchAll(PDO::FETCH_COLUMN);
            if ($cIds) { $cin = implode(',', array_fill(0, count($cIds), '?'));
                $pdo->prepare("DELETE FROM extra_choice_price_rows WHERE product_extra_choice_id IN ($cin)")->execute($cIds); }
            $pdo->prepare("DELETE FROM product_extra_parent_choices WHERE product_extra_id IN ($in)")->execute($exIds);
            $pdo->prepare("DELETE FROM product_extra_choices WHERE product_extra_id IN ($in)")->execute($exIds);
            $pdo->prepare("DELETE FROM product_extras WHERE id IN ($in)")->execute($exIds);
        }
    }

    $hasLenCol = false;
    try { $pdo->query('SELECT length_input_label FROM product_extras LIMIT 1'); $hasLenCol = true; } catch (Throwable $e) {}
    $insExtra    = $pdo->prepare('INSERT INTO product_extras (client_id,product_id,parent_choice_id,name,is_required,sort_order,active,parent_match_all) VALUES (?,?,?,?,?,?,1,?)');
    $insExtraLen = $hasLenCol ? $pdo->prepare('INSERT INTO product_extras (client_id,product_id,parent_choice_id,name,is_required,sort_order,active,length_input_label) VALUES (?,?,?,?,?,?,1,?)') : null;
    $insChoice   = $pdo->prepare('INSERT INTO product_extra_choices (product_extra_id,system_id,label,image_path,price_delta,price_percent,price_per_metre,is_default,sort_order,active) VALUES (?,NULL,?,NULL,?,?,0,?,?,1)');
    $insJunction = $pdo->prepare('INSERT INTO product_extra_parent_choices (product_extra_id,product_extra_choice_id) VALUES (?,?)');

    $choiceIdByKeyLabel = []; $sort=0;
    foreach ($J as $ex) {
        $parentIds = [];
        if (!empty($ex['gate'])) foreach ($ex['gate'] as [$pk,$pl]) foreach (($choiceIdByKeyLabel[$pk.'|'.$pl] ?? []) as $cid) $parentIds[]=$cid;
        $parentIds = array_values(array_unique($parentIds));
        $andGate = (!empty($ex['gate']) && count($ex['gate'])>1) ? 1 : 0;
        $primary = $parentIds[0] ?? null;
        $gateDesc = $ex['gate'] ? (' [gate: '.implode($andGate?' AND ':' OR ', array_map(fn($g)=>$g[0].'='.$g[1],$ex['gate'])).']') : '';
        echo sprintf("  %-30s %s%s\n", $ex['name'], $ex['req']?'(req)':'', $gateDesc);

        $extraId=null;
        if ($apply) {
            if (!empty($ex['lenLabel']) && $insExtraLen) $insExtraLen->execute([$clientId,$productId,$primary,$ex['name'],$ex['req']?1:0,$sort++,$ex['lenLabel']]);
            else $insExtra->execute([$clientId,$productId,$primary,$ex['name'],$ex['req']?1:0,$sort++,$andGate]);
            $extraId=(int)$pdo->lastInsertId();
            foreach ($parentIds as $pid){ $insJunction->execute([$extraId,$pid]); $nGate++; }
        }
        $nExtra++;
        $cSort=0;
        foreach ($ex['choices'] as $c) {
            $tag=($c['delta']?" +£{$c['delta']}":'').($c['pct']?" +{$c['pct']}%":'');
            echo "        - {$c['label']}{$tag}\n";
            if ($apply) { $insChoice->execute([$extraId,$c['label'],$c['delta'],$c['pct'],$c['default']?1:0,$cSort++]);
                $choiceIdByKeyLabel[$ex['key'].'|'.$c['label']][]=(int)$pdo->lastInsertId(); }
            else $choiceIdByKeyLabel[$ex['key'].'|'.$c['label']][]=0;
            $nChoice++;
        }
    }
    if ($apply) $pdo->commit();
} catch (Throwable $e) {
    if ($apply && $pdo->inTransaction()) $pdo->rollBack();
    echo "\nFAILED: ".$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n(no changes saved)\n"; exit(1);
}

echo "\n".str_repeat('-',60)."\n";
echo ($apply?"Created":"Would create").": {$nExtra} options, {$nChoice} choices, {$nGate} gating links.\n";
if (!$apply) echo "\nPREVIEW ONLY — re-run with ?apply=1 to build.\n";
else echo "\nDone. Open the Roman Options page to review.\n";
