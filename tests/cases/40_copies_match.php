<?php
declare(strict_types=1);

/**
 * COPIES MATCH. A product is copied three ways — the catalogue push (factory →
 * trade account), opening a new company with its catalogue seeded, and the
 * Duplicate button. Each copy must carry EVERY setting.
 *
 * How: a probe product is built in the factory with every column of every
 * catalogue table set to a non-default value — the column list is read from the
 * database itself, so a column added next month is included automatically.
 * Then each copy is compared column by column. A copy path that forgets a
 * column (the "twin drift" bug behind length_input_label, price_per_unit,
 * code, face_value…) fails here.
 *
 * KNOWN_GAPS lists columns a path is known not to copy today. Each one is either
 * deliberate (says why) or a bug waiting for John's decision (marked BUG). When a
 * gap is fixed, delete its line here.
 */

require_once APP_ROOT . '/_partials/catalogue_push.php';
require_once APP_ROOT . '/_partials/seed_client_from_template.php';

const KNOWN_GAPS = [
    // Deliberate.
    'push.products.cost_price'                    => 'deliberate: the factory\'s buying cost is never pushed to a tenant',
    'push.product_options.cost_price'             => 'deliberate: buying cost never pushed',
    'push.product_extra_choices.cost_price'       => 'deliberate: buying cost never pushed',
    'push.price_table_rows.cost'                  => 'deliberate: buying cost never pushed',
    'push.products.supplier_name'                 => 'deliberate: a tenant buys our product from US, so it has no outside supplier',
    'push.products.sort_order'                    => 'deliberate: placed at the end of the tenant\'s own list',
    'duplicate.products.name'                     => 'deliberate: " (copy)" is added',
    'duplicate.products.sort_order'               => 'deliberate: the copy goes to the end of the list',
];

/** Columns that are identities or links — checked through the links, not compared raw. */
function cp_structural(string $col): bool
{
    return in_array($col, ['id', 'client_id', 'product_id', 'product_extra_id', 'price_table_id', 'product_extra_choice_id',
            'choice_id', 'option_id', 'system_id', 'parent_choice_id', 'created_at', 'updated_at'], true)
        || strncmp($col, 'source_', 7) === 0;
}

