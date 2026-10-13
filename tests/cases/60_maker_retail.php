<?php
declare(strict_types=1);

/**
 * MAKER-RETAILER. A white-label factory (Charlie Made, client 103) that also
 * sells and fits retail. Its own retail job must behave like any shop's:
 * accepting it puts an Install card in Pending Fitting, and — because Charlie
 * makes the blind — it goes straight into CHARLIE's queue with a due date,
 * never the main factory's. Trade work still never gets a fitting.
 */

require_once APP_ROOT . '/quote-builder/_helpers.php';

$m = [];
$fittings = static fn (int $q): int => (int) t_val(
    "SELECT COUNT(*) FROM appointments WHERE quote_id = ? AND appt_kind = 'fitting' AND appointment_date IS NULL", [$q]);

/** A Charlie quote with one roller; $own = Charlie makes it (its own product, not the main factory's). */
$charlieQuote = static function (string $ref, bool $own) use (&$m): int {
    $c   = as_user('charlie.office');
    $qid = flow_new_quote($c, fx('customer_charlie'), $ref);
    $line = flow_add_line($c, $qid, 103, price_scenarios()['Roller, side winder, 1200 × 1600']);
    if ($own) {
        // Make the roller Charlie's own product (as a Duplicate would), for the length of this file.
        $m['own_product'] = (int) $line['product_id'];
        $m['own_source']  = (int) t_val('SELECT source_client_id FROM products WHERE id = ?', [$m['own_product']]);
        db()->prepare('UPDATE products SET source_client_id = NULL WHERE id = ?')->execute([$m['own_product']]);
    }
    return $qid;
};

test('Charlie\'s own retail job: accepting it puts an Install card in Pending Fitting', function () use (&$m, $fittings, $charlieQuote) {
    $qid = $charlieQuote('MAKER RETAIL', true);
    $m['retail'] = $qid;
    check_same('retail', (string) t_val('SELECT sale_type FROM quotes WHERE id = ?', [$qid]), 'sale type of a quote from the quote builder');
    flow_status(as_user('charlie.office'), $qid, 'accepted', ['accepted', 'ordered']);
    check_same(1, $fittings($qid), 'Install cards in Pending Fitting');
    $title = (string) t_val("SELECT title FROM appointments WHERE quote_id = ? AND appt_kind = 'fitting'", [$qid]);
    check_contains('Install:', $title, 'fitting title');
});

test('...and it goes straight into Charlie\'s own factory queue, not the main factory\'s', function () use (&$m) {
    if (empty($m['retail'])) skip('set-up failed');
    $qid = $m['retail'];
    check_same('ordered', (string) t_val('SELECT status FROM quotes WHERE id = ?', [$qid]), 'status (all made in-house → auto-placed)');
    check(t_val('SELECT due_date FROM quotes WHERE id = ?', [$qid]) !== null, 'no due date was promised when it was placed');
    $number = (string) t_val('SELECT quote_number FROM quotes WHERE id = ?', [$qid]);
    $mine = as_user('charlie.office')->get('/factory/incoming-orders.php');
    check_page_ok($mine, 'Charlie\'s Incoming orders');
    check_contains($number, $mine->body, 'the job on Charlie\'s Incoming orders');
    $main = as_user('factory.office')->get('/factory/incoming-orders.php');
    check_page_ok($main, 'main factory Incoming orders');
    check_not_contains($number, $main->body, 'Charlie\'s own retail job on the main factory\'s Incoming orders');
});

test('Charlie\'s trade sale gets no fitting', function () use ($fittings, $charlieQuote) {
    $qid = $charlieQuote('MAKER TRADE', false);
    db()->prepare("UPDATE quotes SET sale_type = 'trade' WHERE id = ?")->execute([$qid]);
    flow_status(as_user('charlie.office'), $qid, 'accepted', ['accepted', 'ordered']);
    check_same(0, $fittings($qid), 'fittings for a trade sale');
});

test('an old unstamped Charlie quote is assumed trade — no fitting', function () use ($fittings, $charlieQuote) {
    $qid = $charlieQuote('MAKER OLD', false);
    db()->prepare('UPDATE quotes SET sale_type = NULL WHERE id = ?')->execute([$qid]);
    flow_status(as_user('charlie.office'), $qid, 'accepted', ['accepted', 'ordered']);
    check_same(0, $fittings($qid), 'fittings for an unstamped factory quote');
});

test('a plain shop\'s retail job still gets its fitting; the main factory\'s jobs never do', function () use ($fittings) {
    $a = as_user('alpha.admin');
    $qid = flow_new_quote($a, fx('customer_alpha'), 'SHOP RETAIL');
    flow_add_line($a, $qid, 101, price_scenarios()['Roller, side winder, 1200 × 1600']);
    flow_status($a, $qid, 'accepted', ['accepted', 'ordered']);
    check_same(1, $fittings($qid), 'Install cards for Alpha\'s retail job');

    // The main factory is trade-only: even a quote stamped retail never gets one.
    $s = as_user('super');
    $fq = flow_new_quote($s, fx('customer_factory'), 'FACTORY RETAIL?');
    db()->prepare("UPDATE quotes SET sale_type = 'retail' WHERE id = ?")->execute([$fq]);
    check(qb_create_appointment_from_quote(db(), $fq) === null, 'the main factory got a fitting');
    check_same(0, $fittings($fq), 'fittings on the main factory');
});

test('clean-up: Charlie\'s roller goes back to being the main factory\'s', function () use (&$m) {
    if (empty($m['own_product'])) skip('nothing to undo');
    db()->prepare('UPDATE products SET source_client_id = ? WHERE id = ?')->execute([$m['own_source'] ?: null, $m['own_product']]);
    check(true, '');
});
