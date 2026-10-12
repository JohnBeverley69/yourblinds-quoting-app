<?php
declare(strict_types=1);

/**
 * NO PEEKING. One company can't read or change another company's quotes,
 * customers or products; a white-label factory can't reach the main factory's
 * accounts screens or its products; the super-admin can.
 *
 * Alpha Blinds is the "victim", Bravo Interiors the "nosy neighbour",
 * Charlie Made the white-label factory.
 */

$s = [];

test('set-up: Alpha has a quote, a customer and products to protect', function () use (&$s) {
    $a = as_user('alpha.admin');
    $s['quote'] = flow_new_quote($a, fx('customer_alpha'), 'ALPHA-SECRET-REF');
    $s['item']  = (int) flow_add_line($a, $s['quote'], 101, price_scenarios()['Roller, side winder, 1200 × 1600'])['id'];
    $s['qnum']  = (string) t_val('SELECT quote_number FROM quotes WHERE id = ?', [$s['quote']]);
    $s['token'] = (string) t_val('SELECT public_token FROM quotes WHERE id = ?', [$s['quote']]);
    $s['product'] = (int) t_val('SELECT id FROM products WHERE client_id = 101 AND source_product_id = 5');
    $s['system']  = (int) t_val('SELECT id FROM product_systems WHERE product_id = ? ORDER BY id LIMIT 1', [$s['product']]);
    $s['option']  = (int) t_val('SELECT id FROM product_options WHERE product_id = ? ORDER BY id LIMIT 1', [$s['product']]);
    $s['extra']   = (int) t_val('SELECT id FROM product_extras WHERE product_id = ? ORDER BY id LIMIT 1', [$s['product']]);
    $s['choice']  = (int) t_val('SELECT id FROM product_extra_choices WHERE product_extra_id = ? ORDER BY id LIMIT 1', [$s['extra']]);
    $s['table']   = (int) t_val('SELECT id FROM price_tables WHERE product_id = ? ORDER BY id LIMIT 1', [$s['product']]);
    $s['customer']= fx('customer_alpha');
    foreach (['quote', 'item', 'product', 'system', 'option', 'extra', 'choice', 'table'] as $k) check($s[$k] > 0, "no $k");
});

/** A fingerprint of everything Alpha owns. Any change by anyone else shows up here. */
$fingerprint = static function (): string {
    $q = [
        'SELECT * FROM quotes WHERE client_id = 101',
        'SELECT i.* FROM quote_items i JOIN quotes q ON q.id = i.quote_id WHERE q.client_id = 101',
        'SELECT * FROM customers WHERE client_id = 101',
        'SELECT * FROM products WHERE client_id = 101',
        'SELECT * FROM product_systems WHERE client_id = 101',
        'SELECT * FROM product_options WHERE client_id = 101',
        'SELECT * FROM product_extras WHERE client_id = 101',
        'SELECT c.* FROM product_extra_choices c JOIN product_extras e ON e.id = c.product_extra_id WHERE e.client_id = 101',
        'SELECT * FROM price_tables WHERE client_id = 101',
        'SELECT COUNT(*), SUM(r.price) FROM price_table_rows r JOIN price_tables t ON t.id = r.price_table_id WHERE t.client_id = 101',
        'SELECT * FROM client_settings WHERE client_id = 101',
        'SELECT * FROM client_markups WHERE client_id = 101',
        'SELECT * FROM appointments WHERE client_id = 101',
        'SELECT * FROM payments WHERE client_id = 101',
    ];
    $all = [];
    foreach ($q as $sql) {
        $rows = t_rows($sql . (stripos($sql, 'COUNT(') === false ? ' ORDER BY 1' : ''));
        foreach ($rows as &$r) unset($r['updated_at']);
        $all[] = $rows;
    }
    return md5(json_encode($all));
};

