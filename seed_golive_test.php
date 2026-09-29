<?php
declare(strict_types=1);

/**
 * Go-live end-to-end test seed — STAGING / LOCAL COPIES ONLY.
 *
 * Builds a realistic test world on top of a "test copy" database (Backup &
 * Restore → Download test copy): 18 dummy trade accounts "Blind 1".."Blind 18"
 * (Blind 19/20 are left for the testers to create through the real screens),
 * each with company details (some VAT-registered, some not), a separate
 * delivery address on some, users in every role (admin, sales who may see
 * money, sales who may not, fitter, office), retail customers, calendar
 * appointments, trade discounts, and the factory catalogue pushed in through
 * the real catalogue-push engine. Plus factory staff logins.
 *
 * Every login shares one password, read from QA_PASSWORD in .env — it is never
 * written in this file. Refuses to run on production (APP_ENV=production) and
 * from the web. Re-runnable: it removes its own previous accounts first.
 *
 *   php seed_golive_test.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_partials/catalogue_push.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$appEnv = strtolower((string) (env('APP_ENV', 'production') ?? 'production'));
if ($appEnv === 'production' || $appEnv === 'prod') {
    fwrite(STDERR, "Refusing: APP_ENV is production. This seed is for staging/local copies only.\n");
    exit(1);
}
$password = (string) (env('QA_PASSWORD', '') ?? '');
if (strlen($password) < 8) {
    fwrite(STDERR, "Set QA_PASSWORD (8+ chars) in .env first.\n");
    exit(1);
}

ini_set('display_errors', '1');
error_reporting(E_ALL);
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

const QA_FACTORY  = 3;
const QA_ACCOUNTS = 18;
const QA_INBOX    = 'john.beverley@me.com';   // staging mail is intercepted here anyway
$hash = password_hash($password, PASSWORD_DEFAULT);

/** Existing columns of a table (cached). */
function qa_cols(PDO $pdo, string $t): array
{
    static $c = [];
    if (!isset($c[$t])) {
        $s = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?');
        $s->execute([$t]);
        $c[$t] = array_flip($s->fetchAll(PDO::FETCH_COLUMN));
    }
    return $c[$t];
}

/** INSERT only the keys the table really has; returns the new id. */
function qa_insert(PDO $pdo, string $t, array $row): int
{
    $row = array_intersect_key($row, qa_cols($pdo, $t));
    $cols = array_keys($row);
    $pdo->prepare('INSERT INTO `' . $t . '` (`' . implode('`,`', $cols) . '`) VALUES ('
        . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($row));
    return (int) $pdo->lastInsertId();
}

function qa_update(PDO $pdo, string $t, array $row, string $where, array $args): void
{
    $row = array_intersect_key($row, qa_cols($pdo, $t));
    if (!$row) return;
    $set = implode(', ', array_map(static fn ($c) => "`$c` = ?", array_keys($row)));
    $pdo->prepare("UPDATE `$t` SET $set WHERE $where")->execute(array_merge(array_values($row), $args));
}

$say = static function (string $s): void { echo $s, "\n"; };

