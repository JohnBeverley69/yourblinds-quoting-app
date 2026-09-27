<?php
declare(strict_types=1);

/**
 * OFFLINE PRICING PROOF — read-only, super-admin only.
 *
 * Exports ONE tenant's pricing catalogue (only the tables + columns the pricing
 * engine reads) plus a batch of randomly generated quote lines priced by the
 * live engine. A tablet-side harness loads the catalogue into SQLite, runs the
 * SAME _partials/pricing_engine.php (PHP-in-WebAssembly) over the same inputs
 * and compares every figure penny-for-penny.
 *
 * Makes NO changes to any data. The output contains real prices — view it,
 * save it locally for the harness, but never commit it (the repo is public).
 *
 *   /offline/parity_sample.php                      → list tenants + catalogue sizes
 *   /offline/parity_sample.php?client_id=N&n=300    → catalogue + N cases (JSON)
 *   &seed=S                                         → reproducible case set
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/pricing_engine.php';
require_once __DIR__ . '/_catalogue_export.php';
requireSuperAdmin();

header('Cache-Control: no-store');
@set_time_limit(120);
// Super-admin diagnostic page: say what broke instead of a blank 500.
set_exception_handler(static function (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'parity_sample failed: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
});

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$clientId = (int) ($_GET['client_id'] ?? 0);

if ($clientId <= 0) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Offline pricing proof — pick a tenant: ?client_id=N&n=300\n\n";
    $rows = $pdo->query(
        'SELECT c.id, c.company_name AS name,
                (SELECT COUNT(*) FROM products p WHERE p.client_id = c.id AND p.active = 1) AS products,
                COALESCE(g.cells, 0) AS grid_cells
           FROM clients c
           LEFT JOIN (SELECT t.client_id, COUNT(*) AS cells
                        FROM price_table_rows r JOIN price_tables t ON t.id = r.price_table_id
                       WHERE t.active = 1 GROUP BY t.client_id) g ON g.client_id = c.id
          ORDER BY products DESC, c.id'
    )->fetchAll();
    foreach ($rows as $r) {
        printf("  #%-5d %-40s %5d products  %8d grid cells\n", $r['id'], mb_substr((string) $r['name'], 0, 40), $r['products'], $r['grid_cells']);
    }
    exit;
}

// The catalogue: the same export the tablet downloads (offline/_catalogue_export.php),
// with costs, so every engine field can be compared.
$out = offline_export_catalogue($pdo, $clientId, true);
$out['cases'] = [];
$c = $clientId;

// ---------------------------------------------------------------------------
// Random quote lines, priced by the live engine = the expected answers.
// ---------------------------------------------------------------------------
$n    = max(1, min(2000, (int) ($_GET['n'] ?? 300)));
$seed = (int) ($_GET['seed'] ?? 20260926);
mt_srand($seed);
$pick = static fn (array $a) => $a ? $a[mt_rand(0, count($a) - 1)] : null;

$products = $pdo->prepare('SELECT * FROM products WHERE client_id = ? AND active = 1');
$products->execute([$c]);
$products = $products->fetchAll();

$sysSt   = $pdo->prepare('SELECT id FROM product_systems WHERE product_id = ? AND client_id = ? AND active = 1');
$fabSt   = $pdo->prepare('SELECT id, band_code FROM product_options WHERE product_id = ? AND client_id = ? AND active = 1');
$tblSt   = $pdo->prepare('SELECT MIN(r.width_mm) wmin, MAX(r.width_mm) wmax, MIN(r.drop_mm) dmin, MAX(r.drop_mm) dmax
                            FROM price_table_rows r JOIN price_tables t ON t.id = r.price_table_id
                           WHERE t.client_id = ? AND t.product_id = ? AND t.active = 1');
$extSt   = $pdo->prepare('SELECT id FROM product_extras WHERE product_id = ? AND client_id = ? AND active = 1');
$choSt   = $pdo->prepare('SELECT * FROM product_extra_choices WHERE product_extra_id = ? AND active = 1');

$cache = [];
for ($i = 0; $i < $n && $products; $i++) {
    $p   = $pick($products);
    $pid = (int) $p['id'];
    if (!isset($cache[$pid])) {
        $sysSt->execute([$pid, $c]);  $systems = $sysSt->fetchAll(PDO::FETCH_COLUMN);
        $fabSt->execute([$pid, $c]);  $fabrics = $fabSt->fetchAll(PDO::FETCH_COLUMN);
        $tblSt->execute([$c, $pid]);  $range   = $tblSt->fetch() ?: [];
        $extSt->execute([$pid, $c]);
        $extras = [];
        foreach ($extSt->fetchAll(PDO::FETCH_COLUMN) as $eid) {
            $choSt->execute([$eid]);
            $extras[(int) $eid] = $choSt->fetchAll();
        }
        $cache[$pid] = compact('systems', 'fabrics', 'range', 'extras');
    }
    ['systems' => $systems, 'fabrics' => $fabrics, 'range' => $range, 'extras' => $extras] = $cache[$pid];

    $sysId = $systems ? (int) $pick($systems) : 0;
    $wmin = (int) ($range['wmin'] ?? 300);  $wmax = max($wmin, (int) ($range['wmax'] ?? 3000));
    $dmin = (int) ($range['dmin'] ?? 300);  $dmax = max($dmin, (int) ($range['dmax'] ?? 3000));
    // ~5% deliberately oversize — the error path must match too.
    $over = mt_rand(1, 20) === 1 ? 1.15 : 1.0;

    $sel = [];
    foreach ($extras as $eid => $choices) {
        if (!$choices) {                          // number-only option
            if (mt_rand(0, 1)) $sel[] = ['extra_id' => $eid, 'choice_id' => 0, 'user_value' => mt_rand(10, 500)];
            continue;
        }
        if (mt_rand(1, 100) > 60) continue;
        $okChoices = array_values(array_filter($choices, static fn ($ch) =>
            $ch['system_id'] === null || (int) $ch['system_id'] === $sysId));
        if (!$okChoices) continue;
        $ch  = $pick($okChoices);
        $row = ['extra_id' => $eid, 'choice_id' => (int) $ch['id']];
        if (!empty($ch['length_input_label']) || (float) ($ch['price_per_unit'] ?? 0) != 0.0 || mt_rand(1, 10) === 1) {
            $row['user_value'] = mt_rand(1, 10) <= 3 ? mt_rand(1, 6) : mt_rand(300, max(300, $wmax));
        }
        $sel[] = $row;
    }

    $input = [
        'product_id' => $pid,
        'system_id'  => $sysId,
        'option_id'  => $fabrics ? (int) $pick($fabrics) : 0,
        'width_mm'   => (int) round(mt_rand($wmin, $wmax) * $over),
        'drop_mm'    => (int) round(mt_rand($dmin, $dmax) * $over),
        'quantity'   => mt_rand(1, 10) <= 8 ? 1 : mt_rand(2, 4),
        'round_up'   => mt_rand(1, 10) <= 9,
        'extras'     => $sel,
    ];
    if (mt_rand(1, 10) === 1) $input['markup_override']   = mt_rand(0, 150);
    if (mt_rand(1, 10) === 1) $input['discount_override'] = mt_rand(0, 40);

    $out['cases'][] = ['input' => $input, 'expected' => pe_calculate_item($pdo, $c, $input)];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
