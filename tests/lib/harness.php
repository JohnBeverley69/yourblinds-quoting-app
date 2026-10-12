<?php
declare(strict_types=1);

/**
 * The tiny test framework. A test file (tests/cases/NN_name.php) calls
 *
 *     test('what it checks, in plain English', function () { ... });
 *
 * and uses the check_* helpers below. A failed check throws; the runner prints
 * the message. Each test file runs in its own PHP process (run_case.php), with
 * the app's bootstrap loaded and the test database already built.
 */

final class TestFailure extends RuntimeException {}
final class TestSkipped extends RuntimeException {}

$GLOBALS['__yb_tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['__yb_tests'][] = [$name, $fn];
}

function fail(string $msg): void { throw new TestFailure($msg); }
function skip(string $why): void { throw new TestSkipped($why); }

function check(bool $ok, string $msg): void
{
    if (!$ok) fail($msg);
}

function check_same($expected, $actual, string $what): void
{
    if ($expected !== $actual) {
        fail("$what: expected " . t_show($expected) . ', got ' . t_show($actual));
    }
}

/** Money / decimals: equal to the penny. */
function check_money($expected, $actual, string $what): void
{
    if (!is_numeric($actual) || abs((float) $expected - (float) $actual) > 0.004) {
        fail("$what: expected £" . number_format((float) $expected, 2) . ', got ' . t_show($actual));
    }
}

function check_contains(string $needle, string $haystack, string $what): void
{
    if (strpos($haystack, $needle) === false) fail("$what: \"$needle\" not found");
}

function check_not_contains(string $needle, string $haystack, string $what): void
{
    if (strpos($haystack, $needle) !== false) fail("$what: \"$needle\" should not be there");
}

function t_show($v): string
{
    $s = json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    return strlen((string) $s) > 300 ? substr($s, 0, 300) . '…' : (string) $s;
}

/** One value from the test database. */
function t_val(string $sql, array $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

function t_row(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r === false ? null : $r;
}

function t_rows(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Pretend to be a logged-in user inside THIS process (for tests that call app
 * functions directly). Sets the same session keys auth/login.php sets.
 */
function t_act_as(string $username): array
{
    $u = t_row('SELECT u.*, c.company_name FROM client_users u JOIN clients c ON c.id = u.client_id WHERE u.username = ?', [$username]);
    if (!$u) fail("fixture user $username missing");
    $roles = array_column(t_rows('SELECT role FROM client_user_roles WHERE user_id = ?', [(int) $u['id']]), 'role');
    $_SESSION = [
        'user_id'         => (int) $u['id'],
        'client_id'       => (int) $u['client_id'],
        'role'            => (string) $u['role'],
        'roles'           => array_values(array_unique(array_merge([(string) $u['role']], $roles))),
        'company_name'    => (string) $u['company_name'],
        'full_name'       => (string) $u['full_name'],
        'is_super_admin'  => (bool) $u['is_super_admin'],
        'pw_fp'           => hash('sha256', (string) $u['password_hash']),
        'auth_checked_at' => time(),
    ];
    return $u;
}

/** Fixture ids by name, so tests never hard-code an auto-increment. */
function fx(string $key): int
{
    static $map = null;
    if ($map === null) {
        $map = json_decode((string) file_get_contents(getenv('YB_TEST_OUT') . '/fixture-ids.json'), true) ?: [];
    }
    if (!isset($map[$key])) fail("unknown fixture id '$key'");
    return (int) $map[$key];
}

require_once __DIR__ . '/http.php';
require_once __DIR__ . '/flows.php';
