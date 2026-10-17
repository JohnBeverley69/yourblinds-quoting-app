<?php
declare(strict_types=1);

/**
 * SIGN-UP NEVER MAKES A FACTORY (overnight audit 2026-10-17). The sign-up
 * trial covered "every paid plan", which since the Factory tier (#1170)
 * included Factory — and that switches clients.is_factory on for good. Any
 * stranger who signed up got the whole factory side, free, for ever.
 */

require_once APP_ROOT . '/_partials/billing_helpers.php';

test('a public sign-up gets its trial but is NOT made a factory', function () {
    db()->exec("DELETE FROM trial_grants");   // one trial per visitor: the tests all come from one IP
    $anon = new YbHttp();
    $email = 'zz.signup.' . substr(md5((string) microtime(true)), 0, 8) . '@example.test';
    // Not signed in, so the token comes from the form itself.
    preg_match('/name="_csrf" value="([^"]+)"/', $anon->get('/auth/signup.php')->body, $m);
    check(!empty($m[1]), 'no CSRF token on the sign-up form');
    $r = $anon->post('/auth/signup.php', ['_csrf' => $m[1], 'company_name' => 'ZZ Signup Probe Ltd', 'full_name' => 'Zed Probe',
        'email' => $email, 'password' => 'Probe-pass-123', 'confirm' => 'Probe-pass-123']);
    check(!$r->phpErrors, "PHP problems:\n    " . implode("\n    ", $r->phpErrors));
    $cid = (int) t_val('SELECT client_id FROM client_users WHERE email = ?', [$email]);
    check($cid > 0, 'sign-up made no company (HTTP ' . $r->status . ')');
    check_same(0, (int) t_val('SELECT COALESCE(is_factory, 0) FROM clients WHERE id = ?', [$cid]), 'is_factory on the new company');
    check_same(0, (int) t_val("SELECT COUNT(*) FROM client_plan_overrides WHERE client_id = ? AND plan_code = 'factory'", [$cid]), 'Factory trial rows');
    check((int) t_val("SELECT COUNT(*) FROM client_plan_overrides WHERE client_id = ? AND override_type = 'trial'", [$cid]) > 0, 'the other plans\' trial was not given');
});

test('a Factory TRIAL never switches the factory side on; a comp does', function () {
    $cid = 102;   // Bravo, an ordinary shop
    $isF = static fn () => (int) t_val('SELECT COALESCE(is_factory, 0) FROM clients WHERE id = ?', [$cid]);
    check_same(0, $isF(), 'set-up: Bravo is not a factory');
    try {
        db()->prepare("INSERT INTO client_plan_overrides (client_id, plan_code, override_type, expires_at, notes, active)
                       VALUES (?, 'factory', 'trial', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'test', 1)")->execute([$cid]);
        billing_sync_feature_flags_force($cid);
        billing_sync_feature_flags($cid);
        check_same(0, $isF(), 'is_factory after a Factory trial');
        db()->prepare("UPDATE client_plan_overrides SET override_type = 'comp', expires_at = NULL WHERE client_id = ? AND plan_code = 'factory'")->execute([$cid]);
        billing_sync_feature_flags_force($cid);
        check_same(1, $isF(), 'is_factory after a Factory comp');
    } finally {
        db()->prepare("DELETE FROM client_plan_overrides WHERE client_id = ? AND plan_code = 'factory'")->execute([$cid]);
        db()->prepare('UPDATE clients SET is_factory = 0 WHERE id = ?')->execute([$cid]);
        billing_sync_feature_flags_force($cid);
    }
});
