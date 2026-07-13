<?php
declare(strict_types=1);

/**
 * Seed: Roller cut build variables + allowances (Bev Roller Blinds).
 *
 * Signed-allowance convention (a value is the actual change to the dimension and
 * the build rule ADDS it — deduction negative, addition positive):
 *
 *   roller_pole   (tube/pole cut, off the WIDTH):   Recess -35 · Exact -25 · Cloth Size +3
 *   roller_fabric (fabric width,  off the POLE CUT): Recess -3  · Exact -3  · Cloth Size  0
 *
 * Build variables (decision tables keyed on the "Exact or Recess" fit option —
 * a formula can't read an option value, so the basis has to be a table column):
 *
 *   Tube_Cut    = Width    + LOOKUP("roller_pole",   <basis>)
 *   Fabric_W    = Tube_Cut + LOOKUP("roller_fabric", <basis>)      (fabric cut width)
 *   Fabric_Drop = Drop + 400                                        (fabric cut drop)
 *
 * The tube is 32mm or 40mm, but the cut allowance is identical for both, so tube
 * size is not a key. Worked example — Width 1200, Drop 1500:
 *   Recess     → Tube_Cut 1165, Fabric_W 1162, Fabric_Drop 1900
 *   Exact      → Tube_Cut 1175, Fabric_W 1172
 *   Cloth Size → Tube_Cut 1203, Fabric_W 1203
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

$ex = $pdo->prepare("SELECT id FROM product_extras WHERE product_id = ? AND client_id = ? AND name = 'Exact or Recess' LIMIT 1");
$ex->execute([$productId, $MASTER]);
$basisId = (int) $ex->fetchColumn();
if ($basisId === 0) { exit("Missing option group 'Exact or Recess' on product {$productId}.\n"); }

// ---- Allowance tables (signed) --------------------------------------------
$allow = [
    'roller_pole'   => ['Recess' => -35, 'Exact' => -25, 'Cloth Size' => 3],
    'roller_fabric' => ['Recess' => -3,  'Exact' => -3,  'Cloth Size' => 0],
];

// Drop the earlier ad-hoc "Roller" table (superseded by the two clean tables).
$pdo->prepare("DELETE FROM allowance_rows WHERE table_name = 'Roller'")->execute();

$insAllow = $pdo->prepare(
    "INSERT INTO allowance_rows (table_name, key_norm, keys_display, value, seq)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE keys_display = VALUES(keys_display), value = VALUES(value), seq = VALUES(seq)"
);
foreach ($allow as $table => $rows) {
    $pdo->prepare("DELETE FROM allowance_rows WHERE table_name = ?")->execute([$table]);
    $seq = 0;
    foreach ($rows as $basis => $val) {
        $insAllow->execute([$table, strtolower($basis), $basis, (float) $val, $seq++]);
        echo sprintf("  %-14s %-12s = %s\n", $table, $basis, rtrim(rtrim(number_format((float) $val, 2, '.', ''), '0'), '.'));
    }
}

// ---- Build variables (decision tables on "Exact or Recess") ---------------
$col   = ['ref' => 'extra:' . $basisId, 'label' => 'Exact or Recess'];
$bases = ['Recess' => 'recess', 'Exact' => 'exact', 'Cloth Size' => 'cloth size'];

$mkRows = static function (string $exprFmt) use ($bases): array {
    $rows = [];
    foreach ($bases as $label => $key) {
        $rows[] = ['cells' => [$label], 'result' => sprintf($exprFmt, $key)];
    }
    return $rows;
};

$vars = [
    ['name' => 'Tube_Cut',    'seq' => 10, 'cols' => [$col], 'rows' => $mkRows('Width + LOOKUP("roller_pole", "%s")')],
    ['name' => 'Fabric_W',    'seq' => 20, 'cols' => [$col], 'rows' => $mkRows('Tube_Cut + LOOKUP("roller_fabric", "%s")')],
    ['name' => 'Fabric_Drop', 'seq' => 30, 'cols' => [],     'rows' => [['cells' => [], 'result' => 'Drop + 400']]],
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
echo "Check in Build Rules test panel: Width 1200 / Recess → Tube_Cut 1165, Fabric_W 1162; Drop 1500 → Fabric_Drop 1900.\n";
