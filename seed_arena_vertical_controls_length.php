<?php
declare(strict_types=1);

/**
 * Surgical add: "Controls Length" to Arena Vertical (#5754) — the one field
 * the exact-mirror review found missing vs the live VET configurator
 * (BLI_CONTROLS_LENGTH). Gated to Control Type = Cord (Wand uses Wand Length
 * instead). £0 spec choice. ADDITIVE + idempotent: skips if the option
 * already exists, so it does NOT disturb the validated per-type headrail/wand
 * colour + AND-gating tree. DRY-RUN unless ?apply=1. Super-admin only.
 *   Preview: /seed_arena_vertical_controls_length.php   Apply: &apply=1
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors','1'); error_reporting(E_ALL);

$user=current_user(); $clientId=(int)$user['client_id'];
$pdo=db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$apply=(($_GET['apply']??'')==='1');

$pr=$pdo->prepare('SELECT id FROM products WHERE client_id=? AND name=? LIMIT 1'); $pr->execute([$clientId,'Arena Vertical']);
$productId=(int)($pr->fetchColumn()?:0);
if(!$productId){ echo "Arena Vertical not found.\n"; exit(1); }

// already present?
$q=$pdo->prepare("SELECT id FROM product_extras WHERE client_id=? AND product_id=? AND name='Controls Length'");
$q->execute([$clientId,$productId]);
if($q->fetchColumn()){ echo "Controls Length already exists on #{$productId} — nothing to do.\n"; exit(0); }

// find Control Type = Cord choice (parent gate)
$ce=$pdo->prepare("SELECT id FROM product_extras WHERE client_id=? AND product_id=? AND name='Control Type' LIMIT 1");
$ce->execute([$clientId,$productId]); $ctrlExtraId=(int)($ce->fetchColumn()?:0);
if(!$ctrlExtraId){ echo "Control Type extra not found — aborting.\n"; exit(1); }
$cc=$pdo->prepare("SELECT id FROM product_extra_choices WHERE product_extra_id=? AND label='Cord' LIMIT 1");
$cc->execute([$ctrlExtraId]); $cordChoiceId=(int)($cc->fetchColumn()?:0);
if(!$cordChoiceId){ echo "Control Type 'Cord' choice not found — aborting.\n"; exit(1); }

$LENGTHS=array_merge(['Standard'], array_map(fn($n)=>$n.' cm', range(40,300,20)));
echo ($apply?"APPLY":"DRY RUN")." — add 'Controls Length' to #{$productId}, gated Control Type=Cord (choice #{$cordChoiceId})\n";
echo "  choices: ".implode(', ',$LENGTHS)."\n";

if($apply){
    $pdo->beginTransaction();
    try{
        $ss=$pdo->prepare('SELECT COALESCE(MAX(sort_order),-1)+1 FROM product_extras WHERE client_id=? AND product_id=?'); $ss->execute([$clientId,$productId]); $so=(int)$ss->fetchColumn();
        // gated, single-parent (OR), parent_match_all=0
        try { $pdo->prepare('INSERT INTO product_extras (client_id,product_id,parent_choice_id,name,is_required,sort_order,active,parent_match_all) VALUES (?,?,?,?,0,?,1,0)')->execute([$clientId,$productId,$cordChoiceId,'Controls Length',$so]); }
        catch(Throwable $e){ $pdo->prepare('INSERT INTO product_extras (client_id,product_id,parent_choice_id,name,is_required,sort_order,active) VALUES (?,?,?,?,0,?,1)')->execute([$clientId,$productId,$cordChoiceId,'Controls Length',$so]); }
        $extraId=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO product_extra_parent_choices (product_extra_id,product_extra_choice_id) VALUES (?,?)')->execute([$extraId,$cordChoiceId]);
        $ins=$pdo->prepare('INSERT INTO product_extra_choices (product_extra_id,system_id,label,image_path,price_delta,price_percent,price_per_metre,is_default,sort_order,active) VALUES (?,NULL,?,NULL,0,0,0,?,?,1)');
        $i=0; foreach($LENGTHS as $l){ $ins->execute([$extraId,$l,$i===0?1:0,$i]); $i++; }
        $pdo->commit();
        echo "\nDone — created Controls Length (#{$extraId}) with ".count($LENGTHS)." choices.\n";
    } catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); echo "\nFAILED: ".$e->getMessage()."\n"; exit(1); }
} else echo "\nPREVIEW ONLY — re-run with ?apply=1.\n";