test('Bravo cannot READ Alpha\'s quote, customer or products through any screen', function () use (&$s) {
    if (empty($s['quote'])) skip('set-up failed');
    $b = as_user('bravo.admin');
    $secrets = [$s['qnum'], 'ALPHA-SECRET-REF', 'alpha.customer@example.test', 'Mrs Alpha Customer'];
    $urls = [
        "/quote-builder/edit.php?id={$s['quote']}",
        "/quote-history/view.php?id={$s['quote']}",
        "/pdf-generator/quote_pdf.php?id={$s['quote']}",
        "/pdf-generator/pdf.php?id={$s['quote']}",
        "/quote-builder/order_suppliers.php?id={$s['quote']}",
        "/quote-builder/deposit.php?quote_id={$s['quote']}",
        "/customer-manager/edit.php?id={$s['customer']}",
        "/calendar/new.php?quote_id={$s['quote']}",
        "/orders/index.php?q=" . urlencode($s['qnum']),
        "/orders/index.php?q=ALPHA-SECRET-REF",
        "/customer-manager/index.php?q=Alpha",
        "/admin/products/edit.php?id={$s['product']}",
        "/admin/products/options.php?product_id={$s['product']}",
        "/admin/products/extras.php?product_id={$s['product']}",
        "/admin/products/price-tables.php?product_id={$s['product']}",
        "/admin/products/price-table.php?id={$s['table']}",
        "/quote-builder/api/product-data.php?product_id={$s['product']}",
        "/quote-builder/api/fabrics-search.php?product_id={$s['product']}&q=a",
        "/quote-builder/api/preview.php?product_id={$s['product']}&system_id={$s['system']}&option_id={$s['option']}&width=1200&drop=1600&round_up=1",
    ];
    $problems = [];
    foreach ($urls as $u) {
        $r = $b->get($u);
        if ($r->phpErrors) { $problems[] = "$u: PHP problems: " . implode(' | ', array_slice($r->phpErrors, 0, 2)); continue; }
        // A search page repeats what was typed (search box, "results for …") — that's not a leak.
        $body = $r->body;
        if (preg_match('/[?&]q=([^&]+)/', $u, $m)) $body = str_replace(htmlspecialchars(urldecode($m[1])), '', $body);
        foreach ($secrets as $sec) {
            if (strpos($body, $sec) !== false) $problems[] = "$u showed Bravo Alpha's \"$sec\"";
        }
        // The product endpoints must not hand over Alpha's catalogue either.
        if (strpos($u, '/api/') !== false && $r->status === 200) {
            $j = json_decode($r->body, true);
            if (strpos($u, 'preview.php') !== false && is_array($j) && isset($j['sell_price']) && empty($j['error'])) {
                $problems[] = "$u priced Alpha's product for Bravo (sell £{$j['sell_price']})";
            }
            if (strpos($u, 'product-data.php') !== false && is_array($j) && !empty($j['systems'])) {
                $problems[] = "$u returned Alpha's product systems to Bravo";
            }
            if (strpos($u, 'fabrics-search.php') !== false && is_array($j) && !empty($j['results'] ?? $j['fabrics'] ?? (array_is_list($j) ? $j : []))) {
                $problems[] = "$u returned Alpha's fabrics to Bravo";
            }
        }
        if (preg_match('#/admin/products/(edit|options|extras|price-tables|price-table)\.php#', $u) && $r->status === 200
            && strpos($r->body, (string) t_val('SELECT name FROM products WHERE id = ?', [$s['product']])) !== false
            && !in_array($r->status, [403, 404], true)) {
            // Same-named products exist in Bravo's own copy, so only the status says it was refused.
            if (strpos($u, 'edit.php') !== false) $problems[] = "$u opened Alpha's product for Bravo (HTTP 200)";
        }
    }
    if ($problems) fail(implode("\n", $problems));
});

