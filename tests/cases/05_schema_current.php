<?php
declare(strict_types=1);

/**
 * The test database structure (tests/schema.sql) must include every migration
 * in setup/migrations — otherwise the other tests run without the newest
 * tables/columns and can't see bugs in them.
 *
 * Loads schema.sql into a scratch database, runs every migration on it, and
 * fails if any migration still had something to add.
 * Fix: php tests/tools/refresh_schema.php (and commit tests/schema.sql).
 */

test('tests/schema.sql already contains every migration', function () {
    $scratch = getenv('DB_NAME') . '_schema';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $my = new mysqli((string) getenv('DB_HOST'), (string) getenv('DB_USER'), (string) ($_ENV['DB_PASS'] ?? ''), '', (int) getenv('DB_PORT'));
    $my->query("DROP DATABASE IF EXISTS `$scratch`");
    $my->query("CREATE DATABASE `$scratch` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $my->select_db($scratch);
    $my->multi_query((string) file_get_contents(APP_ROOT . '/tests/schema.sql'));
    do { if ($r = $my->store_result()) $r->free(); } while ($my->more_results() && $my->next_result());

    $shape = static function () use ($my, $scratch): array {
        $out = [];
        $r = $my->query("SELECT CONCAT(table_name, '.', column_name, ' ', column_type) FROM information_schema.columns WHERE table_schema = '$scratch'");
        foreach ($r->fetch_all() as $row) $out[] = $row[0];
        $r = $my->query("SELECT DISTINCT CONCAT(table_name, ' index ', index_name) FROM information_schema.statistics WHERE table_schema = '$scratch'");
        foreach ($r->fetch_all() as $row) $out[] = $row[0];
        sort($out);
        return $out;
    };
    $before = $shape();

    $env = array_merge(getenv(), ['DB_NAME' => $scratch]);
    $failed = [];
    foreach (glob(APP_ROOT . '/setup/migrations/*.php') as $m) {
        $log = tempnam((string) getenv('YB_TEST_OUT'), 'mig');
        $p = proc_open([PHP_BINARY, '-d', 'auto_prepend_file=' . APP_ROOT . '/tests/lib/server_prepend.php', $m],
            [0 => ['pipe', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']], $pipes, APP_ROOT, $env);
        fclose($pipes[0]);
        if (proc_close($p) !== 0) $failed[] = basename($m) . ': ' . trim(substr((string) file_get_contents($log), -300));
        @unlink($log);
    }
    $added = array_diff($shape(), $before);
    $my->query("DROP DATABASE `$scratch`");
    if ($failed) fail("migrations failed on a fresh database:\n    " . implode("\n    ", $failed));
    if ($added) fail("tests/schema.sql is behind the migrations — run php tests/tools/refresh_schema.php and commit it. Missing:\n    "
        . implode("\n    ", array_slice(array_values($added), 0, 15)));
});
