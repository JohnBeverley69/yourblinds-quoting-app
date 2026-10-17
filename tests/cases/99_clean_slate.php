<?php
declare(strict_types=1);

/**
 * CLEAN SLATE (John, 2026-10-17): setup/tools/clean_slate.php clears every
 * account's quotes, orders, customers, calendar and their trail, and must never
 * touch the catalogue. It runs LAST (99_) because it empties the test data.
 */

$cat = static fn (): array => [
    'products'     => (int) t_val('SELECT COUNT(*) FROM products'),
    'price rows'   => (int) t_val('SELECT COUNT(*) FROM price_table_rows'),
    'choices'      => (int) t_val('SELECT COUNT(*) FROM product_extra_choices'),
    'trade discs'  => (int) t_val('SELECT COUNT(*) FROM trade_discounts'),
    'suppliers'    => (int) t_val('SELECT COUNT(*) FROM suppliers'),
    'accounts'     => (int) t_val('SELECT COUNT(*) FROM clients'),
    'users'        => (int) t_val('SELECT COUNT(*) FROM client_users'),
];
$tool = '/setup/tools/clean_slate.php?apply=1&confirm=CLEAR-EVERYTHING';

test('clean slate: a company admin is refused', function () use ($tool) {
    check_refused(t_submit(as_user('alpha.admin'), $tool, ['_run_confirm' => 1]), 'a company admin running the clean slate');
});

test('clean slate: refused without a fresh backup, then clears orders and customers but keeps the catalogue', function () use ($cat, $tool) {
    db()->exec('UPDATE clients SET last_backup_at = NULL WHERE id = 3');
    $r = t_submit(as_user('super'), $tool, ['_run_confirm' => 1]);
    check(str_contains($r->body, 'REFUSED'), 'it refuses with no backup in the last 24 hours');

    $before = $cat();
    db()->exec('UPDATE clients SET last_backup_at = NOW() WHERE id = 3');
    $r = t_submit(as_user('super'), $tool, ['_run_confirm' => 1]);
    check(str_contains($r->body, 'Done — no quotes, orders or customers left'), "it reports done:\n" . substr($r->body, -400));
    check_same(0, (int) t_val('SELECT COUNT(*) FROM quotes'), 'quotes left');
    check_same(0, (int) t_val('SELECT COUNT(*) FROM customers'), 'customers left');
    check_same(0, (int) t_val('SELECT COUNT(*) FROM appointments'), 'calendar appointments left');
    check_same($before, $cat(), 'catalogue, accounts and users (must be untouched)');
});
