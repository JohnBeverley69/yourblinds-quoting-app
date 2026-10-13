<?php
declare(strict_types=1);

/**
 * FACTORY OFFICE TICK. "Create orders" used to double as factory office: a
 * salesperson on a factory account with the Factory role and Create orders
 * quietly got the factory Pipeline and production set-up. Factory office is
 * now its own tick (can_factory_office, step 3 of Tyler's critique).
 */

$s = [];

test('set-up: a Charlie salesperson with the Factory role and Create orders, but not Factory office', function () use (&$s) {
    $hash = password_hash('yb-test-password', PASSWORD_BCRYPT, ['cost' => 4]);
    db()->prepare("INSERT INTO client_users (client_id, username, full_name, email, password_hash, role, active, is_super_admin,
                       email_verified_at, can_create_quotes, can_create_orders, can_factory_office)
                   VALUES (103, 'charlie.sales', 'Charlie Sales', 'charlie.sales@example.test', ?, 'sales', 1, 0, NOW(), 1, 1, 0)")
        ->execute([$hash]);
    $s['user'] = (int) db()->lastInsertId();
    foreach (['sales', 'factory'] as $r) {
        db()->prepare('INSERT INTO client_user_roles (user_id, role) VALUES (?, ?)')->execute([$s['user'], $r]);
    }
    check($s['user'] > 0, 'user not created');
});

test('without the tick: shop orders yes, factory office no', function () use (&$s) {
    if (empty($s['user'])) skip('set-up failed');
    $h = as_user('charlie.sales');
    check_page_ok($h->get('/orders/index.php'), 'Orders (shop)');
    foreach (['/factory/pipeline.php', '/factory/production-areas.php', '/factory/build-rules-v2.php', '/factory/settings.php'] as $p) {
        check_refused($h->get($p), "$p for a salesperson without Factory office");
    }
    check_page_ok($h->get('/factory/floor.php'), 'the Floor (any Factory-role login)');
});

test('Charlie\'s admin ticks Factory office on the Edit user screen → they get the factory office', function () use (&$s) {
    if (empty($s['user'])) skip('set-up failed');
    $r = t_submit(as_user('charlie.office'), '/admin/users_edit.php?id=' . $s['user'], [
        '_action' => 'update', 'id' => $s['user'], 'first_name' => 'Charlie', 'last_name' => 'Sales',
        'email' => 'charlie.sales@example.test', 'username' => 'charlie.sales', 'roles' => ['sales', 'factory'],
        'active' => 1, 'can_create_quotes' => 1, 'can_create_orders' => 1, 'can_factory_office' => 1,
    ]);
    check(!$r->phpErrors, 'Edit user: PHP problems: ' . implode(' | ', array_slice($r->phpErrors, 0, 2)));
    check_same(1, (int) t_val('SELECT can_factory_office FROM client_users WHERE id = ?', [$s['user']]), 'Factory office saved');
    check_page_ok(as_user('charlie.sales')->get('/factory/pipeline.php'), 'factory Pipeline with the tick');
});

test('a shop that isn\'t a factory never gets the tick, even if one is posted', function () {
    $alpha = (int) t_val("SELECT id FROM client_users WHERE username = 'alpha.sales'");
    t_submit(as_user('alpha.admin'), '/admin/users_edit.php?id=' . $alpha, [
        '_action' => 'update', 'id' => $alpha, 'first_name' => 'Alpha', 'last_name' => 'Sales',
        'email' => 'alpha.sales@example.test', 'username' => 'alpha.sales', 'roles' => ['sales'],
        'active' => 1, 'can_create_quotes' => 1, 'can_factory_office' => 1,
    ]);
    check_same(0, (int) t_val('SELECT can_factory_office FROM client_users WHERE id = ?', [$alpha]), 'Factory office on a non-factory account');
});
