<?php
declare(strict_types=1);

/** Runs one PHP fixture builder (tests/fixtures/NN_*.php) with the app loaded. Started by tests/run.php. */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/env.php';
if (getenv('YB_IGNORE_DOTENV') !== '1') { fwrite(STDERR, "run via tests/run.php\n"); exit(2); }
yb_test_env_guard(yb_test_env());
yb_test_env_apply(yb_test_env());
ini_set('memory_limit', '1024M');
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/auth/middleware.php';
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');

/** Record a named id for the tests (read back with fx('name')). */
function fx_set(string $key, int $id): void
{
    $file = getenv('YB_TEST_OUT') . '/fixture-ids.json';
    $map  = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    $map[$key] = $id;
    file_put_contents($file, json_encode($map, JSON_PRETTY_PRINT));
}

require $argv[1];
