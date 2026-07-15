<?php
declare(strict_types=1);

/**
 * Seed: Perfect Fit roller cut build variables + allowances (Bev PF Roller).
 *
 * From the official Louvolite "PF gen2 Roller — Side Control, Frame & Component
 * Deductions" sheet. Perfect Fit is measured by the GLASS SIZE (width x drop;
 * max width 1400, min 150), and every component comes off that glass size —
 * there is NO recess/exact/cloth basis. Signed-allowance convention (the rule
 * ADDS the value):
 *
 *   Frame width rails  = Glass Width - 28
 *   Frame drop rails   = Glass Drop  - 67
 *   Roller tube        = Glass Width - 18
 *   Bottom bar         = Glass Width - 21
 *   Fabric width       = Glass Width - 21
 *   Fabric drop        = Glass Drop  + 200   (Beverley: cut long, trimmed on assembly)
 *
 * All six are editable on the Allowances page (table "pf_roller"). Width/Drop in
 * the build engine are the entered glass measurements.
 *
 * Worked example — glass 1000 x 1200:
 *   Frame 972 x 1133 · Tube 982 · Bottom bar 979 · Fabric 979 x 1400
 *
 * Idempotent (upsert). Run via web: /seed_pf_roller_cut.php (super-admin).
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

$prod = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev PF Roller' LIMIT 1");
$prod->execute([$MASTER]);
$productId = (int) $prod->fetchColumn();
if ($productId === 0) { exit("Could not find product 'Bev PF Roller' for client {$MASTER}.\n"); }

// ---- Allowance table (signed, editable) -----------------------------------
$rows = [
    ['frame_width', 'Frame width rails', -28],
    ['frame_drop',  'Frame drop rails',  -67],
    ['tube',        'Roller tube',       -18],
    ['bottom_bar',  'Bottom bar',        -21],
    ['fabric_width','Fabric width',      -21],
    ['fabric_drop', 'Fabric drop',        200],
];
$pdo->prepare("DELETE FROM allowance_rows WHERE table_name = 'pf_roller'")->execute();
$insAllow = $pdo->prepare(
    "INSERT INTO allowance_rows (table_name, key_norm, keys_display, value, seq)
     VALUES ('pf_roller', ?, ?, ?, ?)"
);
$seq = 0;
foreach ($rows as [$key, $disp, $val]) {
    $insAllow->execute([$key, $disp, (float) $val, $seq++]);
    echo sprintf("  pf_roller  %-18s = %s\n", $disp, rtrim(rtrim(number_format((float) $val, 2, '.', ''), '0'), '.'));
}

// ---- Build variables (flat, off the glass Width/Drop) ---------------------
$vars = [
    ['name' => 'Frame_W',     'seq' => 10, 'result' => 'Width + LOOKUP("pf_roller", "frame_width")'],
    ['name' => 'Frame_Drop',  'seq' => 20, 'result' => 'Drop + LOOKUP("pf_roller", "frame_drop")'],
    ['name' => 'Tube_Cut',    'seq' => 30, 'result' => 'Width + LOOKUP("pf_roller", "tube")'],
    ['name' => 'Bottom_Bar',  'seq' => 40, 'result' => 'Width + LOOKUP("pf_roller", "bottom_bar")'],
    ['name' => 'Fabric_W',    'seq' => 50, 'result' => 'Width + LOOKUP("pf_roller", "fabric_width")'],
    ['name' => 'Fabric_Drop', 'seq' => 60, 'result' => 'Drop + LOOKUP("pf_roller", "fabric_drop")'],
];

$upVar = $pdo->prepare(
    "INSERT INTO build_variables (product_id, name, seq, columns_json, rows_json)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE seq = VALUES(seq), columns_json = VALUES(columns_json), rows_json = VALUES(rows_json)"
);
foreach ($vars as $v) {
    $upVar->execute([
        $productId, $v['name'], $v['seq'],
        json_encode([], JSON_UNESCAPED_UNICODE),
        json_encode([['cells' => [], 'result' => $v['result']]], JSON_UNESCAPED_UNICODE),
    ]);
    echo "  build var {$v['name']}\n";
}

echo "\nDone — Perfect Fit roller cut on product {$productId} (Bev PF Roller).\n";
echo "Test panel (glass 1000 x 1200): Frame 972 x 1133, Tube 982, Bottom bar 979, Fabric 979 x 1400.\n";
