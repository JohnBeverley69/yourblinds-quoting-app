<?php
declare(strict_types=1);

/**
 * 2026 price-list corrections on the FACTORY catalogue (John, 2026-10-13),
 * found by checking the system against BeverleyBlinds Master 2026.pdf and
 * Roller 2026.xlsx (Roller tab = trade price).
 *
 *   1. PF Venetian, SPECIAL frame (Golden Oak / Mahogany) = standard frame + 35%.
 *      Its grids held different figures. Each special-frame band grid is
 *      rebuilt as the same band's standard-frame grid x 1.35 (same supplier
 *      list basis; both frames carry the same buying discount and markup).
 *      Price undo is recorded per system.
 *   2. Roller, Grip Fix Cassette (Louvolite 32 grip fit): was £0. Width table
 *      £18.14-£31.30, plus a "Grip Fix End Caps" option (Standard / Chrome £3.15).
 *   3. Roller, Louvolite back bar: added to Bottom Bar Options with its width table.
 *   4. Roller, pulls: new "Pull" option: None / Wood Barrel Pull £1.20 /
 *      Metallic Fabric Pull £2.37.
 *   5. Roller, Senses fascia centre support £0.58 each (workbook £0.582):
 *      new "Centre Supports" option under the Senses fascia, priced per unit
 *      with a Qty box.
 *
 * Idempotent: re-running finds what it already added and only fixes prices.
 * Changes reach trade accounts with the next catalogue push.
 *
 * Super-admin, web-runnable:
 *   /setup/tools/price_corrections_2026.php           → what it would change
 *   /setup/tools/price_corrections_2026.php?apply=1   → make the changes
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__, 2) . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
require_once dirname(__DIR__, 2) . '/_partials/price_table_undo.php';
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$fac = (int) (function_exists('factory_client_id') ? factory_client_id() : 3);
if (PHP_SAPI === 'cli') parse_str(implode('&', array_slice($argv, 1)), $_GET);
$apply = !empty($_GET['apply']);
echo $apply ? "APPLYING 2026 price corrections (factory client $fac)\n\n" : "PREVIEW — nothing is changed. Add ?apply=1 to make these changes.\n\n";

$one = static function (string $sql, array $p = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetch(PDO::FETCH_ASSOC) ?: null; };
$all = static function (string $sql, array $p = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); };
$product = static fn (string $name) => $one('SELECT id FROM products WHERE client_id = ? AND name = ? LIMIT 1', [$fac, $name]);

// ---------------------------------------------------------------- 1. PF Venetian special frame
echo "1. PF Venetian special frame = standard frame + 35%\n";
$pfv = $product('Bev PF Venetian');
if (!$pfv) {
    echo "   Bev PF Venetian not found — skipped.\n\n";
} else {
    $std = $one("SELECT id FROM product_systems WHERE client_id = ? AND product_id = ? AND name = 'Standard Frame'", [$fac, $pfv['id']]);
    $sp  = $one("SELECT id FROM product_systems WHERE client_id = ? AND product_id = ? AND name = 'Special Frame'", [$fac, $pfv['id']]);
    if (!$std || !$sp) {
        echo "   Standard/Special Frame systems not found — skipped.\n\n";
    } else {
        $spTabs = $all('SELECT id, band_code FROM price_tables WHERE client_id = ? AND product_id = ? AND system_id = ? AND active = 1', [$fac, $pfv['id'], $sp['id']]);
        $plan = []; $changed = 0; $orphans = 0;
        foreach ($spTabs as $t) {
            $src = $one('SELECT id FROM price_tables WHERE client_id = ? AND product_id = ? AND system_id = ? AND band_code = ? AND active = 1 LIMIT 1',
                        [$fac, $pfv['id'], $std['id'], $t['band_code']]);
            if (!$src) { echo "   band {$t['band_code']}: no standard-frame twin — skipped\n"; continue; }
            $stdCells = [];
            foreach ($all('SELECT width_mm, drop_mm, price FROM price_table_rows WHERE price_table_id = ?', [$src['id']]) as $r) {
                $stdCells[$r['width_mm'] . 'x' . $r['drop_mm']] = (float) $r['price'];
            }
            $n = 0; $ex = null;
            foreach ($all('SELECT id, width_mm, drop_mm, price FROM price_table_rows WHERE price_table_id = ?', [$t['id']]) as $r) {
                $k = $r['width_mm'] . 'x' . $r['drop_mm'];
                if (!isset($stdCells[$k])) { $orphans++; continue; }
                $want = round($stdCells[$k] * 1.35, 2);
                if (abs($want - (float) $r['price']) >= 0.005) {
                    $plan[] = [(int) $r['id'], $want]; $n++;
                    $ex ??= "{$r['width_mm']}x{$r['drop_mm']}: list £" . number_format((float) $r['price'], 2) . " → £" . number_format($want, 2);
                }
            }
            $changed += $n;
            echo "   band {$t['band_code']}: $n cell(s) to change" . ($ex ? " (e.g. $ex)" : '') . "\n";
        }
        if ($orphans) echo "   $orphans special-frame cell(s) have no standard-frame twin — left as they are\n";
        if ($apply && $plan) {
            pu_begin($pdo, $fac, 'system:' . (int) $sp['id'], array_column($spTabs, 'id'), 'PF Venetian special frame = standard + 35% (2026 list)');
            $u = $pdo->prepare('UPDATE price_table_rows SET price = ? WHERE id = ?');
            $pdo->beginTransaction();
            foreach ($plan as [$id, $p]) $u->execute([$p, $id]);
            $pdo->commit();
            pu_finish_all();
            echo "   → $changed cell(s) updated (Undo is on the price grid page)\n";
        }
        echo "\n";
    }
}

// ---------------------------------------------------------------- roller helpers
$roller = $product('Bev Roller Blinds');
if (!$roller) { echo "Bev Roller Blinds not found — roller steps skipped.\n"; exit; }
$rid = (int) $roller['id'];

$findExtra = static fn (string $name, ?int $parent = null) => $one(
    'SELECT id FROM product_extras WHERE client_id = ? AND product_id = ? AND name = ? AND (parent_choice_id <=> ?) LIMIT 1',
    [$fac, $rid, $name, $parent]);
$findChoice = static fn (int $extraId, string $label) => $one(
    'SELECT * FROM product_extra_choices WHERE product_extra_id = ? AND label = ? LIMIT 1', [$extraId, $label]);
$choiceByLabel = static fn (string $extraName, string $label) => $one(
    'SELECT c.* FROM product_extra_choices c JOIN product_extras e ON e.id = c.product_extra_id
      WHERE e.client_id = ? AND e.product_id = ? AND e.name = ? AND c.label = ? AND c.active = 1 LIMIT 1',
    [$fac, $rid, $extraName, $label]);

$addExtra = static function (string $name, ?int $parent, int $required) use ($pdo, $fac, $rid, $findExtra, $apply): ?int {
    $e = $findExtra($name, $parent);
    if ($e) { echo "   option \"$name\" already there\n"; return (int) $e['id']; }
    echo "   + option \"$name\"" . ($parent ? " (shown when choice #$parent is picked)" : '') . "\n";
    if (!$apply) return null;
    $sort = (int) $pdo->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM product_extras WHERE product_id = $rid")->fetchColumn();
    $pdo->prepare('INSERT INTO product_extras (client_id, product_id, parent_choice_id, name, is_required, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, 1)')
        ->execute([$fac, $rid, $parent, $name, $required, $sort]);
    $id = (int) $pdo->lastInsertId();
    if ($parent) $pdo->prepare('INSERT INTO product_extra_parent_choices (product_extra_id, product_extra_choice_id) VALUES (?, ?)')->execute([$id, $parent]);
    return $id;
};
$addChoice = static function (?int $extraId, string $label, array $set) use ($pdo, $findChoice, $apply): ?int {
    $c = $extraId ? $findChoice($extraId, $label) : null;
    $desc = isset($set['price_per_unit']) ? '£' . number_format($set['price_per_unit'], 2) . ' each' : (isset($set['price_delta']) && $set['price_delta'] ? '£' . number_format($set['price_delta'], 2) : '£0');
    if ($c) {
        $diff = [];
        foreach ($set as $k => $v) if ((string) $c[$k] !== (string) $v && !(is_numeric($c[$k]) && is_numeric($v) && abs((float) $c[$k] - (float) $v) < 0.005)) $diff[$k] = $v;
        if (!$diff) { echo "     choice \"$label\" ($desc) already right\n"; return (int) $c['id']; }
        echo "     ~ choice \"$label\" → $desc\n";
        if ($apply) {
            $pdo->prepare('UPDATE product_extra_choices SET ' . implode(', ', array_map(fn ($k) => "$k = ?", array_keys($diff))) . ' WHERE id = ?')
                ->execute([...array_values($diff), (int) $c['id']]);
        }
        return (int) $c['id'];
    }
    echo "     + choice \"$label\" ($desc)\n";
    if (!$apply || !$extraId) return null;
    $sort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM product_extra_choices WHERE product_extra_id = ' . (int) $extraId)->fetchColumn();
    $cols = array_merge(['product_extra_id', 'label', 'sort_order', 'active'], array_keys($set));
    $pdo->prepare('INSERT INTO product_extra_choices (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
        ->execute([$extraId, $label, $sort, 1, ...array_values($set)]);
    return (int) $pdo->lastInsertId();
};
$setWidthTable = static function (?int $choiceId, array $rows, string $what) use ($pdo, $all, $apply): void {
    $have = [];
    if ($choiceId) foreach ($all('SELECT width_mm, price FROM extra_choice_price_rows WHERE product_extra_choice_id = ?', [$choiceId]) as $r) $have[(int) $r['width_mm']] = (float) $r['price'];
    $n = 0; foreach ($rows as $w => $p) if (!isset($have[$w]) || abs($have[$w] - $p) >= 0.005) $n++;
    $extra = array_diff_key($have, $rows);
    if (!$n && !$extra) { echo "     $what width prices already right\n"; return; }
    echo "     $what width prices: $n to set (" . reset($rows) . "–" . end($rows) . ")" . ($extra ? ', ' . count($extra) . ' stray width(s) removed' : '') . "\n";
    if (!$apply || !$choiceId) return;
    $up = $pdo->prepare('INSERT INTO extra_choice_price_rows (product_extra_choice_id, width_mm, price) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE price = VALUES(price)');
    foreach ($rows as $w => $p) $up->execute([$choiceId, $w, $p]);
    if ($extra) $pdo->prepare('DELETE FROM extra_choice_price_rows WHERE product_extra_choice_id = ? AND width_mm IN (' . implode(',', array_map('intval', array_keys($extra))) . ')')->execute([$choiceId]);
};

// ---------------------------------------------------------------- 2. Grip Fix Cassette
echo "2. Roller: Louvolite 32 grip fit (Grip Fix Cassette)\n";
$grip = $choiceByLabel('Fascia Options', 'Grip Fix Cassette');
if (!$grip) {
    echo "   Grip Fix Cassette choice not found — skipped.\n";
} else {
    $setWidthTable((int) $grip['id'], [600 => 18.14, 900 => 20.77, 1200 => 23.41, 1500 => 26.04, 1800 => 28.67, 2100 => 31.30], 'Grip Fix Cassette');
    $caps = $addExtra('Grip Fix End Caps', (int) $grip['id'], 1);
    $addChoice($caps, 'Standard', ['price_delta' => 0, 'is_default' => 1]);
    $addChoice($caps, 'Chrome', ['price_delta' => 3.15, 'is_default' => 0]);
}
echo "\n";

// ---------------------------------------------------------------- 3. Louvolite back bar
echo "3. Roller: Louvolite back bar\n";
$bb = $findExtra('Bottom Bar Options', null);
if (!$bb) {
    echo "   Bottom Bar Options not found — skipped.\n";
} else {
    $bbc = $addChoice((int) $bb['id'], 'Louvolite Back Bar', ['price_delta' => 0]);
    $setWidthTable($bbc, [610 => 5.39, 762 => 6.03, 914 => 6.67, 1067 => 7.32, 1219 => 7.96, 1524 => 9.96, 1829 => 11.25,
                          2134 => 12.54, 2438 => 13.82, 2913 => 15.83, 3250 => 17.26, 3500 => 18.32, 3900 => 20.01, 4500 => 21.84], 'Louvolite Back Bar');
}
echo "\n";

// ---------------------------------------------------------------- 4. Pulls
echo "4. Roller: pulls\n";
$pull = $addExtra('Pull', null, 1);
$addChoice($pull, 'None', ['price_delta' => 0, 'is_default' => 1]);
$addChoice($pull, 'Wood Barrel Pull', ['price_delta' => 1.20, 'is_default' => 0]);
$addChoice($pull, 'Metallic Fabric Pull', ['price_delta' => 2.37, 'is_default' => 0]);
echo "\n";

// ---------------------------------------------------------------- 5. Senses centre support
echo "5. Roller: Senses fascia centre support\n";
$senses = $choiceByLabel('Fascia Options', 'Senses');
if (!$senses) {
    echo "   Senses fascia choice not found — skipped.\n";
} else {
    $cs = $addExtra('Centre Supports', (int) $senses['id'], 1);
    $addChoice($cs, 'None', ['price_delta' => 0, 'is_default' => 1]);
    $addChoice($cs, 'Centre Support', ['price_delta' => 0, 'price_per_unit' => 0.58, 'length_input_label' => 'Qty', 'is_default' => 0]);
}
echo "\n";

echo $apply
    ? "Done. Push the factory catalogue (Push updates → Bev Roller Blinds and Bev PF Venetian → every account) so trade accounts get these prices.\n"
    : "Nothing changed. Run with ?apply=1 to make these changes.\n";
