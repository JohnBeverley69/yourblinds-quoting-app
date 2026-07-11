<?php
declare(strict_types=1);

/**
 * Seed: Hem_To_Hem (fabric drop cut) build variable for Bev Vertical Blinds.
 *
 * Hem_To_Hem = Drop - 55 (mm). Applies to all systems/controls (every vertical
 * blind has fabric). Matches Blind Matrix, which is correct for this figure:
 * ON066564 printed Drop 1490 -> 1435 and Drop 2035 -> 1980, both Drop - 55.
 *
 * Plain formula (no question columns). Idempotent upsert into build_variables.
 * Run: /seed_vertical_hem.php (super-admin).
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

$upsert = $pdo->prepare(
    "INSERT INTO build_variables (product_id, name, seq, columns_json, rows_json)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE seq = VALUES(seq), columns_json = VALUES(columns_json), rows_json = VALUES(rows_json)"
);
$upsert->execute([
    $productId, 'Hem_To_Hem', 13,
    json_encode([], JSON_UNESCAPED_UNICODE),
    json_encode([['cells' => [], 'result' => 'Drop - 55']], JSON_UNESCAPED_UNICODE),
]);

echo "Seeded Hem_To_Hem on product {$productId} (Bev Vertical Blinds) = Drop - 55.\n";
echo "Test: Drop 1490 -> 1435; Drop 2035 -> 1980 (matches Blind Matrix ON066564).\n";
