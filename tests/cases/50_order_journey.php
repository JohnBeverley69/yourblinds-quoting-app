<?php
declare(strict_types=1);

/**
 * ORDER JOURNEY. One trade order goes all the way through, by the real screens:
 *
 *   Alpha places it (a made-in-house roller + a bought-in Forest Wood blind)
 *   → the factory presses Process order (floor jobs + a PO to Home Creations;
 *     the e-mail is dropped — tests never send mail)
 *   → the floor finishes the roller → the Forest Wood arrives from the supplier
 *   → Ready → Dispatched (delivery note) → Invoice → Payment → Paid.
 *
 * After every step the order's stage and money are checked. The invoice and
 * payment figures are compared with tests/goldens/journey.json.
 */

const JOURNEY_GOLDEN = APP_ROOT . '/tests/goldens/journey.json';

$j = [];
$stage = static fn (int $qid): string => (string) t_val('SELECT fulfilment_stage FROM quotes WHERE id = ?', [$qid]);
$step  = static function (TResponse $r, string $what): void {
    if ($r->phpErrors) fail("$what: PHP problems:\n    " . implode("\n    ", array_slice($r->phpErrors, 0, 5)));
    if ($r->status >= 400) fail("$what: HTTP {$r->status}");
};
/** The flash message the factory screens leave in the session, read off the next page. */
$flash = static function (YbHttp $h, string $page): string {
    $b = $h->get($page)->body;
    return preg_match_all('/class="[^"]*(?:alert|flash)[^"]*"[^>]*>(.*?)<\/(?:div|p)>/s', $b, $m)
        ? trim(preg_replace('/\s+/', ' ', strip_tags(implode(' | ', $m[1])))) : '';
};

test('1. Alpha places an order: a made roller + a bought-in Forest Wood blind', function () use (&$j, $stage) {
    $a = as_user('alpha.admin');
    $j['quote'] = flow_new_quote($a, fx('customer_alpha'), 'JOURNEY');
    $sc = price_scenarios();
    $roller = flow_add_line($a, $j['quote'], 101, $sc['Roller, side winder, 1200 × 1600'], 'Kitchen');
    $fw     = flow_add_line($a, $j['quote'], 101, $sc['Forest Wood 50mm, 4 hold-downs + 2 brackets + wand, 1650mm pelmet'], 'Bedroom');

    // The saved blind costs exactly what the live price said it would.
    foreach ([[$roller, 'Roller, side winder, 1200 × 1600'], [$fw, 'Forest Wood 50mm, 4 hold-downs + 2 brackets + wand, 1650mm pelmet']] as [$line, $name]) {
        $preview = price_line('alpha', $sc[$name]);
        check_money($preview['line_total'], $line['line_total'], "saved line total of \"$name\" vs the live price");
    }
    $j['number'] = (string) t_val('SELECT quote_number FROM quotes WHERE id = ?', [$j['quote']]);
    flow_place_order($a, $j['quote']);
    check_same('ordered', (string) t_val('SELECT status FROM quotes WHERE id = ?', [$j['quote']]), 'status after placing');
    check_same('confirmed', $stage($j['quote']), 'stage after placing');
    check(t_val('SELECT due_date FROM quotes WHERE id = ?', [$j['quote']]) !== null, 'no due date was promised when the order was placed');
});

test('2. the factory sees it and presses Process order', function () use (&$j, $stage, $step, $flash) {
    if (empty($j['quote'])) skip('order not placed');
    $f = as_user('factory.office');
    $pipe = $f->get('/factory/pipeline.php');
    check_page_ok($pipe, 'Pipeline');
    check_contains($j['number'], $pipe->body, 'the new order on the Pipeline');

    $r = t_submit($f, '/factory/process-order.php', ['quote_id' => $j['quote'], 'back' => '/factory/pipeline.php']);
    $step($r, 'Process order');
    $msg = $flash($f, '/factory/pipeline.php');
    check_same('in_production', $stage($j['quote']), "stage after Process order ($msg)");
    check_same(1, (int) t_val("SELECT COUNT(*) FROM factory_jobs WHERE quote_id = ? AND status = 'in_production'", [$j['quote']]), 'factory job in production');
    check((int) t_val('SELECT COUNT(*) FROM factory_blind_jobs WHERE quote_id = ?', [$j['quote']]) >= 1, 'no blind on the floor for the roller');
    $po = t_row('SELECT * FROM supplier_orders WHERE quote_id = ? AND ordered_by_factory_id = 3', [$j['quote']]);
    check($po !== null, "no purchase order went to the supplier for the Forest Wood ($msg)");
    check_same('Home Creations', trim((string) $po['supplier_name']), 'PO supplier');
    check_same('supplier162@example.test', (string) $po['email'], 'PO e-mail address (fake — mail is never sent in tests)');
    check(t_val('SELECT supplier_ordered_at FROM quotes WHERE id = ?', [$j['quote']]) !== null, 'supplier_ordered_at not stamped');
});

