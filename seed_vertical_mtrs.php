<?php
declare(strict_types=1);

/**
 * Seed: Mtrs (fabric metres) build variable for Bev Vertical Blinds.
 *
 * Mtrs = ROUNDUP( (Drop + 95) * Vanes / 1000 ) — each vane's fabric is the full
 * drop plus 95mm of top+bottom pockets, times the number of vanes (Trucks + 1
 * spare), rounded up to the whole metre. Confirmed against Blind Matrix (correct
 * here): ON066564 line 1 (Drop 1490, Vanes 32) -> 51m; line 2 (Drop 2035,
 * Vanes 27) -> 58m.
 *
 * Runs after Vanes (seq 3). Plain formula, no question columns. Idempotent
 * upsert into build_variables. Run: /seed_vertical_mtrs.php (super-admin).
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
    $productId, 'Mtrs', 14,
    json_encode([], JSON_UNESCAPED_UNICODE),
    json_encode([['cells' => [], 'result' => 'ROUNDUP((Drop + 95) * Vanes / 1000)']], JSON_UNESCAPED_UNICODE),
]);

echo "Seeded Mtrs on product {$productId} (Bev Vertical Blinds) = ROUNDUP((Drop + 95) * Vanes / 1000).\n";
echo "Test: Drop 1490, Vanes 32 -> 51; Drop 2035, Vanes 27 -> 58 (matches Blind Matrix ON066564).\n";