// ── 0. Clear a previous run ─────────────────────────────────────────────────
$old = $pdo->query("SELECT id FROM clients WHERE account_ref LIKE 'BLIND%'")->fetchAll(PDO::FETCH_COLUMN);
if ($old) {
    $in = implode(',', array_map('intval', $old));
    foreach (['appointments', 'customers', 'suppliers', 'client_settings', 'client_plan_overrides',
              'client_markups', 'client_discounts', 'trade_discounts'] as $t) {
        if (qa_cols($pdo, $t)) $pdo->exec("DELETE FROM `$t` WHERE client_id IN ($in)");
    }
    $pdo->exec("DELETE FROM client_user_roles WHERE user_id IN (SELECT id FROM client_users WHERE client_id IN ($in))");
    $pdo->exec("DELETE FROM client_users WHERE client_id IN ($in)");
    foreach (['price_table_rows' => 'price_table_id IN (SELECT id FROM price_tables WHERE client_id IN (%s))',
              'price_tables' => 'client_id IN (%s)',
              'extra_choice_price_rows' => 'product_extra_choice_id IN (SELECT c.id FROM product_extra_choices c JOIN product_extras e ON e.id = c.product_extra_id WHERE e.client_id IN (%s))',
              'product_extra_parent_choices' => 'product_extra_id IN (SELECT id FROM product_extras WHERE client_id IN (%s))',
              'product_extra_choices' => 'product_extra_id IN (SELECT id FROM product_extras WHERE client_id IN (%s))',
              'product_extras' => 'client_id IN (%s)', 'product_options' => 'client_id IN (%s)',
              'product_systems' => 'client_id IN (%s)', 'products' => 'client_id IN (%s)',
              'product_categories' => 'client_id IN (%s)'] as $t => $w) {
        if (qa_cols($pdo, $t)) $pdo->exec("DELETE FROM `$t` WHERE " . sprintf($w, $in));
    }
    $pdo->exec("DELETE FROM clients WHERE id IN ($in)");
    $say('Removed ' . count($old) . ' accounts from a previous run.');
}
$pdo->exec("DELETE FROM client_user_roles WHERE user_id IN (SELECT id FROM client_users WHERE username LIKE 'qa.%')");
$pdo->exec("DELETE FROM client_users WHERE username LIKE 'qa.%'");
$pdo->exec('DELETE FROM login_attempts');

// ── 1. Factory: name, inbox, supplier emails, staff logins ──────────────────
qa_update($pdo, 'clients', [
    'company_name' => 'Beverley Blinds (Factory — TEST)', 'contact_name' => 'Factory Office',
    'email' => QA_INBOX, 'phone' => '024 7600 0000', 'vat_number' => 'GB 999 9999 73',
    'address1' => 'Unit 2, Test Park', 'town' => 'Coventry', 'county' => 'West Midlands',
    'postcode' => 'CV7 9EP', 'account_ref' => null,
], 'id = ?', [QA_FACTORY]);
qa_update($pdo, 'client_settings', [
    'order_notify_email' => QA_INBOX, 'factory_notify_email' => QA_INBOX, 'reply_to_email' => QA_INBOX,
    'feature_accounts' => 1, 'bank_account_name' => 'Beverley Blinds TEST',
    'bank_sort_code' => '00-00-00', 'bank_account_number' => '00000000',
], 'client_id = ?', [QA_FACTORY]);
$pdo->prepare('UPDATE suppliers SET email = ? WHERE client_id = ?')->execute([QA_INBOX, QA_FACTORY]);
// Every tenant's supplier contacts were scrubbed by the test copy — point them all at the inbox.
$pdo->prepare("UPDATE suppliers SET email = ? WHERE email LIKE '%@example.invalid' OR email IS NULL OR email = ''")->execute([QA_INBOX]);

$user = static function (int $clientId, string $username, string $name, string $role, array $flags,
                         array $extraRoles = []) use ($pdo, $hash): int {
    [$first, $last] = array_pad(explode(' ', $name, 2), 2, '');
    $id = qa_insert($pdo, 'client_users', array_merge([
        'client_id' => $clientId, 'username' => $username, 'full_name' => $name,
        'first_name' => $first, 'last_name' => $last, 'email' => null, 'password_hash' => $hash,
        'role' => $role, 'active' => 1, 'is_super_admin' => 0, 'email_verified_at' => date('Y-m-d H:i:s'),
    ], $flags));
    foreach (array_unique(array_merge([$role], $extraRoles)) as $r) {
        qa_insert($pdo, 'client_user_roles', ['user_id' => $id, 'role' => $r]);
    }
    return $id;
};
$all = ['can_create_quotes' => 1, 'can_create_orders' => 1, 'can_view_all_customer_jobs' => 1,
        'can_view_costs' => 1, 'dash_view_revenue' => 1, 'dash_view_team' => 1,
        'dash_view_products' => 1, 'dash_view_profit' => 1, 'dash_view_recent' => 1];
