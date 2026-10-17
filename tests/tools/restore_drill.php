<?php
declare(strict_types=1);

/**
 * RESTORE DRILL — prove a real backup restores and works with the current code.
 *
 *   php tests/tools/restore_drill.php <backup.sql> [--keep]
 *
 * LOCAL databases only (DB_HOST must be 127.0.0.1 / localhost; DB_USER / DB_PASS
 * from the environment, else root with no password). Never reads .env.
 *
 * Safety:
 *  - Every run makes its OWN scratch databases with a unique name
 *    (yb_drill_<date>_<random> and yb_drillref_…), created with plain CREATE
 *    DATABASE so it fails rather than reuse anything. It only ever drops the
 *    databases it created in this run — never a name it didn't make.
 *  - They're dropped at the end, including when a step fails or the script
 *    dies part-way (unless --keep). The dump holds real data.
 *  - The password reaches the mysql client through a private temporary option
 *    file (deleted straight after), never on the command line.
 *
 * Steps, each printed PASS / FAIL:
 *   1. Load the dump (timed).
 *   2. Run every setup/migrations/*.php — a backup may predate the code.
 *   3. Compare the schema with tests/schema.sql (what the code expects), loaded
 *      into the second scratch DB on the same server so both read alike:
 *      tables, columns (type, NULL, default), indexes (unique + column order)
 *      and foreign keys (column → referenced table.column). Anything the code
 *      expects that differs or is missing = FAIL. Extra things only in the
 *      backup are listed for information.
 *   4. Row counts; the factory company (FACTORY_CLIENT_ID, default 3) must exist.
 *   5. Re-price every saved quote line's base price with the current engine.
 *      On a same-day backup every line matches; after later price changes,
 *      differences are expected (listed, not failed).
 *
 * Exit code 0 = PASS. Procedure + expected output: README "Recovery".
 * Live dumps use MySQL 8's utf8mb4_0900_ai_ci collation; on MariaDB (e.g. XAMPP)
 * the drill swaps it for utf8mb4_unicode_ci while loading and says so.
 * Needs the mysql client on the PATH or MYSQL=… (XAMPP: C:/xampp/mysql/bin/mysql.exe).
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 2);

// ── Child mode: step 5 runs in its own process, pointed at the drill DB by DB_NAME. ──
if (in_array('--reprice', $argv, true)) {
    require $root . '/bootstrap.php';
    require_once $root . '/_partials/pricing_engine.php';
    $pdo = db();
    if (strpos((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'yb_drill_') !== 0) {
        echo json_encode(['fatal' => 'not a drill database']); exit(1);
    }
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

// Unique scratch names; plain CREATE DATABASE fails if the name is somehow taken.
$tag   = date('Ymd_His') . '_' . bin2hex(random_bytes(3));
$DRILL = 'yb_drill_' . $tag;
$REF   = 'yb_drillref_' . $tag;
$created = [];
$optFile = null;
register_shutdown_function(static function () use (&$created, &$optFile, $keep, $my, $say, $DRILL): void {
    if ($optFile && is_file($optFile)) @unlink($optFile);
    if ($keep) {
        if ($created) $say("--keep: left in place: " . implode(', ', $created) . '. Drop them when done: they hold real data.');
        return;
    }
    foreach ($created as $db) { try { $my->query("DROP DATABASE `$db`"); } catch (Throwable $e) { $say("could not drop $db: " . $e->getMessage()); } }
    if ($created) $say('Scratch databases dropped (the backup holds real data).');
});
foreach ([$DRILL, $REF] as $db) {
    $my->query("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created[] = $db;
}
$say('Restore drill: ' . basename($file) . ' (' . round(filesize($file) / 1048576) . ' MB) → ' . $DRILL
   . ' on ' . $host . ' (' . $my->server_info . ')');

// 1. Load — password via a private option file, never argv.
$optFile = tempnam(sys_get_temp_dir(), 'ybdrill');
@chmod($optFile, 0600);
file_put_contents($optFile, "[client]\nuser=" . $user . "\npassword=" . $pass . "\nhost=" . $host . "\n");
$mysql = getenv('MYSQL') ?: (is_file('C:/xampp/mysql/bin/mysql.exe') ? 'C:/xampp/mysql/bin/mysql.exe' : 'mysql');
$t0 = microtime(true);
$p = proc_open([$mysql, '--defaults-extra-file=' . $optFile, '--max_allowed_packet=512M', $DRILL],
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$in = fopen($file, 'rb');
while (($line = fgets($in)) !== false) {
    if ($isMaria) $line = str_replace('utf8mb4_0900_ai_ci', 'utf8mb4_unicode_ci', $line);
    if (@fwrite($pipes[0], $line) === false) break;   // client died: its error is read below
}
fclose($in); fclose($pipes[0]);
$err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
$rc = proc_close($p);
@unlink($optFile);
$secs = round(microtime(true) - $t0);
$q = static fn(string $sql): array => $my->query($sql)->fetch_all();
$tables = (int) $q("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DRILL'")[0][0];
$step($rc === 0 && $tables > 0, "1. Loaded: $tables tables in {$secs}s" . ($isMaria ? ' (MariaDB: collation swapped for the drill)' : '')
    . ($rc !== 0 ? "\n     mysql said: " . trim($err) : ''));
if ($rc !== 0 || $tables === 0) { $say('RESULT: FAIL'); exit(1); }

// 2. Migrations
$env = array_merge(getenv(), ['YB_IGNORE_DOTENV' => '1', 'APP_ENV' => 'testing', 'DB_HOST' => $host,
    'DB_NAME' => $DRILL, 'DB_USER' => $user, 'DB_PASS' => $pass]);
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

// 3. Schema: the expected schema loaded on the same server, then compared item by item.
$my->select_db($REF);
$my->multi_query((string) file_get_contents($root . '/tests/schema.sql'));
do { if ($r = $my->store_result()) $r->free(); } while ($my->more_results() && $my->next_result());
$shape = static function (string $db) use ($q): array {
    $s = ['tables' => [], 'columns' => [], 'indexes' => [], 'fks' => []];
    foreach ($q("SELECT table_name FROM information_schema.tables WHERE table_schema = '$db' AND table_type = 'BASE TABLE'") as [$t]) $s['tables'][$t] = true;
    foreach ($q("SELECT table_name, column_name, column_type, is_nullable, COALESCE(column_default, '∅')
                   FROM information_schema.columns WHERE table_schema = '$db'") as [$t, $c, $type, $null, $def]) {
        // Defaults read alike once both copies sit on the same server; strip quoting styles anyway.
        $s['columns']["$t.$c"] = $type . ($null === 'YES' ? ' NULL' : ' NOT NULL') . ' default ' . trim((string) $def, "'");
    }
    foreach ($q("SELECT table_name, index_name, non_unique, GROUP_CONCAT(column_name ORDER BY seq_in_index)
                   FROM information_schema.statistics WHERE table_schema = '$db'
                  GROUP BY table_name, index_name, non_unique") as [$t, $i, $nu, $cols]) {
        // Keyed by what the index does (table, unique?, columns), not its name.
        $s['indexes']["$t " . ($i === 'PRIMARY' ? 'PRIMARY' : ($nu ? 'INDEX' : 'UNIQUE')) . "($cols)"] = true;
    }
    foreach ($q("SELECT table_name, column_name, referenced_table_name, referenced_column_name
                   FROM information_schema.key_column_usage
                  WHERE table_schema = '$db' AND referenced_table_name IS NOT NULL") as [$t, $c, $rt, $rc]) {
        $s['fks']["$t.$c → $rt.$rc"] = true;
    }
    return $s;
};
$want = $shape($REF);
$have = $shape($DRILL);
$problems = [];
foreach (array_keys($want['tables']) as $t) if (!isset($have['tables'][$t])) $problems[] = "table missing: $t";
foreach ($want['columns'] as $k => $def) {
    if (!isset($have['columns'][$k])) { $problems[] = "column missing: $k"; continue; }
    if ($have['columns'][$k] !== $def) $problems[] = "column differs: $k — expected $def, backup has {$have['columns'][$k]}";
}
foreach (array_keys($want['indexes']) as $k) if (!isset($have['indexes'][$k])) $problems[] = "index missing: $k";
foreach (array_keys($want['fks']) as $k) if (!isset($have['fks'][$k])) $problems[] = "foreign key missing: $k";
$extras = array_merge(
    array_map(static fn($t) => "table $t", array_keys(array_diff_key($have['tables'], $want['tables']))),
    array_map(static fn($c) => "column $c", array_keys(array_diff_key($have['columns'], $want['columns']))),
    array_map(static fn($i) => "index $i", array_keys(array_diff_key($have['indexes'], $want['indexes']))),
    array_map(static fn($f) => "foreign key $f", array_keys(array_diff_key($have['fks'], $want['fks']))));
$step(!$problems, sprintf('3. Schema: %d tables, %d columns (type, NULL, default), %d indexes, %d foreign keys compared — %s',
        count($want['tables']), count($want['columns']), count($want['indexes']), count($want['fks']),
        $problems ? count($problems) . ' problem(s):' : 'all match')
    . ($problems ? "\n     " . implode("\n     ", $problems) : '')
    . ($extras ? "\n     (info) only in the backup: " . implode('; ', $extras) : ''));

// 4. Counts
$my->select_db($DRILL);
$n = static fn(string $sql): int => (int) $q($sql)[0][0];
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

$say($failed ? 'RESULT: FAIL' : 'RESULT: PASS');
exit($failed ? 1 : 0);   // the shutdown handler drops the scratch databases
