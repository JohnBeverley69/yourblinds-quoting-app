<?php
declare(strict_types=1);

/**
 * Seed: "Multiple blinds in one fascia" cut branch for Bev Roller Blinds.
 *
 * When several blinds share one continuous fascia, the intermediate blinds lose
 * LESS width (no per-blind end-cap deduction), so the tube/fabric are cut to a
 * flat allowance off the width regardless of fit/fascia. Figures come from the
 * workshop Excel (Tube = Width-25, Fabric = Width-28) and are EDITABLE on the
 * Allowances page as the `roller_multiple` table — change them there any time.
 *
 * Driven by the "Multiple Blinds in One Fascia" option (Yes/No). The Yes row is
 * placed FIRST in each decision table so it wins over the normal fascia x fit
 * rows (build_eval = first matching row); a blank flag cell on the existing rows
 * means they still apply for every non-multiple blind, unchanged.
 *
 * Only Tube_Cut and Fabric_W are touched — Fascia_Cut / Fabric_Drop /
 * Chain_Length are left exactly as seed_roller_cut.php set them.
 *
 * Idempotent (upsert). Run via web: /seed_roller_multiple.php (super-admin).
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
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

$extraId = static function (string $name) use ($pdo, $productId, $MASTER): int {
    $q = $pdo->prepare("SELECT id FROM product_extras WHERE product_id = ? AND client_id = ? AND name = ? LIMIT 1");
    $q->execute([$productId, $MASTER, $name]);
    return (int) $q->fetchColumn();
};
$fasciaId   = $extraId('Fascia Options');
$fitId      = $extraId('Exact or Recess');
$multipleId = $extraId('Multiple Blinds in One Fascia');
foreach (['Fascia Options' => $fasciaId, 'Exact or Recess' => $fitId, 'Multiple Blinds in One Fascia' => $multipleId] as $n => $id) {
    if ($id === 0) { exit("Missing option group '{$n}' on product {$productId}.\n"); }
}

// ---- Editable allowance table (the "box" John can change) -------------------
$insAllow = $pdo->prepare(
    "INSERT INTO allowance_rows (table_name, key_norm, keys_display, value, seq)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE keys_display = VALUES(keys_display), value = VALUES(value), seq = VALUES(seq)"
);
$multipleRows = [
    ['tube',   'Multiple in one fascia · Tube (off Width)',   -25],
    ['fabric', 'Multiple in one fascia · Fabric (off Width)', -28],
];
foreach ($multipleRows as $i => [$key, $disp, $val]) {
    $insAllow->execute(['roller_multiple', $key, $disp, (float) $val, $i]);
    echo sprintf("  roller_multiple  %-28s = %s\n", $disp, (string) $val);
}

// ---- Rebuild Tube_Cut + Fabric_W with the Multiple flag branch --------------
$colMultiple = ['ref' => 'extra:' . $multipleId, 'label' => 'Multiple Blinds in One Fascia'];
$colFascia   = ['ref' => 'extra:' . $fasciaId,   'label' => 'Fascia Options'];
$colFit       = ['ref' => 'extra:' . $fitId,     'label' => 'Exact or Recess'];

$LL_FASCIAS = ['LL 70mm Cassette', 'LL 40mm Cassette', 'Grip Fix Cassette'];

// Tube_Cut: [Multiple, Fascia, Fit]. Yes row first (flat, wins), then the
// original fascia x fit rows with a blank Multiple cell (= any → non-multiple).
$tubeRows = [];
$tubeRows[] = ['cells' => ['Yes', '', ''],       'result' => 'Width + LOOKUP("roller_multiple", "tube")'];
$tubeRows[] = ['cells' => ['', '', 'Cloth Size'],'result' => 'Width + LOOKUP("roller_pole", "cloth")'];
$tubeRows[] = ['cells' => ['', 'No Fascia', 'Recess'],'result' => 'Width + LOOKUP("roller_pole", "standard", "recess")'];
$tubeRows[] = ['cells' => ['', 'No Fascia', 'Exact'], 'result' => 'Width + LOOKUP("roller_pole", "standard", "exact")'];
$tubeRows[] = ['cells' => ['', 'Senses', 'Recess'],'result' => 'Width + LOOKUP("roller_pole", "senses", "recess")'];
$tubeRows[] = ['cells' => ['', 'Senses', 'Exact'], 'result' => 'Width + LOOKUP("roller_pole", "senses", "exact")'];
foreach ($LL_FASCIAS as $f) $tubeRows[] = ['cells' => ['', $f, 'Recess'], 'result' => 'Width + LOOKUP("roller_pole", "ll", "recess")'];
foreach ($LL_FASCIAS as $f) $tubeRows[] = ['cells' => ['', $f, 'Exact'],  'result' => 'Width + LOOKUP("roller_pole", "ll", "exact")'];
$tubeRows[] = ['cells' => ['', '', 'Recess'], 'result' => 'Width + LOOKUP("roller_pole", "standard", "recess")'];
$tubeRows[] = ['cells' => ['', '', 'Exact'],  'result' => 'Width + LOOKUP("roller_pole", "standard", "exact")'];

// Fabric_W: [Multiple]. Yes → flat off Width; else the usual tube - 3.
$fabricRows = [
    ['cells' => ['Yes'], 'result' => 'Width + LOOKUP("roller_multiple", "fabric")'],
    ['cells' => [''],    'result' => 'Tube_Cut + LOOKUP("roller_fabric", "offset")'],
];

$upVar = $pdo->prepare(
    "INSERT INTO build_variables (product_id, name, seq, columns_json, rows_json)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE seq = VALUES(seq), columns_json = VALUES(columns_json), rows_json = VALUES(rows_json)"
);
$upVar->execute([$productId, 'Tube_Cut', 10,
    json_encode([$colMultiple, $colFascia, $colFit], JSON_UNESCAPED_UNICODE),
    json_encode($tubeRows, JSON_UNESCAPED_UNICODE)]);
echo "  build var Tube_Cut (" . count($tubeRows) . " rows, +Multiple column)\n";

$upVar->execute([$productId, 'Fabric_W', 20,
    json_encode([$colMultiple], JSON_UNESCAPED_UNICODE),
    json_encode($fabricRows, JSON_UNESCAPED_UNICODE)]);
echo "  build var Fabric_W (" . count($fabricRows) . " rows, +Multiple column)\n";

echo "\nDone — multiple-in-fascia cut on product {$productId}.\n";
echo "Check (Width 1000): Multiple=Yes → Tube 975, Fabric 972; Multiple=No, LL70/Recess → Tube 955, Fabric 952 (unchanged).\n";
echo "Edit the -25 / -28 any time on the Allowances page (table 'roller_multiple').\n";