$none = ['can_create_quotes' => 0, 'can_create_orders' => 0, 'can_view_all_customer_jobs' => 0,
         'can_view_costs' => 0, 'dash_view_revenue' => 0, 'dash_view_team' => 0,
         'dash_view_products' => 0, 'dash_view_profit' => 0, 'dash_view_recent' => 0];

$user(QA_FACTORY, 'qa.super', 'Quinn Super', 'admin', $all + ['is_super_admin' => 1], ['factory']);
$user(QA_FACTORY, 'qa.office', 'Olive Office', 'office', $all, ['factory']);
$user(QA_FACTORY, 'qa.floor', 'Fred Floor', 'office', $none, ['factory']);
$say('Factory staff: qa.super (super-admin), qa.office, qa.floor.');

// ── 2. Trade accounts ───────────────────────────────────────────────────────
$towns = [
    ['Leicester', 'Leicestershire', 'LE1 %d', 'High Street'], ['Nottingham', 'Nottinghamshire', 'NG1 %d', 'Market Street'],
    ['Derby', 'Derbyshire', 'DE1 %d', 'Friar Gate'], ['Northampton', 'Northamptonshire', 'NN1 %d', 'Gold Street'],
    ['Wigan', 'Greater Manchester', 'WN1 %d', 'Standishgate'], ['Preston', 'Lancashire', 'PR1 %d', 'Fishergate'],
    ['York', 'North Yorkshire', 'YO1 %d', 'Stonegate'], ['Swindon', 'Wiltshire', 'SN1 %d', 'Regent Street'],
    ['Cardiff', 'South Glamorgan', 'CF10 %d', 'Queen Street'], ['Perth', 'Perthshire', 'PH1 %d', 'South Street'],
    ['Brixham', 'Devon', 'TQ5 %d', 'Fore Street'], ['Leamington Spa', 'Warwickshire', 'CV32 %d', 'The Parade'],
];
$firsts = ['Alice', 'Ben', 'Chloe', 'Dan', 'Emma', 'Frank', 'Grace', 'Harry', 'Isla', 'Jack', 'Katie', 'Liam',
           'Mia', 'Noah', 'Olivia', 'Paul', 'Rosie', 'Sam', 'Tara', 'Will'];
$lasts  = ['Archer', 'Baker', 'Carter', 'Dawson', 'Ellis', 'Fletcher', 'Gibson', 'Hughes', 'Irving', 'Jones',
           'Knight', 'Lawson', 'Mason', 'Nolan', 'Owens', 'Parker', 'Quinn', 'Reid', 'Shaw', 'Turner'];
$pickName = static function (int $i) use ($firsts, $lasts): string {
    return $firsts[$i % count($firsts)] . ' ' . $lasts[($i * 7 + 3) % count($lasts)];
};

