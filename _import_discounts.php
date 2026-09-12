<?php
declare(strict_types=1);
/* TEMP one-time: load each imported account's 2026 trade discounts. Maps BM
   categories -> master products -> the account's mirrored product. Dry-run
   default; ?go=1 writes. Idempotent (skips a base discount already present).
   git rm after. */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors','1'); error_reporting(E_ALL);
$pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;
$DISC = array (
  'JBW001' => 
  array (
    'Vertical Blinds' => 15.0,
    'No Frills Vertical' => 15.0,
    'Vertical Head Rail Only' => 15.0,
    'Vertical Fabric Only' => 15.0,
    'Roller Blind' => 15.0,
    'No Frills Roller' => 15.0,
    'Perfect Fit' => 10.0,
  ),
  'GRB001' => 
  array (
    'Vertical Blinds' => 15.0,
    'No Frills Vertical' => 15.0,
    'Vertical Head Rail Only' => 15.0,
    'Vertical Fabric Only' => 15.0,
    'Roller Blind' => 15.0,
    'No Frills Roller' => 15.0,
    'Perfect Fit' => 10.0,
    'Wood & Fuax Venetains' => 10.0,
  ),
  'DAV001' => 
  array (
    'Vertical Blinds' => 12.0,
    'No Frills Vertical' => 12.0,
    'Vertical Head Rail Only' => 12.0,
    'Vertical Fabric Only' => 12.0,
    'Roller Blind' => 12.0,
    'No Frills Roller' => 12.0,
  ),
  'VESTA001' => 
  array (
    'Vertical Blinds' => 15.0,
    'No Frills Vertical' => 15.0,
    'Vertical Head Rail Only' => 15.0,
    'Vertical Fabric Only' => 15.0,
    'Roller Blind' => 15.0,
    'No Frills Roller' => 15.0,
    'Wood & Fuax Venetains' => 10.0,
  ),
  'AB4U001' => 
  array (
    'Wood & Fuax Venetains' => 7.5,
    'Vertical Blinds' => 12.0,
    'No Frills Vertical' => 12.0,
    'Vertical Head Rail Only' => 12.0,
    'Vertical Fabric Only' => 12.0,
    'Roller Blind' => 12.0,
    'No Frills Roller' => 12.0,
    'Perfect Fit' => 6.0,
    'Pleated' => 6.0,
  ),
  'JBL001' => 
  array (
    'Vertical Blinds' => 15.0,
    'No Frills Vertical' => 15.0,
    'Vertical Head Rail Only' => 15.0,
    'Vertical Fabric Only' => 15.0,
    'Roller Blind' => 15.0,
    'No Frills Roller' => 15.0,
    'Perfect Fit' => 10.0,
  ),
  'JBN001' => 
  array (
    'Vertical Blinds' => 15.0,
    'No Frills Vertical' => 15.0,
    'Vertical Head Rail Only' => 15.0,
    'Vertical Fabric Only' => 15.0,
    'Roller Blind' => 15.0,
    'No Frills Roller' => 15.0,
    'Perfect Fit' => 10.0,
  ),
  'BT001' => 
  array (
    'Vertical Blinds' => 15.0,
    'No Frills Vertical' => 15.0,
    'Vertical Fabric Only' => 15.0,
    'Vertical Head Rail Only' => 15.0,
    'Roller Blind' => 15.0,
    'No Frills Roller' => 15.0,
    'Wood & Fuax Venetains' => 4.5,
    'Pleated' => 3.0,
    'Perfect Fit' => 3.0,
    'Metal Venetian' => 4.5,
  ),
  'PRES001' => 
  array (
    'Vertical Blinds' => 12.0,
    'No Frills Vertical' => 12.0,
    'Vertical Head Rail Only' => 12.0,
    'Vertical Fabric Only' => 12.0,
    'Roller Blind' => 12.0,
    'No Frills Roller' => 12.0,
    'Wood & Fuax Venetains' => 7.5,
  ),
  'B4L001' => 
  array (
    'Vertical Blinds' => 9.0,
    'No Frills Vertical' => 9.0,
    'Vertical Head Rail Only' => 9.0,
    'Vertical Fabric Only' => 9.0,
    'Roller Blind' => 9.0,
    'No Frills Roller' => 9.0,
  ),
  'JBM001' => 
  array (
    'Vertical Blinds' => 15.0,
    'No Frills Vertical' => 15.0,
    'Vertical Head Rail Only' => 15.0,
    'Vertical Fabric Only' => 15.0,
    'Roller Blind' => 15.0,
    'No Frills Roller' => 15.0,
    'Perfect Fit' => 10.0,
  ),
  'PSI001' => 
  array (
    'Vertical Blinds' => 9.0,
    'No Frills Vertical' => 9.0,
    'Vertical Fabric Only' => 9.0,
    'Vertical Head Rail Only' => 9.0,
    'Roller Blind' => 9.0,
    'No Frills Roller' => 9.0,
  ),
  'EXP001' => 
  array (
    'Vertical Blinds' => 12.0,
    'No Frills Vertical' => 12.0,
    'Vertical Head Rail Only' => 12.0,
    'Vertical Fabric Only' => 12.0,
    'Roller Blind' => 12.0,
    'No Frills Roller' => 12.0,
    'Wood & Fuax Venetains' => 4.5,
    'Perfect Fit' => 4.5,
  ),
  'BRIX001' => 
  array (
    'Vertical Blinds' => 15.0,
    'No Frills Vertical' => 15.0,
    'Vertical Fabric Only' => 15.0,
    'Vertical Head Rail Only' => 15.0,
    'Roller Blind' => 15.0,
    'No Frills Roller' => 15.0,
    'Wood & Fuax Venetains' => 4.5,
    'Perfect Fit' => 4.5,
  ),
  'VMB001' => 
  array (
    'Vertical Blinds' => 15.0,
    'No Frills Vertical' => 15.0,
    'Vertical Fabric Only' => 15.0,
    'Roller Blind' => 15.0,
    'No Frills Roller' => 15.0,
    'Wood & Fuax Venetains' => 10.0,
    'Pleated' => 10.0,
    'Perfect Fit' => 10.0,
  ),
  'FORT001' => 
  array (
    'Vertical Blinds' => 9.0,
    'No Frills Vertical' => 9.0,
    'Vertical Head Rail Only' => 9.0,
    'Vertical Fabric Only' => 9.0,
    'Roller Blind' => 9.0,
  ),
  'SBY002' => 
  array (
    'Vertical Blinds' => 9.0,
    'No Frills Vertical' => 9.0,
    'Vertical Head Rail Only' => 9.0,
    'Vertical Fabric Only' => 9.0,
    'Roller Blind' => 9.0,
    'No Frills Roller' => 9.0,
  ),
  'XB001' => 
  array (
    'Vertical Blinds' => 15.0,
    'No Frills Vertical' => 15.0,
    'Vertical Head Rail Only' => 15.0,
    'Vertical Fabric Only' => 15.0,
    'Roller Blind' => 15.0,
    'No Frills Roller' => 15.0,
    'Perfect Fit' => 5.0,
    'Wood & Fuax Venetains' => 7.5,
  ),
  'PAU001' => 
  array (
    'Vertical Blinds' => 12.0,
    'No Frills Vertical' => 12.0,
    'Vertical Head Rail Only' => 12.0,
    'Vertical Fabric Only' => 12.0,
    'Roller Blind' => 12.0,
    'No Frills Roller' => 12.0,
    'Perfect Fit' => 10.0,
    'Pleated' => 10.0,
    'Wood & Fuax Venetains' => 7.5,
  ),
  'PBS001' => 
  array (
    'Vertical Blinds' => 12.0,
    'No Frills Vertical' => 12.0,
    'Vertical Head Rail Only' => 12.0,
    'Vertical Fabric Only' => 12.0,
    'Roller Blind' => 12.0,
    'No Frills Roller' => 12.0,
  ),
  'HAY001' => 
  array (
    'Vertical Blinds' => 12.0,
    'No Frills Vertical' => 12.0,
    'Vertical Head Rail Only' => 12.0,
    'Vertical Fabric Only' => 12.0,
    'Roller Blind' => 12.0,
    'No Frills Roller' => 12.0,
    'Perfect Fit' => 6.0,
    'Metal Venetian' => 6.0,
  ),
  'RCB001' => 
  array (
    'Vertical Blinds' => 12.0,
    'No Frills Vertical' => 12.0,
    'Vertical Head Rail Only' => 12.0,
    'Vertical Fabric Only' => 12.0,
    'Roller Blind' => 12.0,
    'No Frills Roller' => 12.0,
    'Perfect Fit' => 8.0,
    'Wood & Fuax Venetains' => 7.5,
  ),
  'LB001' => 
  array (
    'Vertical Blinds' => 15.0,
    'No Frills Vertical' => 15.0,
    'Vertical Head Rail Only' => 15.0,
    'Vertical Fabric Only' => 15.0,
    'Roller Blind' => 15.0,
    'No Frills Roller' => 15.0,
  ),
  'TSB001' => 
  array (
    'Vertical Blinds' => 9.0,
    'No Frills Vertical' => 9.0,
    'Vertical Head Rail Only' => 9.0,
    'Vertical Fabric Only' => 9.0,
    'Roller Blind' => 9.0,
    'No Frills Roller' => 9.0,
  ),
  'SBL001' => 
  array (
    'Wood & Fuax Venetains' => 5.0,
  ),
  'MB001' => 
  array (
    'Vertical Blinds' => 12.0,
    'No Frills Vertical' => 12.0,
    'Vertical Head Rail Only' => 12.0,
    'Vertical Fabric Only' => 12.0,
    'Roller Blind' => 12.0,
    'No Frills Roller' => 12.0,
    'Wood & Fuax Venetains' => 5.0,
  ),
  'MBU001' => 
  array (
    'Vertical Blinds' => 12.0,
    'No Frills Vertical' => 12.0,
    'Vertical Head Rail Only' => 12.0,
    'Vertical Fabric Only' => 12.0,
    'No Frills Roller' => 12.0,
    'Roller Blind' => 12.0,
  ),
  'REDR001' => 
  array (
    'Vertical Blinds' => 15.0,
    'No Frills Vertical' => 15.0,
    'Vertical Head Rail Only' => 15.0,
    'Vertical Fabric Only' => 15.0,
    'Roller Blind' => 15.0,
    'No Frills Roller' => 15.0,
    'Perfect Fit' => 10.0,
    'Pleated' => 10.0,
    'Wood & Fuax Venetains' => 7.5,
  ),
  'GOB001' => 
  array (
    'Vertical Blinds' => 12.0,
    'No Frills Vertical' => 12.0,
    'Vertical Head Rail Only' => 12.0,
    'Vertical Fabric Only' => 12.0,
    'Roller Blind' => 12.0,
    'No Frills Roller' => 12.0,
    'Perfect Fit' => 6.0,
    'Wood & Fuax Venetains' => 4.5,
  ),
  'DDB001' => 
  array (
    'Vertical Blinds' => 12.0,
    'No Frills Vertical' => 12.0,
    'Vertical Head Rail Only' => 12.0,
    'Vertical Fabric Only' => 12.0,
    'Roller Blind' => 12.0,
    'No Frills Roller' => 12.0,
  ),
  'PB001' => 
  array (
    'Vertical Blinds' => 12.0,
    'No Frills Vertical' => 12.0,
    'Vertical Fabric Only' => 12.0,
    'Roller Blind' => 12.0,
    'No Frills Roller' => 12.0,
  ),
  'FB001' => 
  array (
    'Vertical Blinds' => 9.0,
    'No Frills Vertical' => 9.0,
    'Vertical Head Rail Only' => 9.0,
    'Roller Blind' => 9.0,
    'No Frills Roller' => 9.0,
  ),
  'RB001' => 
  array (
    'Vertical Blinds' => 12.0,
    'No Frills Vertical' => 12.0,
    'Vertical Fabric Only' => 12.0,
    'Vertical Head Rail Only' => 12.0,
    'Roller Blind' => 12.0,
    'No Frills Roller' => 12.0,
  ),
  'TBASD001' => 
  array (
    'Vertical Blinds' => 15.0,
    'No Frills Vertical' => 15.0,
    'Vertical Head Rail Only' => 15.0,
    'Vertical Fabric Only' => 15.0,
    'Roller Blind' => 15.0,
    'No Frills Roller' => 15.0,
    'Wood & Fuax Venetains' => 10.0,
  ),
  'SJB001' => 
  array (
    'Vertical Blinds' => 15.0,
    'No Frills Vertical' => 15.0,
    'Vertical Head Rail Only' => 15.0,
    'Vertical Fabric Only' => 15.0,
    'Roller Blind' => 15.0,
    'No Frills Roller' => 15.0,
    'Perfect Fit' => 10.0,
  ),
  'SAGA001' => 
  array (
    'Vertical Blinds' => 12.0,
    'No Frills Vertical' => 12.0,
    'Vertical Head Rail Only' => 12.0,
    'Vertical Fabric Only' => 12.0,
    'Roller Blind' => 12.0,
    'No Frills Roller' => 12.0,
  ),
  'BCS001' => 
  array (
    'Vertical Blinds' => 10.2,
    'No Frills Vertical' => 10.2,
    'Vertical Head Rail Only' => 10.2,
    'Vertical Fabric Only' => 10.2,
    'Roller Blind' => 10.2,
    'No Frills Roller' => 10.2,
    'Wood & Fuax Venetains' => 4.5,
    'Perfect Fit' => 4.5,
  ),
  'SWB001' => 
  array (
    'Vertical Blinds' => 9.0,
    'No Frills Vertical' => 9.0,
    'Vertical Head Rail Only' => 9.0,
    'Vertical Fabric Only' => 9.0,
    'Roller Blind' => 9.0,
    'No Frills Roller' => 9.0,
    'Perfect Fit' => 10.0,
    'Pleated' => 10.0,
  ),
  'BLIN001' => 
  array (
    'Vertical Blinds' => 15.0,
    'No Frills Vertical' => 15.0,
    'Vertical Fabric Only' => 15.0,
    'Roller Blind' => 9.0,
    'No Frills Roller' => 9.0,
    'Vertical Head Rail Only' => 15.0,
  ),
  'CAR001' => 
  array (
    'Vertical Blinds' => 15.0,
    'No Frills Vertical' => 15.0,
    'Vertical Head Rail Only' => 15.0,
    'Vertical Fabric Only' => 15.0,
    'Roller Blind' => 15.0,
    'No Frills Roller' => 15.0,
    'Pleated' => 6.0,
    'Perfect Fit' => 6.0,
  ),
  'EBS001' => 
  array (
    'Vertical Blinds' => 15.0,
    'No Frills Vertical' => 15.0,
    'Vertical Head Rail Only' => 15.0,
    'Vertical Fabric Only' => 15.0,
    'Roller Blind' => 15.0,
    'No Frills Roller' => 15.0,
    'Wood & Fuax Venetains' => 3.0,
  ),
  'TFB001' => 
  array (
    'Vertical Blinds' => 12.0,
    'No Frills Vertical' => 12.0,
    'Vertical Head Rail Only' => 12.0,
    'Vertical Fabric Only' => 12.0,
    'Roller Blind' => 9.0,
    'No Frills Roller' => 9.0,
    'Wood & Fuax Venetains' => 5.0,
  ),
  'SUP001' => 
  array (
    'Vertical Blinds' => 10.0,
    'No Frills Vertical' => 10.0,
    'Vertical Head Rail Only' => 10.0,
    'Vertical Fabric Only' => 10.0,
    'Roller Blind' => 10.0,
    'No Frills Roller' => 10.0,
    'Wood & Fuax Venetains' => 4.5,
  ),
  'SCL001' => 
  array (
    'Vertical Blinds' => 9.0,
    'No Frills Vertical' => 9.0,
    'Vertical Head Rail Only' => 9.0,
    'Vertical Fabric Only' => 9.0,
    'Roller Blind' => 9.0,
    'No Frills Roller' => 9.0,
  ),
  'FNB001' => 
  array (
    'Vertical Blinds' => 9.0,
    'No Frills Vertical' => 9.0,
    'Vertical Head Rail Only' => 9.0,
    'Vertical Fabric Only' => 9.0,
  ),
  'RB002' => 
  array (
    'Vertical Blinds' => 12.0,
    'No Frills Vertical' => 12.0,
    'Vertical Head Rail Only' => 12.0,
    'Vertical Fabric Only' => 12.0,
    'Roller Blind' => 12.0,
    'No Frills Roller' => 12.0,
    'Wood & Fuax Venetains' => 7.5,
  ),
  'NAC001' => 
  array (
    'Vertical Blinds' => 15.0,
    'No Frills Vertical' => 15.0,
    'Vertical Fabric Only' => 15.0,
    'Roller Blind' => 15.0,
    'No Frills Roller' => 15.0,
  ),
);
$MAP = [
  'Vertical Blinds'         => ['Bev Vertical Blinds'],
  'Vertical Head Rail Only' => ['Bev Vertical Blind Head Rail Only'],
  'Vertical Fabric Only'    => ['Bev Vertical Fabrics Only'],
  'Roller Blind'            => ['Bev Roller Blinds'],
  'Pleated'                 => ['Bev Pleated'],
  'Perfect Fit'             => ['Bev PF Roller','Bev PF Venetian','Bev PF Pleated','Bev PF Shutter'],
  'Wood & Fuax Venetains'   => ['Bev 25mm Venetian','Bev Embassy Faux Wood','Bev Forest Wood'],
  'Metal Venetian'          => ['Bev 25mm Venetian'],
];

