<?php
declare(strict_types=1);

/**
 * Seed: H_Cut (headrail cut) build variable for Bev Vertical Blinds.
 *
 * H_Cut = Width - a headrail cut deduction that depends on system, whether the
 * blind is corded or wand, and (for wand) whether it stacks or draws from the
 * centre, and on recess vs exact. Values are Beverley's "Vertical Cutt
 * allowances" sheet:
 *
 *              Cord (any draw)   Wand stack*    Wand centre**
 *   SlimLine   30 / 20           12 / 2         20 / 10        (Recess / Exact)
 *   Vogue      33 / 23           22 / 12        32 / 22
 *   Nova       25 / 15           15 / 5         15 / 5   (Nova has no centre split)
 *   No Thrills  (wand only)      12 / 2         20 / 10  (mirrors SlimLine wand)
 *
 *   *  stack  = Left Stack, Right Stack, Split Draw 2 Wands
 *   ** centre = Center Left, Center Right
 *
 * Built as a decision table (System · Control · Wand Options · Exact or Recess).
 * Cord is draw-independent so no Draw column is needed; wand centre rows sit
 * above the wand stack catch-all (first-match-wins). No Thrills mirrors SlimLine
 * wand (it isn't on the sheet — confirm the values if they differ).
 *
 * Idempotent upsert into build_variables. Run: /seed_vertical_hcut.php (super-admin).
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}

ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

$prod = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev Vertical Blinds' LIMIT 1");
$prod->execute([$MASTER]);
$productId = (int) $prod->fetchColumn();
if ($productId === 0) { exit("Could not find product 'Bev Vertical Blinds' for client {$MASTER}.\n"); }

$ex = $pdo->prepare("SELECT id, name FROM product_extras WHERE product_id = ? AND client_id = ? AND name IN ('Control Options','Wand Options','Exact or Recess')");
$ex->execute([$productId, $MASTER]);
$extraId = [];
foreach ($ex->fetchAll(PDO::FETCH_ASSOC) as $r) { $extraId[(string) $r['name']] = (int) $r['id']; }
foreach (['Control Options','Wand Options','Exact or Recess'] as $need) {
    if (empty($extraId[$need])) { exit("Missing option group '{$need}' on product {$productId}.\n"); }
}

$cols = [
    ['ref' => 'system',                             'label' => 'System'],
    ['ref' => 'extra:' . $extraId['Control Options'], 'label' => 'Control Options'],
    ['ref' => 'extra:' . $extraId['Wand Options'],    'label' => 'Wand Options'],
    ['ref' => 'extra:' . $extraId['Exact or Recess'], 'label' => 'Exact or Recess'],
];

// [System, Control, Wand, Basis] cells + result.
$row = static fn (string $sys, string $ctrl, string $wand, string $basis, int $deduct): array =>
    ['cells' => [$sys, $ctrl, $wand, $basis], 'result' => 'Width - ' . $deduct];

$rows = [];

// System deduction sets: [cordR, cordE, stackR, stackE, centreR, centreE].
$byWandCentre = [
    'SlimLine'   => [30, 20, 12, 2, 20, 10],
    'Vogue'      => [33, 23, 22, 12, 32, 22],
];
foreach ($byWandCentre as $sys => $d) {
    // Cord (any wand / any draw).
    $rows[] = $row($sys, 'Corded', '', 'Recess', $d[0]);
    $rows[] = $row($sys, 'Corded', '', 'Exact',  $d[1]);
    // Wand centre (before the stack catch-all).
    foreach (['Center Left', 'Center Right'] as $c) {
        $rows[] = $row($sys, 'Wand', $c, 'Recess', $d[4]);
        $rows[] = $row($sys, 'Wand', $c, 'Exact',  $d[5]);
    }
    // Wand stack (catch-all: Left/Right Stack, Split Draw 2 Wands).
    $rows[] = $row($sys, 'Wand', '', 'Recess', $d[2]);
    $rows[] = $row($sys, 'Wand', '', 'Exact',  $d[3]);
}

// Nova — no centre split (wand all = 15/5).
$rows[] = $row('Nova', 'Corded', '', 'Recess', 25);
$rows[] = $row('Nova', 'Corded', '', 'Exact',  15);
$rows[] = $row('Nova', 'Wand',   '', 'Recess', 15);
$rows[] = $row('Nova', 'Wand',   '', 'Exact',  5);

// No Thrills — wand only, mirrors SlimLine wand.
foreach (['Center Left', 'Center Right'] as $c) {
    $rows[] = $row('No Thrills', 'Wand', $c, 'Recess', 20);
    $rows[] = $row('No Thrills', 'Wand', $c, 'Exact',  10);
}
$rows[] = $row('No Thrills', 'Wand', '', 'Recess', 12);
$rows[] = $row('No Thrills', 'Wand', '', 'Exact',  2);

$upsert = $pdo->prepare(
    "INSERT INTO build_variables (product_id, name, seq, columns_json, rows_json)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE seq = VALUES(seq), columns_json = VALUES(columns_json), rows_json = VALUES(rows_json)"
);
$upsert->execute([
    $productId, 'H_Cut', 10,
    json_encode($cols, JSON_UNESCAPED_UNICODE),
    json_encode($rows, JSON_UNESCAPED_UNICODE),
]);

echo "Seeded H_Cut on product {$productId} (Bev Vertical Blinds) — " . count($rows) . " rows.\n";
echo "Test: SlimLine/Corded/Recess/Width=1200 → 1170; Nova/Corded/Recess/1200 → 1175;\n";
echo "SlimLine/Wand/Center Left/Recess/1200 → 1180; SlimLine/Wand/Left Stack/Recess/1200 → 1188.\n";
