<?php
declare(strict_types=1);

/**
 * SIGNED-IN SESSIONS. Switching a person off must end the sessions they already
 * have open — not just stop the next login (session_still_valid(), which the
 * server re-checks at most every 30 seconds).
 *
 * The tests wind the 30-second timer back instead of waiting. By design the
 * re-check "fails open": if the database can't be read, nobody is logged out.
 */

/** Sign in, prove a page opens, change the account, and check the next request is turned away. */
$revoked = static function (string $who, string $page, callable $change, callable $undo, string $what): void {
    $h = as_user($who);
    check_page_ok($h->get($page), "$page before: $what");
    $change();
    try {
        // Inside the 30-second window the session is not re-checked yet (by design).
        t_expire_auth_check($h);
        check_refused($h->get($page), "$page after $what");
        // And it stays out: the session was ended, not just one page refused.
        check_refused($h->get('/dashboard/index.php'), "dashboard after $what");
    } finally {
        $undo();
    }
};

test('switching a user off ends their open session', function () use ($revoked) {
    $id = fx('user_alpha_sales');
    $revoked('alpha.sales', '/customer-manager/index.php',
        fn () => db()->prepare('UPDATE client_users SET active = 0 WHERE id = ?')->execute([$id]),
        fn () => db()->prepare('UPDATE client_users SET active = 1 WHERE id = ?')->execute([$id]),
        'the user was deactivated');
});

test('suspending a company ends its people\'s open sessions', function () use ($revoked) {
    $revoked('bravo.admin', '/admin/settings.php',
        fn () => db()->exec('UPDATE clients SET active = 0 WHERE id = 102'),
        fn () => db()->exec('UPDATE clients SET active = 1 WHERE id = 102'),
        'the company was suspended');
});

test('changing a password ends sessions signed in with the old one', function () use ($revoked) {
    $id = fx('user_alpha_admin');
    $old = (string) t_val('SELECT password_hash FROM client_users WHERE id = ?', [$id]);
    $revoked('alpha.admin', '/admin/settings.php',
        fn () => db()->prepare('UPDATE client_users SET password_hash = ? WHERE id = ?')->execute([password_hash('another-password', PASSWORD_BCRYPT, ['cost' => 4]), $id]),
        fn () => db()->prepare('UPDATE client_users SET password_hash = ? WHERE id = ?')->execute([$old, $id]),
        'the password was changed');
});

test('removing super-admin ends a session that had it', function () use ($revoked) {
    $id = fx('user_super');
    $revoked('super', '/master-admin/index.php',
        fn () => db()->prepare('UPDATE client_users SET is_super_admin = 0 WHERE id = ?')->execute([$id]),
        fn () => db()->prepare('UPDATE client_users SET is_super_admin = 1 WHERE id = ?')->execute([$id]),
        'super-admin was removed');
});

test('an untouched session carries on working after the re-check', function () {
    $h = as_user('alpha.admin');
    t_expire_auth_check($h);
    check_page_ok($h->get('/admin/settings.php'), 'settings after a routine re-check');
    check_page_ok($h->get('/dashboard/index.php'), 'dashboard after a routine re-check');
});
