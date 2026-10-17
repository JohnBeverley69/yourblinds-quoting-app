<?php
declare(strict_types=1);

/**
 * RESTORE DRILL — prove a real backup restores and works with the current code.
 *
 *   php tests/tools/restore_drill.php <backup.sql> [--keep]
 *
 * Runs against a scratch LOCAL database only (yb_restore_drill on 127.0.0.1;
 * DB_HOST / DB_USER / DB_PASS from the environment, else root with no password).
 * Never touches .env or the live site. Steps, each printed with PASS / FAIL:
 *
 *   1. Load the dump into yb_restore_drill (timed).
 *   2. Run every setup/migrations/*.php against it — a backup may predate the code.
 *   3. Compare its columns with tests/schema.sql (what the code expects).
 *      Anything the code expects but the restore lacks = FAIL.
 *   4. Row counts: companies, users, products, quotes, customers; the factory
 *      company (FACTORY_CLIENT_ID, default 3) must exist.
 *   5. Re-price every saved quote line's base price with the current engine and
 *      compare with what was saved. On a same-day backup every line matches;
 *      after later price-list changes, differences are expected (reported, not failed).
 *   6. Drop the scratch databases (unless --keep). The dump holds real data.
 *
 * Exit code 0 = PASS. The full procedure and expected output: README "Recovery".
 *
 * Live dumps use MySQL 8's utf8mb4_0900_ai_ci collation. On MariaDB (e.g. XAMPP)
 * the drill swaps it for utf8mb4_unicode_ci while loading; a real restore should
 * go onto MySQL 8, where nothing is swapped.
 * Needs the mysql client on the PATH or MYSQL=… (XAMPP: C:/xampp/mysql/bin/mysql.exe).
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 2);
const DRILL_DB = 'yb_restore_drill';
const REF_DB   = 'yb_restore_ref';

// ── Child mode: step 5 runs in its own process pointed at the drill DB. ──
if (in_array('--reprice', $argv, true)) {
    require $root . '/bootstrap.php';
    require_once $root . '/_partials/pricing_engine.php';
    $pdo = db();
    if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== DRILL_DB) { echo json_encode(['fatal' => 'wrong database']); exit(1); }
    $rows = $pdo->query('SELECT qi.id, qi.product_id, qi.system_id, qi.option_id, qi.width_mm, qi.drop_mm, qi.quantity,
                                qi.base_price, qi.product_name_snapshot, q.client_id
                           FROM quote_items qi JOIN quotes q ON q.id = qi.quote_id ORDER BY qi.id')->fetchAll(PDO::FETCH_ASSOC);
    $res = ['lines' => count($rows), 'same' => 0, 'differ' => [], 'error' => []];
    foreach ($rows as $r) {
        $out = pe_calculate_item($pdo, (int) $r['client_id'], [
            'product_id' => $r['product_id'], 'system_id' => $r['system_id'], 'option_id' => $r['option_id'],
            'width_mm' => $r['width_mm'], 'drop_mm' => $r['drop_mm'], 'quantity' => $r['quantity'],
            'extras' => [], 'round_up' => true,   // the quote screen rounds up to the next grid size
        ]);
        $label = "line {$r['id']} {$r['product_name_snapshot']} {$r['width_mm']}x{$r['drop_mm']}";
        if (isset($out['error'])) { $res['error'][] = "$label: {$out['error']}"; continue; }
        $saved = round((float) $r['base_price'], 2);
        $now   = round((float) ($out['base_price'] ?? -1), 2);
        if (abs($saved - $now) < 0.01) $res['same']++;
        else $res['differ'][] = "$label: saved £$saved, now £$now";
    }
    echo json_encode($res);
    exit(0);
}

// ── Main ──
$file = null;
foreach (array_slice($argv, 1) as $a) if ($a[0] !== '-') $file = $a;
$keep = in_array('--keep', $argv, true);
if ($file === null || !is_file($file)) {
    fwrite(STDERR, "Usage: php tests/tools/restore_drill.php <backup.sql> [--keep]\n");
    exit(2);
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$user = getenv('DB_USER') ?: 'root';
$pass = (string) (getenv('DB_PASS') ?: '');
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) { fwrite(STDERR, "REFUSING: local databases only (DB_HOST=$host).\n"); exit(2); }

$failed = false;
$say  = static function (string $s): void { fwrite(STDOUT, $s . "\n"); };
$step = static function (bool $ok, string $s) use ($say, &$failed): void { if (!$ok) $failed = true; $say(($ok ? 'PASS ' : 'FAIL ') . $s); };

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$my = new mysqli($host, $user, $pass);
$isMaria = stripos((string) $my->server_info, 'mariadb') !== false;
$say('Restore drill: ' . basename($file) . ' (' . round(filesize($file) / 1048576) . ' MB) → ' . DRILL_DB
   . ' on ' . $host . ' (' . $my->server_info . ')');

// 1. Load
foreach ([DRILL_DB, REF_DB] as $db) {
    $my->query("DROP DATABASE IF EXISTS `$db`");
    $my->query("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}
$mysql = getenv('MYSQL') ?: (is_file('C:/xampp/mysql/bin/mysql.exe') ? 'C:/xampp/mysql/bin/mysql.exe' : 'mysql');
$cmd = [$mysql, '-h', $host, '-u', $user, '--max_allowed_packet=512M', DRILL_DB];
if ($pass !== '') array_splice($cmd, 5, 0, ['-p' . $pass]);
$t0 = microtime(true);
$p = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$in = fopen($file, 'rb');
while (($line = fgets($in)) !== false) {
    if ($isMaria) $line = str_replace('utf8mb4_0900_ai_ci', 'utf8mb4_unicode_ci', $line);
    fwrite($pipes[0], $line);
}
fclose($in); fclose($pipes[0]);
$err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
$rc = proc_close($p);
$secs = round(microtime(true) - $t0);
$tables = (int) $my->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='" . DRILL_DB . "'")->fetch_row()[0];
$step($rc === 0 && $tables > 0, "1. Loaded: $tables tables in {$secs}s" . ($isMaria ? ' (MariaDB: collation swapped for the drill)' : '')
    . ($rc !== 0 ? "\n     mysql said: " . trim($err) : ''));
if ($rc !== 0 || $tables === 0) goto finish;

// 2. Migrations
$env = array_merge(getenv(), ['YB_IGNORE_DOTENV' => '1', 'APP_ENV' => 'testing', 'DB_HOST' => $host,
    'DB_NAME' => DRILL_DB, 'DB_USER' => $user, 'DB_PASS' => $pass]);
if ($pass === '') unset($env['DB_PASS']);
$prepend = $root . '/tests/lib/server_prepend.php';   // re-applies an empty DB_PASS (Windows drops it)
$runPhp = static function (array $args) use ($env, $root, $prepend): array {
    $p = proc_open(array_merge([PHP_BINARY, '-d', 'auto_prepend_file=' . $prepend], $args),
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $env);
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), $out];
};
$migs = glob($root . '/setup/migrations/*.php');
$bad = [];
foreach ($migs as $m) {
    [$mrc, $out] = $runPhp([$m]);
    if ($mrc !== 0 || preg_match('/Fatal error|Uncaught|SQLSTATE/i', $out)) $bad[] = basename($m) . ': ' . trim(substr($out, 0, 300));
}
$step(!$bad, '2. Migrations: ' . (count($migs) - count($bad)) . ' of ' . count($migs) . ' ran clean'
    . ($bad ? "\n     " . implode("\n     ", $bad) : ''));

// 3. Schema vs what the code expects
$my->select_db(REF_DB);
$my->multi_query((string) file_get_contents($root . '/tests/schema.sql'));
do { if ($r = $my->store_result()) $r->free(); } while ($my->more_results() && $my->next_result());
$diff = static function (string $a, string $b) use ($my): array {
    $q = $my->query("SELECT CONCAT(x.table_name, '.', x.column_name) FROM information_schema.columns x
                       LEFT JOIN information_schema.columns y ON y.table_schema = '$b' AND y.table_name = x.table_name AND y.column_name = x.column_name
                      WHERE x.table_schema = '$a' AND y.column_name IS NULL ORDER BY 1");
    return array_column($q->fetch_all(), 0);
};
$missing = $diff(REF_DB, DRILL_DB);
$extra   = $diff(DRILL_DB, REF_DB);
$step(!$missing, '3. Schema: ' . ($missing ? count($missing) . ' column(s) the code expects are missing: ' . implode(', ', $missing) : 'every table and column the code expects is present')
    . ($extra ? "\n     (info) only in the backup: " . implode(', ', $extra) : ''));

// 4. Counts
$my->select_db(DRILL_DB);
$n = static fn(string $sql): int => (int) $my->query($sql)->fetch_row()[0];
$fid = (int) (getenv('FACTORY_CLIENT_ID') ?: 3);
$counts = ['companies' => 'clients', 'users' => 'client_users', 'products' => 'products', 'quotes' => 'quotes',
           'quote lines' => 'quote_items', 'customers' => 'customers', 'invoices' => 'factory_ar_invoices'];
$parts = [];
foreach ($counts as $label => $t) $parts[] = $n("SELECT COUNT(*) FROM `$t`") . " $label";
$hasFactory = $n("SELECT COUNT(*) FROM clients WHERE id = $fid") === 1;
$step($hasFactory && $n('SELECT COUNT(*) FROM client_users') > 0,
    '4. Data: ' . implode(', ', $parts) . ($hasFactory ? "; factory company #$fid present" : "; factory company #$fid MISSING"));

// 5. Re-price
[$prc, $out] = $runPhp([__FILE__, '--reprice']);
$res = json_decode(trim((string) substr($out, (int) strrpos($out, '{"'))), true);
if ($prc !== 0 || !is_array($res) || isset($res['fatal'])) {
    $step(false, '5. Re-price: the engine failed — ' . trim(substr($out, 0, 500)));
} else {
    $say('PASS 5. Re-price: ' . $res['lines'] . ' saved quote line(s); ' . $res['same'] . ' match to the penny'
        . ($res['differ'] ? ', ' . count($res['differ']) . ' differ (prices changed since?)' : '')
        . ($res['error'] ? ', ' . count($res['error']) . ' no longer price (product/size gone?)' : ''));
    foreach (array_merge($res['differ'], $res['error']) as $l) $say('     (info) ' . $l);
}

finish:
if (!$keep) {
    $my->query('DROP DATABASE IF EXISTS `' . DRILL_DB . '`');
    $my->query('DROP DATABASE IF EXISTS `' . REF_DB . '`');
    $say('6. Scratch databases dropped (the backup holds real data).');
} else {
    $say('6. --keep: ' . DRILL_DB . ' left in place. Drop it when done: it holds real data.');
}
$say($failed ? 'RESULT: FAIL' : 'RESULT: PASS');
exit($failed ? 1 : 0);
