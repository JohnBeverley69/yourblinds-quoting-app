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
        check_same(403, $r->status, 'price list import (Platinum) for a Bronze company');

        // Gold by comp → Messages opens; Platinum features still don't.
        db()->exec("INSERT INTO client_plan_overrides (client_id, plan_code, override_type, notes) VALUES (102, 'gold', 'comp', 'gate test')");
        check(as_user('bravo.admin')->get('/messages/index.php')->status < 400, 'Messages for a Gold company');
        check(as_user('bravo.admin')->get('/admin/products/price-tables-bulk-import.php')->status < 400, 'price list import for a Gold company');

        // Adding staff (Team, Platinum) is refused on Gold; the page itself still opens.
        $r = t_submit(as_user('bravo.admin'), '/admin/users.php', ['_action' => 'create', 'first_name' => 'Gate', 'last_name' => 'Test',
            'username' => 'gate.test.office', 'email' => '', 'password' => 'longenough1', 'roles' => ['office']]);
        check_contains('Team and permissions is part of the Platinum plan', $r->body, 'adding an office person on Gold');
        check_same(0, (int) t_val("SELECT COUNT(*) FROM client_users WHERE username = 'gate.test.office'"), 'no user was added');

        // The main factory and the super-admin are never locked.
        check(plan_feature_allowed(3, 'messages_log') && plan_feature_allowed(3, 'team'), 'the main factory is never locked');
        check(as_user('super')->get('/messages/index.php')->status < 400, 'the super-admin is never locked');
    } finally {
        db()->exec("DELETE FROM client_plan_overrides WHERE client_id = 102 AND notes = 'gate test'");
        db()->exec("DELETE FROM client_users WHERE username = 'gate.test.office'");
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