// Base settings from a real trade tenant, so the dummies look like the genuine article.
$baseSettings = $pdo->query('SELECT s.* FROM client_settings s JOIN products p ON p.client_id = s.client_id
                              WHERE p.source_client_id = ' . QA_FACTORY . ' AND s.client_id <> ' . QA_FACTORY . '
                              ORDER BY s.client_id LIMIT 1')->fetch() ?: [];
unset($baseSettings['id'], $baseSettings['created_at'], $baseSettings['updated_at']);

$factoryProducts = $pdo->query('SELECT id, name FROM products WHERE client_id = ' . QA_FACTORY . ' AND active = 1 ORDER BY id')->fetchAll();
$prefixes = array_values(array_filter(array_map(static fn ($r) => trim((string) $r['prefix']),
    $pdo->query('SELECT prefix FROM library_suppliers')->fetchAll())));

$summary = [];
for ($n = 1; $n <= QA_ACCOUNTS; $n++) {
    [$town, $county, $pc, $street] = $towns[$n % count($towns)];
    $ref     = sprintf('BLIND%03d', $n);
    $vat     = $n % 3 !== 0;                   // every third account is NOT VAT-registered
    $contact = $pickName($n);
    $plan    = $n <= 10 ? 'gold' : ($n <= 14 ? 'silver' : 'free');

    $cid = qa_insert($pdo, 'clients', [
        'company_name' => "Blind $n", 'account_ref' => $ref, 'account_code' => $ref,
        'contact_name' => $contact, 'email' => "accounts@blind$n.test",
        'phone' => sprintf('01%03d %06d', 100 + $n, 400000 + $n * 137),
        'mobile' => sprintf('07700 9%05d', $n * 11), 'vat_number' => $vat ? sprintf('GB %03d %04d %02d', 100 + $n, 1000 + $n * 37, $n) : null,
        'address1' => (10 + $n) . ' ' . $street, 'address2' => $n % 2 ? 'Unit ' . $n : null,
        'town' => $town, 'county' => $county, 'postcode' => sprintf($pc, ($n % 9) + 1) . 'AB',
        'notes' => 'Go-live test account — safe to delete.', 'active' => 1,
    ]);

    $settings = array_merge($baseSettings, [
        'client_id' => $cid, 'quote_prefix' => 'B' . $n, 'vat_percent' => $vat ? 20 : 0,
        'email_from_name' => "Blind $n", 'reply_to_email' => "accounts@blind$n.test",
        'calendar_show_money' => $n % 2,                  // odd accounts show money on the calendar
        'feature_accounts' => $plan === 'gold' ? 1 : 0,
        'feature_maps' => $plan !== 'free' ? 1 : 0, 'feature_postcode_lookup' => $plan !== 'free' ? 1 : 0,
        'feature_ampm_slots' => $n % 4 === 0 ? 1 : 0,
        'supplier_delivery_address' => $n % 2 === 0
            ? "Blind $n Warehouse\n" . (5 + $n) . " Industrial Estate\n$town\n" . sprintf($pc, ($n % 9) + 2) . 'ZZ'
            : null,
        'order_notify_email' => QA_INBOX, 'factory_notify_email' => QA_INBOX,
        'default_sale_type' => $n % 5 === 0 ? 'trade' : 'retail',
        'bank_account_name' => "Blind $n Ltd", 'bank_sort_code' => '00-00-' . sprintf('%02d', $n),
        'bank_account_number' => sprintf('%08d', 10000000 + $n),
        'payment_instructions' => "Pay by bank transfer quoting your quote number.",
    ]);
    qa_insert($pdo, 'client_settings', $settings);
    if ($plan !== 'free') {
        qa_insert($pdo, 'client_plan_overrides', ['client_id' => $cid, 'plan_code' => $plan,
            'override_type' => 'comp', 'notes' => 'Go-live test', 'active' => 1]);
    }

    // Catalogue — the real push engine, exactly as Master Admin → Push updates.
    $errs = 0;
    foreach ($prefixes as $pfx) {
        $r = push_catalogue_to_client($pdo, QA_FACTORY, $cid, $pfx);
        $errs += count($r['errors']);
        foreach ($r['errors'] as $e) $say("  push error Blind $n: {$e['product']}: {$e['message']}");
    }

    // Tenant's own supplier list (third-party orders all land in the test inbox).
    foreach (['Beverley Blinds Trade', 'Louvolite', 'Decora'] as $s) {
        qa_insert($pdo, 'suppliers', ['client_id' => $cid, 'name' => $s, 'email' => QA_INBOX,
            'account_number' => $ref . '-' . strtoupper(substr($s, 0, 3))]);
    }

    // Trade discounts on even accounts: 10/15/20 % on a few factory products.
    if ($n % 2 === 0) {
        foreach (array_slice($factoryProducts, 0, 4) as $k => $fp) {
            qa_insert($pdo, 'trade_discounts', ['client_id' => $cid, 'product_id' => (int) $fp['id'],
                'discount_percent' => [10, 15, 20, 12.5][$k], 'active' => 1, 'notes' => 'Go-live test']);
        }
    }

    // Users — every role; one salesperson approved to see money, one not.
    $slug = "blind$n";
    $u = [];
    $u['admin']   = $user($cid, "$slug.admin", $contact, 'admin', $all);
    $u['sales']   = $user($cid, "$slug.sales", $pickName($n + 20), 'sales',
        ['can_create_quotes' => 1, 'can_create_orders' => 1, 'can_view_all_customer_jobs' => 1,
         'can_view_costs' => 1, 'dash_view_revenue' => 1, 'dash_view_profit' => 1, 'dash_view_recent' => 1]);
    $u['salesnm'] = $user($cid, "$slug.salesnm", $pickName($n + 40), 'sales', array_merge($none, ['can_create_quotes' => 1]));
    $u['fitter']  = $user($cid, "$slug.fitter", $pickName($n + 60), 'sales',
        array_merge($none, ['can_view_fittings_only' => 1]), ['fitter']);
    if ($n % 3 === 0) {
        $u['office'] = $user($cid, "$slug.office", $pickName($n + 80), 'office',
            ['can_create_quotes' => 1, 'can_create_orders' => 1, 'can_view_all_customer_jobs' => 1]);
    }

    // Retail customers + a few appointments this week and next.
    $custIds = [];
    for ($k = 1; $k <= 4; $k++) {
        $nm = $pickName($n * 5 + $k + 100);
        $custIds[] = qa_insert($pdo, 'customers', [
            'client_id' => $cid, 'name' => $nm, 'email' => "cust{$k}.blind$n@example.test",
            'phone' => sprintf('01%03d %06d', 200 + $k, 500000 + $n * 100 + $k),
            'mobile' => sprintf('07700 8%05d', $n * 10 + $k), 'has_whatsapp' => $k % 2,
            'address1' => ($k * 3) . ' ' . ['Church Lane', 'Mill Road', 'Park Avenue', 'Station Road'][$k - 1],
            'town' => $town, 'county' => $county, 'postcode' => sprintf($pc, $k) . 'CD',
            'notes' => $k === 1 ? 'Prefers mornings. Dog in the garden.' : null,
        ]);
    }
    $appt = static function (int $cust, ?int $userId, string $kind, int $dayOffset, string $time, string $note,
                             string $status = 'booked') use ($pdo, $cid, $n, $street, $town, $county, $pc): void {
        qa_insert($pdo, 'appointments', [
            'client_id' => $cid, 'client_user_id' => $userId, 'customer_id' => $cust,
            'title' => ucfirst($kind) . ' — Blind ' . $n, 'appt_kind' => $kind,
            'appointment_date' => date('Y-m-d', strtotime("+$dayOffset day")), 'appointment_time' => $time,
            'duration_minutes' => $kind === 'fitting' ? 120 : 60, 'status' => $status,
            'installation_address1' => $n . ' ' . $street, 'installation_town' => $town,
            'installation_county' => $county, 'installation_postcode' => sprintf($pc, 1) . 'EF',
            'notes' => $note,
        ]);
    };
    $appt($custIds[0], $u['sales'], 'measure', 0, '09:30:00', 'Lounge + 2 bedrooms. Park on the drive.');
    $appt($custIds[1], $u['salesnm'], 'measure', 1, '14:00:00', 'Conservatory roof — bring pleated samples.');
    $appt($custIds[2], $u['fitter'], 'fitting', 2, '10:00:00', 'Fit 3 verticals, 1 roller.');
    $appt($custIds[3], $u['sales'], 'measure', -1, '11:00:00', 'Measured yesterday.', 'completed');

    $summary[] = sprintf('Blind %-2d %s  VAT:%-3s plan:%-6s money-on-calendar:%s users:%d push-errors:%d',
        $n, $ref, $vat ? 'yes' : 'no', $plan, $n % 2 ? 'on' : 'off', count($u), $errs);
}

$say('');
foreach ($summary as $line) $say($line);
$say('');
$say('Done. Logins: blindN.admin / .sales (sees money) / .salesnm (no money) / .fitter / .office (every 3rd),');
$say('plus qa.super / qa.office / qa.floor at the factory. Password = QA_PASSWORD in .env.');
