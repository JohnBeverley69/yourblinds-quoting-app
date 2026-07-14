<?php
declare(strict_types=1);

/**
 * Seed: Roller cut build variables + allowances (Bev Roller Blinds).
 *
 * Decoded from the workshop "ROLLER CALCULATOR AND LABEL PRINTER" and confirmed
 * with John. Signed-allowance convention — a value is the actual change to the
 * dimension and the rule ADDS it (deduction negative, addition positive).
 *
 * Per the official Louvolite cassette sheet (rows used: Standard Roller & Open
 * Cassette inc 70mm) + Beverley's own fabric/drop spec:
 *
 *   TUBE cut, off WIDTH:
 *                                 Recess  Exact  Cloth
 *     Standard (None)              -35    -35     +3
 *     LL 70/40 · Senses · GripFix  -45    -35     +3
 *   (Open 70mm cassette: recess -10 to blind size, then tube -35 = -45.
 *    Exact = blind size given, so just -35. LL 40 uses the 70mm figures.)
 *
 *   FABRIC (cloth) cut = TUBE - 3  (Beverley runout, ~1.5mm each side) —
 *     exposed as the editable roller_fabric allowance ("offset").
 *
 *   FASCIA (extrusion) cut, off WIDTH — comes from the Allowances page
 *     (roller_fascia), defaults LL/Senses -12 / -4 / +38 (Recess/Exact/Cloth);
 *     Standard (None) → blank.
 *
 *   FABRIC DROP = Drop + 400 for any scallop / trim; + 350 only for "Not Required".
 *   CHAIN LENGTH = (Drop - 100) * 2 — chain-operated (Side Winder) only.
 *
 * Fascia/Fit/Scallops/Control are decision-table columns (a formula can't read an
 * option value). All allowances (roller_pole/roller_fabric/roller_fascia) are
 * editable on the Allowances page, LL-prefixed with Senses cloned. P&F Roller is
 * a separate product.
 *
 * Worked example — Width 1000, Drop 1500 (Not Required):
 *   None/Recess:  Tube 965, Fabric 962, Fascia blank, Drop 1850
 *   LL 70/Recess: Tube 955, Fabric 952, Fascia 988
 *   LL 70/Exact:  Tube 965, Fabric 962, Fascia 996
 *   None/Cloth:   Tube 1003, Fabric 1000
 *
 * Idempotent (upsert). Run via web: /seed_roller_cut.php (super-admin).
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

$prod = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev Roller Blinds' LIMIT 1");
$prod->execute([$MASTER]);
$productId = (int) $prod->fetchColumn();
if ($productId === 0) { exit("Could not find product 'Bev Roller Blinds' for client {$MASTER}.\n"); }

// Resolve the option-group ids we branch on.
$extraId = static function (string $name) use ($pdo, $productId, $MASTER): int {
    $q = $pdo->prepare("SELECT id FROM product_extras WHERE product_id = ? AND client_id = ? AND name = ? LIMIT 1");
    $q->execute([$productId, $MASTER, $name]);
    return (int) $q->fetchColumn();
};
$fasciaId  = $extraId('Fascia Options');
$fitId     = $extraId('Exact or Recess');
$scallopId = $extraId('Scallops and Trims');
$controlId = $extraId('Control Options');
foreach (['Fascia Options' => $fasciaId, 'Exact or Recess' => $fitId, 'Scallops and Trims' => $scallopId, 'Control Options' => $controlId] as $n => $id) {
    if ($id === 0) { exit("Missing option group '{$n}' on product {$productId}.\n"); }
}

// ---- Allowance tables (signed) --------------------------------------------
$allow = [
    'roller_pole' => [
        ['cloth',           'Cloth Size (any fascia)', 3],
        ['standard|recess', 'Standard · Recess', -35],
        ['standard|exact',  'Standard · Exact',  -35],
        ['ll|recess',       'LL · Recess',       -45],
        ['ll|exact',        'LL · Exact',        -35],
        ['senses|recess',   'Senses · Recess',   -45],
        ['senses|exact',    'Senses · Exact',    -35],
    ],
    'roller_fabric' => [
        ['offset', 'Fabric (off the tube)', -3],
    ],
    'roller_fascia' => [
        ['ll|recess',     'LL · Recess',     -12],
        ['ll|exact',      'LL · Exact',      -4],
        ['ll|cloth',      'LL · Cloth',       38],
        ['senses|recess', 'Senses · Recess', -12],
        ['senses|exact',  'Senses · Exact',  -4],
        ['senses|cloth',  'Senses · Cloth',   38],
    ],
];

// Remove the earlier ad-hoc / superseded table.
$pdo->prepare("DELETE FROM allowance_rows WHERE table_name = 'Roller'")->execute();

$insAllow = $pdo->prepare(
    "INSERT INTO allowance_rows (table_name, key_norm, keys_display, value, seq)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE keys_display = VALUES(keys_display), value = VALUES(value), seq = VALUES(seq)"
);
foreach ($allow as $table => $rows) {
    $pdo->prepare("DELETE FROM allowance_rows WHERE table_name = ?")->execute([$table]);
    $seq = 0;
    foreach ($rows as [$key, $disp, $val]) {
        $insAllow->execute([$table, $key, $disp, (float) $val, $seq++]);
        echo sprintf("  %-14s %-20s = %s\n", $table, $disp, rtrim(rtrim(number_format((float) $val, 2, '.', ''), '0'), '.'));
    }
}

// ---- Build variables (decision tables) ------------------------------------
$colFascia  = ['ref' => 'extra:' . $fasciaId,  'label' => 'Fascia Options'];
$colFit     = ['ref' => 'extra:' . $fitId,     'label' => 'Exact or Recess'];
$colScallop = ['ref' => 'extra:' . $scallopId, 'label' => 'Scallops and Trims'];
$colControl = ['ref' => 'extra:' . $controlId, 'label' => 'Control Options'];

// LL fascias share the open-70mm figures; Grip Fix uses the LL 70mm cassette too.
$LL_FASCIAS = ['LL 70mm Cassette', 'LL 40mm Cassette', 'Grip Fix Cassette'];

// Tube_Cut — Width + tube allowance by Fascia × Fit. Cloth (+3) first (any
// fascia); then Standard (None) / LL (open 70mm, incl Grip Fix) / Senses.
$tubeRows = [];
$tubeRows[] = ['cells' => ['', 'Cloth Size'],   'result' => 'Width + LOOKUP("roller_pole", "cloth")'];
$tubeRows[] = ['cells' => ['None', 'Recess'],   'result' => 'Width + LOOKUP("roller_pole", "standard", "recess")'];
$tubeRows[] = ['cells' => ['None', 'Exact'],    'result' => 'Width + LOOKUP("roller_pole", "standard", "exact")'];
$tubeRows[] = ['cells' => ['Senses', 'Recess'], 'result' => 'Width + LOOKUP("roller_pole", "senses", "recess")'];
$tubeRows[] = ['cells' => ['Senses', 'Exact'],  'result' => 'Width + LOOKUP("roller_pole", "senses", "exact")'];
foreach ($LL_FASCIAS as $f) $tubeRows[] = ['cells' => [$f, 'Recess'], 'result' => 'Width + LOOKUP("roller_pole", "ll", "recess")'];
foreach ($LL_FASCIAS as $f) $tubeRows[] = ['cells' => [$f, 'Exact'],  'result' => 'Width + LOOKUP("roller_pole", "ll", "exact")'];
$tubeRows[] = ['cells' => ['', 'Recess'], 'result' => 'Width + LOOKUP("roller_pole", "standard", "recess")'];  // any other fascia
$tubeRows[] = ['cells' => ['', 'Exact'],  'result' => 'Width + LOOKUP("roller_pole", "standard", "exact")'];

// Fascia_Cut — Width + fascia (extrusion) allowance from the Allowances page;
// LL fascias (incl Grip Fix) use the LL rows; Standard (None) → blank.
$fasciaRows = [];
$fasciaRows[] = ['cells' => ['Senses', 'Recess'],     'result' => 'Width + LOOKUP("roller_fascia", "senses", "recess")'];
$fasciaRows[] = ['cells' => ['Senses', 'Exact'],      'result' => 'Width + LOOKUP("roller_fascia", "senses", "exact")'];
$fasciaRows[] = ['cells' => ['Senses', 'Cloth Size'], 'result' => 'Width + LOOKUP("roller_fascia", "senses", "cloth")'];
foreach ($LL_FASCIAS as $f) {
    $fasciaRows[] = ['cells' => [$f, 'Recess'],     'result' => 'Width + LOOKUP("roller_fascia", "ll", "recess")'];
    $fasciaRows[] = ['cells' => [$f, 'Exact'],      'result' => 'Width + LOOKUP("roller_fascia", "ll", "exact")'];
    $fasciaRows[] = ['cells' => [$f, 'Cloth Size'], 'result' => 'Width + LOOKUP("roller_fascia", "ll", "cloth")'];
}
$fasciaRows[] = ['cells' => ['', ''], 'result' => '""'];   // None / anything else → no fascia piece

// Fabric_Drop — Drop + 400 for any scallop / trim; + 350 only for "Not Required"
// (plain, no scallop). The scallop choices were split per-shape, so the old
// shaped-label list no longer matched — every scalloped bottom now gets +400.
$dropRows = [
    ['cells' => ['Not Required'], 'result' => 'Drop + 350'],
    ['cells' => [''],             'result' => 'Drop + 400'],
];

$vars = [
    ['name' => 'Tube_Cut',     'seq' => 10, 'cols' => [$colFascia, $colFit], 'rows' => $tubeRows],
    ['name' => 'Fabric_W',     'seq' => 20, 'cols' => [],                    'rows' => [['cells' => [], 'result' => 'Tube_Cut + LOOKUP("roller_fabric", "offset")']]],
    ['name' => 'Fascia_Cut',   'seq' => 30, 'cols' => [$colFascia, $colFit], 'rows' => $fasciaRows],
    ['name' => 'Fabric_Drop',  'seq' => 40, 'cols' => [$colScallop],         'rows' => $dropRows],
    // Chain loop = twice the hanging length (100mm short of the drop) — only on
    // a chain-operated blind (Side Winder); blank for motor / spring controls.
    ['name' => 'Chain_Length', 'seq' => 50, 'cols' => [$colControl], 'rows' => [
        ['cells' => ['Side Winder'], 'result' => '(Drop - 100) * 2'],
        ['cells' => [''],            'result' => '""'],
    ]],
];

$upVar = $pdo->prepare(
    "INSERT INTO build_variables (product_id, name, seq, columns_json, rows_json)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE seq = VALUES(seq), columns_json = VALUES(columns_json), rows_json = VALUES(rows_json)"
);
foreach ($vars as $v) {
    $upVar->execute([
        $productId, $v['name'], $v['seq'],
        json_encode($v['cols'], JSON_UNESCAPED_UNICODE),
        json_encode($v['rows'], JSON_UNESCAPED_UNICODE),
    ]);
    echo "  build var {$v['name']} (" . count($v['rows']) . " rows)\n";
}

echo "\nDone — roller cut on product {$productId} (Bev Roller Blinds).\n";
echo "Test panel (Width 1000 / Drop 1500, Not Required): None+Recess → Tube 965 Fabric 962 Drop 1850; " .
     "LL 70+Recess → 955/952/Fascia 988; LL 70+Exact → 965/962/Fascia 996; None+Cloth → 1003/1000.\n";