$dry = (($_GET['go'] ?? '') !== '1');
if ($dry) echo "DRY RUN — add ?go=1 to write.\n\n";

// master products by name -> id
$mp = [];
foreach ($pdo->query("SELECT id, name FROM products WHERE client_id = {$MASTER}")->fetchAll(PDO::FETCH_ASSOC) as $r) $mp[(string) $r['name']] = (int) $r['id'];

$accByRef = $pdo->prepare("SELECT id FROM clients WHERE account_ref = ? LIMIT 1");
$accProd  = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND source_product_id = ? LIMIT 1");
$existing = $pdo->prepare("SELECT id FROM trade_discounts WHERE client_id = ? AND product_id = ? AND system_id IS NULL AND band_code IS NULL AND extra_id IS NULL AND choice_id IS NULL LIMIT 1");
$ins      = $pdo->prepare("INSERT INTO trade_discounts (client_id, product_id, discount_percent, active, notes) VALUES (?, ?, ?, 1, ?)");

$rowsIns = 0; $accs = 0; $unmapped = []; $missingMaster = []; $noAccProd = 0; $conflicts = [];
foreach ($DISC as $ref => $cats) {
    $accByRef->execute([$ref]); $cid = (int) ($accByRef->fetchColumn() ?: 0);
    if (!$cid) { echo "  ! no account for code {$ref}\n"; continue; }

    // Resolve to per-product MAX% for this account.
    $perProd = [];   // account product_id => ['pct'=>, 'cats'=>[]]
    foreach ($cats as $cat => $pct) {
        if (!isset($MAP[$cat])) { $unmapped[$cat] = ($unmapped[$cat] ?? 0) + 1; continue; }
        foreach ($MAP[$cat] as $mname) {
            $mid = $mp[$mname] ?? 0;
            if (!$mid) { $missingMaster[$mname] = true; continue; }
            $accProd->execute([$cid, $mid]); $pid = (int) ($accProd->fetchColumn() ?: 0);
            if (!$pid) { $noAccProd++; continue; }
            if (!isset($perProd[$pid])) $perProd[$pid] = ['pct' => $pct, 'cats' => [$cat]];
            else {
                $perProd[$pid]['cats'][] = $cat;
                if (abs($perProd[$pid]['pct'] - $pct) > 0.001) $conflicts[] = "{$ref} product#{$pid}: " . implode('/', $perProd[$pid]['cats']) . " ({$perProd[$pid]['pct']} vs {$pct}) -> kept higher";
                $perProd[$pid]['pct'] = max($perProd[$pid]['pct'], $pct);
            }
        }
    }
    if (!$perProd) continue;
    $accs++;
    foreach ($perProd as $pid => $info) {
        $existing->execute([$cid, $pid]);
        if ($existing->fetchColumn()) continue;   // already has a base discount
        if ($dry) { echo "  would set {$ref} #{$pid} = {$info['pct']}%  (" . implode('+', array_unique($info['cats'])) . ")\n"; $rowsIns++; continue; }
        $ins->execute([$cid, $pid, $info['pct'], 'Migrated 2026 trade discount']);
        $rowsIns++;
    }
}

echo "\n" . ($dry ? 'WOULD write' : 'Wrote') . " {$rowsIns} discount row(s) across {$accs} account(s).\n";
if ($unmapped)      { echo "\nCategories not mapped to a product (skipped): " . implode(', ', array_map(fn($k,$v)=>"$k x$v", array_keys($unmapped), $unmapped)) . "\n"; }
if ($missingMaster) { echo "Master product names NOT found (fix \$MAP): " . implode(', ', array_keys($missingMaster)) . "\n"; }
if ($noAccProd)     { echo "Account-product lookups that missed: {$noAccProd}\n"; }
if ($conflicts)     { echo "Conflicts (same product, two %s): " . count($conflicts) . "\n"; foreach (array_slice($conflicts,0,10) as $c) echo "  - {$c}\n"; }