test('3. the floor finishes the roller — still waiting on the supplier, so not Ready', function () use (&$j, $stage, $step) {
    if (empty($j['quote'])) skip('order not processed');
    $f = as_user('factory.office');
    $step(t_submit($f, '/factory/set-status.php', ['quote_id' => $j['quote'], 'status' => 'made', 'force_made' => 1, 'back' => '/factory/pipeline.php']), 'mark made');
    check_same(0, (int) t_val("SELECT COUNT(*) FROM factory_blind_jobs WHERE quote_id = ? AND status <> 'complete'", [$j['quote']]), 'blinds still open on the floor');
    check_same('in_production', $stage($j['quote']), 'stage while the bought-in blind is still awaited');
    // Dispatch must be refused until it's Ready.
    $step(t_submit($f, '/factory/set-status.php', ['quote_id' => $j['quote'], 'status' => 'dispatched', 'back' => '/factory/pipeline.php']), 'early dispatch attempt');
    check_same(0, (int) t_val("SELECT COUNT(*) FROM factory_ar_delivery_notes WHERE source_quote_id = ?", [$j['quote']]), 'a delivery note was made before the order was ready');
    check_same('in_production', $stage($j['quote']), 'stage after a refused dispatch');
});

test('4. the Forest Wood arrives from Home Creations → Ready', function () use (&$j, $stage, $step) {
    if (empty($j['quote'])) skip('order not processed');
    $f = as_user('factory.office');
    $step(t_submit($f, '/factory/boughtin-received.php', ['quote_id' => $j['quote'], 'supplier_name' => 'Home Creations', 'back' => '/factory/pipeline.php']), 'mark received');
    check(t_val('SELECT received_at FROM supplier_orders WHERE quote_id = ?', [$j['quote']]) !== null, 'the PO is not marked received');
    check_same('ready', $stage($j['quote']), 'stage once everything is made and received');
});

test('5. dispatched → a delivery note exists', function () use (&$j, $stage, $step) {
    if (empty($j['quote'])) skip('order not ready');
    $f = as_user('factory.office');
    $step(t_submit($f, '/factory/set-status.php', ['quote_id' => $j['quote'], 'status' => 'dispatched', 'back' => '/factory/pipeline.php']), 'dispatch');
    check_same('dispatched', $stage($j['quote']), 'stage after dispatch');
    $dn = t_row('SELECT * FROM factory_ar_delivery_notes WHERE source_quote_id = ? ORDER BY id DESC LIMIT 1', [$j['quote']]);
    check($dn !== null, 'no delivery note');
    check($dn['dispatched_at'] !== null, 'delivery note not marked dispatched');
    $j['dn'] = (int) $dn['id'];
    check((int) t_val('SELECT COUNT(*) FROM factory_ar_delivery_note_lines WHERE delivery_note_id = ?', [$j['dn']]) >= 2, 'delivery note is missing lines');
    check_page_ok($f->get('/master-admin/delivery-note-pdf.php?id=' . $j['dn']), 'delivery note PDF');
});

