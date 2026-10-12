<?php
declare(strict_types=1);

// Quick checks that the test database was built the way the other tests expect.

test('the test database has the factory and three fake companies', function () {
    check_same(4, (int) t_val('SELECT COUNT(*) FROM clients WHERE id IN (3,101,102,103)'), 'companies');
    check_same('Test Factory Ltd', t_val('SELECT company_name FROM clients WHERE id = 3'), 'factory name');
});

test('no real e-mail addresses anywhere in the test data', function () {
    foreach (['clients' => 'email', 'client_users' => 'email', 'suppliers' => 'email', 'customers' => 'email'] as $t => $c) {
        $bad = t_val("SELECT COUNT(*) FROM `$t` WHERE $c IS NOT NULL AND $c <> '' AND $c NOT LIKE '%@example.test'");
        check_same(0, (int) $bad, "$t.$c not @example.test");
    }
});

test('a fake person can log in through the real login page', function () {
    $h = as_user('alpha.admin');
    check_page_ok($h->get('/dashboard/index.php'), 'dashboard');
});
