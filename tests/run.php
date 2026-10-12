<?php
declare(strict_types=1);

/**
 * YourBlinds automatic tests — the one command.
 *
 *   php tests/run.php                      build the test DB, run everything
 *   php tests/run.php --only=prices        just the test files whose name contains "prices"
 *   php tests/run.php --update-goldens     re-capture the expected prices (review the diff!)
 *   php tests/run.php --keep-db            skip the rebuild (faster when re-running one file)
 *
 * What it does:
 *   1. Wipes and rebuilds the TEST database (default yb_ci on 127.0.0.1) from
 *      tests/schema.sql + tests/fixtures/*  — refuses any other database.
 *   2. Starts the PHP built-in web server on 127.0.0.1:8765 for the page tests.
 *   3. Runs each tests/cases/*.php in its own PHP process and prints the result.
 * Exit code 0 = everything passed. See tests/README.md.
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/lib/env.php';

$opts = getopt('', ['only:', 'update-goldens', 'keep-db', 'verbose']);
if (isset($opts['update-goldens'])) putenv('YB_UPDATE_GOLDENS=1');

$ROOT = dirname(__DIR__);
$env  = yb_test_env();
yb_test_env_guard($env);
@mkdir($env['YB_TEST_OUT'], 0777, true);
foreach (['YB_TEST_SERVER_LOG', 'YB_TEST_CASE_LOG'] as $k) @file_put_contents($env[$k], '');

$started = microtime(true);
$say = static function (string $s): void { fwrite(STDOUT, $s . "\n"); };

// The child processes get the normal environment plus the test settings.
$childEnv = array_merge(getenv(), $env);
if (isset($opts['update-goldens'])) $childEnv['YB_UPDATE_GOLDENS'] = '1';

$runPhp = static function (array $args, ?string &$out = null) use ($childEnv, $ROOT, $env): int {
    $cmd = array_merge([PHP_BINARY, '-d', 'memory_limit=1024M'], $args);
    // Output goes to a file, not a pipe: on Windows a pipe can't be polled and a
    // chatty child would block once the pipe buffer fills.
    $tmp = tempnam($env['YB_TEST_OUT'], 'run');
    $p = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $tmp, 'w'], 2 => ['file', $tmp, 'a']], $pipes, $ROOT, $childEnv);
    fclose($pipes[0]);
    $rc = proc_close($p);
    $out = (string) file_get_contents($tmp);
    @unlink($tmp);
    return $rc;
};

// ---------------------------------------------------------------------------
// 1. Build the test database
// ---------------------------------------------------------------------------
if (!isset($opts['keep-db'])) {
    $say("Building test database {$env['DB_NAME']} on {$env['DB_HOST']}…");
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $my = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], '', (int) $env['DB_PORT']);
    $my->set_charset('utf8mb4');
    $my->query("DROP DATABASE IF EXISTS `{$env['DB_NAME']}`");
    $my->query("CREATE DATABASE `{$env['DB_NAME']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $my->select_db($env['DB_NAME']);
    // Same SQL mode as a stock MariaDB/MySQL install, minus ONLY_FULL_GROUP_BY
    // (the live server leaves it off).
    $my->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    $fixtures = glob(__DIR__ . '/fixtures/*.sql') ?: [];
    sort($fixtures);
    $files = array_merge([__DIR__ . '/schema.sql'], $fixtures);
    foreach ($files as $f) {
        $sql = (string) file_get_contents($f);
        try {
            $my->multi_query($sql);
            do { if ($res = $my->store_result()) $res->free(); } while ($my->more_results() && $my->next_result());
        } catch (mysqli_sql_exception $e) {
            fwrite(STDERR, "Loading " . basename($f) . " failed: " . $e->getMessage() . "\n");
            exit(2);
        }
    }
    $my->close();
    @unlink($env['YB_TEST_OUT'] . '/fixture-ids.json');
    // PHP fixture builders (fake companies, users, quotes) — run in order.
    foreach (glob(__DIR__ . '/fixtures/*.php') ?: [] as $f) {
        $rc = $runPhp([__DIR__ . '/lib/run_fixture.php', $f], $out);
        if ($rc !== 0) { fwrite(STDERR, "Fixture " . basename($f) . " failed:\n$out\n"); exit(2); }
        if (isset($opts['verbose'])) $say(trim($out));
    }
    $say(sprintf('  database ready (%.1fs)', microtime(true) - $started));
}

// ---------------------------------------------------------------------------
// 2. Start the web server for the page tests
// ---------------------------------------------------------------------------
$port = (int) $env['YB_TEST_PORT'];
$serverCmd = [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $ROOT,
    '-d', 'log_errors=1', '-d', 'error_log=' . $env['YB_TEST_SERVER_LOG'],
    '-d', 'display_errors=0', '-d', 'memory_limit=512M',
    '-d', 'auto_prepend_file=' . __DIR__ . '/lib/server_prepend.php'];
$server = proc_open($serverCmd, [0 => ['pipe', 'r'], 1 => ['file', $env['YB_TEST_OUT'] . '/server-access.log', 'w'],
    2 => ['file', $env['YB_TEST_OUT'] . '/server-access.log', 'a']], $spipes, $ROOT, $childEnv);
$up = false;
for ($i = 0; $i < 50 && !$up; $i++) {
    $s = @fsockopen('127.0.0.1', $port, $en, $es, 0.2);
    if ($s) { fclose($s); $up = true; } else usleep(100000);
}
if (!$up) { fwrite(STDERR, "Could not start the PHP web server on port $port.\n"); exit(2); }
register_shutdown_function(static function () use ($server) {
    $st = proc_get_status($server);
    if ($st['running']) {
        // proc_terminate does not reach the real php.exe on Windows; kill the tree.
        if (DIRECTORY_SEPARATOR === '\\') @exec('taskkill /F /T /PID ' . (int) $st['pid'] . ' 2>NUL');
        else proc_terminate($server);
    }
});

// ---------------------------------------------------------------------------
// 3. Run the test files
// ---------------------------------------------------------------------------
$files = glob(__DIR__ . '/cases/*.php') ?: [];
sort($files);
if (isset($opts['only'])) {
    $files = array_values(array_filter($files, static fn ($f) => stripos(basename($f), (string) $opts['only']) !== false));
}

$results = [];
foreach ($files as $f) {
    $title = preg_replace('/^\d+_/', '', basename($f, '.php'));
    $say("\n▶ " . $title);
    // Login lock-out counts per IP; every request comes from 127.0.0.1.
    try {
        $pdo = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_NAME']}", $env['DB_USER'], $env['DB_PASS']);
        $pdo->exec('DELETE FROM login_attempts');
        $pdo = null;
    } catch (Throwable $e) {}
    $rc = $runPhp([__DIR__ . '/lib/run_case.php', $f], $out);
    $seen = 0;
    foreach (preg_split('/\R/', (string) $out) as $line) {
        if (strncmp($line, '@@YB ', 5) !== 0) continue;
        $r = json_decode(substr($line, 5), true);
        if (!is_array($r)) continue;
        $seen++;
        $r['file'] = $title;
        $results[] = $r;
        $mark = ['pass' => '  ✔', 'fail' => '  ✘', 'skip' => '  –'][$r['status']] ?? '  ?';
        $say(sprintf('%s %s%s', $mark, $r['name'], $r['ms'] > 2000 ? sprintf(' (%.1fs)', $r['ms'] / 1000) : ''));
        if ($r['status'] !== 'pass' && $r['message'] !== '') {
            $say('      ' . str_replace("\n", "\n      ", $r['message']));
        }
    }
    if ($rc !== 0 && $seen === 0) {
        $results[] = ['file' => $title, 'name' => '(the test file crashed)', 'status' => 'fail', 'ms' => 0, 'message' => trim(substr((string) $out, -2000))];
        $say("  ✘ the test file crashed:\n      " . str_replace("\n", "\n      ", trim(substr((string) $out, -2000))));
    }
}

// ---------------------------------------------------------------------------
// 4. Summary (also written to the GitHub Actions job summary when present)
// ---------------------------------------------------------------------------
$count = ['pass' => 0, 'fail' => 0, 'skip' => 0];
foreach ($results as $r) $count[$r['status']] = ($count[$r['status']] ?? 0) + 1;
$secs = microtime(true) - $started;
$say(sprintf("\n%d passed, %d failed, %d skipped in %.0fs", $count['pass'], $count['fail'], $count['skip'], $secs));

if (($summary = getenv('GITHUB_STEP_SUMMARY')) !== false && $summary !== '') {
    $md = $count['fail'] === 0
        ? "## ✅ All {$count['pass']} checks passed\n\nNothing this change touches broke prices, pages, tenant privacy, catalogue copies or the order journey.\n"
        : "## ❌ {$count['fail']} check(s) failed\n\nThis change broke something below. Don't merge until it's fixed (or the expected value is deliberately updated).\n";
    $md .= "\n| | Area | Check |\n|---|---|---|\n";
    foreach ($results as $r) {
        if ($r['status'] === 'pass') continue;
        $md .= '| ' . ($r['status'] === 'fail' ? '❌' : '➖') . ' | ' . $r['file'] . ' | ' . str_replace(['|', "\n"], ['\\|', '<br>'], $r['name'] . ($r['message'] !== '' ? ' — ' . $r['message'] : '')) . " |\n";
    }
    $perFile = [];
    foreach ($results as $r) { $perFile[$r['file']][$r['status']] = ($perFile[$r['file']][$r['status']] ?? 0) + 1; }
    $md .= "\n**By area:** ";
    foreach ($perFile as $f => $c) $md .= "$f " . ($c['pass'] ?? 0) . '/' . array_sum($c) . ' · ';
    @file_put_contents($summary, rtrim($md, ' ·') . "\n", FILE_APPEND);
}

file_put_contents($env['YB_TEST_OUT'] . '/results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
exit($count['fail'] === 0 && $results ? 0 : 1);
