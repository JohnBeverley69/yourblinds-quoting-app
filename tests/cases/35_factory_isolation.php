<?php
declare(strict_types=1);

/**
 * TWO FACTORIES. The main factory (Test Factory, client 3) and a white-label
 * factory (Charlie Made, client 103) must be independent: neither reads or
 * changes the other's products, build set-up or delivery notes.
 */

require_once APP_ROOT . '/_partials/order_stage.php';

$s = [];

test('set-up: Charlie owns a product of its own (a Duplicate makes it his)', function () use (&$s) {
    $c = as_user('charlie.office');
    // charlie.office is also an admin of Charlie Made, so it can Duplicate one of its linked copies.
    $src = (int) t_val('SELECT id FROM products WHERE client_id = 103 AND source_product_id = 5');
    $r = t_submit($c, '/admin/products/duplicate.php', ['id' => $src]);
    check($r->status === 302 && preg_match('/edit\.php\?id=(\d+)/', (string) $r->location, $m), 'Charlie could not duplicate its roller');
    $s['charlie_product'] = (int) $m[1];
    db()->prepare("UPDATE products SET name = 'Charlie Own Roller' WHERE id = ?")->execute([$s['charlie_product']]);
    t_act_as('charlie.office');
    check(factory_owns_product(db(), $s['charlie_product']), 'the duplicate is not Charlie\'s own product');
    $_SESSION = [];
});

/** Everything a factory sets up per product, for both factories. */
$buildFingerprint = static function (): string {
    $rows = [];
    foreach (['worksheet_templates', 'worksheet_template_versions', 'build_variables', 'build_rules', 'product_route_steps',
              'product_area_map', 'product_lead_times', 'allowance_rows', 'production_areas'] as $t) {
        $r = t_rows("SELECT * FROM `$t` ORDER BY 1");
        foreach ($r as &$x) unset($x['updated_at']);
        $rows[$t] = $r;
    }
    return md5(json_encode($rows));
};

/** [page, how the product is passed] for every factory page that takes a product. */
$productPages = ['/factory/worksheets.php', '/factory/build-rules.php', '/factory/build-rules-v2.php', '/factory/routes.php',
    '/factory/production-areas.php', '/factory/roller-label-editor.php', '/factory/allowances.php'];

test('the main factory cannot open or change the white-label factory\'s product on any factory page', function () use (&$s, $productPages, $buildFingerprint) {
    if (empty($s['charlie_product'])) skip('set-up failed');
    $f = as_user('factory.office');
    $pid = $s['charlie_product'];
    $before = $buildFingerprint();
    $problems = [];
    foreach ($productPages as $p) {
        $r = $f->get("$p?product_id=$pid");
        if ($r->phpErrors) $problems[] = "$p: PHP problems: " . implode(' | ', array_slice($r->phpErrors, 0, 2));
        if (strpos($r->body, 'Charlie Own Roller') !== false) $problems[] = "$p?product_id=$pid showed the main factory Charlie's product";
        t_submit($f, $p, ['product_id' => $pid, 'name' => 'HACKED', 'label' => 'HACKED', 'layout_json' => '{}', 'formula' => '1',
            'variable' => 'hacked', 'stream' => 'hacked', 'area_id' => 1, 'days' => 99, 'action' => 'save', '_action' => 'save']);
        if ($buildFingerprint() !== $before) { $problems[] = "$p: a POST with Charlie's product changed factory set-up data"; $before = $buildFingerprint(); }
    }
    $r = $f->get("/factory/fabrics-search.php?product_id=$pid&q=a");
    if ($r->status === 200 && preg_match('/"(name|label)"/', $r->body) && strpos($r->body, '[]') === false) {
        $problems[] = "fabrics-search.php returned Charlie's fabrics to the main factory";
    }
    if ($problems) fail(implode("\n", $problems));
});

test('the white-label factory cannot open or change the main factory\'s products on any factory page', function () use ($productPages, $buildFingerprint) {
    $c = as_user('charlie.office');
    $before = $buildFingerprint();
    $problems = [];
    foreach ([5, 8, 85] as $pid) {
        $name = (string) t_val('SELECT name FROM products WHERE id = ?', [$pid]);
        foreach ($productPages as $p) {
            $r = $c->get("$p?product_id=$pid");
            if ($r->phpErrors) $problems[] = "$p: PHP problems: " . implode(' | ', array_slice($r->phpErrors, 0, 2));
            // Charlie's linked copies share the name, so look for the product's own build data instead.
            if ($p === '/factory/build-rules.php' || $p === '/factory/build-rules-v2.php') {
                // The page's own fixed text, plus the sidebar every factory page now carries
                // (its "Spacing is compact…" button is not a build variable).
                $src = (string) file_get_contents(APP_ROOT . $p) . (string) file_get_contents(APP_ROOT . '/_partials/sidebar.php');
                foreach (t_rows('SELECT name AS variable FROM build_variables WHERE product_id = ? AND LENGTH(name) > 5', [$pid]) as $v) {
                    if (strpos($src, (string) $v['variable']) !== false) continue;   // a fixed label the page always shows
                    if (preg_match('/\b' . preg_quote((string) $v['variable'], '/') . '\b/', $r->body)) {
                        $problems[] = "$p?product_id=$pid showed the main factory's build variable \"{$v['variable']}\" ($name)";
                        break;
                    }
                }
            }
            t_submit($c, $p, ['product_id' => $pid, 'name' => 'HACKED', 'label' => 'HACKED', 'layout_json' => '{}', 'formula' => '1',
                'variable' => 'hacked', 'stream' => 'hacked', 'area_id' => 1, 'days' => 99, 'action' => 'save', '_action' => 'save']);
            if ($buildFingerprint() !== $before) { $problems[] = "$p: Charlie's POST changed the main factory's set-up for $name"; $before = $buildFingerprint(); }
        }
    }
    $r = $c->get('/factory/roller-label-editor.php?product_id=5');
    if (!in_array($r->status, [403, 404], true) && strpos($r->body, 'Bev Roller Blinds') !== false) {
        $problems[] = 'roller-label-editor opened the main factory\'s roller for Charlie (HTTP ' . $r->status . ')';
    }
    $al = $c->get('/factory/allowances.php?product_id=5');
    foreach (array_slice(array_unique(array_column(t_rows('SELECT table_name FROM allowance_rows WHERE client_id = 3 LIMIT 50'), 'table_name')), 0, 5) as $chart) {
        if ($chart !== '' && strpos($al->body, htmlspecialchars($chart)) !== false) { $problems[] = "allowances showed Charlie the main factory's chart \"$chart\""; break; }
    }
    if ($problems) fail(implode("\n", $problems));
});