test('Bravo cannot CHANGE or DELETE anything of Alpha\'s, whatever ids it sends', function () use (&$s, $fingerprint) {
    if (empty($s['quote'])) skip('set-up failed');
    $before = $fingerprint();
    $b = as_user('bravo.admin');
    $quote = ['id' => $s['quote'], 'quote_id' => $s['quote'], 'item_id' => $s['item'], 'ids' => [$s['quote']]];
    $prod  = ['id' => $s['product'], 'product_id' => $s['product'], 'system_id' => $s['system'], 'option_id' => $s['option'],
              'extra_id' => $s['extra'], 'choice_id' => $s['choice'], 'table_id' => $s['table'], 'price_table_id' => $s['table'],
              'ids' => [$s['product']], 'product_ids' => [$s['product']]];
    $harm  = ['name' => 'HACKED BY BRAVO', 'label' => 'HACKED', 'active' => 0, 'notes' => 'HACKED', 'confirm' => 1, 'confirm_name' => 'HACKED',
              'width' => '999', 'drop' => '999', 'markup' => 99, 'discount' => 99, 'bump_percent' => 50];
    $posts = [
        ['/quote-builder/change_status.php', $quote + ['target_status' => 'declined']],
        ['/quote-builder/delete.php',        $quote],
        ['/quote-builder/delete_item.php',   $quote],
        ['/quote-builder/update_item.php',   $quote + $harm + ['product_id' => $s['product'], 'system_id' => $s['system'], 'option_id' => $s['option']]],
        ['/quote-builder/duplicate_item.php', $quote],
        ['/quote-builder/add_item.php',      $quote + ['product_id' => $s['product'], 'system_id' => $s['system'], 'option_id' => $s['option'], 'width' => '1200', 'drop' => '1600', 'round_up' => 1]],
        ['/quote-builder/save_details.php',  $quote + $harm + ['customer_reference' => 'HACKED', 'end_customer_name' => 'HACKED']],
        ['/quote-builder/save_override.php', $quote + ['price_override' => '1.00']],
        ['/quote-builder/save_wt.php',       $quote + ['wt_amount' => '99']],
        ['/quote-builder/set_unit.php',      $quote + ['unit' => 'inch']],
        ['/quote-builder/deposit.php',       $quote + ['deposit_amount' => '1', 'amount' => '1']],
        ['/quote-builder/renew_link.php',    $quote],
        ['/quote-history/bulk_delete.php',   ['ids' => [$s['quote']], 'quote_ids' => [$s['quote']]]],
        ['/orders/archive.php',              $quote],
        ['/customer-manager/edit.php',       ['id' => $s['customer'], 'customer_id' => $s['customer']] + $harm + ['email' => 'hacked@example.test']],
        ['/customer-manager/delete.php',     ['id' => $s['customer'], 'customer_id' => $s['customer'], 'ids' => [$s['customer']]]],
        ['/admin/products/edit.php',         $prod + $harm],
        ['/admin/products/delete.php',       $prod],
        ['/admin/products/set-active.php',   $prod + ['active' => 0]],
        ['/admin/products/duplicate.php',    $prod],
        ['/admin/products/detach-source.php', $prod],
        ['/admin/products/set-category.php', $prod + ['category_id' => 0]],
        ['/admin/products/system-delete.php', $prod + ['id' => $s['system']]],
        ['/admin/products/systems.php',      $prod + $harm + ['action' => 'delete', '_action' => 'delete']],
        ['/admin/products/option-delete.php', $prod + ['id' => $s['option']]],
        ['/admin/products/option-edit.php',  $prod + $harm + ['id' => $s['option']]],
        ['/admin/products/option-set-default.php', $prod + ['id' => $s['option']]],
        ['/admin/products/option-set-band.php', $prod + ['band_code' => 'ZZ', 'option_ids' => [$s['option']]]],
        ['/admin/products/extra-delete.php', $prod + ['id' => $s['extra']]],
        ['/admin/products/extra-edit.php',   $prod + $harm + ['id' => $s['extra']]],
        ['/admin/products/extra-choice-delete.php', $prod + ['id' => $s['choice']]],
        ['/admin/products/extra-choice-edit.php', $prod + $harm + ['id' => $s['choice']]],
        ['/admin/products/price-table-delete.php', $prod + ['id' => $s['table']]],
        ['/admin/products/price-table.php',  $prod + $harm + ['id' => $s['table'], 'action' => 'bump', '_action' => 'bump']],
        ['/admin/products/reorder.php',      $prod + ['order' => [$s['product']]]],
    ];
    $problems = [];
    foreach ($posts as [$path, $fields]) {
        $r = t_submit($b, $path, $fields);
        if ($r->phpErrors) $problems[] = "$path: PHP problems: " . implode(' | ', array_slice($r->phpErrors, 0, 2));
        if ($fingerprint() !== $before) {
            $problems[] = "$path CHANGED Alpha's data when Bravo sent Alpha's ids";
            $before = $fingerprint();
        }
    }
    if ($problems) fail(implode("\n", $problems));
});

test('Alpha\'s customer-facing quote link works only with its own secret token', function () use (&$s) {
    if (empty($s['token'])) skip('quote has no public link token');
    $anon = new YbHttp();
    $ok = $anon->get('/quote-history/public.php?token=' . urlencode($s['token']));
    check($ok->status === 200 && strpos($ok->body, $s['qnum']) !== false, 'the real link did not open the quote (HTTP ' . $ok->status . ')');
    $bad = $anon->get('/quote-history/public.php?token=' . str_repeat('a', 40));
    check(strpos($bad->body, $s['qnum']) === false, 'a made-up token showed Alpha\'s quote');
    check(!$bad->phpErrors && !$ok->phpErrors, 'PHP problems on the public link page');
});

$accountsPages = ['account.php', 'bank.php', 'commissions.php', 'commission-pdf.php', 'credit-note.php', 'credit-note-pdf.php',
    'delivery-note-pdf.php', 'dispatch.php', 'invoice-edit.php', 'invoice-pdf.php', 'new-order.php', 'promotions.php',
    'quickbooks-customers.php', 'record-payment.php', 'statement.php', 'statement-pdf.php', 'statement-run.php',
    'statement-run-pdf.php', 'wholesale.php'];

