<?php
declare(strict_types=1);

/**
 * MIXED-FACTORY ORDER. A white-label factory (Charlie Made, client 103) places
 * one order holding a main-factory roller AND a vertical it makes itself — the
 * shape of Tyler's TYL-2026-0006. It stays ONE order for the customer, but each
 * factory has its own part (order_parts.php): its own status, floor, due date,
 * "new order" email and stage. Nothing one factory does moves the other's part.
 * The main factory bills and delivers to Charlie, never Charlie's customer, and
 * Charlie's Pipeline shows the main factory's part as a read-only "Buy" line.
 */

require_once APP_ROOT . '/quote-builder/_helpers.php';
require_once APP_ROOT . '/_partials/blind_jobs.php';
require_once APP_ROOT . '/_partials/factory_order_delete.php';
require_once APP_ROOT . '/_partials/order_stage.php';
require_once APP_ROOT . '/_partials/due_dates.php';
require_once APP_ROOT . '/_partials/factory_ar.php';
require_once APP_ROOT . '/_partials/factory_console.php';

$x = [];
/** Floor jobs on the order whose line is owned by $factory. */
$jobsOf = static fn (int $q, int $factory): int => (int) t_val(
    'SELECT COUNT(*) FROM factory_blind_jobs j
       JOIN quote_items qi ON qi.id = j.quote_item_id JOIN products p ON p.id = qi.product_id
      WHERE j.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?', [$q, $factory]);
$fj = static fn (int $q, int $factory): string => (string) (t_val(
    'SELECT status FROM factory_jobs WHERE quote_id = ? AND factory_client_id = ?', [$q, $factory]) ?: '');
$part = static fn (int $q, int $factory): array => t_row(
    'SELECT * FROM order_factory_parts WHERE quote_id = ? AND factory_client_id = ?', [$q, $factory]) ?? [];
$status = static fn (string $user, int $q, string $to) => t_submit(as_user($user), '/factory/set-status.php',
    ['quote_id' => $q, 'status' => $to, 'back' => '/factory/pipeline.php']);

test('set-up: Charlie places one order with a main-factory roller and its own vertical', function () use (&$x) {
    // The main factory has a new-order address, so placing it tells the main factory.
    db()->prepare("UPDATE client_settings SET order_notify_email = 'factory.orders@example.test' WHERE client_id = 3")->execute();
    $c   = as_user('charlie.office');
    $qid = flow_new_quote($c, fx('customer_charlie'), 'MIXED FACTORIES');
    flow_add_line($c, $qid, 103, price_scenarios()['Roller, side winder, 1200 × 1600']);
    $vert = flow_add_line($c, $qid, 103, price_scenarios()['Vertical Vogue, gold headrail + brackets per metre'], 'Kitchen');
    // The vertical becomes Charlie's own product (as a Duplicate would), for the length of this file.
    $x['own_product'] = (int) $vert['product_id'];
    $x['own_source']  = (int) t_val('SELECT source_client_id FROM products WHERE id = ?', [$x['own_product']]);
    db()->prepare('UPDATE products SET source_client_id = NULL WHERE id = ?')->execute([$x['own_product']]);
    db()->prepare("UPDATE quotes SET sale_type = 'trade' WHERE id = ?")->execute([$qid]);
    flow_place_order($c, $qid);
    $x['q'] = $qid;
    $x['number'] = (string) t_val('SELECT quote_number FROM quotes WHERE id = ?', [$qid]);
    check_same('ordered', (string) t_val('SELECT status FROM quotes WHERE id = ?', [$qid]), 'status once placed');
    check_same([3, 103], op_order_factories(db(), $qid), 'factories making it');
});

test('placing it: each factory gets its own part, and only the main factory is emailed', function () use (&$x, $part) {
    if (empty($x['q'])) skip('set-up failed');
    check(!empty($part($x['q'], 3)), 'no part for the main factory');
    check(!empty($part($x['q'], 103)), 'no part for Charlie');
    check(!empty($part($x['q'], 3)['notified_at']), 'the main factory was not told about its part');
    check(empty($part($x['q'], 103)['notified_at']), 'Charlie was emailed about its own order');
    check_same('confirmed', (string) ($part($x['q'], 3)['fulfilment_stage'] ?? ''), 'main factory stage');
    check_same('confirmed', (string) ($part($x['q'], 103)['fulfilment_stage'] ?? ''), 'Charlie stage');
});

test('each factory puts only its own blinds on its floor', function () use (&$x, $jobsOf) {
    if (empty($x['q'])) skip('set-up failed');
    bj_release_order(db(), $x['q'], 3);
    bj_release_order(db(), $x['q'], 103);
    check_same(1, $jobsOf($x['q'], 3), 'main factory floor jobs');
    check_same(1, $jobsOf($x['q'], 103), 'Charlie floor jobs');
    check_same(['total' => 1, 'done' => 0], bj_order_progress(db(), [$x['q']], 3)[$x['q']] ?? null, 'main factory progress (its own blinds only)');
});

test('a resync by one factory leaves the other factory\'s floor jobs alone', function () use (&$x, $jobsOf) {
    if (empty($x['q'])) skip('set-up failed');
    $r = bj_resync_order(db(), $x['q'], 3);
    check_same(0, $r['removed'], 'jobs removed by the main factory\'s resync');
    check_same(1, $jobsOf($x['q'], 103), 'Charlie floor jobs after the main factory resynced');
    bj_resync_order_all(db(), $x['q']);
    check_same(1, $jobsOf($x['q'], 3), 'main factory floor jobs after a resync of every factory');
    check_same(1, $jobsOf($x['q'], 103), 'Charlie floor jobs after a resync of every factory');
});

test('each factory\'s status is its own: the main factory moving its part never moves Charlie\'s', function () use (&$x, $fj, $status, $part) {
    if (empty($x['q'])) skip('set-up failed');
    $r = $status('factory.office', $x['q'], 'in_production');
    check(!$r->phpErrors, 'set-status: PHP problems: ' . implode(' | ', array_slice($r->phpErrors, 0, 2)));
    check_same('in_production', $fj($x['q'], 3), 'main factory status');
    check_same('', $fj($x['q'], 103), 'Charlie status after the main factory took its part in');
    $r = $status('charlie.office', $x['q'], 'in_production');
    check(!$r->phpErrors, 'set-status (Charlie): PHP problems: ' . implode(' | ', array_slice($r->phpErrors, 0, 2)));
    check_same('in_production', $fj($x['q'], 103), 'Charlie status');
    // Finishing the main factory's blind makes ITS part made / Ready — Charlie's stays in production.
    bj_force_complete_order(db(), $x['q'], null, 3);
    bj_maybe_complete_order(db(), $x['q'], 3);
    check_same('made', $fj($x['q'], 3), 'main factory status once its blind is finished');
    check_same('in_production', $fj($x['q'], 103), 'Charlie status once the main factory\'s blind is finished');
    recompute_order_stage(db(), $x['q']);
    check_same('ready', (string) $part($x['q'], 3)['fulfilment_stage'], 'main factory stage');
    check_same('in_production', (string) $part($x['q'], 103)['fulfilment_stage'], 'Charlie stage');
    check_same('in_production', (string) t_val('SELECT fulfilment_stage FROM quotes WHERE id = ?', [$x['q']]), 'customer-facing stage (the slowest part)');
});

test('each Pipeline shows only its own part; Charlie sees the main factory\'s part as a Buy line', function () use (&$x) {
    if (empty($x['q'])) skip('set-up failed');
    $mainName = (string) t_val('SELECT company_name FROM clients WHERE id = 3');
    $main = fc_order_status(db(), 3, t_row('SELECT * FROM quotes WHERE id = ?', [$x['q']]));
    $mine = fc_order_status(db(), 103, t_row('SELECT * FROM quotes WHERE id = ?', [$x['q']]));
    check_same('ready', $main['stage'], 'the main factory\'s Pipeline stage');
    check_same('in_production', $mine['stage'], 'Charlie\'s Pipeline stage');
    $t = ot_order_tracks(db(), [$x['q']], 103)[$x['q']] ?? [];
    check(isset($t['partners'][$mainName]), 'Charlie\'s tracks don\'t show the main factory\'s part');
    check_same('ready', (string) ($t['partners'][$mainName]['stage'] ?? ''), 'the main factory\'s part as Charlie sees it');
    check(empty(ot_order_tracks(db(), [$x['q']], 3)[$x['q']]['partners'] ?? []), 'the main factory sees a Buy line on someone else\'s order');
    $page = as_user('charlie.office')->get('/factory/pipeline.php');
    check_page_ok($page, 'Charlie\'s Pipeline');
    check_contains('Buy: ' . htmlspecialchars($mainName, ENT_QUOTES), $page->body, 'the Buy line on Charlie\'s Pipeline');
    check_page_ok(as_user('factory.office')->get('/factory/pipeline.php'), 'main factory Pipeline');
});

test('Charlie can\'t send the job out until the main factory has dispatched its part', function () use (&$x) {
    if (empty($x['q'])) skip('set-up failed');
    bj_force_complete_order(db(), $x['q'], null, 103);
    bj_maybe_complete_order(db(), $x['q'], 103);
    check(!os_is_ready(db(), $x['q'], 103), 'Charlie\'s part reads Ready while the main factory\'s is still here');
    check(os_is_ready(db(), $x['q'], 3), 'the main factory\'s part is ready to go out');
});

test('stepping back to new resets only that factory\'s part', function () use (&$x, $fj, $jobsOf, $status) {
    if (empty($x['q'])) skip('set-up failed');
    $r = $status('factory.office', $x['q'], 'new');
    check(!$r->phpErrors, 'set-status: PHP problems: ' . implode(' | ', array_slice($r->phpErrors, 0, 2)));
    check_same('', $fj($x['q'], 3), 'main factory status after stepping back');
    check_same(0, $jobsOf($x['q'], 3), 'main factory floor jobs after stepping back');
    check_same('made', $fj($x['q'], 103), 'Charlie status after the main factory stepped back');
    check_same(1, $jobsOf($x['q'], 103), 'Charlie floor jobs after the main factory stepped back');
});

test('the main factory can\'t delete the order while Charlie makes part of it', function () use (&$x) {
    if (empty($x['q'])) skip('set-up failed');
    check_contains('Charlie Made Ltd also makes part of it', fod_delete_block_reason(db(), $x['q'], 3), 'delete guard');
    check(fod_other_factory_reason(db(), $x['q'], 103) !== '', 'Charlie\'s delete guard names the main factory');
});

test('due dates: each factory\'s own, the customer sees the later one', function () use (&$x, $part) {
    if (empty($x['q'])) skip('set-up failed');
    dd_set_due(db(), $x['q'], '2030-01-10', 3);
    dd_set_due(db(), $x['q'], '2030-02-20', 103);
    check_same('2030-01-10', (string) $part($x['q'], 3)['due_date'], 'main factory due date');
    check_same('2030-02-20', (string) $part($x['q'], 103)['due_date'], 'Charlie due date');
    check_same('2030-02-20', (string) t_val('SELECT due_date FROM quotes WHERE id = ?', [$x['q']]), 'customer-facing due date');
    check_same('2030-01-10', op_due_for(db(), [$x['q']], 3)[$x['q']] ?? '', 'the main factory\'s screens');
});

test('billing: an order Charlie keyed in for its own customer is billed and delivered to Charlie', function () use (&$x) {
    if (empty($x['q'])) skip('set-up failed');
    check_same(103, ar_order_account_id(db(), $x['q']), 'account for Charlie\'s own order');
    db()->prepare('UPDATE quotes SET account_client_id = 101 WHERE id = ?')->execute([$x['q']]);
    check_same(103, ar_order_account_id(db(), $x['q']), 'account when Charlie keyed it in for its customer (Alpha)');
    $placed = array_values(array_filter(ar_placed_orders(db(), 3), static fn ($o) => (int) $o['id'] === $x['q']));
    check_same(103, (int) ($placed[0]['account_id'] ?? $placed[0]['account_client_id'] ?? 0), 'the account on the main factory\'s invoicing list');
    $fc = fc_order_status(db(), 3, t_row('SELECT * FROM quotes WHERE id = ?', [$x['q']]));
    check_same(103, (int) $fc['account_key'], 'the account on the main factory\'s console');
    db()->prepare('UPDATE quotes SET account_client_id = NULL WHERE id = ?')->execute([$x['q']]);
});

test('clean-up: Charlie\'s vertical goes back to being the main factory\'s', function () use (&$x) {
    if (empty($x['own_product'])) skip('nothing to undo');
    db()->prepare('UPDATE products SET source_client_id = ? WHERE id = ?')->execute([$x['own_source'] ?: null, $x['own_product']]);
    db()->prepare('UPDATE client_settings SET order_notify_email = NULL WHERE client_id = 3')->execute();
    check(true, '');
});
