<?php
declare(strict_types=1);

/**
 * 2026 price-list corrections, part 2 (John, 2026-10-13): motors and pleated
 * face-fix brackets, on the FACTORY catalogue.
 *
 *   1. Vogue vertical tilt-only motorisation (Vouge Vertical 2026.xlsx: cost x
 *      the 30% motor markup = the price list): a "Motorisation (Tilt Only)"
 *      option on the Vogue system only, with Tilt Motor £66.70, then Remote
 *      £16.47 and Charger £11.07 / 3m Cable £7.54 once a motor is picked.
 *   2. Night & Day motors, the same prices as the roller (John): 32mm £40.29,
 *      40mm £48.09, 40mm hard-wired £75.01, then remotes £15.08 / £20.54 /
 *      £28.60 and charger £4.23, 3m cable £3.90, mini hub £80.60, hub £109.20.
 *      The remote and extras groups copy the roller's settings (required,
 *      tick-several).
 *   3. Pleated (freehang and skylight) face-fix brackets (Cell Skylight
 *      2026.xlsx): Face Fix £1.09, 40mm £2.30, 70mm £2.30. Top fix stays £0,
 *      it's always included.
 *
 * Idempotent. Changes reach trade accounts with the next catalogue push.
 *   /setup/tools/price_corrections_2026b.php           → what it would change
 *   /setup/tools/price_corrections_2026b.php?apply=1   → make the changes
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__, 2) . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$fac = (int) (function_exists('factory_client_id') ? factory_client_id() : 3);
if (PHP_SAPI === 'cli') parse_str(implode('&', array_slice($argv, 1)), $_GET);
$apply = !empty($_GET['apply']);
echo $apply ? "APPLYING 2026 corrections part 2 (factory client $fac)\n\n" : "PREVIEW — nothing is changed. Add ?apply=1 to make these changes.\n\n";

$one = static function (string $sql, array $p = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetch(PDO::FETCH_ASSOC) ?: null; };
$product = static fn (string $name) => $one('SELECT id FROM products WHERE client_id = ? AND name = ? LIMIT 1', [$fac, $name]);
$hasCol = static function (string $t, string $c) use ($pdo): bool {
    return (bool) $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c'")->fetchColumn();
};

/** Add (or find) an option; $parents = choice ids it shows under. $flags copied in. */
$addExtra = static function (int $pid, string $name, array $parents, array $flags = []) use ($pdo, $fac, $one, $apply, $hasCol): ?int {
    $first = $parents[0] ?? null;
    $e = $one('SELECT id FROM product_extras WHERE client_id = ? AND product_id = ? AND name = ? AND (parent_choice_id <=> ?) LIMIT 1', [$fac, $pid, $name, $first]);
    if ($e) {
        echo "   option \"$name\" already there\n";
        if ($apply) foreach ($parents as $pc) $pdo->prepare('INSERT IGNORE INTO product_extra_parent_choices (product_extra_id, product_extra_choice_id) VALUES (?, ?)')->execute([(int) $e['id'], $pc]);
        return (int) $e['id'];
    }
    echo "   + option \"$name\"" . ($parents ? ' (shown when choice #' . implode('/#', $parents) . ' is picked)' : '') . "\n";
    if (!$apply) return null;
    $sort = (int) $pdo->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM product_extras WHERE product_id = $pid")->fetchColumn();
    $row = ['client_id' => $fac, 'product_id' => $pid, 'parent_choice_id' => $first, 'name' => $name, 'is_required' => 1, 'sort_order' => $sort, 'active' => 1];
    foreach ($flags as $k => $v) if ($hasCol('product_extras', $k)) $row[$k] = $v;
    $pdo->prepare('INSERT INTO product_extras (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));
    $id = (int) $pdo->lastInsertId();
    foreach ($parents as $pc) $pdo->prepare('INSERT INTO product_extra_parent_choices (product_extra_id, product_extra_choice_id) VALUES (?, ?)')->execute([$id, $pc]);
    return $id;
};
/** Add (or fix the price of) a choice. */
$addChoice = static function (?int $extraId, string $label, float $price, bool $default = false, ?int $systemId = null) use ($pdo, $one, $apply): ?int {
    $c = $extraId ? $one('SELECT id, price_delta, system_id FROM product_extra_choices WHERE product_extra_id = ? AND label = ? AND (system_id <=> ?) LIMIT 1', [$extraId, $label, $systemId]) : null;
    $desc = '£' . number_format($price, 2);
    if ($c) {
        if (abs((float) $c['price_delta'] - $price) < 0.005) { echo "     choice \"$label\" ($desc) already right\n"; return (int) $c['id']; }
        echo "     ~ choice \"$label\": £" . number_format((float) $c['price_delta'], 2) . " → $desc\n";
        if ($apply) $pdo->prepare('UPDATE product_extra_choices SET price_delta = ? WHERE id = ?')->execute([$price, (int) $c['id']]);
        return (int) $c['id'];
    }
    echo "     + choice \"$label\" ($desc)" . ($default ? ' [default]' : '') . "\n";
    if (!$apply || !$extraId) return null;
    $sort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM product_extra_choices WHERE product_extra_id = ' . (int) $extraId)->fetchColumn();
    $pdo->prepare('INSERT INTO product_extra_choices (product_extra_id, system_id, label, price_delta, is_default, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, 1)')
        ->execute([$extraId, $systemId, $label, $price, $default ? 1 : 0, $sort]);
    return (int) $pdo->lastInsertId();
};
/** The roller's own settings for an option group, so copies behave the same. */
$rollerFlags = static function (string $name) use ($one, $fac, $hasCol): array {
    $cols = array_values(array_filter(['allow_multi', 'collapse_multi', 'is_required'], fn ($c) => $hasCol('product_extras', $c)));
    $r = $one('SELECT ' . implode(', ', $cols) . " FROM product_extras e JOIN products p ON p.id = e.product_id
                WHERE p.client_id = ? AND p.name = 'Bev Roller Blinds' AND e.name = ? AND e.active = 1 LIMIT 1", [$fac, $name]);
    return $r ? array_map('intval', $r) : [];
};

// ---------------------------------------------------------------- 1. Vogue motor
echo "1. Vogue vertical: tilt-only motorisation\n";
$vert = $product('Bev Vertical Blinds');
$vogue = $vert ? $one("SELECT id FROM product_systems WHERE client_id = ? AND product_id = ? AND name LIKE 'Vogue%' LIMIT 1", [$fac, $vert['id']]) : null;
if (!$vogue) {
    echo "   Vogue system not found — skipped.\n";
} else {
    $vid = (int) $vert['id']; $vs = (int) $vogue['id'];
    $mx = $addExtra($vid, 'Motorisation (Tilt Only)', [], ['is_required' => 1]);
    $addChoice($mx, 'None', 0, true, $vs);
    $motor = $addChoice($mx, 'Tilt Motor', 66.70, false, $vs);
    $rem = $addExtra($vid, 'Motor Remote', $motor ? [$motor] : [], ['is_required' => 1]);
    $addChoice($rem, 'Not Needed', 0, true);
    $addChoice($rem, 'Remote', 16.47);
    $ext = $addExtra($vid, 'Motor Extras', $motor ? [$motor] : [], $rollerFlags('Optional Extras'));
    $addChoice($ext, 'None', 0, true);
    $addChoice($ext, 'Charger', 11.07);
    $addChoice($ext, '3m Cable', 7.54);
}
echo "\n";

// ---------------------------------------------------------------- 2. Night & Day motors
echo "2. Night & Day: motors (same as roller)\n";
$nd = $product('Bev Night & Day');
if (!$nd) {
    echo "   Bev Night & Day not found — skipped.\n";
} else {
    $nid = (int) $nd['id'];
    $mx = $addExtra($nid, 'Motorisation', [], ['is_required' => 1]);
    $addChoice($mx, 'None', 0, true);
    $motors = array_values(array_filter([
        $addChoice($mx, '32mm Motor', 40.29),
        $addChoice($mx, '40mm Motor', 48.09),
        $addChoice($mx, '40mm Hard Wire Motor', 75.01),
    ]));
    $rem = $addExtra($nid, 'Remote Options', $motors, $rollerFlags('Remote Options'));
    $addChoice($rem, 'Not Needed', 0, true);
    $addChoice($rem, 'Single channel', 15.08);
    $addChoice($rem, '15 Channel', 20.54);
    $addChoice($rem, '15 Channel Wall Mount', 28.60);
    $ext = $addExtra($nid, 'Optional Extras', $motors, $rollerFlags('Optional Extras'));
    $addChoice($ext, 'None', 0, true);
    $addChoice($ext, 'Charger', 4.23);
    $addChoice($ext, '3Mtr Charging Cable', 3.90);
    $addChoice($ext, 'Mini Hub', 80.60);
    $addChoice($ext, 'Hub', 109.20);
}
echo "\n";

// ---------------------------------------------------------------- 3. Pleated brackets
echo "3. Pleated (freehang + skylight): face-fix brackets\n";
$pl = $product('Bev Pleated');
$bt = $pl ? $one("SELECT id FROM product_extras WHERE client_id = ? AND product_id = ? AND name = 'Bracket Type' AND active = 1 LIMIT 1", [$fac, $pl['id']]) : null;
if (!$bt) {
    echo "   Bev Pleated / Bracket Type not found — skipped.\n";
} else {
    foreach (['Top Fix' => 0.0, 'Face Fix' => 1.09, '40mm Face Fix' => 2.30, '70mm Face Fix' => 2.30] as $label => $price) {
        $c = $one('SELECT id FROM product_extra_choices WHERE product_extra_id = ? AND label = ? AND active = 1 LIMIT 1', [(int) $bt['id'], $label]);
        if (!$c) { echo "     choice \"$label\" not found — skipped\n"; continue; }
        $addChoice((int) $bt['id'], $label, $price);
    }
}
echo "\n";

echo $apply
    ? "Done. Push the factory catalogue (Push updates → Bev → every account) so trade accounts get these prices.\n"
    : "Nothing changed. Run with ?apply=1 to make these changes.\n";
