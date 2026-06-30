<?php
declare(strict_types=1);

/**
 * Seed the Arena ROLLER guided journey (Phase 2 options) onto product
 * "Arena Roller" (#5755), mirroring the Arena ROB configurator for the five
 * systems we stock: Louvolite, Senses, Grip Fit, Perfect Fit, Perfect Fit
 * Golden Oak.
 *
 * Pricing (Arena trade figures, stored as-is — the engine never marks up or
 * discounts extras, so these are the customer-facing surcharges; tweak in the
 * Options UI if a tenant wants margin):
 *   - WIDTH-based  -> extra_choice_price_rows (round-up by width). Source:
 *       seed_data/arena_roller_surcharge_width.csv (shapes, bottom bars,
 *       cassettes, Senses fascia wrap).
 *   - FLAT (£)     -> price_delta. PERCENT (%) -> price_percent. Source:
 *       seed_data/arena_roller_surcharge_flat.csv (soft rise, metal chain 5%,
 *       braid, eyelets, pulls, finials, endcap, + Somfy RTS/Zigbee motors,
 *       remotes, accessories).
 *   - Structural £0 selections (control, sides, colours, brackets, PF, exact/
 *       recess, reverse roll, fit height) are defined inline below.
 *
 * Gating uses product_extras.parent_choice_id (primary parent) +
 * product_extra_parent_choices (all parents) + parent_match_all (1 = AND
 * across distinct parent options). System-specific choices carry system_id.
 *
 * SAFE BY DEFAULT — DRY RUN unless ?apply=1. Idempotent: wipes only THIS
 * product's extras/choices/junctions/price-rows then rebuilds, so re-running
 * is safe (but will clobber manual Options-UI edits — re-seed then re-tweak).
 * Super-admin only.
 *
 *   Preview : /seed_arena_roller_options.php
 *   Apply   : /seed_arena_roller_options.php?apply=1
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

$PRODUCT_NAME = 'Arena Roller';
$apply = (($_GET['apply'] ?? '') === '1');

$p = $pdo->prepare('SELECT id, name FROM products WHERE client_id = ? AND name = ? LIMIT 1');
$p->execute([$clientId, $PRODUCT_NAME]);
$product = $p->fetch();
if (!$product) { echo "Product \"$PRODUCT_NAME\" not found for client $clientId.\n"; exit(1); }
$productId = (int) $product['id'];

// System name -> id
$sysRows = $pdo->prepare('SELECT id, name FROM product_systems WHERE client_id = ? AND product_id = ?');
$sysRows->execute([$clientId, $productId]);
$SYS = [];
foreach ($sysRows->fetchAll() as $r) $SYS[$r['name']] = (int) $r['id'];
$ALL_ROLLER = ['Louvolite', 'Senses', 'Grip Fit'];           // chain/manual systems
$PF_SYS     = ['Perfect Fit', 'Perfect Fit Golden Oak'];

// ---- Load surcharge CSVs -------------------------------------------------
$readCsv = static function (string $path): array {
    if (!is_file($path)) throw new RuntimeException("Missing: $path");
    $fh = fopen($path, 'r'); $h = fgetcsv($fh); $rows = [];
    while (($r = fgetcsv($fh)) !== false) { if ($r === [null] || $r === false) continue; $rows[] = array_combine($h, $r); }
    fclose($fh); return $rows;
};
$WIDTH = $readCsv(__DIR__ . '/seed_data/arena_roller_surcharge_width.csv'); // system,group,choice,width_mm,price
$FLAT  = $readCsv(__DIR__ . '/seed_data/arena_roller_surcharge_flat.csv');  // system,group,choice,price,kind

// width rows grouped by (system|group|choice)
$widthByChoice = [];
foreach ($WIDTH as $r) $widthByChoice[$r['system'].'|'.$r['group'].'|'.$r['choice']][] = [(int)$r['width_mm'], (float)$r['price']];
// flat rows grouped by (system|group)
$flatByGroup = [];
foreach ($FLAT as $r) $flatByGroup[$r['system'].'|'.$r['group']][] = $r;

// ---- Helper: build choice arrays ----------------------------------------
// A choice = ['label','sys'(name|null),'delta','pct','width'(rows|null),'default']
$mk = static fn($label,$sys=null,$delta=0.0,$pct=0.0,$width=null,$def=false)=>
    ['label'=>$label,'sys'=>$sys,'delta'=>$delta,'pct'=>$pct,'width'=>$width,'default'=>$def];

// Width-priced option group -> choices (one per system that has it) + a £0 default
$widthChoices = function(string $group, string $defLabel, array $systems) use ($widthByChoice,$mk) {
    $out = [$mk($defLabel,null,0,0,null,true)];
    foreach ($systems as $s) {
        foreach ($widthByChoice as $key=>$rows) {
            [$ksys,$kgrp,$kchoice] = explode('|',$key,3);
            if ($ksys===$s && $kgrp===$group) $out[] = $mk($kchoice,$s,0,0,$rows,false);
        }
    }
    return $out;
};
// Flat/percent option group -> choices (per system) + a £0 "None"
$flatChoices = function(string $group, string $noneLabel, array $systems) use ($flatByGroup,$mk) {
    $out = [$mk($noneLabel,null,0,0,null,true)];
    foreach ($systems as $s) {
        foreach (($flatByGroup[$s.'|'.$group] ?? []) as $r) {
            $isPct = ($r['kind']==='percent');
            $out[] = $mk($r['choice'], $s, $isPct?0:(float)$r['price'], $isPct?(float)$r['price']:0, null, false);
        }
    }
    return $out;
};
$colour = static function(array $labels) use ($mk){ $o=[]; foreach($labels as $i=>$l) $o[]=$mk($l,null,0,0,null,$i===0); return $o; };

// ---- The journey (ordered). Each extra: key,name,required,gate,choices -----
// gate = null | [[parentKey, choiceLabel], ...]  (AND across entries)
$J = [];
$J[] = ['key'=>'exact','name'=>'Exact or Recess','req'=>true,'gate'=>null,'choices'=>$colour(['Exact','Recess'])];
$J[] = ['key'=>'ctrl','name'=>'Control Type','req'=>true,'gate'=>null,'choices'=>$colour(['Manual','Motorised'])];

// -- Manual branch --
$J[] = ['key'=>'side','name'=>'Control Side','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>$colour(['Left','Right'])];
$J[] = ['key'=>'chaintype','name'=>'Chain Type','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>$colour(['Breakaway','Tensioned'])];
$J[] = ['key'=>'chainmat','name'=>'Chain Material','req'=>false,'gate'=>[['ctrl','Manual']],'choices'=>[
        $mk('Plastic',null,0,0,null,true), $mk('Metal (+5%)',null,0,5.0,null,false)]];
$J[] = ['key'=>'chaincol','name'=>'Chain Colour','req'=>false,'gate'=>[['ctrl','Manual']],
        'choices'=>$colour(['White Plastic','Black Plastic','Anthracite Plastic','Chrome','Antique','Black Metal','White','Anthracite'])];
$J[] = ['key'=>'controlslen','name'=>'Controls Length','req'=>false,'gate'=>[['ctrl','Manual']],
        'choices'=>$colour(['Standard','50 cm','75 cm','100 cm','125 cm','150 cm','175 cm','200 cm'])];

// -- Cassette (system-specific width tables) --
$J[] = ['key'=>'cassette','name'=>'Cassette Type','req'=>false,'gate'=>null,
        'choices'=>$widthChoices('Cassette','Open / No Cassette',['Louvolite','Senses'])];
$J[] = ['key'=>'cassettecol','name'=>'Cassette Colour','req'=>false,'gate'=>[['cassette','__priced__']],
        'choices'=>$colour(['White','Anthracite','Black','Silver','Cream'])];
$J[] = ['key'=>'cassetteend','name'=>'Cassette Endcap Colour','req'=>false,'gate'=>[['cassette','__priced__']],
        'choices'=>$colour(['White','Chrome','Black Chrome','Anthracite','Cream'])];

// -- Bottom bar (system-specific width tables) --
$J[] = ['key'=>'bottombar','name'=>'Bottom Bar Type','req'=>false,'gate'=>null,
        'choices'=>$widthChoices('Bottom Bar','Standard',$ALL_ROLLER)];
$J[] = ['key'=>'bottombarend','name'=>'Bottom Bar Endcap Colour','req'=>false,'gate'=>null,
        'choices'=>$colour(['White','Chrome','Black Chrome','Anthracite','Cream'])];

// -- Brackets --
$J[] = ['key'=>'brackets','name'=>'Brackets (Fixing)','req'=>false,'gate'=>null,'choices'=>$colour(['Face Fix','Top Fix'])];
$J[] = ['key'=>'bracketcol','name'=>'Brackets Colour','req'=>false,'gate'=>null,'choices'=>$colour(['White','Black','Anthracite','Silver'])];
$J[] = ['key'=>'bracketcov','name'=>'Bracket Covers','req'=>false,'gate'=>null,'choices'=>$colour(['No','Yes'])];

// -- Senses fascia wrap (width table) --
$J[] = ['key'=>'fascia','name'=>'Full Fascia Fabric Wrap','req'=>false,'gate'=>null,
        'choices'=>$widthChoices('Fascia','No',['Senses'])];

// -- Shapes (system-specific width tables) --
$J[] = ['key'=>'shape','name'=>'Shape','req'=>false,'gate'=>null,
        'choices'=>$widthChoices('Shape','Square (Shape 1)',$ALL_ROLLER)];

// -- Decorative trim (flat, system-specific) --
$J[] = ['key'=>'softrise','name'=>'Soft Rise','req'=>false,'gate'=>null,'choices'=>$flatChoices('Roller Option','No',['Louvolite','Senses'])];
$J[] = ['key'=>'braid','name'=>'Braid','req'=>false,'gate'=>null,'choices'=>$flatChoices('Braid','None',$ALL_ROLLER)];
$J[] = ['key'=>'eyelets','name'=>'Eyelets','req'=>false,'gate'=>null,'choices'=>$flatChoices('Eyelets','None',$ALL_ROLLER)];
$J[] = ['key'=>'pull','name'=>'Pull','req'=>false,'gate'=>null,'choices'=>$flatChoices('Pull','None',$ALL_ROLLER)];
$J[] = ['key'=>'finial','name'=>'Finial','req'=>false,'gate'=>null,'choices'=>$flatChoices('Finial','None',$ALL_ROLLER)];

// -- Motorised branch --
$J[] = ['key'=>'motorsys','name'=>'Motor System','req'=>false,'gate'=>[['ctrl','Motorised']],'choices'=>$colour(['Somfy RTS','Somfy Zigbee'])];
$J[] = ['key'=>'motorrts','name'=>'Motor (RTS)','req'=>false,'gate'=>[['ctrl','Motorised'],['motorsys','Somfy RTS']],'choices'=>
        array_merge([$mk('— Select —',null,0,0,null,true)], array_map(fn($r)=>$mk($r['choice'],null,(float)$r['price']), $flatByGroup['RTS|Motor']??[]))];
$J[] = ['key'=>'remoterts','name'=>'Remote (RTS)','req'=>false,'gate'=>[['ctrl','Motorised'],['motorsys','Somfy RTS']],'choices'=>
        array_merge([$mk('None',null,0,0,null,true)], array_map(fn($r)=>$mk($r['choice'],null,(float)$r['price']), $flatByGroup['RTS|Remote']??[]))];
$J[] = ['key'=>'accrts','name'=>'Motor Accessories (RTS)','req'=>false,'gate'=>[['ctrl','Motorised'],['motorsys','Somfy RTS']],'choices'=>
        array_merge([$mk('None',null,0,0,null,true)], array_map(fn($r)=>$mk($r['choice'],null,(float)$r['price']), $flatByGroup['RTS|Accessory']??[]))];
$J[] = ['key'=>'motorzig','name'=>'Motor (Zigbee)','req'=>false,'gate'=>[['ctrl','Motorised'],['motorsys','Somfy Zigbee']],'choices'=>
        array_merge([$mk('— Select —',null,0,0,null,true)], array_map(fn($r)=>$mk($r['choice'],null,(float)$r['price']), $flatByGroup['Zigbee|Motor']??[]))];
$J[] = ['key'=>'remotezig','name'=>'Remote (Zigbee)','req'=>false,'gate'=>[['ctrl','Motorised'],['motorsys','Somfy Zigbee']],'choices'=>
        array_merge([$mk('None',null,0,0,null,true)], array_map(fn($r)=>$mk($r['choice'],null,(float)$r['price']), $flatByGroup['Zigbee|Remote']??[]))];
$J[] = ['key'=>'acczig','name'=>'Motor Accessories (Zigbee)','req'=>false,'gate'=>[['ctrl','Motorised'],['motorsys','Somfy Zigbee']],'choices'=>
        array_merge([$mk('None',null,0,0,null,true)], array_map(fn($r)=>$mk($r['choice'],null,(float)$r['price']), $flatByGroup['Zigbee|Accessory']??[]))];

// -- Perfect Fit branch (system-scoped choices) --
$pfChoice = static function(array $labels) use ($mk,$PF_SYS){ $o=[]; $first=true; foreach($PF_SYS as $s){ foreach($labels as $l){ $o[]=$mk($l,$s,0,0,null,$first); $first=false; } } return $o; };
$J[] = ['key'=>'pfbracket','name'=>'PF Bracket Size','req'=>false,'gate'=>null,'choices'=>$pfChoice(['18 mm','20 mm','22 mm','24 mm','26 mm','28 mm','30 mm','32 mm'])];
$J[] = ['key'=>'pfhandle','name'=>'PF Handle','req'=>false,'gate'=>null,'choices'=>$pfChoice(['Bottom','None'])];
$J[] = ['key'=>'packing','name'=>'Packing Piece','req'=>false,'gate'=>null,'choices'=>$pfChoice(['None','2 mm','6 mm','2mm and 6mm'])];

// -- Universal tail --
$J[] = ['key'=>'reverse','name'=>'Reverse Roll','req'=>false,'gate'=>null,'choices'=>$colour(['No','Yes'])];
$J[] = ['key'=>'fitheight','name'=>'Fit Height','req'=>false,'gate'=>null,'lenLabel'=>'mm','choices'=>[$mk('Fit Height',null,0,0,null,true)]];

// =========================================================================
echo ($apply ? "APPLY" : "DRY RUN (preview only)") . " — Arena Roller options onto #{$productId}\n";
echo "Systems: " . implode(', ', array_keys($SYS)) . "\n" . str_repeat('=', 66) . "\n\n";

$nExtra=0; $nChoice=0; $nWidth=0; $nGate=0;
if ($apply) $pdo->beginTransaction();
try {
    if ($apply) {
        // Wipe this product's option tree (rebuild fresh).
        $ids = $pdo->prepare('SELECT id FROM product_extras WHERE client_id=? AND product_id=?');
        $ids->execute([$clientId,$productId]);
        $exIds = $ids->fetchAll(PDO::FETCH_COLUMN);
        if ($exIds) {
            $in = implode(',', array_fill(0, count($exIds), '?'));
            $chIds = $pdo->prepare("SELECT id FROM product_extra_choices WHERE product_extra_id IN ($in)");
            $chIds->execute($exIds);
            $cIds = $chIds->fetchAll(PDO::FETCH_COLUMN);
            if ($cIds) {
                $cin = implode(',', array_fill(0, count($cIds), '?'));
                $pdo->prepare("DELETE FROM extra_choice_price_rows WHERE product_extra_choice_id IN ($cin)")->execute($cIds);
            }
            $pdo->prepare("DELETE FROM product_extra_parent_choices WHERE product_extra_id IN ($in)")->execute($exIds);
            $pdo->prepare("DELETE FROM product_extra_choices WHERE product_extra_id IN ($in)")->execute($exIds);
            $pdo->prepare("DELETE FROM product_extras WHERE id IN ($in)")->execute($exIds);
        }
    }

    $insExtra = $pdo->prepare('INSERT INTO product_extras (client_id,product_id,parent_choice_id,name,is_required,sort_order,active,parent_match_all) VALUES (?,?,?,?,?,?,1,?)');
    $insExtraNoMatch = $pdo->prepare('INSERT INTO product_extras (client_id,product_id,parent_choice_id,name,is_required,sort_order,active) VALUES (?,?,?,?,?,?,1)');
    $insChoice = $pdo->prepare('INSERT INTO product_extra_choices (product_extra_id,system_id,label,image_path,price_delta,price_percent,price_per_metre,is_default,sort_order,active) VALUES (?,?,?,NULL,?,?,0,?,?,1)');
    $insLenChoice = null; // set lazily if length-input column exists
    $insJunction = $pdo->prepare('INSERT INTO product_extra_parent_choices (product_extra_id,product_extra_choice_id) VALUES (?,?)');
    $insWidth = $pdo->prepare('INSERT INTO extra_choice_price_rows (product_extra_choice_id,width_mm,price) VALUES (?,?,?)');

    $choiceIdByKeyLabel = [];   // "key|label" -> [choice ids]  (priced cassette/bottombar = many)
    $pricedByKey = [];          // key -> [choice ids of NON-default choices]
    $sort=0;

    // Does product_extras have a length_input_label column? (for Fit Height mm input)
    $hasLenCol = false;
    try { $pdo->query('SELECT length_input_label FROM product_extras LIMIT 1'); $hasLenCol = true; } catch (Throwable $e) { $hasLenCol = false; }
    $insExtraLen = $hasLenCol ? $pdo->prepare('INSERT INTO product_extras (client_id,product_id,parent_choice_id,name,is_required,sort_order,active,length_input_label) VALUES (?,?,?,?,?,?,1,?)') : null;

    foreach ($J as $ex) {
        // Resolve gating to a primary parent choice id + all parent choice ids.
        $parentIds = [];
        if (!empty($ex['gate'])) {
            foreach ($ex['gate'] as [$pk, $plabel]) {
                if ($plabel === '__priced__') {
                    // every priced (non-default) choice of the parent
                    foreach (($pricedByKey[$pk] ?? []) as $cid) $parentIds[] = $cid;
                } else {
                    foreach (($choiceIdByKeyLabel[$pk.'|'.$plabel] ?? []) as $cid) $parentIds[] = $cid;
                }
            }
        }
        $parentIds = array_values(array_unique($parentIds));
        $andGate = (!empty($ex['gate']) && count($ex['gate']) > 1) ? 1 : 0;
        $primary = $parentIds[0] ?? null;

        $gateDesc = $ex['gate'] ? (' [gate: '.implode($andGate?' AND ':' OR ', array_map(fn($g)=>$g[0].'='.$g[1],$ex['gate'])).']') : '';
        echo sprintf("  %-26s %s%s\n", $ex['name'], $ex['req']?'(req)':'', $gateDesc);

        $extraId = null;
        if ($apply) {
            if (!empty($ex['lenLabel']) && $insExtraLen) {
                $insExtraLen->execute([$clientId,$productId,$primary,$ex['name'],$ex['req']?1:0,$sort++,$ex['lenLabel']]);
            } elseif ($andGate) {
                $insExtra->execute([$clientId,$productId,$primary,$ex['name'],$ex['req']?1:0,$sort++,1]);
            } else {
                $insExtra->execute([$clientId,$productId,$primary,$ex['name'],$ex['req']?1:0,$sort++,0]);
            }
            $extraId = (int)$pdo->lastInsertId();
            foreach ($parentIds as $pid) { $insJunction->execute([$extraId,$pid]); $nGate++; }
        }
        $nExtra++;

        $cSort=0;
        foreach ($ex['choices'] as $c) {
            $sysId = $c['sys']!==null ? ($SYS[$c['sys']] ?? null) : null;
            $tag = ($c['sys']?" [{$c['sys']}]":'') . ($c['delta']?" +£{$c['delta']}":'') . ($c['pct']?" +{$c['pct']}%":'') . ($c['width']?' +width-table':'');
            echo "        - {$c['label']}{$tag}\n";
            if ($apply) {
                $insChoice->execute([$extraId,$sysId,$c['label'],$c['delta'],$c['pct'],$c['default']?1:0,$cSort++]);
                $cid = (int)$pdo->lastInsertId();
                $choiceIdByKeyLabel[$ex['key'].'|'.$c['label']][] = $cid;
                if (!$c['default']) $pricedByKey[$ex['key']][] = $cid;
                if (!empty($c['width'])) { foreach ($c['width'] as [$w,$pr]) { $insWidth->execute([$cid,$w,$pr]); $nWidth++; } }
            } else {
                $choiceIdByKeyLabel[$ex['key'].'|'.$c['label']][] = 0; // placeholder
                if (!$c['default']) $pricedByKey[$ex['key']][] = 0;
            }
            $nChoice++;
        }
    }

    if ($apply) $pdo->commit();
} catch (Throwable $e) {
    if ($apply && $pdo->inTransaction()) $pdo->rollBack();
    echo "\nFAILED: ".$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n(no changes saved)\n"; exit(1);
}

echo "\n".str_repeat('-',66)."\n";
echo ($apply?"Created":"Would create").": {$nExtra} options, {$nChoice} choices, {$nWidth} width-price rows, {$nGate} gating links.\n";
if (!$apply) echo "\nPREVIEW ONLY — re-run with ?apply=1 to build.\n";
else { echo "\nDone. Open the Roller Options page to review.\n"
     . "NOTE: 'Fit Height' is a plain mm input only if the length-input column\n"
     . "exists; otherwise it shows as a single carrier choice — check + adjust.\n"; }