/** Column metadata (non-generated) for a table, from the database itself. */
function cp_columns(string $table): array
{
    return t_rows("SELECT column_name AS name, data_type AS type, column_type AS ctype, column_default AS def,
                          is_nullable AS nullable, character_maximum_length AS len, numeric_precision AS prec, numeric_scale AS scale, extra AS extra
                     FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = ?
                      AND extra NOT LIKE '%GENERATED%' AND extra NOT IN ('VIRTUAL','PERSISTENT','STORED')
                 ORDER BY ordinal_position", [$table]);
}

/** A value for a column that is NOT its default, so a forgotten column shows. */
function cp_value(array $c, string $table)
{
    $def = $c['def'] === null || strtoupper((string) $c['def']) === 'NULL' ? null : trim((string) $c['def'], "'");
    $t = strtolower($c['type']);
    if ($t === 'enum') {
        preg_match_all("/'([^']*)'/", $c['ctype'], $m);
        foreach ($m[1] as $v) if ($v !== $def) return $v;
        return $m[1][0];
    }
    if ($t === 'tinyint') return ((string) $def === '1') ? 0 : 1;
    if (in_array($t, ['int', 'smallint', 'mediumint', 'bigint'], true)) return ((string) $def === '7') ? 8 : 7;
    if (in_array($t, ['decimal', 'double', 'float'], true)) {
        $v = ((int) ($c['scale'] ?? 2)) > 0 ? 1.25 : 2;
        return ((string) $def === (string) $v) ? 2 : $v;
    }
    if (in_array($t, ['date'], true)) return '2026-01-02';
    if (in_array($t, ['datetime', 'timestamp'], true)) return '2026-01-02 03:04:05';
    if (in_array($t, ['json'], true) || ($t === 'longtext' && stripos((string) $c['ctype'], 'json') !== false)) return '{"t":1}';
    $len = (int) ($c['len'] ?? 255);
    $v = substr('T' . $c['name'], 0, max(1, min($len, 40)));
    return $v === $def ? 'U' : $v;
}

/** Insert a probe row: every non-structural column gets a non-default value, then $fixed on top. */
function cp_insert(string $table, array $fixed): int
{
    $row = [];
    foreach (cp_columns($table) as $c) {
        if (cp_structural($c['name'])) continue;
        if (stripos((string) $c['extra'], 'auto_increment') !== false) continue;
        $row[$c['name']] = cp_value($c, $table);
    }
    $row = $fixed + $row;
    db()->prepare("INSERT INTO `$table` (`" . implode('`,`', array_keys($row)) . '`) VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')')
        ->execute(array_values($row));
    return (int) db()->lastInsertId();
}

/** Build the probe product in the factory. Returns its ids. */
function cp_build_probe(): array
{
    $p = [];
    $p['product'] = cp_insert('products', ['client_id' => 3, 'name' => 'Bev ZZ Copy Probe', 'active' => 1, 'price_source' => 'own',
        'category_id' => null, 'requires_option' => 1, 'width_only' => 0, 'price_per_slat' => 0, 'price_per_sqm' => 1]);
    $p['sys1'] = cp_insert('product_systems', ['client_id' => 3, 'product_id' => $p['product'], 'name' => 'Probe System A', 'active' => 1, 'is_default' => 0]);
    $p['sys2'] = cp_insert('product_systems', ['client_id' => 3, 'product_id' => $p['product'], 'name' => 'Probe System B', 'active' => 1, 'is_default' => 1]);
    $p['opt']  = cp_insert('product_options', ['client_id' => 3, 'product_id' => $p['product'], 'system_id' => $p['sys1'],
        'name' => 'Probe Fabric', 'band_code' => 'PB', 'active' => 1, 'is_default' => 1]);
    $p['table'] = cp_insert('price_tables', ['client_id' => 3, 'product_id' => $p['product'], 'system_id' => $p['sys1'],
        'band_code' => 'PB', 'name' => 'Probe Grid', 'active' => 1]);
    foreach ([[1000, 1000, 10], [1000, 2000, 20], [2000, 2000, 30]] as [$w, $d, $pr]) {
        cp_insert('price_table_rows', ['price_table_id' => $p['table'], 'width_mm' => $w, 'drop_mm' => $d, 'price' => $pr]);
    }
    $p['extraA'] = cp_insert('product_extras', ['client_id' => 3, 'product_id' => $p['product'], 'name' => 'Probe Option A',
        'parent_choice_id' => null, 'active' => 1]);
    $p['choiceA'] = cp_insert('product_extra_choices', ['product_extra_id' => $p['extraA'], 'system_id' => $p['sys1'],
        'label' => 'Probe Choice A', 'active' => 1]);
    $p['extraB'] = cp_insert('product_extras', ['client_id' => 3, 'product_id' => $p['product'], 'name' => 'Probe Option B',
        'parent_choice_id' => $p['choiceA'], 'active' => 1]);
    $p['choiceB'] = cp_insert('product_extra_choices', ['product_extra_id' => $p['extraB'], 'system_id' => null,
        'label' => 'Probe Choice B', 'active' => 1]);
    db()->prepare('INSERT INTO product_extra_parent_choices (product_extra_id, product_extra_choice_id) VALUES (?, ?)')->execute([$p['extraB'], $p['choiceA']]);
    db()->prepare('INSERT INTO product_extra_choice_bands (choice_id, band_code) VALUES (?, ?)')->execute([$p['choiceA'], 'PB']);
    db()->prepare('INSERT INTO product_extra_choice_options (choice_id, option_id) VALUES (?, ?)')->execute([$p['choiceA'], $p['opt']]);
    cp_insert('extra_choice_price_rows', ['product_extra_choice_id' => $p['choiceA'], 'width_mm' => 1500, 'price' => 4.5]);
    return $p;
}

/** Every row of a product's catalogue, keyed by a natural name so two copies line up. */
function cp_snapshot(int $productId): array
{
    $s = ['products' => ['P' => t_row('SELECT * FROM products WHERE id = ?', [$productId])]];
    $sysName = [];
    foreach (t_rows('SELECT * FROM product_systems WHERE product_id = ?', [$productId]) as $r) { $s['product_systems'][$r['name']] = $r; $sysName[$r['id']] = $r['name']; }
    $optName = [];
    foreach (t_rows('SELECT * FROM product_options WHERE product_id = ?', [$productId]) as $r) {
        $r['~system'] = $sysName[$r['system_id']] ?? null; $s['product_options'][$r['name']] = $r; $optName[$r['id']] = $r['name'];
    }
    foreach (t_rows('SELECT * FROM price_tables WHERE product_id = ?', [$productId]) as $t) {
        $t['~system'] = $sysName[$t['system_id']] ?? null; $s['price_tables'][$t['name']] = $t;
        foreach (t_rows('SELECT * FROM price_table_rows WHERE price_table_id = ?', [$t['id']]) as $r) $s['price_table_rows'][$t['name'] . "@{$r['width_mm']}x{$r['drop_mm']}"] = $r;
    }
    $choiceLabel = [];
    $extras = t_rows('SELECT * FROM product_extras WHERE product_id = ?', [$productId]);
    foreach ($extras as $e) {
        foreach (t_rows('SELECT * FROM product_extra_choices WHERE product_extra_id = ?', [$e['id']]) as $c) {
            $choiceLabel[$c['id']] = $e['name'] . '/' . $c['label'];
            $c['~system'] = $sysName[$c['system_id']] ?? null;
            $c['~bands'] = implode(',', array_column(t_rows('SELECT band_code FROM product_extra_choice_bands WHERE choice_id = ? ORDER BY 1', [$c['id']]), 'band_code'));
            $c['~fabrics'] = implode(',', array_map(static fn ($o) => $optName[$o['option_id']] ?? '?', t_rows('SELECT option_id FROM product_extra_choice_options WHERE choice_id = ?', [$c['id']])));
            $s['product_extra_choices'][$e['name'] . '/' . $c['label']] = $c;
            foreach (t_rows('SELECT * FROM extra_choice_price_rows WHERE product_extra_choice_id = ?', [$c['id']]) as $r) $s['extra_choice_price_rows'][$e['name'] . '/' . $c['label'] . '@' . $r['width_mm']] = $r;
        }
    }
    foreach ($extras as $e) {
        $e['~parent'] = $choiceLabel[$e['parent_choice_id']] ?? null;
        $e['~parents'] = implode(',', array_map(static fn ($x) => $choiceLabel[$x['product_extra_choice_id']] ?? '?',
            t_rows('SELECT product_extra_choice_id FROM product_extra_parent_choices WHERE product_extra_id = ? ORDER BY 1', [$e['id']])));
        $s['product_extras'][$e['name']] = $e;
    }
    return $s;
}

/** Compare a copy with its source; returns ["path.table.column" => "what differs"]. */
function cp_compare(string $path, array $src, array $copy): array
{
    $gaps = [];
    foreach ($src as $table => $rows) {
        foreach ($rows as $key => $row) {
            if (!isset($copy[$table][$key])) { $gaps["$path.$table"] = "row \"$key\" missing in the copy"; continue; }
            $c = $copy[$table][$key];
            foreach ($row as $col => $v) {
                if (cp_structural($col)) continue;
                $cv = $c[$col] ?? null;
                $same = is_numeric($v) && is_numeric($cv) ? abs((float) $v - (float) $cv) < 0.00001 : (string) $v === (string) $cv;
                if (!$same) {
                    $name = $col[0] === '~' ? $table . '.' . ['~system' => 'system_id', '~bands' => 'choice_bands', '~fabrics' => 'choice_fabrics',
                        '~parent' => 'parent_choice_id', '~parents' => 'parent_choices'][$col] : "$table.$col";
                    if ($col === '~bands') $name = 'product_extra_choice_bands';
                    if ($col === '~fabrics') $name = 'product_extra_choice_options';
                    if ($col === '~parents') $name = 'product_extra_parent_choices';
                    $gaps["$path.$name"] = "\"$key\": source " . t_show($v) . ', copy ' . t_show($cv);
                }
            }
        }
    }
    return $gaps;
}

function cp_report(array $gaps): void
{
    $GLOBALS["cp_seen"] = array_merge($GLOBALS["cp_seen"] ?? [], $gaps);
    file_put_contents(getenv("YB_TEST_OUT") . "/copy-gaps.json", json_encode($GLOBALS["cp_seen"], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $new = array_diff_key($gaps, KNOWN_GAPS);
    if ($new) {
        $lines = [];
        foreach ($new as $k => $why) $lines[] = "$k — $why";
        fail("a copy forgot something (add it to the copy, or to KNOWN_GAPS with a reason if deliberate):\n    " . implode("\n    ", $lines));
    }
}

$probe = [];

test('a probe product with every setting filled in is built in the factory', function () use (&$probe) {
    $probe = cp_build_probe();
    $snap = cp_snapshot($probe['product']);
    check(count($snap['product_extra_choices'] ?? []) === 2, 'probe choices not built');
});

test('the catalogue PUSH copies every column (factory → Alpha)', function () use (&$probe) {
    if (!$probe) skip('no probe');
    $sum = push_catalogue_to_client(db(), 3, 101, 'Bev ZZ Copy Probe');
    check(empty($sum['errors']), 'push errors: ' . t_show($sum['errors']));
    $copyId = (int) t_val('SELECT id FROM products WHERE client_id = 101 AND source_product_id = ?', [$probe['product']]);
    check($copyId > 0, 'the push made no copy carrying source_product_id');
    cp_report(cp_compare('push', cp_snapshot($probe['product']), cp_snapshot($copyId)));
});

test('opening a NEW company with a seeded catalogue copies every column too', function () use (&$probe) {
    if (!$probe) skip('no probe');
    db()->exec("INSERT INTO clients (id, company_name, email, active) VALUES (150, 'Echo Seed Test Ltd', 'echo@example.test', 1)");
    db()->exec('INSERT INTO client_settings (client_id) VALUES (150)');
    db()->beginTransaction();
    $sum = seed_client_from_template(db(), 3, 150);
    db()->commit();
    $copyId = (int) t_val('SELECT id FROM products WHERE client_id = 150 AND source_product_id = ?', [$probe['product']]);
    check($copyId > 0, 'the seed did not copy the probe product: ' . t_show($sum));
    cp_report(cp_compare('push', cp_snapshot($probe['product']), cp_snapshot($copyId)));
});

test('every row a new company gets carries its source marker (seed = push, #1056)', function () {
    $checks = [
        'products'              => 'SELECT COUNT(*) FROM products WHERE client_id = 150 AND (source_product_id IS NULL OR source_client_id <> 3)',
        'product_systems'       => 'SELECT COUNT(*) FROM product_systems WHERE client_id = 150 AND source_system_id IS NULL',
        'product_options'       => 'SELECT COUNT(*) FROM product_options WHERE client_id = 150 AND source_option_id IS NULL',
        'product_extras'        => 'SELECT COUNT(*) FROM product_extras WHERE client_id = 150 AND source_extra_id IS NULL',
        'product_extra_choices' => 'SELECT COUNT(*) FROM product_extra_choices c JOIN product_extras e ON e.id = c.product_extra_id WHERE e.client_id = 150 AND c.source_choice_id IS NULL',
        'price_tables'          => 'SELECT COUNT(*) FROM price_tables WHERE client_id = 150 AND source_table_id IS NULL',
    ];
    foreach ($checks as $t => $sql) check_same(0, (int) t_val($sql), "$t rows without a source marker in the new company");
    $want = (int) t_val('SELECT COUNT(*) FROM products WHERE client_id = 3 AND active = 1');
    check_same($want, (int) t_val('SELECT COUNT(*) FROM products WHERE client_id = 150'), 'products the new company got (every active, ready factory product)');
});

test('a new company gets our trade prices only — no markups or discounts copied from the factory', function () {
    // John, 2026-10-13: the factory's client_discounts are BUYING discounts (40% off
    // Forest Wood's list); copied across they became the company's selling discount.
    check((int) t_val('SELECT COUNT(*) FROM client_discounts WHERE client_id = 3') > 0, 'fixture: the factory should have buying discounts to not copy');
    check_same(0, (int) t_val('SELECT COUNT(*) FROM client_markups WHERE client_id = 150'), 'markup rows the new company got');
    check_same(0, (int) t_val('SELECT COUNT(*) FROM client_discounts WHERE client_id = 150'), 'discount rows the new company got');
});

test('the seeded company and a pushed company end up with the same catalogue', function () {
    // Alpha was seeded at set-up and has just been pushed to again; Echo was seeded now.
    $shape = static fn (int $cid) => t_rows(
        'SELECT p.source_product_id, p.name,
                (SELECT COUNT(*) FROM product_systems s WHERE s.product_id = p.id) sys,
                (SELECT COUNT(*) FROM product_options o WHERE o.product_id = p.id) fab,
                (SELECT COUNT(*) FROM product_extras e WHERE e.product_id = p.id) opt,
                (SELECT COUNT(*) FROM product_extra_choices c JOIN product_extras e ON e.id = c.product_extra_id WHERE e.product_id = p.id) ch,
                (SELECT COUNT(*) FROM price_tables t WHERE t.product_id = p.id) tabs,
                (SELECT ROUND(SUM(r.price), 2) FROM price_table_rows r JOIN price_tables t ON t.id = r.price_table_id WHERE t.product_id = p.id) grid
           FROM products p WHERE p.client_id = ? ORDER BY p.source_product_id', [$cid]);
    $a = $shape(101); $e = $shape(150);
    check_same(json_encode($a), json_encode($e), 'Alpha (seeded + pushed) vs Echo (seeded)');
});

test('pushing again changes nothing (the push is repeatable)', function () {
    $fp = static fn () => md5(json_encode([
        t_rows('SELECT id, name, active, source_product_id FROM products WHERE client_id = 101 ORDER BY id'),
        t_rows('SELECT o.id, o.name, o.band_code, o.active FROM product_options o WHERE o.client_id = 101 ORDER BY id'),
        t_rows('SELECT c.id, c.label, c.price_delta, c.active FROM product_extra_choices c JOIN product_extras e ON e.id = c.product_extra_id WHERE e.client_id = 101 ORDER BY c.id'),
        t_val('SELECT SUM(r.price) FROM price_table_rows r JOIN price_tables t ON t.id = r.price_table_id WHERE t.client_id = 101'),
    ]));
    $before = $fp();
    $sum = push_catalogue_to_client(db(), 3, 101, 'Bev');
    check(empty($sum['errors']), 'push errors: ' . t_show($sum['errors']));
    check_same($before, $fp(), 'Alpha\'s catalogue after a second push');
});

test('a push still works after the master re-gates an option and deletes its old parent choice', function () {
    // Live 2026-10-13: Forest Wood and Embassy failed on every account with a
    // foreign-key error. Pruning the removed choice on the account deleted the
    // option shown under it (cascade); the push then re-used that option's id.
    $ins = static fn (string $sql, array $p) => (db()->prepare($sql)->execute($p) ? (int) db()->lastInsertId() : 0);
    $pid = cp_insert('products', ['client_id' => 3, 'name' => 'Bev ZZ Regate Probe', 'active' => 1, 'price_source' => 'own',
        'category_id' => null, 'requires_option' => 0, 'width_only' => 0, 'price_per_slat' => 0, 'price_per_sqm' => 0]);
    $exA = $ins('INSERT INTO product_extras (client_id, product_id, name, active) VALUES (3, ?, ?, 1)', [$pid, 'Regate Parent']);
    $c1  = $ins('INSERT INTO product_extra_choices (product_extra_id, label, active) VALUES (?, ?, 1)', [$exA, 'Old Parent']);
    $c2  = $ins('INSERT INTO product_extra_choices (product_extra_id, label, active) VALUES (?, ?, 1)', [$exA, 'New Parent']);
    $exB = $ins('INSERT INTO product_extras (client_id, product_id, parent_choice_id, name, active) VALUES (3, ?, ?, ?, 1)', [$pid, $c1, 'Regate Child']);
    $ins('INSERT INTO product_extra_choices (product_extra_id, label, active) VALUES (?, ?, 1)', [$exB, 'Child Choice']);
    $ins('INSERT INTO product_extra_parent_choices (product_extra_id, product_extra_choice_id) VALUES (?, ?)', [$exB, $c1]);

    $sum = push_catalogue_to_client(db(), 3, 101, 'Bev ZZ Regate Probe');
    check(empty($sum['errors']), 'first push errors: ' . t_show($sum['errors']));

    // On the master: the child option now hangs off the new choice; the old one is deleted.
    db()->prepare('UPDATE product_extras SET parent_choice_id = ? WHERE id = ?')->execute([$c2, $exB]);
    db()->prepare('UPDATE product_extra_parent_choices SET product_extra_choice_id = ? WHERE product_extra_id = ?')->execute([$c2, $exB]);
    db()->prepare('DELETE FROM product_extra_choices WHERE id = ?')->execute([$c1]);

    $sum = push_catalogue_to_client(db(), 3, 101, 'Bev ZZ Regate Probe');
    check(empty($sum['errors']), 'second push errors: ' . t_show($sum['errors']));
    $gate = t_val("SELECT pc.label FROM product_extras e JOIN products p ON p.id = e.product_id
                    JOIN product_extra_choices pc ON pc.id = e.parent_choice_id
                   WHERE p.client_id = 101 AND p.name = 'Bev ZZ Regate Probe' AND e.name = 'Regate Child'");
    check_same('New Parent', $gate, "the account's child option is shown under");
});

test('an account can set its markup on a factory product, but never a discount', function () {
    // John, 2026-10-13: ABC Blinds could type a 99% "buying discount" on Bev 25mm
    // Venetian. On a factory product the discount is ours; the grid is already
    // the account's trade price.
    require_once APP_ROOT . '/_partials/pricing_engine.php';
    $t = t_row("SELECT t.id, t.product_id, t.system_id FROM price_tables t JOIN products p ON p.id = t.product_id
                 WHERE p.client_id = 101 AND p.source_client_id = 3 AND t.system_id IS NOT NULL LIMIT 1");
    check(!empty($t), 'fixture: Alpha has no factory product with a system');
    $r = t_submit(as_user('alpha.admin'), '/admin/products/price-table.php?id=' . $t['id'],
        ['action' => 'update_trade_terms', 'discount_percent' => '99', 'markup_percent' => '40']);
    check(!$r->phpErrors, "save terms: PHP problems:\n    " . implode("\n    ", $r->phpErrors));
    check_same(0, (int) t_val('SELECT COUNT(*) FROM client_discounts WHERE client_id = 101 AND product_id = ?', [$t['product_id']]), 'discount rows saved on the factory product');
    check_same(40.0, (float) t_val('SELECT markup_percent FROM client_markups WHERE client_id = 101 AND product_id = ? AND system_id = ?', [$t['product_id'], $t['system_id']]), 'the markup the account typed');
    // A discount row already there (typed before the lock, or copied by the old set-up) is ignored.
    db()->prepare('INSERT INTO client_discounts (client_id, product_id, system_id, discount_percent) VALUES (101, ?, ?, 99)')->execute([$t['product_id'], $t['system_id']]);
    check_same(0.0, pe_discount_for_system(db(), 101, (int) $t['product_id'], (int) $t['system_id']), 'discount the engine uses on the factory product');
    db()->prepare('DELETE FROM client_discounts WHERE client_id = 101 AND product_id = ?')->execute([$t['product_id']]);
    db()->prepare('DELETE FROM client_markups WHERE client_id = 101 AND product_id = ?')->execute([$t['product_id']]);
});

test('the DUPLICATE button copies every column', function () use (&$probe) {
    if (!$probe) skip('no probe');
    $h = as_user('super');   // admin of the factory account, which owns the probe
    $r = t_submit($h, '/admin/products/duplicate.php', ['id' => $probe['product']]);
    check(!$r->phpErrors, "duplicate: PHP problems:\n    " . implode("\n    ", $r->phpErrors));
    check($r->status === 302 && preg_match('/edit\.php\?id=(\d+)/', (string) $r->location, $m), 'Duplicate did not make a copy (HTTP ' . $r->status . ' → ' . $r->location . ')');
    $copy = cp_snapshot((int) $m[1]);
    cp_report(cp_compare('duplicate', cp_snapshot($probe['product']), $copy));
});

test('known copy gaps are still real (delete a KNOWN_GAPS line once it is fixed)', function () {
    // Informational: names any KNOWN_GAPS line that no longer happens, so the list doesn't rot.
    // Never fails — a fixed gap is good news; it shows as a skipped line.
    $seen = json_decode((string) @file_get_contents(getenv('YB_TEST_OUT') . '/copy-gaps.json'), true) ?: [];
    $stale = array_keys(array_diff_key(KNOWN_GAPS, $seen));
    if ($stale) skip('fixed now, delete from KNOWN_GAPS: ' . implode(', ', $stale));
});

test('a duplicated real product prices exactly like the original (Forest Wood, Roller fascia, PF Shutter)', function () {
    require_once APP_ROOT . '/tests/lib/prices.php';
    $h = as_user('super');
    foreach (['Forest Wood 50mm, 4 hold-downs + 2 brackets + wand, 1650mm pelmet' => 85, 'Roller, 3 blinds on one Senses fascia' => 5,
              'PF Shutter, 3 panels (panel split)' => 199] as $name => $pid) {
        $r = t_submit($h, '/admin/products/duplicate.php', ['id' => $pid]);
        check($r->status === 302 && preg_match('/edit\.php\?id=(\d+)/', (string) $r->location, $m), "Duplicate of #$pid failed");
        $copy = (int) $m[1];
        // Re-point the line at the copy, matching everything by name.
        $q = price_scenarios()[$name];
        $byName = static fn (string $sql, array $p) => (int) t_val($sql, $p);
        $q['product_id'] = $copy;
        $q['system_id'] = $byName('SELECT c.id FROM product_systems c JOIN product_systems s ON s.name = c.name WHERE c.product_id = ? AND s.id = ?', [$copy, $q['system_id']]);
        $q['option_id'] = $byName('SELECT c.id FROM product_options c JOIN product_options s ON s.name = c.name AND s.band_code <=> c.band_code
                                    WHERE c.product_id = ? AND s.id = ? ORDER BY c.id LIMIT 1', [$copy, $q['option_id']]);
        $mapChoice = static fn (int $cid) => $byName('SELECT c.id FROM product_extra_choices c JOIN product_extras ce ON ce.id = c.product_extra_id
                JOIN product_extra_choices s ON s.label = c.label JOIN product_extras se ON se.id = s.product_extra_id AND se.name = ce.name
               WHERE ce.product_id = ? AND s.id = ? LIMIT 1', [$copy, $cid]);
        foreach ($q['extras'] as &$e) {
            $e['extra_id'] = $byName('SELECT c.id FROM product_extras c JOIN product_extras s ON s.name = c.name AND s.sort_order = c.sort_order
                                       WHERE c.product_id = ? AND s.id = ? ORDER BY c.id LIMIT 1', [$copy, $e['extra_id']]);
            if (isset($e['choice_id']))  $e['choice_id'] = $mapChoice((int) $e['choice_id']);
            if (isset($e['choice_ids'])) $e['choice_ids'] = array_map($mapChoice, $e['choice_ids']);
        }
        unset($e);
        $orig = price_figures(qb_preview_response(db(), 3, price_scenarios()[$name], true, false, 0));
        $dup  = price_figures(qb_preview_response(db(), 3, $q, true, false, 0));
        check(!isset($orig['error']), "$name did not price on the original: " . ($orig['error'] ?? ''));
        check_same(t_show($orig), t_show($dup), "$name — duplicate vs original");
    }
});

test('a product deleted from the catalogue is HIDDEN on the account at the next push, and comes back if re-created', function () {
    $mk = static fn () => cp_insert('products', ['client_id' => 3, 'name' => 'Bev ZZ Hide Probe', 'active' => 1, 'price_source' => 'own',
        'category_id' => null, 'requires_option' => 0, 'width_only' => 0, 'price_per_slat' => 0, 'price_per_sqm' => 0]);
    $copy = static fn () => db()->query("SELECT id, active FROM products WHERE client_id = 101 AND name = 'Bev ZZ Hide Probe'")->fetchAll(PDO::FETCH_ASSOC);

    $pid = $mk();
    $sum = push_catalogue_to_client(db(), 3, 101, 'Bev ZZ Hide Probe');
    check(empty($sum['errors']), 'first push errors: ' . t_show($sum['errors']));
    $first = $copy();
    check_same(1, count($first), 'copies on the account after the first push');
    check_same(1, (int) $first[0]['active'], 'the copy starts active');

    db()->prepare('DELETE FROM products WHERE id = ?')->execute([$pid]);   // deleted on the catalogue
    $sum = push_catalogue_to_client(db(), 3, 101, 'Bev ZZ Hide Probe');
    $after = $copy();
    check_same(1, count($after), 'the copy is kept, not deleted (old quotes still open)');
    check_same(0, (int) $after[0]['active'], 'the copy is now Inactive');
    check((int) $sum['products_hidden'] >= 1, 'the push reports it hid a deleted product');

    // An account's OWN product is never touched by this.
    $own = cp_insert('products', ['client_id' => 101, 'name' => 'Alpha Own Hide Check', 'active' => 1, 'price_source' => 'own',
        'category_id' => null, 'requires_option' => 0, 'width_only' => 0, 'price_per_slat' => 0, 'price_per_sqm' => 0]);
    push_catalogue_to_client(db(), 3, 101, 'Bev ZZ Hide Probe');
    check_same(1, (int) t_val('SELECT active FROM products WHERE id = ?', [$own]), "the account's own product stays active");

    // Re-created under the same name → the old copy is taken over again, not refused.
    $mk();
    $sum = push_catalogue_to_client(db(), 3, 101, 'Bev ZZ Hide Probe');
    check(empty($sum['errors']), 're-create push errors: ' . t_show($sum['errors']));
    $back = $copy();
    check_same(1, count($back), 'still one copy after re-creating it');
    check_same(1, (int) $back[0]['active'], 'the copy is active again');
    check_same((int) $first[0]['id'], (int) $back[0]['id'], 'it is the same copy, re-linked');
});

test('when the master changes a grid\'s sizes, the next push leaves the copy with exactly the master\'s cells', function () {
    // Audit 2026-10-17: the push only ever ADDED/updated cells. Rename the
    // master's 600/700 widths to 610/710 and drop 800, and the account kept the
    // old 600/700/800 cells at the old prices next to the new ones — and the
    // round-up lookup priced a 590mm blind from the stale 600 cell. Same for a
    // width-priced option choice.
    $ins = static fn (string $sql, array $p) => (db()->prepare($sql)->execute($p) ? (int) db()->lastInsertId() : 0);
    $pid = cp_insert('products', ['client_id' => 3, 'name' => 'Bev ZZ Prune Probe', 'active' => 1, 'price_source' => 'own',
        'category_id' => null, 'requires_option' => 0, 'width_only' => 0, 'price_per_slat' => 0, 'price_per_sqm' => 0]);
    $sys = $ins('INSERT INTO product_systems (client_id, product_id, name, active) VALUES (3, ?, ?, 1)', [$pid, 'Prune System']);
    $tab = $ins("INSERT INTO price_tables (client_id, product_id, system_id, band_code, name, active) VALUES (3, ?, ?, 'A', 'Prune Grid', 1)", [$pid, $sys]);
    $ex  = $ins('INSERT INTO product_extras (client_id, product_id, name, active) VALUES (3, ?, ?, 1)', [$pid, 'Prune Option']);
    $ch  = $ins('INSERT INTO product_extra_choices (product_extra_id, label, active) VALUES (?, ?, 1)', [$ex, 'Prune Choice']);
    foreach ([600 => 10, 700 => 20, 800 => 30] as $w => $p) {
        foreach ([1000, 1500] as $d) {
            $ins('INSERT INTO price_table_rows (price_table_id, width_mm, drop_mm, price) VALUES (?, ?, ?, ?)', [$tab, $w, $d, $p + $d / 100]);
        }
        $ins('INSERT INTO extra_choice_price_rows (product_extra_choice_id, width_mm, price) VALUES (?, ?, ?)', [$ch, $w, $p / 10]);
    }

    $grid    = static fn (int $t) => t_rows('SELECT width_mm, drop_mm, ROUND(price, 2) price FROM price_table_rows WHERE price_table_id = ? ORDER BY width_mm, drop_mm', [$t]);
    $widths  = static fn (int $c) => t_rows('SELECT width_mm, ROUND(price, 2) price FROM extra_choice_price_rows WHERE product_extra_choice_id = ? ORDER BY width_mm', [$c]);
    $copyTab = static fn () => (int) t_val('SELECT id FROM price_tables WHERE client_id = 101 AND source_table_id = ?', [$tab]);
    $copyCh  = static fn () => (int) t_val('SELECT c.id FROM product_extra_choices c JOIN product_extras e ON e.id = c.product_extra_id
                                             WHERE e.client_id = 101 AND c.source_choice_id = ?', [$ch]);

    $sum = push_catalogue_to_client(db(), 3, 101, 'Bev ZZ Prune Probe');
    check(empty($sum['errors']), 'first push errors: ' . t_show($sum['errors']));
    check_same(t_show($grid($tab)), t_show($grid($copyTab())), 'the copy\'s grid after the first push');
    check_same(t_show($widths($ch)), t_show($widths($copyCh())), 'the copy\'s width prices after the first push');

    // On the master: 600→610 and 700→710 renamed in place at new prices, 800 dropped.
    db()->prepare('UPDATE price_table_rows SET width_mm = width_mm + 10, price = price + 5 WHERE price_table_id = ? AND width_mm IN (600, 700)')->execute([$tab]);
    db()->prepare('DELETE FROM price_table_rows WHERE price_table_id = ? AND width_mm = 800')->execute([$tab]);
    db()->prepare('UPDATE extra_choice_price_rows SET width_mm = width_mm + 10, price = price + 1 WHERE product_extra_choice_id = ? AND width_mm IN (600, 700)')->execute([$ch]);
    db()->prepare('DELETE FROM extra_choice_price_rows WHERE product_extra_choice_id = ? AND width_mm = 800')->execute([$ch]);

    $sum = push_catalogue_to_client(db(), 3, 101, 'Bev ZZ Prune Probe');
    check(empty($sum['errors']), 'second push errors: ' . t_show($sum['errors']));
    check_same(t_show($grid($tab)), t_show($grid($copyTab())), 'the copy\'s grid after the master changed its sizes');
    check_same(t_show($widths($ch)), t_show($widths($copyCh())), 'the copy\'s width prices after the master changed its sizes');
    check_same(6, (int) ($sum['price_table_cells_removed'] ?? 0), 'price cells the push reports removing');
    check_same(3, (int) ($sum['width_table_cells_removed'] ?? 0), 'width-table cells the push reports removing');
    // Price undo: the push's change to the copy's grid is recorded (and undoable).
    $copyPid = (int) t_val('SELECT product_id FROM price_tables WHERE id = ?', [$copyTab()]);
    $undoRows = static fn () => (int) t_val("SELECT COUNT(*) FROM price_undo WHERE client_id = 101 AND scope = ? AND after_hash IS NOT NULL", ['product:' . $copyPid]);
    check_same(1, $undoRows(), 'undo snapshots recorded for the copy by the size-changing push');

    // And again: nothing left to remove, nothing changes, no new undo entry.
    $sum = push_catalogue_to_client(db(), 3, 101, 'Bev ZZ Prune Probe');
    check_same(1, $undoRows(), 'undo snapshots after a repeat push that changed nothing');
    check_same(0, (int) ($sum['price_table_cells_removed'] ?? 0) + (int) ($sum['width_table_cells_removed'] ?? 0), 'cells removed by a repeat push');
    check_same(t_show($grid($tab)), t_show($grid($copyTab())), 'the copy\'s grid after a repeat push');
});
