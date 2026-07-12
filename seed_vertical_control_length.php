<?php
declare(strict_types=1);

/**
 * Seed: CH_L (chain length) and C_L (cord/control length) for Bev Vertical Blinds.
 *
 * Same calc across all systems. CH_L (chain) is corded-only. C_L (cord) is on
 * corded blinds AND wand split/centre draws (Center Left/Right, Split Draw 2
 * Wands) — plain wand stacks have neither (no rule → blank on the worksheet).
 * The chain depends on whether a fit height was entered:
 *
 *   CH_L = corded: IF(Fit_height > 0, (Fit_height - 1500) * 2, Drop * 1.5)
 *   C_L  = corded: CH_L + 2 * Width   |   wand split/centre: 2 * Width
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

$getExtra = static function (PDO $pdo, int $pid, int $master, string $name): int {
    $s = $pdo->prepare("SELECT id FROM product_extras WHERE product_id = ? AND client_id = ? AND name = ? LIMIT 1");
    $s->execute([$pid, $master, $name]);
    return (int) $s->fetchColumn();
};
$ctrlId = $getExtra($pdo, $productId, $MASTER, 'Control Options');
$wandId = $getExtra($pdo, $productId, $MASTER, 'Wand Options');
if ($ctrlId === 0) { exit("Missing 'Control Options' on product {$productId}.\n"); }
if ($wandId === 0) { exit("Missing 'Wand Options' on product {$productId}.\n"); }

$colCtrl = ['ref' => 'extra:' . $ctrlId, 'label' => 'Control Options'];
$colWand = ['ref' => 'extra:' . $wandId, 'label' => 'Wand Options'];

// CH_L: chain, Corded only (wand blinds have no chain).
// C_L: cord — Corded = chain + 2*Width; a WAND split/centre draw (Center Left,
// Center Right, Split Draw 2 Wands) also has a cord = 2*Width; plain wand
// stacks → no rule → blank. Same across all systems.
$variables = [
    ['name' => 'CH_L', 'seq' => 11, 'columns' => [$colCtrl], 'rows' => [
        ['cells' => ['Corded'], 'result' => 'IF(Fit_height > 0, (Fit_height - 1500) * 2, Drop * 1.5)'],
    ]],
    ['name' => 'C_L', 'seq' => 12, 'columns' => [$colCtrl, $colWand], 'rows' => [
        ['cells' => ['Corded', ''],                   'result' => 'CH_L + 2 * Width'],
        ['cells' => ['Wand', 'Center Left'],          'result' => '2 * Width'],
        ['cells' => ['Wand', 'Center Right'],         'result' => '2 * Width'],
        ['cells' => ['Wand', 'Split Draw 2 Wands'],   'result' => '2 * Width'],
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
        json_encode($v['columns'], JSON_UNESCAPED_UNICODE),
        json_encode($v['rows'], JSON_UNESCAPED_UNICODE),
    ]);
    echo "  {$v['name']} seeded (" . count($v['rows']) . " row(s)).\n";
}

echo "\nSeeded CH_L + C_L on product {$productId} (Bev Vertical Blinds).\n";
echo "Corded: CH_L = fit-height/drop rule, C_L = CH_L + 2*Width. Wand split/centre: C_L = 2*Width, no CH_L.\n";
echo "Test (Corded): no fit height Drop=1490,Width=2360 → CH_L 2235, C_L 6955;\n";
echo "fit height 3000,Width=2000 → CH_L 3000 (loop ends 1.5m off floor), C_L 7000. Wand → no rule (blank).\n";
