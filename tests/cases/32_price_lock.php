<?php
declare(strict_types=1);

/**
 * PRICE LOCK. A trade account's copy of a factory product is billed by the
 * factory from the account's own copy, so the account must not be able to
 * change what feeds the price: option choices included (overnight audit
 * 2026-10-17 — the choice editor's lock looked up a column that doesn't exist
 * and so never fired; choice delete had no lock at all; the bulk options
 * import checked intval() of an array).
 */

$lk = [];

test('set-up: Alpha has a factory-copy product with an option choice', function () use (&$lk) {
    $row = t_row("SELECT c.id AS choice, e.id AS extra, p.id AS product, c.label, c.price_delta
                    FROM product_extra_choices c
                    JOIN product_extras e ON e.id = c.product_extra_id
                    JOIN products p ON p.id = e.product_id
                   WHERE p.client_id = 101 AND p.source_client_id = 3
                   ORDER BY c.id LIMIT 1");
    check(!empty($row), 'fixture: Alpha has no factory-copy option choice');
    $lk = $row;
});

$choiceSnap = static fn (int $id) => t_row('SELECT label, price_delta, price_percent, price_per_metre, active FROM product_extra_choices WHERE id = ?', [$id]);

test('a trade account cannot reprice an option choice on its factory copy', function () use (&$lk, $choiceSnap) {
    if (!$lk) skip('set-up failed');
    $before = $choiceSnap((int) $lk['choice']);
    $r = t_submit(as_user('alpha.admin'), '/admin/products/extra-choice-edit.php?id=' . $lk['choice'],
        ['label' => $lk['label'], 'price_delta' => '7.77', 'price_percent' => '3', 'price_per_metre' => '0', 'active' => 1]);
    check(!$r->phpErrors, "PHP problems:\n    " . implode("\n    ", $r->phpErrors));
    check_same($before, $choiceSnap((int) $lk['choice']), 'the factory-copy choice after the account saved it');
});

test('a trade account cannot delete an option choice on its factory copy', function () use (&$lk) {
    if (!$lk) skip('set-up failed');
    $r = t_submit(as_user('alpha.admin'), '/admin/products/extra-choice-delete.php', ['id' => $lk['choice'], 'extra_id' => $lk['extra']]);
    check(!$r->phpErrors, "PHP problems:\n    " . implode("\n    ", $r->phpErrors));
    check_same(1, (int) t_val('SELECT COUNT(*) FROM product_extra_choices WHERE id = ?', [$lk['choice']]), 'the choice is still there');
});

test('the lock lookup finds the product behind every kind of row', function () use (&$lk) {
    if (!$lk) skip('set-up failed');
    require_once APP_ROOT . '/_partials/product_lock.php';
    check_same((int) $lk['product'], pl_product_of('choice', (int) $lk['choice']), 'product of the choice');
    check_same((int) $lk['product'], pl_product_of('extra', (int) $lk['extra']), 'product of the option');
});