test('a white-label factory cannot open ANY of the main factory\'s accounts screens', function () use ($accountsPages) {
    $c = as_user('charlie.office');
    $problems = [];
    foreach ($accountsPages as $p) {
        try { check_refused($c->get("/master-admin/$p?id=1&account_id=101&account=101"), "master-admin/$p as the white-label factory"); }
        catch (TestFailure $e) { $problems[] = $e->getMessage(); }
    }
    if ($problems) fail(implode("\n", $problems));
});

test('the main factory\'s office and the super-admin CAN open the accounts screens', function () {
    $problems = [];
    foreach (['factory.office', 'super'] as $who) {
        $h = as_user($who);
        foreach (['wholesale.php', 'dispatch.php', 'statement-run.php', 'bank.php', 'promotions.php', 'commissions.php'] as $p) {
            try { check_page_ok($h->get("/master-admin/$p"), "master-admin/$p as $who"); } catch (TestFailure $e) { $problems[] = $e->getMessage(); }
        }
    }
    if ($problems) fail(implode("\n", $problems));
});

test('a white-label factory owns only its OWN products, never the main factory\'s or its copies', function () {
    t_act_as('charlie.office');
    check_same(103, current_factory_id(), 'white-label factory id');
    foreach ([5, 85, (int) t_val('SELECT id FROM products WHERE client_id = 101 LIMIT 1')] as $pid) {
        check(!factory_owns_product(db(), $pid), "white-label factory claims product #$pid");
    }
    // Its seeded copies are linked to the main factory, so they're not its own either (#1058).
    $copy = (int) t_val('SELECT id FROM products WHERE client_id = 103 LIMIT 1');
    check(!factory_owns_product(db(), $copy), 'a linked copy counts as the white-label factory\'s own product');
    foreach (factory_products(db(), 103) as $p) {
        $pid = (int) (is_array($p) ? ($p['id'] ?? 0) : $p);
        $owner = (int) t_val('SELECT client_id FROM products WHERE id = ?', [$pid]);
        check_same(103, $owner, "factory_products() for the white-label factory lists product #$pid of client");
    }
    t_act_as('factory.office');
    check_same(3, current_factory_id(), 'main factory id');
    check(factory_owns_product(db(), 5), 'the main factory does not own its own roller');
    $_SESSION = [];
});

test('the main factory\'s orders never show on a white-label factory\'s screens (and do on its own)', function () use (&$s) {
    if (empty($s['quote'])) skip('set-up failed');
    $a = as_user('alpha.admin');
    $qid = flow_new_quote($a, fx('customer_alpha'), 'WL-PROBE');
    flow_add_line($a, $qid, 101, price_scenarios()['Roller, side winder, 1200 × 1600']);
    flow_place_order($a, $qid);
    $num = (string) t_val('SELECT quote_number FROM quotes WHERE id = ?', [$qid]);

    $f = as_user('factory.office');
    $seen = false;
    foreach (['/factory/pipeline.php', '/factory/incoming-orders.php', '/factory/orders.php?stage=all'] as $p) {
        $r = $f->get($p);
        check_page_ok($r, "$p as the factory");
        $seen = $seen || strpos($r->body, $num) !== false;
    }
    check($seen, "the factory can't see Alpha's placed order $num on its own screens");

    $c = as_user('charlie.office');
    foreach (['/factory/pipeline.php', '/factory/incoming-orders.php', '/factory/incoming-orders.php?lane=all'] as $p) {
        $r = $c->get($p);
        check_page_ok($r, "$p as the white-label factory");
        check_not_contains($num, $r->body, "$p as the white-label factory shows the main factory's order");
    }
    // Opening the main factory's order by number must show none of it (refused, or "no lines to edit").
    $r = $c->get("/factory/edit-order.php?order=$qid");
    check(!$r->phpErrors, 'PHP problems on edit-order');
    check_not_contains($num, $r->body, 'white-label factory opening the main factory\'s order by id');
    check_not_contains('Alpha Blinds', $r->body, 'white-label factory opening the main factory\'s order by id');
    check_not_contains('Lounge', $r->body, 'white-label factory opening the main factory\'s order by id (a line showed)');
});

test('the super-admin can see across companies (platform screens and the factory\'s order view)', function () use (&$s) {
    $h = as_user('super');
    check_page_ok($h->get('/master-admin/index.php'), 'Master Admin home');
    check_page_ok($h->get('/master-admin/trade-accounts.php'), 'trade accounts');
    $placed = (int) t_val("SELECT id FROM quotes WHERE client_id = 101 AND status = 'ordered' ORDER BY id DESC LIMIT 1");
    if ($placed) check_page_ok($h->get("/factory/edit-order.php?order=$placed"), 'factory order view of Alpha\'s order');
});
