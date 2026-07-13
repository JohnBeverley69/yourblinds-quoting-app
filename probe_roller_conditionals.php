<?php
declare(strict_types=1);

/**
 * Diagnostic: compare the conditional-option wiring (parent_choice_id +
 * product_extra_parent_choices junction) for "End Cap Colour" on the master
 * roller vs the ABC copy — to see why "End Cap Colour" appears under Bottom Bar
 * "Senses Metal Cloth Covered" on master but not on ABC. Read-only.
 *
 * /probe_roller_conditionals.php  (super-admin)
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

$masterRoller = (int) $pdo->query("SELECT id FROM products WHERE client_id={$MASTER} AND name='Bev Roller Blinds' LIMIT 1")->fetchColumn();
$abc = $pdo->query("SELECT id, client_id FROM products WHERE source_client_id={$MASTER} AND source_product_id={$masterRoller} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$hasJunction = false;
try { $pdo->query('SELECT 1 FROM product_extra_parent_choices LIMIT 0'); $hasJunction = true; } catch (Throwable $e) {}

$dump = function (int $clientId, int $productId, string $tag) use ($pdo, $hasJunction) {
    echo "=== {$tag}  (client {$clientId}, product {$productId}) ===\n";
    // Bottom Bar "Senses Metal Cloth Covered" choice
    $bb = $pdo->prepare("SELECT c.id, c.label FROM product_extra_choices c
                          JOIN product_extras e ON e.id = c.product_extra_id
                         WHERE e.client_id=? AND e.product_id=? AND e.name LIKE 'Bottom Bar%' AND c.label LIKE 'Senses Metal%'");
    $bb->execute([$clientId, $productId]);
    $bbRows = $bb->fetchAll(PDO::FETCH_ASSOC);
    echo "  Bottom Bar 'Senses Metal Cloth Covered' choice(s): " . (count($bbRows) ? implode(', ', array_map(fn($r)=>"#{$r['id']} \"{$r['label']}\"", $bbRows)) : 'NONE') . "\n";
    $bbIds = array_map(fn($r)=>(int)$r['id'], $bbRows);

    // End Cap Colour extras + their parent wiring
    $ec = $pdo->prepare("SELECT id, name, parent_choice_id FROM product_extras
                          WHERE client_id=? AND product_id=? AND name LIKE 'End Cap Colour%' ORDER BY id");
    $ec->execute([$clientId, $productId]);
    foreach ($ec->fetchAll(PDO::FETCH_ASSOC) as $e) {
        $pcid = $e['parent_choice_id'];
        $pcLabel = '';
        if ($pcid !== null) {
            $q = $pdo->prepare("SELECT c.label, pe.name grp FROM product_extra_choices c JOIN product_extras pe ON pe.id=c.product_extra_id WHERE c.id=?");
            $q->execute([(int)$pcid]); $r = $q->fetch(PDO::FETCH_ASSOC);
            $pcLabel = $r ? "\"{$r['label']}\" (under {$r['grp']})" : "#{$pcid} (missing!)";
        }
        echo "  End Cap Colour #{$e['id']}: parent_choice_id=" . ($pcid ?? 'NULL') . ($pcLabel ? " → {$pcLabel}" : '') . "\n";
        if ($hasJunction) {
            $j = $pdo->prepare("SELECT pep.product_extra_choice_id cid, c.label, pe.name grp
                                  FROM product_extra_parent_choices pep
                                  JOIN product_extra_choices c ON c.id=pep.product_extra_choice_id
                                  JOIN product_extras pe ON pe.id=c.product_extra_id
                                 WHERE pep.product_extra_id=?");
            $j->execute([(int)$e['id']]);
            $jr = $j->fetchAll(PDO::FETCH_ASSOC);
            echo "      junction parents: " . (count($jr) ? implode(', ', array_map(fn($x)=>"#{$x['cid']} \"{$x['label']}\" (under {$x['grp']})", $jr)) : 'NONE') . "\n";
            $linkedToBB = array_filter($jr, fn($x)=>in_array((int)$x['cid'],$bbIds,true));
            if ($bbIds) echo "      linked to Senses Metal Cloth Covered? " . (count($linkedToBB)||in_array((int)$pcid,$bbIds,true) ? 'YES' : 'NO') . "\n";
        }
    }
    echo "\n";
};

echo "junction table present: " . ($hasJunction ? 'yes' : 'no') . "\n\n";
$dump($MASTER, $masterRoller, 'MASTER');
if ($abc) $dump((int)$abc['client_id'], (int)$abc['id'], 'ABC'); else echo "No ABC roller product found.\n";
