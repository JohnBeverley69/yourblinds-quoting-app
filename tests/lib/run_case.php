<?php
declare(strict_types=1);

/**
 * Runs ONE test file in this process and reports each test as a line
 * "@@YB {json}" on stdout. Started by tests/run.php — not meant to be run by hand
 * (use `php tests/run.php --only=<name>` instead).
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$file = $argv[1] ?? '';
if (!is_file($file)) { fwrite(STDERR, "no such test file: $file\n"); exit(2); }

require_once __DIR__ . '/env.php';
if (getenv('YB_IGNORE_DOTENV') !== '1') { fwrite(STDERR, "run via tests/run.php\n"); exit(2); }
yb_test_env_guard(yb_test_env());
yb_test_env_apply(yb_test_env());

ini_set('memory_limit', '1024M');
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/auth/middleware.php';
ini_set('log_errors', '1');
ini_set('error_log', (string) getenv('YB_TEST_CASE_LOG'));
require_once __DIR__ . '/harness.php';

// Any PHP warning raised while a test runs fails that test (deprecations and
// notices are ignored, as on the live server).
$GLOBALS['__yb_warnings'] = [];
set_error_handler(static function (int $no, string $msg, string $f, int $l): bool {
    if ($no & (E_DEPRECATED | E_USER_DEPRECATED | E_NOTICE | E_USER_NOTICE | 2048)) return true;
    if (!(error_reporting() & $no)) return true;   // @-suppressed
    $GLOBALS['__yb_warnings'][] = "$msg at " . str_replace(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR, '', $f) . ":$l";
    return true;
});

$emit = static function (array $r): void {
    echo "\n@@YB " . json_encode($r, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
};

try {
    require $file;
} catch (Throwable $e) {
    $emit(['name' => '(loading ' . basename($file) . ')', 'status' => 'fail', 'ms' => 0,
           'message' => get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine()]);
    exit(1);
}

foreach ($GLOBALS['__yb_tests'] as [$name, $fn]) {
    $GLOBALS['__yb_warnings'] = [];
    $t = microtime(true);
    $status = 'pass'; $message = '';
    try {
        ob_start();
        $fn();
        ob_end_clean();
        if ($GLOBALS['__yb_warnings']) {
            $status = 'fail';
            $message = "PHP warnings:\n    " . implode("\n    ", array_slice(array_unique($GLOBALS['__yb_warnings']), 0, 5));
        }
    } catch (TestSkipped $e) {
        while (ob_get_level() > 0) ob_end_clean();
        $status = 'skip'; $message = $e->getMessage();
    } catch (TestFailure $e) {
        while (ob_get_level() > 0) ob_end_clean();
        $status = 'fail'; $message = $e->getMessage();
    } catch (Throwable $e) {
        while (ob_get_level() > 0) ob_end_clean();
        $status = 'fail';
        $message = get_class($e) . ': ' . $e->getMessage() . ' at '
            . str_replace(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR, '', $e->getFile()) . ':' . $e->getLine();
    }
    // A test that left a transaction open would poison the next one.
    try { if (db()->inTransaction()) db()->rollBack(); } catch (Throwable $e) {}
    $emit(['name' => $name, 'status' => $status, 'ms' => (int) round((microtime(true) - $t) * 1000), 'message' => $message]);
}
