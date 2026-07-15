<?php
declare(strict_types=1);

/**
 * Seed: Perfect Fit pleated cut build variables + allowances (Bev PF Pleated).
 *
 * From the official Louvolite "PF gen2 — 20mm Pleated & Cellular, Deductions For
 * Making A Perfect Fit Pleated Blind" sheet. Perfect Fit is measured by the
 * GLASS SIZE (width x drop; max width 1400, min 350). All components come off the
 * glass size (signed-allowance convention — the rule ADDS the value):
 *
 *   Frame top/bottom rails = Glass Width - 28
 *   Frame side rails       = Glass Drop  - 28
 *   Headrails (x2) + slats = Glass Width - 16
 *   Fabric width           = Glass Width - 16
 *   Blind (fabric) drop    = Glass Drop  - 0   (= glass drop; number of pleats is
 *                                                read off Louvolite's 20mm drop chart)
 *   Cord length            = 4 x Width + 2 x Drop   (from the sheet's assembly steps)
 *
 * No tube / bottom bar (pleated has neither). Deductions are the same for the
 * Equipleat, Cellular and Loop Cord methods. All editable on the Allowances page
 * (table "pf_pleated"). Width/Drop = the entered glass measurements.
 *
 * Worked example — glass 1000 x 1200:
 *   Frame 972 (T/B) x 1172 (sides) · Headrail 984 · Fabric 984 x 1200 · Cord 6400
 *
 * Idempotent (upsert). Run via web: /seed_pf_pleated_cut.php (super-admin).
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

$prod = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev PF Pleated' LIMIT 1");
$prod->execute([$MASTER]);
$productId = (int) $prod->fetchColumn();
if ($productId === 0) { exit("Could not find product 'Bev PF Pleated' for client {$MASTER}.\n"); }

// ---- Allowance table (signed, editable) -----------------------------------
$rows = [
    ['frame_tb',     'Frame top/bottom rails', -28],
    ['frame_side',   'Frame side rails',       -28],
    ['headrail',     'Headrails + slats',      -16],
    ['fabric_width', 'Fabric width',           -16],
    ['drop',         'Blind drop',               0],
];
$pdo->prepare("DELETE FROM allowance_rows WHERE table_name = 'pf_pleated'")->execute();
$insAllow = $pdo->prepare(
    "INSERT INTO allowance_rows (table_name, key_norm, keys_display, value, seq)
     VALUES ('pf_pleated', ?, ?, ?, ?)"
);
$seq = 0;
foreach ($rows as [$key, $disp, $val]) {
    $insAllow->execute([$key, $disp, (float) $val, $seq++]);
    echo sprintf("  pf_pleated  %-22s = %s\n", $disp, rtrim(rtrim(number_format((float) $val, 2, '.', ''), '0'), '.'));
}

// ---- Build variables (flat, off the glass Width/Drop) ---------------------
$vars = [
    ['name' => 'Frame_TB',     'seq' => 10, 'result' => 'Width + LOOKUP("pf_pleated", "frame_tb")'],
    ['name' => 'Frame_Side',   'seq' => 20, 'result' => 'Drop + LOOKUP("pf_pleated", "frame_side")'],
    ['name' => 'Headrail',     'seq' => 30, 'result' => 'Width + LOOKUP("pf_pleated", "headrail")'],
    ['name' => 'Fabric_W',     'seq' => 40, 'result' => 'Width + LOOKUP("pf_pleated", "fabric_width")'],
    ['name' => 'Blind_Drop',   'seq' => 50, 'result' => 'Drop + LOOKUP("pf_pleated", "drop")'],
    ['name' => 'Cord_Length',  'seq' => 60, 'result' => '4 * Width + 2 * Drop'],
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

echo "\nDone — Perfect Fit pleated cut on product {$productId} (Bev PF Pleated).\n";
echo "Test panel (glass 1000 x 1200): Frame 972 x 1172, Headrail 984, Fabric 984 x 1200, Cord 6400.\n";