test('one factory\'s dispatched delivery note doesn\'t move another factory\'s order to Dispatched', function () {
    $a = as_user('alpha.admin');
    $qid = flow_new_quote($a, fx('customer_alpha'), 'TWO-FACTORIES');
    flow_add_line($a, $qid, 101, price_scenarios()['Roller, side winder, 1200 × 1600']);
    flow_place_order($a, $qid);
    t_act_as('factory.office');
    $before = recompute_order_stage(db(), $qid);
    check($before !== 'dispatched', 'set-up: order already dispatched');
    // Charlie (another factory) dispatches a note against the same order number.
    db()->prepare("INSERT INTO factory_ar_delivery_notes (factory_client_id, account_client_id, dn_number, source_quote_id, status, dispatched_at, created_by)
                   VALUES (103, 101, 'DN-CHARLIE-1', ?, 'dispatched', NOW(), 0)")->execute([$qid]);
    check_same($before, os_derive_stage(db(), $qid, 3), 'the main factory\'s stage after Charlie\'s note');
    check_same($before, (string) recompute_order_stage(db(), $qid), 'the main factory\'s stage, recomputed');

    // And the reverse: a DRAFT note of the main factory is left alone when Charlie dispatches.
    db()->prepare("INSERT INTO factory_ar_delivery_notes (factory_client_id, account_client_id, dn_number, source_quote_id, status, created_by)
                   VALUES (3, 101, 'DN-MAIN-DRAFT', ?, 'draft', 0)")->execute([$qid]);
    db()->prepare("DELETE FROM factory_ar_delivery_notes WHERE dn_number = 'DN-CHARLIE-1'")->execute();
    $err = os_mark_dispatched_dn(db(), $qid, 103, 0);
    check_same('draft', (string) t_val("SELECT status FROM factory_ar_delivery_notes WHERE dn_number = 'DN-MAIN-DRAFT'"),
        "the main factory's draft note after Charlie dispatched ($err)");
    check((int) t_val("SELECT COUNT(*) FROM factory_ar_delivery_notes WHERE source_quote_id = ? AND factory_client_id = 103", [$qid]) <= 1,
        'Charlie got more than one note');
    $_SESSION = [];
});

test('a factory\'s Build Rules save never changes another factory\'s allowances (audit 2026-10-17)', function () use (&$s) {
    if (empty($s['charlie_product'])) skip('set-up failed');
    // Charlie gets its own copy of one main-factory allowance, so both factories have the key.
    db()->exec("DELETE FROM allowance_rows WHERE client_id = 103 AND table_name = 'roller_pole' AND key_norm = 'cloth'");
    db()->exec("INSERT INTO allowance_rows (client_id, table_name, key_norm, keys_display, value, seq) VALUES (103, 'roller_pole', 'cloth', 'Cloth', 5, 0)");
    $main = static fn () => (float) t_val("SELECT value FROM allowance_rows WHERE client_id = 3 AND table_name = 'roller_pole' AND key_norm = 'cloth'");
    $mainBefore = $main();
    $c = as_user('charlie.office');
    $r = t_submit($c, '/factory/build-rules-v2.php?product_id=' . $s['charlie_product'],
        ['cut' => ['Anything' => ['alw:roller_pole:cloth' => '999']], 'cutdir' => ['Anything' => 'mixed']]);
    check(!$r->phpErrors, "PHP problems:\n    " . implode("\n    ", $r->phpErrors));
    check_same($mainBefore, $main(), 'the main factory\'s allowance after Charlie saved its own');
    check_same(999.0, (float) t_val("SELECT value FROM allowance_rows WHERE client_id = 103 AND table_name = 'roller_pole' AND key_norm = 'cloth'"),
        'Charlie\'s own allowance after its save');
    db()->exec("DELETE FROM allowance_rows WHERE client_id = 103 AND table_name = 'roller_pole' AND key_norm = 'cloth'");
    $_SESSION = [];
});
