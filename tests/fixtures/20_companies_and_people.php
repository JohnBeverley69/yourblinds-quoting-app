<?php
declare(strict_types=1);

/**
 * Fake companies and people (no real data). Runs after the SQL fixtures, with
 * the app loaded (tests/lib/run_fixture.php).
 *
 *   client 3    Test Factory Ltd      — the main factory (FACTORY_CLIENT_ID=3)
 *   client 101  Alpha Blinds Ltd      — trade account, seeded from the factory catalogue
 *   client 102  Bravo Interiors Ltd   — trade account, seeded (the "other tenant")
 *   client 103  Charlie Made Ltd      — white-label factory (is_factory = 1), seeded
 *
 * Every login's password is "yb-test-password" (test database only).
 * The catalogue copies are made with the REAL seed_client_from_template(), so
 * the copies test (cases/40_copies_match.php) checks what a new company gets.
 */

require_once APP_ROOT . '/_partials/seed_client_from_template.php';

$pdo  = db();
$hash = password_hash('yb-test-password', PASSWORD_BCRYPT, ['cost' => 4]);

$pdo->exec("UPDATE client_users SET active = 0 WHERE client_id = 3");   // none exist, but be sure
$companies = [
    101 => ['Alpha Blinds Ltd',    0, 'ALP'],
    102 => ['Bravo Interiors Ltd', 0, 'BRA'],
    103 => ['Charlie Made Ltd',    1, 'CHA'],
];
foreach ($companies as $id => [$name, $isFactory, $prefix]) {
    $pdo->prepare('INSERT INTO clients (id, company_name, email, active, is_factory, account_ref, address1, town, postcode)
                   VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?)')
        ->execute([$id, $name, strtolower($prefix) . '@example.test', $isFactory, $prefix . '001', '1 Fake Street', 'Testtown', 'TE1 1ST']);
    $pdo->prepare('INSERT INTO client_settings (client_id, quote_prefix, reply_to_email) VALUES (?, ?, ?)')
        ->execute([$id, $prefix, strtolower($prefix) . '@example.test']);
    $pdo->beginTransaction();   // as master-admin/new-client.php does
    $summary = seed_client_from_template($pdo, 3, $id);
    $pdo->commit();
    echo "$name seeded: " . json_encode($summary) . "\n";
    fx_set('client_' . strtolower(explode(' ', $name)[0]), $id);
}
fx_set('client_factory', 3);

// People. [username, client, primary role, extra roles, super, flags]
$all = ['can_create_quotes' => 1, 'can_create_orders' => 1, 'can_view_all_customer_jobs' => 1, 'can_view_costs' => 1,
        'dash_view_revenue' => 1, 'dash_view_team' => 1, 'dash_view_products' => 1, 'dash_view_profit' => 1, 'dash_view_recent' => 1];
$people = [
    ['super',           3,   'admin',  ['factory'], 1, $all],
    ['factory.office',  3,   'factory', [],         0, ['can_create_orders' => 1, 'can_view_costs' => 1, 'dash_view_revenue' => 1]],
    ['factory.floor',   3,   'factory', [],         0, []],
    ['alpha.admin',     101, 'admin',  [],          0, $all],
    ['alpha.sales',     101, 'sales',  [],          0, ['can_create_quotes' => 1]],
    ['alpha.fitter',    101, 'fitter', [],          0, []],
    ['bravo.admin',     102, 'admin',  [],          0, $all],
    ['charlie.office',  103, 'factory', ['admin'],  0, ['can_create_orders' => 1, 'can_view_costs' => 1] + $all],
];
foreach ($people as [$user, $client, $role, $extra, $super, $flags]) {
    $cols = ['client_id', 'username', 'full_name', 'email', 'password_hash', 'role', 'active', 'is_super_admin', 'email_verified_at'];
    $vals = [$client, $user, ucwords(str_replace('.', ' ', $user)), $user . '@example.test', $hash, $role, 1, $super, date('Y-m-d H:i:s')];
    // Every permission explicit (the table defaults can_create_quotes to 1). The primary
    // role column is an old ENUM without fitter/factory: like admin/users.php we store it
    // anyway (it ends up blank) and the real roles live in client_user_roles.
    foreach (array_keys($all) + [9 => 'can_view_fittings_only'] as $k) { $cols[] = $k; $vals[] = (int) ($flags[$k] ?? 0); }
    $pdo->prepare('INSERT INTO client_users (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($vals), '?')) . ')')
        ->execute($vals);
    $uid = (int) $pdo->lastInsertId();
    foreach (array_unique(array_merge([$role], $extra)) as $r) {
        $pdo->prepare('INSERT INTO client_user_roles (user_id, role) VALUES (?, ?)')->execute([$uid, $r]);
    }
    fx_set('user_' . str_replace('.', '_', $user), $uid);
}

// One end customer per tenant.
foreach ([3 => 'Factory', 101 => 'Alpha', 102 => 'Bravo', 103 => 'Charlie'] as $cid => $tag) {
    $pdo->prepare('INSERT INTO customers (client_id, name, email, address1, town, postcode) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$cid, "Mrs $tag Customer", strtolower($tag) . '.customer@example.test', '2 Fake Road', 'Testtown', 'TE2 2ND']);
    fx_set('customer_' . strtolower($tag), (int) $pdo->lastInsertId());
}

// Alpha's trade terms with the factory: 10% off Roller, 15% off Forest Wood 50mm.
// Stored against Alpha's OWN copies of the products (as the trade-account
// discount screen does); a factory quote FOR Alpha finds them via the master id.
$copy = static function (string $sql, array $p) use ($pdo): int {
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return (int) $st->fetchColumn();
};
$roller = $copy('SELECT id FROM products WHERE client_id = 101 AND source_product_id = ?', [5]);
$fw     = $copy('SELECT id FROM products WHERE client_id = 101 AND source_product_id = ?', [85]);
$fw50   = $copy('SELECT id FROM product_systems WHERE client_id = 101 AND source_system_id = ?', [246]);
$pdo->prepare('INSERT INTO trade_discounts (client_id, product_id, system_id, band_code, extra_id, choice_id, discount_percent, active, notes)
               VALUES (101, ?, NULL, NULL, NULL, NULL, 10, 1, "fixture"), (101, ?, ?, NULL, NULL, NULL, 15, 1, "fixture")')
    ->execute([$roller, $fw, $fw50]);

// The factory's supplier list has an In House row; keep every supplier e-mail fake.
$pdo->exec("UPDATE suppliers SET email = CONCAT('supplier', id, '@example.test')");