test('6. invoiced → the invoice bills the right money', function () use (&$j, $step) {
    if (empty($j['dn'])) skip('not dispatched');
    $f = as_user('factory.office');
    $step(t_submit($f, '/master-admin/wholesale.php', ['_action' => 'inv_raise', 'quote_id' => $j['quote']]), 'raise invoice');
    $inv = t_row('SELECT i.* FROM factory_ar_invoices i JOIN factory_ar_invoice_orders o ON o.invoice_id = i.id WHERE o.quote_id = ? AND i.status <> \'void\'', [$j['quote']]);
    check($inv !== null, 'no invoice was raised');
    check_same(101, (int) $inv['account_client_id'], 'invoice account');
    check(in_array($inv['status'], ['raised', 'sent'], true), 'invoice status ' . $inv['status']);
    $j['inv'] = (int) $inv['id'];
    $j['money'] = [
        'invoice_net'   => (float) $inv['subtotal'],
        'invoice_vat'   => (float) $inv['vat'],
        'invoice_total' => (float) $inv['total'],
        // Dates in descriptions ("Delivery charge — Carrier, 12/10/2026") change daily; blank them.
        'invoice_lines' => array_map(static fn ($l) => [$l['line_type'], preg_replace('#\d{1,2}/\d{1,2}/\d{4}#', '<date>', (string) $l['description']), (float) $l['quantity'], (float) $l['unit_net'], (float) $l['line_net']],
                            t_rows('SELECT * FROM factory_ar_invoice_lines WHERE invoice_id = ? ORDER BY id', [$j['inv']])),
    ];
    check($j['money']['invoice_total'] > 0, 'invoice total is £0');
    check_money(round($j['money']['invoice_net'] + $j['money']['invoice_vat'], 2), $j['money']['invoice_total'], 'net + VAT = total');
    // Raising it twice must not double-bill.
    t_submit($f, '/master-admin/wholesale.php', ['_action' => 'inv_raise', 'quote_id' => $j['quote']]);
    check_same(1, (int) t_val("SELECT COUNT(*) FROM factory_ar_invoice_orders o JOIN factory_ar_invoices i ON i.id = o.invoice_id WHERE o.quote_id = ? AND i.status <> 'void'", [$j['quote']]), 'invoices for the order after a second click');
    check_page_ok($f->get('/master-admin/invoice-pdf.php?id=' . $j['inv']), 'invoice PDF');
});

test('7. the invoice figures are the expected ones (tests/goldens/journey.json)', function () use (&$j) {
    if (empty($j['money'])) skip('no invoice');
    if (getenv('YB_UPDATE_GOLDENS') === '1') {
        file_put_contents(JOURNEY_GOLDEN, json_encode(['note' => 'Expected invoice for the order-journey test — written by --update-goldens.',
            'money' => $j['money']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) . "\n");
        return;
    }
    $g = json_decode((string) @file_get_contents(JOURNEY_GOLDEN), true);
    check(is_array($g), 'tests/goldens/journey.json missing — run php tests/run.php --update-goldens');
    foreach (['invoice_net', 'invoice_vat', 'invoice_total'] as $k) check_money($g['money'][$k], $j['money'][$k], $k);
    check_same(t_show($g['money']['invoice_lines']), t_show($j['money']['invoice_lines']), 'invoice lines');
});

test('8. paid in full → the invoice is paid and the account owes nothing', function () use (&$j, $step) {
    if (empty($j['inv'])) skip('no invoice');
    $f = as_user('factory.office');
    $total = (float) t_val('SELECT total FROM factory_ar_invoices WHERE id = ?', [$j['inv']]);
    check_page_ok($f->get('/master-admin/record-payment.php?account_id=101'), 'record payment screen');
    $step(t_submit($f, '/master-admin/record-payment.php', ['account_id' => 101, 'action' => 'record', 'amount' => number_format($total, 2, '.', ''),
        'payment_date' => date('Y-m-d'), 'method' => 'bank', 'reference' => 'JOURNEY', 'alloc' => [$j['inv'] => number_format($total, 2, '.', '')]]), 'record payment');
    $inv = t_row('SELECT status, amount_paid, total FROM factory_ar_invoices WHERE id = ?', [$j['inv']]);
    check_same('paid', $inv['status'], 'invoice status after payment');
    check_money($inv['total'], $inv['amount_paid'], 'amount paid');
    check_same(1, (int) t_val("SELECT COUNT(*) FROM factory_ar_payments WHERE account_client_id = 101 AND reference = 'JOURNEY'"), 'payment recorded once');
    check_page_ok($f->get('/master-admin/statement.php?account_id=101'), 'Alpha\'s statement');
    check_page_ok($f->get('/master-admin/account.php?id=101'), 'Alpha\'s account page');
});

test('9. after the journey every screen that shows the order still opens cleanly', function () use (&$j) {
    if (empty($j['quote'])) skip('no order');
    $f = as_user('factory.office');
    foreach (['/factory/pipeline.php', '/factory/incoming-orders.php?lane=dispatched', '/factory/orders.php?stage=all',
              '/factory/edit-order.php?order=' . $j['quote'], '/master-admin/wholesale.php', '/factory/worksheets.php'] as $p) {
        check_page_ok($f->get($p), "$p as the factory");
    }
    $a = as_user('alpha.admin');
    check_page_ok($a->get('/quote-builder/edit.php?id=' . $j['quote']), 'Alpha\'s order page');
    check_page_ok($a->get('/orders/index.php'), 'Alpha\'s orders list');
});
