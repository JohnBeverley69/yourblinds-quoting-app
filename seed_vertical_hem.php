<?php
declare(strict_types=1);

/**
 * Seed: Hem_To_Hem (fabric drop cut) build variable for Bev Vertical Blinds.
 *
 * The hem deduction depends on how the drop was measured, captured in the
 * "Exact or Recess" option:
 *
 *   Recess       -> Drop - 55   (matches Blind Matrix ON066564: 1490 -> 1435, 2035 -> 1980)
 *   Exact        -> Drop - 45
 *   Hem to Hem   -> Drop        (customer gave the finished hem-to-hem length; no deduction)
 *   (anything else / not given) -> Drop - 55   (recess is the safe default)
 *
 * Column binds to the "Exact or Recess" group by id (test panel) and by name
 * (real orders, whose tenant group ids differ). NB "Hem to Hem" is not yet a
 * choice on that option, so that row stays dormant until the choice is added.
 *
 * Idempotent upsert into build_variables. Run: /seed_vertical_hem.php (super-admin).
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

// The measurement option the hem deduction keys off.
$ex = $pdo->prepare("SELECT id FROM product_extras WHERE product_id = ? AND client_id = ? AND name = 'Exact or Recess' LIMIT 1");
$ex->execute([$productId, $MASTER]);
$measureId = (int) $ex->fetchColumn();
if ($measureId === 0) { exit("Missing 'Exact or Recess' option on product {$productId}.\n"); }

$columns = [['ref' => 'extra:' . $measureId, 'label' => 'Exact or Recess']];
$rows = [
    ['cells' => ['Recess'],     'result' => 'Drop - 55'],
    ['cells' => ['Exact'],      'result' => 'Drop - 45'],
    ['cells' => ['Hem to Hem'], 'result' => 'Drop'],
    ['cells' => [''],           'result' => 'Drop - 55'],   // default: treat as recess
];

$upsert = $pdo->prepare(
    "INSERT INTO build_variables (product_id, name, seq, columns_json, rows_json)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE seq = VALUES(seq), columns_json = VALUES(columns_json), rows_json = VALUES(rows_json)"
);
$upsert->execute([
    $productId, 'Hem_To_Hem', 13,
    json_encode($columns, JSON_UNESCAPED_UNICODE),
    json_encode($rows, JSON_UNESCAPED_UNICODE),
]);

echo "Seeded Hem_To_Hem on product {$productId} (Bev Vertical Blinds), keyed on 'Exact or Recess' (extra:{$measureId}).\n";
echo "Recess: Drop-55 (1490 -> 1435, 2035 -> 1980, matches BM). Exact: Drop-45. Hem to Hem: Drop. Default: Drop-55.\n";
