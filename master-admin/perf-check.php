<?php
declare(strict_types=1);

/**
 * Performance check — super-admin diagnostic.
 *
 * Reports the three things that most commonly make the app feel slow on
 * a shared host:
 *   1. Is OPcache on? (off = every request recompiles all PHP — the
 *      usual cause of "the whole site got slower as it grew")
 *   2. DB round-trip latency (local vs remote DB server)
 *   3. Table sizes + a couple of representative query timings (data growth)
 *
 * Read-only. Safe to leave in master-admin/ (super-admin gated, not in
 * any nav). Visit: /master-admin/perf-check.php
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

header('Content-Type: text/plain; charset=utf-8');

echo "YourBlinds — performance check\n";
echo "==============================\n\n";

// ── PHP / runtime ──────────────────────────────────────────────────
echo "PHP\n";
echo "  version:            " . PHP_VERSION . "\n";
echo "  memory_limit:       " . ini_get('memory_limit') . "\n";
echo "  max_execution_time: " . ini_get('max_execution_time') . "\n\n";

// ── OPcache (biggest single lever) ─────────────────────────────────
echo "OPcache\n";
if (function_exists('opcache_get_status')) {
    $os = @opcache_get_status(false);
    if (is_array($os)) {
        $on = !empty($os['opcache_enabled']);
        echo "  enabled:            " . ($on ? 'YES' : 'NO') . "\n";
        if ($on) {
            $stat = $os['opcache_statistics'] ?? [];
            $mem  = $os['memory_usage'] ?? [];
            $hits = (int) ($stat['hits'] ?? 0);
            $miss = (int) ($stat['misses'] ?? 0);
            $rate = ($hits + $miss) > 0 ? round($hits / ($hits + $miss) * 100, 1) : 0.0;
            $used = (float) ($mem['used_memory'] ?? 0);
            $free = (float) ($mem['free_memory'] ?? 0);
            echo "  cached scripts:     " . ($stat['num_cached_scripts'] ?? '?') . "\n";
            echo "  hit rate:           " . $rate . "%\n";
            echo "  memory used:        " . round($used / 1048576, 1) . " MB of "
               . round(($used + $free) / 1048576, 1) . " MB\n";
        } else {
            echo "  → OPcache is installed but OFF. Turning it on is usually the\n";
            echo "    single biggest speed-up. Enable in php.ini / hosting panel:\n";
            echo "      opcache.enable=1\n";
        }
    } else {
        echo "  status unavailable (opcache.restrict_api set, or disabled).\n";
    }
} else {
    echo "  NOT loaded — the OPcache extension isn't active. Enabling it is\n";
    echo "  usually the single biggest speed-up for a growing PHP app.\n";
}
echo "\n";

// ── Database latency ───────────────────────────────────────────────
echo "Database\n";
echo "  host:               " . (getenv('DB_HOST') ?: '(unset)') . "\n";
$pdo = db();
$n = 8;
$t0 = microtime(true);
for ($i = 0; $i < $n; $i++) {
    $pdo->query('SELECT 1')->fetchColumn();
}
$avg = (microtime(true) - $t0) / $n * 1000;
echo "  SELECT 1 avg (×$n):  " . round($avg, 2) . " ms/query\n";
echo "  → " . ($avg < 2 ? "LOCAL — fast; query count is not the main cost"
        : ($avg < 10 ? "moderate latency"
        : "HIGH latency — DB looks remote; cutting query count per page matters")) . "\n\n";

// ── Data sizes + representative query timings ──────────────────────
echo "Table sizes & timings\n";
$tables = ['products', 'product_systems', 'product_options', 'price_tables',
           'price_table_rows', 'product_extras', 'product_extra_choices',
           'quotes', 'quote_items', 'customers', 'appointments'];
foreach ($tables as $tbl) {
    try {
        $t = microtime(true);
        $c = (int) $pdo->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
        printf("  %-22s %8d rows  (%.1f ms)\n", $tbl, $c, (microtime(true) - $t) * 1000);
    } catch (Throwable $e) {
        printf("  %-22s   n/a\n", $tbl);
    }
}

echo "\nDONE\n";
