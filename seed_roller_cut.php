<?php
declare(strict_types=1);

/**
 * Seed: Roller cut build variables + allowances (Bev Roller Blinds).
 *
 * Decoded from the workshop "ROLLER CALCULATOR AND LABEL PRINTER" and confirmed
 * with John. Signed-allowance convention — a value is the actual change to the
 * dimension and the rule ADDS it (deduction negative, addition positive).
 *
 * The cut depends on the FASCIA (Fascia Options) and the FIT (Exact or Recess):
 *
 *   POLE (tube) cut, off WIDTH:
 *                          Recess   Exact   Cloth Size
 *     None (standard)       -35     -25      +3
 *     Senses / LL 70 / LL40 -47     -37      +3   (cassette)
 *     Grip Fix Cassette     -42     -42      -42  (fixed, any fit)
 *
 *   FABRIC (cloth) cut  = POLE - 3  (always — incl. Grip Fix, per John).
 *
 *   FASCIA (cassette extrusion) cut, off WIDTH (cassette fascias only):
 *     Senses / LL 70 / LL40 -12     -4       +38
 *     Grip Fix Cassette     -20     -20      -20
 *     None                  (blank — no fascia)
 *
 *   FABRIC DROP = Drop + 400 for any scallop / trim; Drop + 350 only for
 *     "Not Required" (plain bottom, no scallop).
 *
 *   CHAIN LENGTH = (Drop - 100) * 2  (the continuous chain loop) — only on a
 *     chain-operated blind (Control Options = Side Winder); blank for motor/spring.
 *
 * A formula can't read an option value, so Fascia/Fit/Scallops are decision-table
 * columns. Cruze/Hybrid/Senses Universal from the calculator aren't in the
 * YourBlinds catalogue, so they're omitted (add later if offered). P&F Roller is
 * a separate product (Bev PF Roller) and is not handled here.
 *
 * Worked example — Width 1200, Drop 1500:
 *   None/Recess:   Tube 1165, Fabric 1162, Fascia blank, Drop 1850
 *   Senses/Recess: Tube 1153, Fabric 1150, Fascia 1188
 *   Grip Fix:      Tube 1158, Fabric 1155, Fascia 1180
 *   None/Cloth:    Tube 1203, Fabric 1200
 *   +scallop shape: Drop 1900
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
        ['gripfix',          'Grip Fix (any fit)', -42],
        ['cloth',            'Cloth Size',          3],
        ['cassette|recess',  'Cassette · Recess',  -47],
        ['cassette|exact',   'Cassette · Exact',   -37],
        ['standard|recess',  'Standard · Recess',  -35],
        ['standard|exact',   'Standard · Exact',   -25],
    ],
    'roller_fascia' => [
        ['gripfix',          'Grip Fix (any fit)', -20],
        ['cassette|recess',  'Cassette · Recess',  -12],
        ['cassette|exact',   'Cassette · Exact',   -4],
        ['cassette|cloth',   'Cassette · Cloth',    38],
    ],
];

// Remove the earlier ad-hoc / superseded tables.
foreach (['Roller', 'roller_fabric'] as $stale) {
    $pdo->prepare("DELETE FROM allowance_rows WHERE table_name = ?")->execute([$stale]);
}

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

$CASSETTES = ['Senses', 'LL 70mm Cassette', 'LL 40mm Cassette'];
$GRIPFIX   = 'Grip Fix Cassette';

// Tube_Cut — Width + pole allowance, keyed on Fascia × Fit (Grip Fix first).
$tubeRows = [];
$tubeRows[] = ['cells' => [$GRIPFIX, ''],  'result' => 'Width + LOOKUP("roller_pole", "gripfix")'];
$tubeRows[] = ['cells' => ['', 'Cloth Size'], 'result' => 'Width + LOOKUP("roller_pole", "cloth")'];
foreach ($CASSETTES as $c) $tubeRows[] = ['cells' => [$c, 'Recess'], 'result' => 'Width + LOOKUP("roller_pole", "cassette", "recess")'];
foreach ($CASSETTES as $c) $tubeRows[] = ['cells' => [$c, 'Exact'],  'result' => 'Width + LOOKUP("roller_pole", "cassette", "exact")'];
$tubeRows[] = ['cells' => ['', 'Recess'], 'result' => 'Width + LOOKUP("roller_pole", "standard", "recess")'];
$tubeRows[] = ['cells' => ['', 'Exact'],  'result' => 'Width + LOOKUP("roller_pole", "standard", "exact")'];

// Fascia_Cut — Width + fascia allowance (cassette + Grip Fix); None → blank.
$fasciaRows = [];
$fasciaRows[] = ['cells' => [$GRIPFIX, ''], 'result' => 'Width + LOOKUP("roller_fascia", "gripfix")'];
foreach ($CASSETTES as $c) $fasciaRows[] = ['cells' => [$c, 'Recess'],     'result' => 'Width + LOOKUP("roller_fascia", "cassette", "recess")'];
foreach ($CASSETTES as $c) $fasciaRows[] = ['cells' => [$c, 'Exact'],      'result' => 'Width + LOOKUP("roller_fascia", "cassette", "exact")'];
foreach ($CASSETTES as $c) $fasciaRows[] = ['cells' => [$c, 'Cloth Size'], 'result' => 'Width + LOOKUP("roller_fascia", "cassette", "cloth")'];
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
    ['name' => 'Fabric_W',     'seq' => 20, 'cols' => [],                    'rows' => [['cells' => [], 'result' => 'Tube_Cut - 3']]],
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
echo "Test panel (Width 1200 / Drop 1500): None+Recess → Tube 1165 Fabric 1162 Drop 1850; " .
     "Senses+Recess → 1153/1150/Fascia 1188; Grip Fix → 1158/1155/Fascia 1180; None+Cloth → 1203/1200.\n";
