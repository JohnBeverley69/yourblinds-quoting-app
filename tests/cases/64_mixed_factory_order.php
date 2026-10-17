<?php
declare(strict_types=1);

/**
 * MIXED-FACTORY ORDER. A white-label factory (Charlie Made, client 103) places
 * one order holding a main-factory roller AND a vertical it makes itself — the
 * shape of Tyler's TYL-2026-0006. Both factories then work the same order. One
 * factory's floor actions must never touch the other's: a resync, a reset or a
 * force-complete by the main factory leaves Charlie's floor jobs alone, its
 * progress counts only its own blinds, and it can't delete the order or step it
 * back to new while Charlie makes part of it.
 */

require_once APP_ROOT . '/quote-builder/_helpers.php';
require_once APP_ROOT . '/_partials/blind_jobs.php';
require_once APP_ROOT . '/_partials/factory_order_delete.php';

$x = [];
/** Floor jobs on the order whose line is owned by $factory. */
$jobsOf = static fn (int $q, int $factory): int => (int) t_val(
    'SELECT COUNT(*) FROM factory_blind_jobs j
       JOIN quote_items qi ON qi.id = j.quote_item_id JOIN products p ON p.id = qi.product_id
      WHERE j.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?', [$q, $factory]);

test('set-up: Charlie places one order with a main-factory roller and its own vertical', function () use (&$x) {
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
    check_same('ordered', (string) t_val('SELECT status FROM quotes WHERE id = ?', [$qid]), 'status once placed');
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

test('a force-complete or reset by one factory touches only its own blinds', function () use (&$x, $jobsOf) {
    if (empty($x['q'])) skip('set-up failed');
    check_same(1, bj_force_complete_order(db(), $x['q'], null, 3), 'blinds the main factory completed');
    check_same(0, (int) t_val("SELECT COUNT(*) FROM factory_blind_jobs j
                               JOIN quote_items qi ON qi.id = j.quote_item_id JOIN products p ON p.id = qi.product_id
                              WHERE j.quote_id = ? AND p.source_client_id IS NULL AND j.status = 'complete'", [$x['q']]),
               'Charlie blinds completed by the main factory');
    bj_clear_order(db(), $x['q'], 3);
    check_same(0, $jobsOf($x['q'], 3), 'main factory floor jobs after its reset');
    check_same(1, $jobsOf($x['q'], 103), 'Charlie floor jobs after the main factory\'s reset');
});

test('the main factory can\'t delete the order or step it back to new while Charlie makes part of it', function () use (&$x) {
    if (empty($x['q'])) skip('set-up failed');
    check_contains('Charlie Made Ltd also makes part of it', fod_delete_block_reason(db(), $x['q'], 3), 'delete guard');
    check(fod_other_factory_reason(db(), $x['q'], 103) !== '', 'Charlie\'s delete guard names the main factory');});

test('...and through the real screens: step back to new is refused, the shared record kept', function () use (&$x) {
    if (empty($x['q'])) skip('set-up failed');
    $f = as_user('factory.office');
    t_submit($f, '/factory/set-status.php', ['quote_id' => $x['q'], 'status' => 'in_production', 'back' => '/factory/pipeline.php']);
    check_same('in_production', (string) t_val('SELECT status FROM factory_jobs WHERE quote_id = ?', [$x['q']]), 'main factory took it into production');
    $r = t_submit($f, '/factory/set-status.php', ['quote_id' => $x['q'], 'status' => 'new', 'back' => '/factory/pipeline.php']);
    check(!$r->phpErrors, 'set-status: PHP problems: ' . implode(' | ', array_slice($r->phpErrors, 0, 2)));
    check_same('in_production', (string) t_val('SELECT status FROM factory_jobs WHERE quote_id = ?', [$x['q']]), 'factory_jobs after a refused step back');
    check_same(1, (int) t_val('SELECT COUNT(*) FROM quotes WHERE id = ?', [$x['q']]), 'the order still exists');
});

test('clean-up: Charlie\'s vertical goes back to being the main factory\'s', function () use (&$x) {
    if (empty($x['own_product'])) skip('nothing to undo');
    db()->prepare('UPDATE products SET source_client_id = ? WHERE id = ?')->execute([$x['own_source'] ?: null, $x['own_product']]);
    check(true, '');
});
