<?php
declare(strict_types=1);

/**
 * The environment every test process runs with — and the safety checks that
 * stop the suite ever touching a real database or sending anything.
 *
 * Settings come from the shell environment where set (GitHub Actions sets the
 * DB_* ones), else the local defaults below (XAMPP MariaDB, root, no password).
 */

function yb_test_env(): array
{
    $get = static function (string $k, string $default): string {
        $v = getenv($k);
        return ($v === false || $v === '') ? $default : $v;
    };
    $root = dirname(__DIR__, 2);
    $out  = $root . '/tests/.out';
    return [
        // Never read the developer's .env (it may hold live keys) — see bootstrap.php.
        'YB_IGNORE_DOTENV'  => '1',
        'APP_ENV'           => 'testing',      // not production: mailer_deliver() drops every email
        'APP_TIMEZONE'      => 'Europe/London',
        'APP_URL'           => 'http://127.0.0.1:' . $get('YB_TEST_PORT', '8765'),
        'DB_HOST'           => $get('DB_HOST', '127.0.0.1'),
        'DB_PORT'           => $get('DB_PORT', '3306'),
        'DB_NAME'           => $get('DB_NAME', 'yb_ci'),
        'DB_USER'           => $get('DB_USER', 'root'),
        'DB_PASS'           => (string) (getenv('DB_PASS') === false ? '' : getenv('DB_PASS')),
        'FACTORY_CLIENT_ID' => '3',
        'YB_TEST_PORT'      => $get('YB_TEST_PORT', '8765'),
        'YB_TEST_OUT'       => $out,
        'YB_TEST_SERVER_LOG'=> $out . '/server-errors.log',
        'YB_TEST_CASE_LOG'  => $out . '/cli-errors.log',
        'YB_UPDATE_GOLDENS' => $get('YB_UPDATE_GOLDENS', '0'),
        // Belt and braces: no real mail / SMS / accounting credentials exist in a test run.
        'MAIL_HOST'         => 'smtp.invalid',
        'SMS_WORKS_KEY'     => 'test-disabled',
    ];
}

/** Refuse anything that could be a real database. Called before the DB is wiped. */
function yb_test_env_guard(array $env): void
{
    $host = strtolower($env['DB_HOST']);
    if (!in_array($host, ['127.0.0.1', 'localhost', '::1', 'mysql'], true)) {
        fwrite(STDERR, "REFUSING: DB_HOST={$env['DB_HOST']} is not a local test database.\n");
        exit(2);
    }
    if (!preg_match('/^yb_(ci|tests?)(_[a-z0-9_]+)?$/', $env['DB_NAME'])) {
        fwrite(STDERR, "REFUSING: DB_NAME={$env['DB_NAME']} must be yb_ci / yb_test(s)_*: the suite wipes it.\n");
        exit(2);
    }
    if ($env['DB_NAME'] === 'yb_test') {
        fwrite(STDERR, "REFUSING: yb_test is the shared QA copy; use yb_ci (the default).\n");
        exit(2);
    }
}

/** Put the test environment into THIS process (for code that runs in-process). */
function yb_test_env_apply(array $env): void
{
    foreach ($env as $k => $v) {
        putenv("$k=$v");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }
}
