<?php
declare(strict_types=1);

/**
 * Seed: Truck_Spec — the truck line that prints on the cutting label.
 *
 * Vogue uses Louvolite's best-fit chart, which gives both a truck COUNT and a
 * carrier SIZE, so it prints as "<count> x <size>mm" (e.g. 16 x 87mm). Every
 * other system uses a fixed carrier, so it prints just the count.
 *
 *   Truck_Spec = Vogue: Trucks & " x " & Truck_Size & "mm"   |   else: Trucks
 *
 * References Trucks + Truck_Size (both computed earlier), so it runs after them.
 * Idempotent upsert into build_variables. Run: /seed_vertical_truck_spec.php.
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

$cols = [['ref' => 'system', 'label' => 'System']];
$rows = [
    ['cells' => ['Vogue'], 'result' => 'Trucks & " x " & Truck_Size & "mm"'],
    ['cells' => [''],       'result' => 'Trucks'],
];

$pdo->prepare(
    "INSERT INTO build_variables (product_id, name, seq, columns_json, rows_json)
     VALUES (?, 'Truck_Spec', 5, ?, ?)
     ON DUPLICATE KEY UPDATE seq = VALUES(seq), columns_json = VALUES(columns_json), rows_json = VALUES(rows_json)"
)->execute([$productId, json_encode($cols, JSON_UNESCAPED_UNICODE), json_encode($rows, JSON_UNESCAPED_UNICODE)]);

echo "Seeded Truck_Spec on product {$productId} (Bev Vertical Blinds).\n";
echo "Vogue prints '<count> x <size>mm' (e.g. 16 x 87mm); other systems print the count.\n";
