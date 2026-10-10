<?php
declare(strict_types=1);

/**
 * Home Creations (Blind Matrix trade portal) — bring our two bought-in wood
 * venetians in line with the supplier's own configurator, and set up Night
 * Shade ready for its price list.
 *
 * Prices on the base grids already match the portal to the penny (list less
 * our buying discount), so this touches OPTIONS and COLOURS only — never a
 * price table.
 *
 *   Bev Embassy Faux Wood (#104)
 *     - slats: + Whisper Oak, + Brushed Graphite (50mm String + Tape);
 *       "Artic White" -> "Arctic White" (63mm)
 *     - tape colours: + None, + Brushed Graphite; Matching -> Matching Tape,
 *       Corn Silk -> Cornsilk
 *   Bev Forest Wood (#85)
 *     - Snow White switched off on plain 50mm String/Tape (it is Gloss only);
 *       Gloss Snow White pinned to the 50mm system (was "all systems")
 *     - tape colours: + Fluid Pewter; To Match -> Matching Tape,
 *       Snow Whie -> Snow White, Haze grey -> Haze Grey, Mist -> Mist Grey
 *   Both: new options, every priced choice = supplier cost + 30% (stored as the
 *   face-value price, cost kept in cost_price):
 *     Pelmet Cutting, Pelmet Size (after a pelmet cut), Alignment Multiple
 *     Blinds, Painted Ends (Forest Wood only), Motor & Parts (+ Metal Toggle
 *     Colour after Metal Toggle), Accessories (per-unit, quantity typed).
 *   Bev Night Shade (new, INACTIVE until its price tables are imported)
 *     - one system "Night Shade 25mm", 89 fabrics in bands A / B / C /
 *       Blackout, options Blind or Recess, Frame Colour, Foam Tape.
 *
 * Additive + idempotent (matched by name / label) — safe to re-run. Nothing is
 * deleted: a wrong colour is switched off (active = 0) so old quotes keep it.
 * DRY RUN unless ?apply=1. Super-admin only; run while logged in to the
 * tenant that owns the Bev products.
 *   Preview: /setup/seeds/seed_home_creations_options.php   Apply: ?apply=1
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/auth/middleware.php';
requireSuperAdmin();
require_run_confirmation();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1'); error_reporting(E_ALL); @set_time_limit(300);

$user     = current_user();
$clientId = (int) ($user['client_id'] ?? 0);
$pdo      = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$apply    = (($_GET['apply'] ?? '') === '1');

const HC_EMBASSY_ID = 104;
const HC_FOREST_ID  = 85;
const HC_NS_NAME    = 'Bev Night Shade';
const HC_SUPPLIER   = 'Home Creations';
const HC_MARKUP     = 1.30;   // supplier cost + 30% = our trade price

$colExists = static function (string $table, string $col) use ($pdo): bool {
    try { $pdo->query("SELECT `$col` FROM `$table` LIMIT 1"); return true; } catch (Throwable $e) { return false; }
};
$HAS = [
    'allow_multi'      => $colExists('product_extras', 'allow_multi'),
    'extra_len'        => $colExists('product_extras', 'length_input_label'),
    'choice_len'       => $colExists('product_extra_choices', 'length_input_label'),
    'per_unit'         => $colExists('product_extra_choices', 'price_per_unit'),
    'face_value'       => $colExists('product_extra_choices', 'face_value'),
    'cost_price'       => $colExists('product_extra_choices', 'cost_price'),
    'supplier_name'    => $colExists('products', 'supplier_name'),
    'price_source'     => $colExists('products', 'price_source'),
    'show_colour'      => $colExists('products', 'show_colour_field'),
];

$log = static function (string $s): void { echo $s, "\n"; };
$sell = static fn(float $cost): float => round($cost * HC_MARKUP + 1e-9, 2);

// ---------------------------------------------------------------- helpers ---

$product = static function (int $id) use ($pdo, $clientId): ?array {
    $st = $pdo->prepare('SELECT id, name FROM products WHERE id = ? AND client_id = ?');
    $st->execute([$id, $clientId]);
    return $st->fetch() ?: null;
};

$findExtras = static function (int $productId, string $name) use ($pdo, $clientId): array {
    $st = $pdo->prepare('SELECT id FROM product_extras WHERE client_id = ? AND product_id = ? AND name = ? ORDER BY sort_order, id');
    $st->execute([$clientId, $productId, $name]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
};

$findChoice = static function (int $extraId, string $label) use ($pdo): ?int {
    $st = $pdo->prepare('SELECT id FROM product_extra_choices WHERE product_extra_id = ? AND label = ? LIMIT 1');
    $st->execute([$extraId, $label]);
    $id = $st->fetchColumn();
    return $id === false ? null : (int) $id;
};

/** Create (or update in place) an option; returns its id (0 on dry run when new). */
$ensureExtra = static function (int $productId, string $name, array $o) use ($pdo, $clientId, $apply, $HAS, $findExtras, $log): int {
    $req   = !empty($o['required']) ? 1 : 0;
    $multi = !empty($o['multi']) ? 1 : 0;
    $len   = $o['len'] ?? null;
    $parents = $o['parents'] ?? [];
    $ids = $findExtras($productId, $name);
    if ($ids) {
        $id = $ids[0];
        $log("    = option '$name' exists (#$id)");
        if ($apply) {
            $sets = ['is_required = ?', 'active = 1']; $vals = [$req];
            if ($HAS['allow_multi']) { $sets[] = 'allow_multi = ?'; $vals[] = $multi; }
            $vals[] = $id;
            $pdo->prepare('UPDATE product_extras SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
        }
    } else {
        $log("    + option '$name'" . ($multi ? ' (tick-several)' : '') . ($parents ? ' — shows after ' . count($parents) . ' parent choice(s)' : ''));
        if (!$apply) return 0;
        $so = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_extras WHERE client_id = ? AND product_id = ?');
        $so->execute([$clientId, $productId]);
        $cols = ['client_id', 'product_id', 'parent_choice_id', 'name', 'is_required', 'sort_order', 'active'];
        $vals = [$clientId, $productId, $parents[0] ?? null, $name, $req, (int) $so->fetchColumn(), 1];
        if ($HAS['allow_multi']) { $cols[] = 'allow_multi'; $vals[] = $multi; }
        if ($HAS['extra_len'] && $len !== null) { $cols[] = 'length_input_label'; $vals[] = $len; }
        $pdo->prepare('INSERT INTO product_extras (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute($vals);
        $id = (int) $pdo->lastInsertId();
    }
    if ($apply && $parents) {
        $pdo->prepare('UPDATE product_extras SET parent_choice_id = ? WHERE id = ?')->execute([$parents[0], $id]);
        $pdo->prepare('DELETE FROM product_extra_parent_choices WHERE product_extra_id = ?')->execute([$id]);
        $j = $pdo->prepare('INSERT INTO product_extra_parent_choices (product_extra_id, product_extra_choice_id) VALUES (?, ?)');
        foreach ($parents as $pc) $j->execute([$id, $pc]);
    }
    return $id;
};

/**
 * Create (or update) a choice. $c: label, cost (supplier £, sell = +30%),
 * per_unit (bool: price is per typed quantity), len (number-box label),
 * default (bool), system_id, bands (array), sort.
 */
$ensureChoice = static function (int $extraId, array $c) use ($pdo, $apply, $HAS, $findChoice, $sell, $log): int {
    $label   = $c['label'];
    $cost    = (float) ($c['cost'] ?? 0);
    $price   = $cost > 0 ? $sell($cost) : 0.0;
    $perUnit = !empty($c['per_unit']) && $HAS['per_unit'];
    $len     = $c['len'] ?? ($perUnit ? 'Qty' : null);
    $priceTxt = $cost > 0 ? sprintf(' £%.2f%s (cost £%.2f)', $price, $perUnit ? ' each' : '', $cost) : '';
    $id = $extraId > 0 ? $findChoice($extraId, $label) : null;
    $vals = [
        'price_delta'    => $perUnit ? 0 : $price,
        'price_percent'  => 0,
        'price_per_metre'=> 0,
        'is_default'     => !empty($c['default']) ? 1 : 0,
        'active'         => 1,
    ];
    if ($HAS['per_unit'])   $vals['price_per_unit'] = $perUnit ? $price : 0;
    if ($HAS['cost_price']) $vals['cost_price'] = $cost > 0 ? $cost : null;
    if ($HAS['face_value']) $vals['face_value'] = 1;
    if ($HAS['choice_len']) $vals['length_input_label'] = $len;
    if ($id) {
        $log("        = $label$priceTxt");
        if ($apply) {
            $sets = []; foreach ($vals as $k => $_) $sets[] = "$k = ?";
            $pdo->prepare('UPDATE product_extra_choices SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute([...array_values($vals), $id]);
        }
    } else {
        $log("        + $label$priceTxt" . ($len ? " [box: $len]" : ''));
        if (!$apply || $extraId <= 0) return 0;
        $vals = ['product_extra_id' => $extraId, 'system_id' => $c['system_id'] ?? null, 'label' => $label,
                 'image_path' => null, 'sort_order' => (int) ($c['sort'] ?? 0)] + $vals;
        $pdo->prepare('INSERT INTO product_extra_choices (' . implode(',', array_keys($vals)) . ') VALUES ('
            . implode(',', array_fill(0, count($vals), '?')) . ')')->execute(array_values($vals));
        $id = (int) $pdo->lastInsertId();
    }
    if ($apply && isset($c['bands'])) {
        $pdo->prepare('DELETE FROM product_extra_choice_bands WHERE choice_id = ?')->execute([$id]);
        $ib = $pdo->prepare('INSERT INTO product_extra_choice_bands (choice_id, band_code) VALUES (?, ?)');
        foreach ($c['bands'] as $b) $ib->execute([$id, $b]);
    }
    return $id;
};

/** Build a list of choices under one option. Returns label => choice id. */
$ensureChoices = static function (int $extraId, array $choices) use ($ensureChoice): array {
    $ids = [];
    foreach ($choices as $i => $c) { $c['sort'] = $c['sort'] ?? $i; $ids[$c['label']] = $ensureChoice($extraId, $c); }
    return $ids;
};

/** Rename a choice in place (no-op if the old label is gone or the new one exists). */
$renameChoice = static function (int $extraId, string $from, string $to) use ($pdo, $apply, $findChoice, $log): void {
    $old = $findChoice($extraId, $from);
    if ($old === null) return;
    if ($findChoice($extraId, $to) !== null) { $log("        ! '$from' left alone — '$to' already exists"); return; }
    $log("        ~ $from -> $to");
    if ($apply) $pdo->prepare('UPDATE product_extra_choices SET label = ? WHERE id = ?')->execute([$to, $old]);
};

/** Add a colour to an existing colour list, copying system + band scope from a sibling. */
$addColourLike = static function (int $extraId, string $label, string $sibling, int $sort) use ($pdo, $findChoice, $ensureChoice, $log): void {
    if ($findChoice($extraId, $label) !== null) { $log("        = $label"); return; }
    $sid = $findChoice($extraId, $sibling);
    $system = null; $bands = [];
    if ($sid !== null) {
        $st = $pdo->prepare('SELECT system_id FROM product_extra_choices WHERE id = ?'); $st->execute([$sid]);
        $system = $st->fetchColumn(); $system = ($system === false || $system === null) ? null : (int) $system;
        $st = $pdo->prepare('SELECT band_code FROM product_extra_choice_bands WHERE choice_id = ?'); $st->execute([$sid]);
        $bands = $st->fetchAll(PDO::FETCH_COLUMN);
    }
    $ensureChoice($extraId, ['label' => $label, 'system_id' => $system, 'bands' => $bands, 'sort' => $sort]);
};

/** Add a slat / fabric row by cloning a sibling row's scope (system, band, supplier). */
$addSlatLike = static function (int $productId, string $band, string $name, string $siblingName) use ($pdo, $clientId, $apply, $log): void {
    $st = $pdo->prepare('SELECT id FROM product_options WHERE client_id = ? AND product_id = ? AND band_code = ? AND name = ?');
    $st->execute([$clientId, $productId, $band, $name]);
    if ($st->fetchColumn()) { $log("    = slat $name ($band)"); return; }
    $st = $pdo->prepare('SELECT * FROM product_options WHERE client_id = ? AND product_id = ? AND band_code = ? AND name = ? LIMIT 1');
    $st->execute([$clientId, $productId, $band, $siblingName]);
    $sib = $st->fetch();
    if (!$sib) { $log("    ! slat $name ($band) NOT added — no '$siblingName' in that band to copy from"); return; }
    $log("    + slat $name ($band)");
    if (!$apply) return;
    unset($sib['id']);
    foreach (['created_at', 'updated_at', 'is_default'] as $k) unset($sib[$k]);
    foreach (array_keys($sib) as $k) if (str_starts_with($k, 'source_')) unset($sib[$k]);   // a new row is no push mirror
    $sib['name'] = $name;
    if (array_key_exists('code', $sib)) $sib['code'] = '';
    $mx = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM product_options WHERE client_id = ? AND product_id = ?');
    $mx->execute([$clientId, $productId]);
    $sib['sort_order'] = (int) $mx->fetchColumn();
    $pdo->prepare('INSERT INTO product_options (' . implode(',', array_keys($sib)) . ') VALUES ('
        . implode(',', array_fill(0, count($sib), '?')) . ')')->execute(array_values($sib));
};

// ----------------------------------------------- shared venetian options ---

/** Options both wood venetians get. $extraParts adds product-only parts. */
$venetianOptions = static function (int $productId, bool $paintedEnds, bool $battery, string $valanceClips)
    use ($ensureExtra, $ensureChoices, $log): void {

    $log('  Pelmet');
    $pelmet = $ensureExtra($productId, 'Pelmet Cutting', []);
    $pc = $ensureChoices($pelmet, [
        ['label' => 'No Return',             'cost' => 2.00],
        ['label' => 'Single Return',         'cost' => 2.00],
        ['label' => 'Double Return',         'cost' => 3.50],
        ['label' => 'Corner Piece customised', 'cost' => 3.50],
        ['label' => 'No cut'],
    ]);
    $cutIds = array_values(array_filter([$pc['No Return'], $pc['Single Return'], $pc['Double Return'], $pc['Corner Piece customised']]));
    $size = $ensureExtra($productId, 'Pelmet Size', ['parents' => $cutIds]);
    $ensureChoices($size, [
        ['label' => 'BS Plus 1CM', 'default' => true],
        ['label' => 'Custom Size', 'len' => 'Pelmet size (mm)'],
    ]);

    $log('  Alignment');
    $align = $ensureExtra($productId, 'Alignment Multiple Blinds', []);
    $ensureChoices($align, [['label' => 'No', 'default' => true], ['label' => 'Yes']]);

    if ($paintedEnds) {
        $log('  Painted ends');
        $pe = $ensureExtra($productId, 'Painted Ends', []);
        $ensureChoices($pe, [
            ['label' => 'None', 'default' => true],
            ['label' => 'Elegant White',  'cost' => 3.50],
            ['label' => 'Haze Grey',      'cost' => 3.50],
            ['label' => 'Charcoal Black', 'cost' => 3.50],
        ]);
    }

    $log('  Motor & parts');
    $parts = $ensureExtra($productId, 'Motor & Parts', ['multi' => true]);
    $list = [
        ['label' => 'Motor - Tilt',              'cost' => 46.00],
        ['label' => 'Remote Control 5 Channels', 'cost' => 10.25],
        ['label' => 'Charging Cable - 5 metres', 'cost' => 6.20],
    ];
    if ($battery) $list[] = ['label' => 'Battery', 'cost' => 7.00];
    $list[] = ['label' => 'Metal Toggle', 'cost' => 1.64];
    $pp = $ensureChoices($parts, $list);
    $tog = $ensureExtra($productId, 'Metal Toggle Colour', ['required' => true, 'parents' => array_filter([$pp['Metal Toggle']])]);
    $ensureChoices($tog, [
        ['label' => 'Matt Silver', 'default' => true],
        ['label' => 'Matt Black'], ['label' => 'Matt Gold'], ['label' => 'Pewter'], ['label' => 'Antique Brass'],
    ]);

    $log('  Accessories (type a quantity)');
    $acc = $ensureExtra($productId, 'Accessories', ['multi' => true]);
    $ensureChoices($acc, [
        ['label' => 'Swivel Bracket Standard',  'cost' => 0.70, 'per_unit' => true],
        ['label' => 'Swivel Bracket Extension', 'cost' => 1.05, 'per_unit' => true],
        ['label' => '3M Velcro Pads',           'cost' => 2.36, 'per_unit' => true],
        ['label' => $valanceClips,              'cost' => 3.38, 'per_unit' => true],
    ]);
};

// ------------------------------------------------------------------- run ---

$log(($apply ? 'APPLY' : 'DRY RUN (preview only)') . " — Home Creations options, client_id $clientId");
$log(str_repeat('=', 64));
if ($apply) $pdo->beginTransaction();
try {
    // ---------------- Embassy ----------------
    $emb = $product(HC_EMBASSY_ID);
    if (!$emb) {
        $log("\n### Embassy #" . HC_EMBASSY_ID . ' NOT FOUND in this tenant — skipped');
    } else {
        $log("\n### {$emb['name']} #{$emb['id']}");
        $log('  Slats');
        foreach (['50mm String', '50mm Tape'] as $band) {
            $addSlatLike($emb['id'], $band, 'Whisper Oak', 'Morning Frost');
            $addSlatLike($emb['id'], $band, 'Brushed Graphite', 'Morning Frost');
        }
        $st = $pdo->prepare("SELECT id, band_code FROM product_options WHERE client_id = ? AND product_id = ? AND name = 'Artic White'");
        $st->execute([$clientId, $emb['id']]);
        foreach ($st->fetchAll() as $r) {
            $log("    ~ Artic White -> Arctic White ({$r['band_code']})");
            if ($apply) $pdo->prepare("UPDATE product_options SET name = 'Arctic White' WHERE id = ?")->execute([$r['id']]);
        }
        $log('  Tape colours');
        foreach ($findExtras($emb['id'], 'Colour') as $ex) {
            $log("    option #$ex");
            $renameChoice($ex, 'Matching', 'Matching Tape');
            $renameChoice($ex, 'Corn Silk', 'Cornsilk');
            $addColourLike($ex, 'Brushed Graphite', 'Elegant White', 90);
            $addColourLike($ex, 'None', 'Elegant White', 91);
        }
        $venetianOptions($emb['id'], false, true, 'Embassy Valance Clips (20 pcs)');
    }

    // ---------------- Forest Wood ----------------
    $fw = $product(HC_FOREST_ID);
    if (!$fw) {
        $log("\n### Forest Wood #" . HC_FOREST_ID . ' NOT FOUND in this tenant — skipped');
    } else {
        $log("\n### {$fw['name']} #{$fw['id']}");
        $log('  Slats');
        $st = $pdo->prepare("SELECT id, band_code FROM product_options WHERE client_id = ? AND product_id = ? AND name = 'Snow White' AND band_code IN ('String','Tape') AND active = 1");
        $st->execute([$clientId, $fw['id']]);
        foreach ($st->fetchAll() as $r) {
            $log("    - Snow White switched off on plain 50mm ({$r['band_code']}) — it is Gloss only");
            if ($apply) $pdo->prepare('UPDATE product_options SET active = 0 WHERE id = ?')->execute([$r['id']]);
        }
        $sys = $pdo->prepare("SELECT id FROM product_systems WHERE client_id = ? AND product_id = ? AND name = '50mm'");
        $sys->execute([$clientId, $fw['id']]);
        $fw50 = (int) ($sys->fetchColumn() ?: 0);
        if ($fw50) {
            $st = $pdo->prepare("SELECT id FROM product_options WHERE client_id = ? AND product_id = ? AND band_code = 'Gloss String' AND system_id IS NULL");
            $st->execute([$clientId, $fw['id']]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $oid) {
                $log('    ~ Gloss String slat pinned to the 50mm system (was all systems)');
                if ($apply) $pdo->prepare('UPDATE product_options SET system_id = ? WHERE id = ?')->execute([$fw50, $oid]);
            }
        }
        $log('  Tape colours');
        foreach ($findExtras($fw['id'], 'Colour') as $ex) {
            $log("    option #$ex");
            $renameChoice($ex, 'To Match', 'Matching Tape');
            $renameChoice($ex, 'To match', 'Matching Tape');
            $renameChoice($ex, 'Snow Whie', 'Snow White');
            $renameChoice($ex, 'Haze grey', 'Haze Grey');
            $renameChoice($ex, 'Mist', 'Mist Grey');
            // Only the 25mm / 38mm lists carry the full colour range.
            if ($findChoice($ex, 'Anthracite') !== null) $addColourLike($ex, 'Fluid Pewter', 'Elegant White', 90);
        }
        $venetianOptions($fw['id'], true, false, 'Forestwood Valance Clips (20 pcs)');
    }

    // ---------------- Night Shade ----------------
    $log("\n### " . HC_NS_NAME);
    $f = $pdo->prepare('SELECT id FROM products WHERE client_id = ? AND name = ?');
    $f->execute([$clientId, HC_NS_NAME]);
    $nsId = (int) ($f->fetchColumn() ?: 0);
    if ($nsId) {
        $log("  = product exists (#$nsId) — options/fabrics topped up, active flag left alone");
    } else {
        $log('  + product (inactive until its price tables are imported)');
        if ($apply) {
            $ss = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM products WHERE client_id = ?'); $ss->execute([$clientId]);
            $cols = ['client_id' => $clientId, 'name' => HC_NS_NAME, 'option_label' => 'Fabric', 'sort_order' => (int) $ss->fetchColumn(), 'active' => 0];
            if ($HAS['show_colour'])   $cols['show_colour_field'] = 1;
            if ($HAS['supplier_name']) $cols['supplier_name'] = HC_SUPPLIER;
            if ($HAS['price_source'])  $cols['price_source'] = 'supplier';
            $pdo->prepare('INSERT INTO products (' . implode(',', array_keys($cols)) . ') VALUES ('
                . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($cols));
            $nsId = (int) $pdo->lastInsertId();
        }
    }
    if ($apply && $nsId) {
        $q = $pdo->prepare("SELECT id FROM product_systems WHERE client_id = ? AND product_id = ? AND name = 'Night Shade 25mm'");
        $q->execute([$clientId, $nsId]);
        if (!$q->fetchColumn()) {
            $pdo->prepare("INSERT INTO product_systems (client_id, product_id, name, sort_order, active, is_default) VALUES (?, ?, 'Night Shade 25mm', 0, 1, 1)")->execute([$clientId, $nsId]);
        }
    }
    $log("  System: Night Shade 25mm");

    // Fabrics: range_colour + Home Creations price group (band).
    $NS_FABRICS = [
        'A' => ['Celeste' => ['Anthracite','Cornflower','Duckegg','Flint','Nude','Sage','Silver','Tuscan Red'],
                'Halo'    => ['Birch','Frost','Iron','Ivory','Linen','Marine','Meteor','Mushroom','Praline','Sea Mist','Soft Damson','Willow']],
        'B' => ['Astral'      => ['Blush','Eucalyptus','Fossil','Lake Blue','Mineral','Pebble'],
                'Fresco'      => ['Muted Sage','Natural Calico','Pale Grey'],
                'Halo Pro FR' => ['Birch','Frost','Ivory','Meteor'],
                'Linen'       => ['Charcoal','Fawn','Light Grey','Oatmeal'],
                'Luna'        => ['Bone','Charcoal','Cloud','Cool Blue','Dusky Rose','Graphite','Parchment','Pumice','Sage','Taupe','Walnut'],
                'Mirage'      => ['Dark Anthracite','Mushroom','Navy','Slate'],
                'Mode'        => ['Charcoal','Cream','Dark Olive','Grey','Navy','Truffle','White'],
                'Raffia'      => ['Flax','Pebble','Slate','Wheat'],
                'Sheer'       => ['Grey']],
        'C' => ['Fresco'      => ['Barley Beige','Moss Green','Soft Pewter'],
                'Luna Pro FR' => ['Bone','Cloud','Graphite','Pumice'],
                'Mirage'      => ['Marine','Midnight','Mocha','Shadow'],
                'Mode'        => ['Forest','Granite','Ivory','Lake Blue'],
                'Raffia'      => ['Barley','Cotton','Flint','Stone']],
        'Blackout' => ['Blackout' => ['Almost Black','Cloud Grey','Granite','Linen','Nautical Gray','Royal Blue']],
    ];
    $nAdd = 0; $nHave = 0; $sort = 0;
    $chk = $pdo->prepare('SELECT id FROM product_options WHERE client_id = ? AND product_id = ? AND band_code = ? AND name = ? AND colour = ?');
    $ins = $pdo->prepare('INSERT INTO product_options (client_id, product_id, system_id, band_code, supplier_name, name, colour, code, sort_order, active) VALUES (?, ?, NULL, ?, ?, ?, ?, \'\', ?, 1)');
    foreach ($NS_FABRICS as $band => $ranges) {
        $n = 0;
        foreach ($ranges as $range => $colours) {
            foreach ($colours as $colour) {
                $n++; $sort++;
                if ($nsId) { $chk->execute([$clientId, $nsId, $band, $range, $colour]); if ($chk->fetchColumn()) { $nHave++; continue; } }
                $nAdd++;
                if ($apply && $nsId) $ins->execute([$clientId, $nsId, $band, HC_SUPPLIER, $range, $colour, $sort]);
            }
        }
        $log("  Band $band: $n fabrics");
    }
    $log("  Fabrics: $nAdd to add, $nHave already there");

    $nsx = $nsId ?: 0;
    if ($nsx || !$apply) {
        $log('  Options');
        $r = $ensureExtra($nsx, 'Blind or Recess', ['required' => true]);
        $ensureChoices($r, [['label' => 'Recess', 'default' => true], ['label' => 'Blind Size']]);
        $fc = $ensureExtra($nsx, 'Frame Colour', ['required' => true]);
        $ensureChoices($fc, [['label' => 'White', 'default' => true], ['label' => 'Anthracite']]);
        $ft = $ensureExtra($nsx, 'Foam Tape Rolls', ['multi' => true]);
        $ensureChoices($ft, [
            ['label' => 'White Foam Tape (5m)', 'cost' => 2.00],
            ['label' => 'Black Foam Tape (5m)', 'cost' => 2.00],
        ]);
    }

    if ($apply) $pdo->commit();
} catch (Throwable $e) {
    if ($apply && $pdo->inTransaction()) $pdo->rollBack();
    echo "\nFAILED: ", $e->getMessage(), "\n", $e->getFile(), ':', $e->getLine(), "\n";
    exit(1);
}

$log("\n" . str_repeat('=', 64));
$log($apply ? 'DONE. Push the Bev products to trade accounts (Master admin > Push updates) when happy.'
            : 'PREVIEW ONLY — nothing changed. Re-run with ?apply=1 to apply.');
