<?php
declare(strict_types=1);

/**
 * Seed: vertical-blind truck & vane build variables (Bev Vertical Blinds).
 *
 * Authors the decision-table build variables that turn a blind's width + options
 * into truck count, carrier size and fabric count — every system in one place:
 *
 *   Truck_Spacing = 77            (mm centre spacing; John's editable "0.077" in mm)
 *   Trucks        = Vogue: BESTFIT(<operation table>, Width, 1)  [Louvolite best fit]
 *                   else:  ROUNDUP(Width / Truck_Spacing), split draws → EVEN(...)
 *   Truck_Size    = Vogue: BESTFIT(<operation table>, Width, 2)  (for "24 x 87mm")
 *   Vanes         = Trucks + 1    (the spare fabric; all systems)
 *
 * Operation → Louvolite table (confirmed against the product's own option names):
 *   Corded, C/L or C/R                       → split  → vogue_split_cord
 *   Corded, anything else                    → 1-way  → vogue_ow_cord
 *   Wand,   Center Left / Center Right        → split  → vogue_split_1wand
 *   Wand,   Split Draw 2 Wands                → split  → vogue_split_2wand
 *   Wand,   anything else (Left/Right Stack)  → 1-way  → vogue_ow_wand
 * Rows are first-match-wins, so the split rows sit above the one-way catch-alls,
 * and the Vogue rows sit above the (System = any) Slimline/Nova/No Thrills rows.
 *
 * Idempotent (upsert into build_variables). Run via web:
 *   /seed_vertical_build_variables.php  (super-admin)
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

// Resolve the product and its option-group ids by name (robust to id drift).
$prod = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev Vertical Blinds' LIMIT 1");
$prod->execute([$MASTER]);
$productId = (int) $prod->fetchColumn();
if ($productId === 0) { exit("Could not find product 'Bev Vertical Blinds' for client {$MASTER}.\n"); }

$ex = $pdo->prepare("SELECT id, name FROM product_extras WHERE product_id = ? AND client_id = ? AND name IN ('Control Options','Draw Options','Wand Options')");
$ex->execute([$productId, $MASTER]);
$extraId = [];
foreach ($ex->fetchAll(PDO::FETCH_ASSOC) as $r) { $extraId[(string) $r['name']] = (int) $r['id']; }
foreach (['Control Options','Draw Options','Wand Options'] as $need) {
    if (empty($extraId[$need])) { exit("Missing option group '{$need}' on product {$productId}.\n"); }
}

$refCtrl = 'extra:' . $extraId['Control Options'];
$refDraw = 'extra:' . $extraId['Draw Options'];
$refWand = 'extra:' . $extraId['Wand Options'];

// Four question columns: System · Control · Draw (cord) · Wand.
$cols = [
    ['ref' => 'system',  'label' => 'System'],
    ['ref' => $refCtrl,  'label' => 'Control Options'],
    ['ref' => $refDraw,  'label' => 'Draw Options'],
    ['ref' => $refWand,  'label' => 'Wand Options'],
];

// Helper: a row is [System, Control, Draw, Wand] cells + a result.
$row = static fn (string $sys, string $ctrl, string $draw, string $wand, string $result): array =>
    ['cells' => [$sys, $ctrl, $draw, $wand], 'result' => $result];

// ---- Trucks: count per system/operation -----------------------------------
$trucksRows = [
    // Vogue — Louvolite best fit, split rows first then one-way catch-alls.
    $row('Vogue', 'Corded', 'C / L', '', 'BESTFIT("vogue_split_cord", Width, 1)'),
    $row('Vogue', 'Corded', 'C / R', '', 'BESTFIT("vogue_split_cord", Width, 1)'),
    $row('Vogue', 'Corded', '',      '', 'BESTFIT("vogue_ow_cord", Width, 1)'),
    $row('Vogue', 'Wand',   '', 'Center Left',        'BESTFIT("vogue_split_1wand", Width, 1)'),
    $row('Vogue', 'Wand',   '', 'Center Right',       'BESTFIT("vogue_split_1wand", Width, 1)'),
    $row('Vogue', 'Wand',   '', 'Split Draw 2 Wands', 'BESTFIT("vogue_split_2wand", Width, 1)'),
    $row('Vogue', 'Wand',   '', '',                   'BESTFIT("vogue_ow_wand", Width, 1)'),
    // Slimline / Nova / No Thrills (System = any, reached only after Vogue).
    $row('', 'Corded', 'C / L', '', 'EVEN(Width / Truck_Spacing)'),
    $row('', 'Corded', 'C / R', '', 'EVEN(Width / Truck_Spacing)'),
    $row('', 'Wand', '', 'Center Left',        'EVEN(Width / Truck_Spacing)'),
    $row('', 'Wand', '', 'Center Right',       'EVEN(Width / Truck_Spacing)'),
    $row('', 'Wand', '', 'Split Draw 2 Wands', 'EVEN(Width / Truck_Spacing)'),
    $row('', '', '', '', 'ROUNDUP(Width / Truck_Spacing)'),
];

// ---- Truck_Size: Vogue best-fit carrier size (for "24 x 87mm") -------------
$sizeRows = [
    $row('Vogue', 'Corded', 'C / L', '', 'BESTFIT("vogue_split_cord", Width, 2)'),
    $row('Vogue', 'Corded', 'C / R', '', 'BESTFIT("vogue_split_cord", Width, 2)'),
    $row('Vogue', 'Corded', '',      '', 'BESTFIT("vogue_ow_cord", Width, 2)'),
    $row('Vogue', 'Wand',   '', 'Center Left',        'BESTFIT("vogue_split_1wand", Width, 2)'),
    $row('Vogue', 'Wand',   '', 'Center Right',       'BESTFIT("vogue_split_1wand", Width, 2)'),
    $row('Vogue', 'Wand',   '', 'Split Draw 2 Wands', 'BESTFIT("vogue_split_2wand", Width, 2)'),
    $row('Vogue', 'Wand',   '', '',                   'BESTFIT("vogue_ow_wand", Width, 2)'),
];

$variables = [
    ['name' => 'Truck_Spacing', 'columns' => [],     'rows' => [['cells' => [], 'result' => '77']]],
    ['name' => 'Trucks',        'columns' => $cols,   'rows' => $trucksRows],
    ['name' => 'Truck_Size',    'columns' => $cols,   'rows' => $sizeRows],
    ['name' => 'Vanes',         'columns' => [],      'rows' => [['cells' => [], 'result' => 'Trucks + 1']]],
];

$upsert = $pdo->prepare(
    "INSERT INTO build_variables (product_id, name, seq, columns_json, rows_json)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE seq = VALUES(seq), columns_json = VALUES(columns_json), rows_json = VALUES(rows_json)"
);

$seq = 0;
foreach ($variables as $v) {
    $upsert->execute([
        $productId, $v['name'], $seq++,
        json_encode($v['columns'], JSON_UNESCAPED_UNICODE),
        json_encode($v['rows'], JSON_UNESCAPED_UNICODE),
    ]);
    echo sprintf("  %-14s %d row(s)\n", $v['name'], count($v['rows']));
}

echo "\nSeeded " . count($variables) . " build variables on product {$productId} (Bev Vertical Blinds).\n";
echo "Truck spacing held as Truck_Spacing = 77 (edit there if it changes).\n";
echo "Test on Build rules: e.g. System=Vogue, Corded, Width=1800 → Trucks 24, Truck_Size 87.\n";
echo "Slimline/Nova truck SIZE has no rule yet (only Vogue's varies) — add if it must print.\n";
