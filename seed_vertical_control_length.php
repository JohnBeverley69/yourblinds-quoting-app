<?php
declare(strict_types=1);

/**
 * Seed: CH_L (chain length) and C_L (cord/control length) for Bev Vertical Blinds.
 *
 * Corded blinds only (wand blinds have no cord/chain → no rule → blank on the
 * worksheet). Same calc across all systems. The chain depends on whether a
 * fit height was entered:
 *
 *   CH_L = IF(Fit_height > 0, (Fit_height - 1500) * 2, Drop * 1.5)
 *   C_L  = CH_L + 2 * Width
 *
 * Fit_height is the floor-to-top-of-blind height the customer enters in the
 * "Fit height" option (0/blank when not given); Drop and Width are the blind
 * dimensions. When a fit height is given the looped tilt chain is cut so its
 * loop ends 1.5m (1500mm) off the floor — the EN 13120 child-safety rule for
 * pull cords/chains — hence (Fit_height - 1500) doubled for the loop. The
 * draw cord follows the chain plus 2 x width.
 *
 * NB replaces Blind Matrix's cord/chain figures, which were wrong (its ON066564
 * printed C/L 7700, CH/L 2980 where this logic gives 6955 / 2235).
 *
 * Idempotent upsert into build_variables. Run: /seed_vertical_control_length.php.
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

$ctrl = $pdo->prepare("SELECT id FROM product_extras WHERE product_id = ? AND client_id = ? AND name = 'Control Options' LIMIT 1");
$ctrl->execute([$productId, $MASTER]);
$ctrlId = (int) $ctrl->fetchColumn();
if ($ctrlId === 0) { exit("Missing 'Control Options' on product {$productId}.\n"); }

// One question column: Control Options. Corded-only row (wand → blank).
$cols = [['ref' => 'extra:' . $ctrlId, 'label' => 'Control Options']];

$variables = [
    ['name' => 'CH_L', 'seq' => 11, 'rows' => [
        ['cells' => ['Corded'], 'result' => 'IF(Fit_height > 0, (Fit_height - 1500) * 2, Drop * 1.5)'],
    ]],
    ['name' => 'C_L', 'seq' => 12, 'rows' => [
        ['cells' => ['Corded'], 'result' => 'CH_L + 2 * Width'],
    ]],
];

$upsert = $pdo->prepare(
    "INSERT INTO build_variables (product_id, name, seq, columns_json, rows_json)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE seq = VALUES(seq), columns_json = VALUES(columns_json), rows_json = VALUES(rows_json)"
);

foreach ($variables as $v) {
    $upsert->execute([
        $productId, $v['name'], $v['seq'],
        json_encode($cols, JSON_UNESCAPED_UNICODE),
        json_encode($v['rows'], JSON_UNESCAPED_UNICODE),
    ]);
    echo "  {$v['name']} seeded (Corded row).\n";
}

echo "\nSeeded CH_L + C_L on product {$productId} (Bev Vertical Blinds).\n";
echo "CH_L runs before C_L (C_L = CH_L + 2*Width). Fit_height defaults to 0 when not given.\n";
echo "Test (Corded): no fit height Drop=1490,Width=2360 → CH_L 2235, C_L 6955;\n";
echo "fit height 3000,Width=2000 → CH_L 3000 (loop ends 1.5m off floor), C_L 7000. Wand → no rule (blank).\n";
