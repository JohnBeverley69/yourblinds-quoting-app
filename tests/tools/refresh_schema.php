<?php
declare(strict_types=1);

/**
 * DEVELOPER TOOL — brings tests/schema.sql up to date after a new migration.
 *
 *   php tests/tools/refresh_schema.php
 *
 * Loads the current tests/schema.sql into a scratch LOCAL database
 * (yb_ci_schema on 127.0.0.1), runs every setup/migrations/*.php against it
 * (they're idempotent), then writes the structure back to tests/schema.sql.
 * Needs mysqldump on the PATH or MYSQLDUMP=… (XAMPP: C:/xampp/mysql/bin/mysqldump.exe).
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 2);
$db   = 'yb_ci_schema';
$host = getenv('DB_HOST') ?: '127.0.0.1';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
if (!in_array($host, ['127.0.0.1', 'localhost'], true)) { fwrite(STDERR, "local databases only\n"); exit(2); }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$my = new mysqli($host, $user, $pass);
$my->query("DROP DATABASE IF EXISTS `$db`");
$my->query("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$my->select_db($db);
$my->multi_query((string) file_get_contents("$root/tests/schema.sql"));
do { if ($r = $my->store_result()) $r->free(); } while ($my->more_results() && $my->next_result());

$env = array_merge(getenv(), ['YB_IGNORE_DOTENV' => '1', 'APP_ENV' => 'testing', 'DB_HOST' => $host,
    'DB_NAME' => $db, 'DB_USER' => $user, 'DB_PASS' => $pass]);
if ($pass === '') unset($env['DB_PASS']);
foreach (glob("$root/setup/migrations/*.php") as $m) {
    $p = proc_open([PHP_BINARY, '-d', 'auto_prepend_file=' . __DIR__ . '/../lib/server_prepend.php', $m],
        [1 => ['file', 'php://stdout', 'w'], 2 => ['file', 'php://stderr', 'w']], $pipes, $root, $env);
    // server_prepend re-applies an empty DB_PASS (Windows drops empty env vars).
    if (proc_close($p) !== 0) fwrite(STDERR, "!! " . basename($m) . " failed\n");
}

$dump = getenv('MYSQLDUMP') ?: (is_file('C:/xampp/mysql/bin/mysqldump.exe') ? 'C:/xampp/mysql/bin/mysqldump.exe' : 'mysqldump');
$cmd = escapeshellarg($dump) . ' -h ' . escapeshellarg($host) . ' -u ' . escapeshellarg($user)
     . ($pass !== '' ? ' -p' . escapeshellarg($pass) : '') . ' --no-data --skip-add-drop-table --skip-comments --skip-dump-date ' . $db;
$sql = shell_exec($cmd);
if (!$sql || strpos($sql, 'CREATE TABLE') === false) { fwrite(STDERR, "mysqldump failed\n"); exit(1); }
$sql = preg_replace('/ AUTO_INCREMENT=\d+/', '', $sql);
file_put_contents("$root/tests/schema.sql", $sql);
$my->query("DROP DATABASE `$db`");
echo "tests/schema.sql refreshed (" . substr_count($sql, 'CREATE TABLE') . " tables).\n";
