<?php
declare(strict_types=1);

/**
 * OFFLINE PRICING PROOF — the on-device half. Not a web page: /offline/proof.php
 * ships this file's SOURCE to the browser, where it runs inside PHP-in-WebAssembly
 * next to an unmodified copy of _partials/pricing_engine.php (files at /app/<repo path>).
 *
 * Reads /app/data.json (parity_sample.php output), builds a SQLite catalogue,
 * prices every case with pe_calculate_item() and compares each result with the
 * live server's answer. Prints one JSON report.
 *
 * Also runs from the CLI for a local check:  php _parity_harness.php data.json
 */

$dataFile = $argv[1] ?? '/app/data.json';
if (!is_file($dataFile)) { http_response_code(404); exit; }   // requested over the web on the server — nothing to do

ini_set('memory_limit', '1024M');   // a whole catalogue decoded at once
$t0 = microtime(true);
$d  = json_decode((string) file_get_contents($dataFile), true, 512, JSON_THROW_ON_ERROR);

// The engine asks which client is the factory (trade-discount gating). On the
// server that's env-driven; the snapshot carries the server's answer.
define('OFFLINE_FACTORY_CLIENT_ID', (int) $d['factory_client_id']);
if (!function_exists('factory_client_id')) {
    function factory_client_id(): int { return OFFLINE_FACTORY_CLIENT_ID; }
}
require __DIR__ . '/../_partials/pricing_engine.php';
require __DIR__ . '/_device_catalogue.php';

// ---------------------------------------------------------------------------
// 1. Build the SQLite catalogue, exactly as the tablet does (_device_catalogue.php).
// ---------------------------------------------------------------------------
$pdo      = offline_sqlite_open(':memory:', (string) $d['today']);
$rowCount = offline_sqlite_build($pdo, $d);
$tBuilt   = microtime(true);

// ---------------------------------------------------------------------------
// 2. Price every case and compare with the server.
// ---------------------------------------------------------------------------

/** Every difference between two engine results, as "path: server ≠ device". */
function parity_diff($exp, $got, string $path = ''): array
{
    if (is_array($exp) && is_array($got)) {
        $out = [];
        foreach (array_unique(array_merge(array_keys($exp), array_keys($got))) as $k) {
            if (!array_key_exists($k, $exp) || !array_key_exists($k, $got)) {
                $out[] = "$path.$k: " . (array_key_exists($k, $exp) ? 'missing on device' : 'extra on device');
                continue;
            }
            $out = array_merge($out, parity_diff($exp[$k], $got[$k], "$path.$k"));
        }
        return $out;
    }
    // Money + percentages: equal to the penny. JSON turns 12.0 / 12 into the
    // same number either way, so compare numerically, not by PHP type.
    if ((is_int($exp) || is_float($exp)) && (is_int($got) || is_float($got))) {
        return abs((float) $exp - (float) $got) < 0.0001 ? [] : ["$path: $exp ≠ $got"];
    }
    return $exp === $got ? [] : ["$path: " . json_encode($exp) . ' ≠ ' . json_encode($got)];
}

$cid = (int) $d['client_id'];
$matched = 0; $priced = 0; $errorsMatched = 0; $mismatches = [];
$maxMs = 0.0;
$tPrice = microtime(true);
foreach ($d['cases'] as $i => $case) {
    $t = microtime(true);
    $got = pe_calculate_item($pdo, $cid, $case['input']);
    $maxMs = max($maxMs, (microtime(true) - $t) * 1000);
    // Round-trip through JSON so both sides have had the same encoding applied.
    $got = json_decode(json_encode($got, JSON_PRESERVE_ZERO_FRACTION), true);
    $diff = parity_diff($case['expected'], $got);
    if (!$diff) {
        $matched++;
        isset($got['error']) ? $errorsMatched++ : $priced++;
    } elseif (count($mismatches) < 25) {
        $mismatches[] = ['case' => $i, 'input' => $case['input'], 'diff' => array_slice($diff, 0, 8)];
    }
}
$tDone = microtime(true);
$n = count($d['cases']);

echo json_encode([
    'client_id'          => $cid,
    'snapshot_taken'     => $d['generated_at'],
    'catalogue_rows'     => $rowCount,
    'cases'              => $n,
    'matched'            => $matched,
    'matched_priced'     => $priced,
    'matched_errors'     => $errorsMatched,
    'mismatched'         => $n - $matched,
    'ms_build_catalogue' => round(($tBuilt - $t0) * 1000),
    'ms_price_all'       => round(($tDone - $tPrice) * 1000),
    'ms_per_line_avg'    => $n ? round(($tDone - $tPrice) * 1000 / $n, 2) : 0,
    'ms_per_line_max'    => round($maxMs, 2),
    'php'                => PHP_VERSION . ' / ' . PHP_OS . ' / ' . PHP_SAPI,
    'mismatches'         => $mismatches,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
