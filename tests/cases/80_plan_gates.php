<?php
declare(strict_types=1);

/**
 * PLAN FEATURES AS REAL SWITCHES (John, 2026-10-17): with enforcement on, a
 * company below a listed feature's tier can't use it (page refused, menu item
 * hidden); off = everyone can. The main factory and the super-admin are never
 * locked. Bravo (102) has no plan = Bronze.
 */

require_once APP_ROOT . '/_partials/plan_gate.php';

$enforce = static fn (bool $on) => app_setting_set(PLAN_ENFORCE_KEY, $on ? '1' : '0');

test('plan gates: off by default — a Bronze company still uses Gold features', function () use ($enforce) {
    $enforce(false);
    check(!plan_enforcing(), 'enforcement should start off');
    $r = as_user('bravo.admin')->get('/messages/index.php');
    check($r->status < 400, 'Messages (a Gold feature) refused while enforcement is off (HTTP ' . $r->status . ')');
});

test('plan gates: on — a Bronze company is refused a Gold feature, with the plan named; Gold gets it', function () use ($enforce) {
    $enforce(true);
    try {
        db()->exec("DELETE FROM client_plan_overrides WHERE client_id = 102");
        $r = as_user('bravo.admin')->get('/messages/index.php');
        check_same(403, $r->status, 'Messages for a Bronze company');
        check_contains('Messages log is part of the Gold plan', $r->body, 'the refusal names the plan');
        check(!str_contains(as_user('bravo.admin')->get('/calendar/index.php')->body, 'href="/messages/index.php"'),
              'the Messages menu item should hide for a Bronze company');

        $r = as_user('bravo.admin')->get('/admin/products/price-tables-bulk-import.php');
        check_same(403, $r->status, 'price list import (Silver) for a Bronze company');

        // Adding anyone — a fitter too — is "Users and permissions" (Silver): refused on Bronze.
        foreach (['office', 'fitter'] as $role) {
            $r = t_submit(as_user('bravo.admin'), '/admin/users.php', ['_action' => 'create', 'first_name' => 'Gate', 'last_name' => 'Test',
                'username' => 'gate.test.' . $role, 'email' => '', 'password' => 'longenough1', 'roles' => [$role]]);
            check_contains('Users and permissions is part of the Silver plan', $r->body, "adding a $role on Bronze");
            check_same(0, (int) t_val('SELECT COUNT(*) FROM client_users WHERE username = ?', ['gate.test.' . $role]), "no $role was added");
        }

        // Gold by comp → Messages and import open.
        db()->exec("INSERT INTO client_plan_overrides (client_id, plan_code, override_type, notes) VALUES (102, 'gold', 'comp', 'gate test')");
        check(as_user('bravo.admin')->get('/messages/index.php')->status < 400, 'Messages for a Gold company');
        check(as_user('bravo.admin')->get('/admin/products/price-tables-bulk-import.php')->status < 400, 'price list import for a Gold company');

        // The main factory and the super-admin are never locked.
        check(plan_feature_allowed(3, 'messages_log') && plan_feature_allowed(3, 'team'), 'the main factory is never locked');
        check(as_user('super')->get('/messages/index.php')->status < 400, 'the super-admin is never locked');
    } finally {
        db()->exec("DELETE FROM client_plan_overrides WHERE client_id = 102 AND notes = 'gate test'");
        db()->exec("DELETE FROM client_users WHERE username IN ('gate.test.office', 'gate.test.fitter')");
        $enforce(false);
    }
});

test('plan gates: the board has the master switch, and only the super-admin can flip it', function () use ($enforce) {
    check_contains('Switch enforcement on', as_user('super')->get('/master-admin/plan-features.php')->body, 'the switch is on the board');
    check_refused(t_submit(as_user('alpha.admin'), '/master-admin/plan-features.php', ['action' => 'enforce', 'on' => '1']), 'a company admin flipping it');
    check(!plan_enforcing(), 'still off after a refused attempt');
    t_submit(as_user('super'), '/master-admin/plan-features.php', ['action' => 'enforce', 'on' => '1']);
    check(plan_enforcing(), 'on after the super-admin switches it on');
    $enforce(false);
});

test('plan gates: online acceptance — with it off for the plan, the quote page asks the customer to get in touch', function () use ($enforce) {
    // Move online acceptance up to Gold for this test, enforce, and check a Bronze company's quote page.
    app_setting_set(BILLING_CATALOGUE_KEY, json_encode(['online_accept' => 'gold']));
    $enforce(true);
    try {
        db()->exec("DELETE FROM client_plan_overrides WHERE client_id = 102");
        check(!plan_feature_allowed(102, 'online_accept'), 'a Bronze company should not have online acceptance');
        check(plan_feature_allowed(3, 'online_accept'), 'the main factory always has it');
        $qid = flow_new_quote(as_user('bravo.admin'), fx('customer_bravo'), 'GATE-ACCEPT');
        $tok = bin2hex(random_bytes(20));
        db()->prepare("UPDATE quotes SET status = 'sent', sent_at = NOW(), public_token = ? WHERE id = ?")->execute([$tok, $qid]);
        $page = (new YbHttp())->get('/quote-history/public.php?token=' . $tok)->body;
        check_contains('To accept this quote, please contact', $page, 'the quote page for a Bronze company');
        check_not_contains('name="signature_name"', $page, 'no accept form');
    } finally {
        app_setting_set(BILLING_CATALOGUE_KEY, null);
        $enforce(false);
    }
});

test('plan gates: EVERY box on the board has a real lock in the code (no describe-only boxes)', function () {
    $cat = require APP_ROOT . '/_partials/plan_feature_catalogue.php';
    $code = '';
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $p = strtr($f->getPathname(), [chr(92) => '/']);
        if ($f->getExtension() !== 'php' || preg_match('#/(vendor|tests|setup|\.git|\.claude)/#', $p)) continue;
        $code .= file_get_contents($p);
    }
    $missing = [];
    foreach ($cat as $key => [$label, , $tier]) {
        if ($tier === 'factory') continue;   // the factory side is its own lock (is_factory)
        if (!preg_match("/plan_(require_feature(_json)?|feature_allowed)\([^;]*'" . preg_quote($key, '/') . "'/", $code)
            && !str_contains($code, "\$_ybPlan('$key')")) {
            $missing[] = "$key ($label)";
        }
    }
    check($missing === [], 'boxes with no lock: ' . implode(', ', $missing));
});

test('plan gates: the core boxes lock too when moved up a tier (calendar, customers, new quote)', function () use ($enforce) {
    app_setting_set(BILLING_CATALOGUE_KEY, json_encode(['calendar' => 'silver', 'customers' => 'silver', 'quotes' => 'silver']));
    $enforce(true);
    try {
        db()->exec("DELETE FROM client_plan_overrides WHERE client_id = 102");
        $b = as_user('bravo.admin');
        check_same(403, $b->get('/customer-manager/index.php')->status, 'Customers for Bronze');
        check_same(403, $b->get('/calendar/week.php')->status, 'Calendar week for Bronze');
        check_same(403, $b->get('/quote-builder/new.php')->status, 'New quote for Bronze');
        $home = $b->get('/orders/index.php?scope=quotes')->body;
        check(!str_contains($home, 'href="/calendar/index.php"'), 'Calendar menu item hidden');
        check(!str_contains($home, 'href="/quote-builder/new.php"'), '+ New hidden');
    } finally {
        app_setting_set(BILLING_CATALOGUE_KEY, null);
        $enforce(false);
    }
});
