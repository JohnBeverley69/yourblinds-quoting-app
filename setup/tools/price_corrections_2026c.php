<?php
declare(strict_types=1);

/**
 * 2026 price-list corrections, part 3 (John, 2026-10-13), on the FACTORY catalogue.
 *
 *   1. Night & Day grids from "Night & day 2026.xlsx" (trade = cost x 1.3). The
 *      standard grids held different list figures (out by up to 65p), and the
 *      cassette ones were 1p out in places. Night & Day is a supplier list
 *      here: trade = list x (1 - buying discount) x (1 + markup). So each cell
 *      gets the list price (to the penny) whose trade price lands on the
 *      spreadsheet's figure. Where no penny list price lands exactly, the
 *      closest one is used (at most 1p out) and counted in the output.
 *      Price undo is recorded per system.
 *   2. Pleated face-fix brackets are priced PER BRACKET (John), not per blind:
 *      Face Fix £1.09, 40mm £2.30, 70mm £2.30 each, with a Qty box.
 *
 * Idempotent. Changes reach trade accounts with the next catalogue push.
 *   /setup/tools/price_corrections_2026c.php           → what it would change
 *   /setup/tools/price_corrections_2026c.php?apply=1   → make the changes
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__, 2) . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
require_once dirname(__DIR__, 2) . '/_partials/price_table_undo.php';
require_once dirname(__DIR__, 2) . '/_partials/pricing_engine.php';
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$fac = (int) (function_exists('factory_client_id') ? factory_client_id() : 3);
if (PHP_SAPI === 'cli') parse_str(implode('&', array_slice($argv, 1)), $_GET);
$apply = !empty($_GET['apply']);
echo $apply ? "APPLYING 2026 corrections part 3 (factory client $fac)\n\n" : "PREVIEW — nothing is changed. Add ?apply=1 to make these changes.\n\n";

$one = static function (string $sql, array $p = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetch(PDO::FETCH_ASSOC) ?: null; };
$all = static function (string $sql, array $p = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); };

// Trade prices from Night & day 2026.xlsx: "system|band|drop" => "width:price,…".
const ND_TRADE = [
    "Standard|A|300" => "600:35.02,800:42.30,1000:49.57,1200:56.57,1400:64.12,1600:71.12,1800:78.13,2000:85.67,2200:92.67,2300:96.44",
    "Standard|A|500" => "600:37.99,800:45.80,1000:54.15,1200:61.96,1400:70.04,1600:78.13,1800:86.21,2000:94.29,2200:102.10,2300:106.41",
    "Standard|A|700" => "600:40.68,800:49.57,1000:58.73,1200:67.35,1400:76.24,1600:85.13,1800:94.02,2000:103.18,2200:111.80,2300:116.11",
    "Standard|A|900" => "600:43.64,800:53.34,1000:63.04,1200:72.74,1400:82.71,1600:92.40,1800:102.10,2000:111.80,2200:121.50,2300:126.35",
    "Standard|A|1100" => "600:46.34,800:56.84,1000:67.35,1200:78.13,1400:88.90,1600:99.41,1800:109.91,2000:120.42,2200:131.20,2300:136.32",
    "Standard|A|1300" => "600:49.30,800:60.61,1000:71.93,1200:83.51,1400:94.83,1600:106.41,1800:117.73,2000:129.04,2200:140.63,2300:146.28",
    "Standard|A|1500" => "600:52.26,800:64.12,1000:76.51,1200:88.90,1400:101.02,1600:113.42,1800:125.81,2000:137.93,2200:150.05,2300:156.25",
    "Standard|A|1700" => "600:54.96,800:67.89,1000:81.09,1200:94.29,1400:107.49,1600:120.42,1800:133.35,2000:146.55,2200:159.75,2300:166.22",
    "Standard|A|1900" => "600:57.65,800:71.66,1000:85.67,1200:99.68,1400:113.69,1600:127.43,1800:141.43,2000:155.44,2200:169.18,2300:176.46",
    "Standard|A|2100" => "600:60.61,800:75.43,1000:90.25,1200:105.07,1400:119.61,1600:134.43,1800:149.25,2000:164.06,2200:178.88,2300:186.15",
    "Standard|A|2300" => "600:63.31,800:79.20,1000:94.56,1200:110.45,1400:125.81,1600:141.70,1800:157.06,2000:172.95,2200:188.31,2300:196.12",
    "Standard|B|300" => "600:41.76,800:50.38,1000:59.27,1200:68.16,1400:76.78,1600:85.40,1800:94.02,2000:102.91,2200:111.53,2300:116.11",
    "Standard|B|500" => "600:45.80,800:55.77,1000:66.00,1200:75.97,1400:85.94,1600:95.91,1800:106.14,2000:116.11,2200:126.08,2300:131.20",
    "Standard|B|700" => "600:50.11,800:61.42,1000:72.47,1200:84.05,1400:95.37,1600:106.41,1800:117.73,2000:129.31,2200:140.36,2300:146.01",
    "Standard|B|900" => "600:54.15,800:66.81,1000:79.20,1200:91.86,1400:104.53,1600:116.92,1800:129.58,2000:141.97,2200:154.63,2300:160.83",
    "Standard|B|1100" => "600:58.46,800:72.47,1000:86.21,1200:99.95,1400:113.96,1600:127.43,1800:141.43,2000:155.17,2200:169.18,2300:175.92",
    "Standard|B|1300" => "600:62.50,800:77.59,1000:92.94,1200:108.03,1400:123.11,1600:138.20,1800:153.29,2000:168.37,2200:183.73,2300:191.00",
    "Standard|B|1500" => "600:66.81,800:82.97,1000:99.68,1200:116.11,1400:132.27,1600:148.71,1800:165.14,2000:181.57,2200:198.01,2300:206.09",
    "Standard|B|1700" => "600:70.85,800:88.63,1000:106.14,1200:123.92,1400:141.70,1600:159.21,1800:176.99,2000:194.51,2200:212.29,2300:221.18",
    "Standard|B|1900" => "600:75.16,800:94.02,1000:112.88,1200:132.01,1400:150.86,1600:169.72,1800:188.85,2000:207.71,2200:226.56,2300:236.26",
    "Standard|B|2100" => "600:79.20,800:99.68,1000:119.61,1200:139.82,1400:160.29,1600:180.23,1800:200.70,2000:220.91,2200:241.11,2300:251.35",
    "Standard|B|2300" => "600:83.24,800:104.80,1000:126.62,1200:148.17,1400:169.45,1600:191.00,1800:212.56,2000:234.11,2200:255.66,2300:266.17",
    "Standard|C|300" => "600:44.82,800:53.88,1000:63.19,1200:72.25,1400:81.55,1600:90.62,1800:99.68,2000:108.98,2200:118.05,2300:122.70",
    "Standard|C|500" => "600:48.25,800:58.53,1000:68.82,1200:79.11,1400:89.15,1600:99.68,1800:109.72,2000:119.76,2200:130.05,2300:135.19",
    "Standard|C|700" => "600:51.92,800:63.19,1000:74.70,1200:85.96,1400:96.98,1600:108.25,1800:119.51,2000:131.03,2200:142.29,2300:147.92",
    "Standard|C|900" => "600:55.35,800:67.84,1000:80.33,1200:92.82,1400:105.07,1600:117.31,1800:129.80,2000:142.05,2200:154.54,2300:160.66",
    "Standard|C|1100" => "600:59.02,800:72.49,1000:85.96,1200:99.68,1400:112.90,1600:126.37,1800:139.84,2000:153.31,2200:166.78,2300:173.39",
    "Standard|C|1300" => "600:62.70,800:77.15,1000:91.60,1200:106.04,1400:120.74,1600:135.19,1800:149.64,2000:164.33,2200:178.78,2300:186.13",
    "Standard|C|1500" => "600:66.37,800:81.80,1000:97.47,1200:112.90,1400:128.58,1600:144.25,1800:159.92,2000:175.60,2200:190.78,2300:198.86",
    "Standard|C|1700" => "600:70.04,800:86.45,1000:103.11,1200:119.76,1400:136.66,1600:153.31,1800:169.72,2000:186.62,2200:203.27,2300:211.36",
    "Standard|C|1900" => "600:73.47,800:91.35,1000:108.98,1200:126.62,1400:144.50,1600:162.13,1800:180.01,2000:197.64,2200:215.27,2300:224.34",
    "Standard|C|2100" => "600:77.15,800:96.00,1000:114.62,1200:133.47,1400:152.33,1600:171.19,1800:189.80,2000:208.66,2200:227.76,2300:237.07",
    "Standard|C|2300" => "600:80.57,800:100.66,1000:120.49,1200:140.33,1400:160.17,1600:180.25,1800:199.84,2000:219.93,2200:239.76,2300:249.56",
    "Cassette|A|300" => "600:40.94,800:51.18,1000:61.42,1200:71.65,1400:81.89,1600:92.13,1800:102.36,2000:112.60,2200:111.43,2300:115.84",
    "Cassette|A|500" => "600:45.55,800:55.10,1000:65.15,1200:74.70,1400:84.25,1600:94.04,1800:103.60,2000:113.15,2200:122.70,2300:127.84",
    "Cassette|A|700" => "600:48.98,800:59.51,1000:70.53,1200:81.06,1400:91.84,1600:102.37,1800:112.90,2000:123.92,2200:134.45,2300:139.60",
    "Cassette|A|900" => "600:52.41,800:63.92,1000:75.92,1200:87.68,1400:99.19,1600:110.94,1800:122.45,2000:134.21,2200:145.96,2300:151.84",
    "Cassette|A|1100" => "600:55.59,800:68.33,1000:81.06,1200:94.04,1400:106.78,1600:119.51,1800:132.25,2000:144.74,2200:157.48,2300:163.84",
    "Cassette|A|1300" => "600:59.27,800:72.74,1000:86.45,1200:100.17,1400:114.13,1600:127.84,1800:141.56,2000:155.27,2200:168.99,2300:175.84",
    "Cassette|A|1500" => "600:62.70,800:77.15,1000:92.09,1200:106.78,1400:121.47,1600:136.41,1800:151.11,2000:165.80,2200:180.25,2300:187.84",
    "Cassette|A|1700" => "600:66.13,800:81.55,1000:97.47,1200:113.15,1400:129.07,1600:144.74,1800:160.41,2000:176.09,2200:192.01,2300:199.84",
    "Cassette|A|1900" => "600:69.55,800:86.21,1000:102.86,1200:119.76,1400:136.66,1600:153.07,1800:169.97,2000:186.86,2200:203.52,2300:211.84",
    "Cassette|A|2100" => "600:72.74,800:90.62,1000:108.25,1200:126.13,1400:143.76,1600:161.64,1800:179.27,2000:197.15,2200:215.03,2300:223.85",
    "Cassette|A|2300" => "600:76.17,800:95.02,1000:113.64,1200:132.74,1400:151.35,1600:170.21,1800:188.82,2000:207.93,2200:226.54,2300:235.85",
    "Cassette|A|2500" => "600:79.84,800:99.68,1000:119.03,1200:139.35,1400:158.70,1600:179.03,1800:198.13,2000:218.70,2200:237.81,2300:247.60",
    "Cassette|B|300" => "600:44.57,800:53.63,1000:63.19,1200:72.49,1400:81.55,1600:91.11,1800:100.17,2000:109.72,2200:118.78,2300:123.68",
    "Cassette|B|500" => "600:48.74,800:59.51,1000:70.29,1200:80.82,1400:91.60,1600:102.37,1800:112.90,2000:123.68,2200:134.21,2300:139.60",
    "Cassette|B|700" => "600:53.39,800:65.39,1000:77.39,1200:89.64,1400:101.39,1600:113.39,1800:125.39,2000:137.64,2200:149.64,2300:155.52",
    "Cassette|B|900" => "600:57.80,800:71.27,1000:84.49,1200:97.96,1400:111.43,1600:124.66,1800:138.13,2000:151.35,2200:164.82,2300:171.44",
    "Cassette|B|1100" => "600:62.45,800:77.15,1000:91.84,1200:106.53,1400:121.23,1600:135.92,1800:150.62,2000:165.31,2200:180.01,2300:187.60",
    "Cassette|B|1300" => "600:66.61,800:82.78,1000:98.94,1200:115.11,1400:131.03,1600:147.19,1800:163.35,2000:179.27,2200:195.68,2300:203.52",
    "Cassette|B|1500" => "600:71.27,800:88.41,1000:106.04,1200:123.68,1400:140.82,1600:158.46,1800:176.09,2000:193.23,2200:210.87,2300:219.44",
    "Cassette|B|1700" => "600:75.68,800:94.29,1000:113.15,1200:132.01,1400:150.86,1600:169.72,1800:188.58,2000:207.44,2200:226.05,2300:235.60",
    "Cassette|B|1900" => "600:80.08,800:100.17,1000:120.25,1200:140.58,1400:160.90,1600:180.99,1800:201.31,2000:221.40,2200:241.48,2300:251.52",
    "Cassette|B|2100" => "600:84.49,800:106.04,1000:127.35,1200:149.15,1400:170.70,1600:192.01,1800:213.80,2000:235.36,2200:256.91,2300:267.68",
    "Cassette|B|2300" => "600:88.90,800:111.68,1000:134.94,1200:157.72,1400:180.74,1600:203.52,1800:226.54,2000:249.32,2200:272.09,2300:283.60",
    "Cassette|B|2500" => "600:92.82,800:117.31,1000:142.54,1200:166.29,1400:190.78,1600:214.78,1800:239.03,2000:263.52,2200:287.52,2300:300.01",
    "Cassette|C|300" => "600:52.66,800:63.19,1000:74.21,1200:84.98,1400:95.76,1600:106.53,1800:117.07,2000:128.09,2200:138.86,2300:144.24",
    "Cassette|C|500" => "600:56.82,800:68.82,1000:81.06,1200:92.82,1400:104.82,1600:117.07,1800:129.07,2000:141.07,2200:153.07,2300:159.08",
    "Cassette|C|700" => "600:60.98,800:74.21,1000:87.68,1200:100.90,1400:114.13,1600:127.35,1800:140.58,2000:154.29,2200:167.52,2300:173.93",
    "Cassette|C|900" => "600:65.15,800:79.59,1000:94.53,1200:108.98,1400:123.68,1600:138.13,1800:152.58,2000:167.27,2200:181.72,2300:189.09",
    "Cassette|C|1100" => "600:69.31,800:85.23,1000:100.90,1200:117.07,1400:132.98,1600:148.66,1800:164.58,2000:180.50,2200:196.17,2300:203.94",
    "Cassette|C|1300" => "600:73.96,800:90.62,1000:107.76,1200:124.90,1400:142.05,1600:159.19,1800:176.09,2000:193.23,2200:210.38,2300:219.10",
    "Cassette|C|1500" => "600:78.13,800:96.25,1000:114.62,1200:132.98,1400:151.35,1600:169.72,1800:188.09,2000:206.46,2200:224.58,2300:233.95",
    "Cassette|C|1700" => "600:82.29,800:101.64,1000:121.23,1200:141.07,1400:160.66,1600:180.50,1800:199.84,2000:219.44,2200:239.03,2300:248.79",
    "Cassette|C|1900" => "600:86.45,800:107.51,1000:128.09,1200:149.15,1400:169.97,1600:190.78,1800:211.60,2000:232.66,2200:253.23,2300:263.96",
    "Cassette|C|2100" => "600:90.62,800:112.90,1000:134.94,1200:157.23,1400:179.03,1600:201.31,1800:223.36,2000:245.64,2200:267.93,2300:278.80",
    "Cassette|C|2300" => "600:94.78,800:118.54,1000:141.56,1200:165.31,1400:188.33,1600:212.09,1800:235.36,2000:258.87,2200:282.13,2300:293.64",
    "Cassette|C|2500" => "600:99.43,800:124.17,1000:148.17,1200:173.64,1400:197.40,1600:222.87,1800:246.87,2000:272.34,2200:296.34,2300:308.49",
];

// ---------------------------------------------------------------- 1. Night & Day
echo "1. Night & Day grids (Night & day 2026.xlsx)\n";
$nd = $one('SELECT id, price_source FROM products WHERE client_id = ? AND name = ? LIMIT 1', [$fac, 'Bev Night & Day']);
if (!$nd) {
    echo "   Bev Night & Day not found — skipped.\n";
} else {
    $want = [];
    foreach (ND_TRADE as $key => $cells) {
        [$sys, $band, $drop] = explode('|', $key);
        foreach (explode(',', $cells) as $c) { [$w, $p] = explode(':', $c); $want[$sys][$band][(int) $drop][(int) $w] = (float) $p; }
    }
    foreach ($want as $sysName => $bands) {
        $sys = $one('SELECT id FROM product_systems WHERE client_id = ? AND product_id = ? AND name = ? LIMIT 1', [$fac, $nd['id'], $sysName]);
        if (!$sys) { echo "   system $sysName not found — skipped\n"; continue; }
        $sid = (int) $sys['id'];
        $isSupplier = ($nd['price_source'] ?? 'own') === 'supplier';
        $f = $isSupplier
            ? (1 - pe_discount_for_system($pdo, $fac, (int) $nd['id'], $sid) / 100) * (1 + pe_markup_for_system($pdo, $fac, (int) $nd['id'], $sid) / 100)
            : 1.0;
        $plan = []; $tabIds = [];
        foreach ($bands as $band => $rows) {
            $t = $one('SELECT id FROM price_tables WHERE client_id = ? AND product_id = ? AND system_id = ? AND band_code = ? AND active = 1 LIMIT 1', [$fac, $nd['id'], $sid, $band]);
            if (!$t) { echo "   $sysName band $band: no grid — skipped\n"; continue; }
            $tabIds[] = (int) $t['id'];
            $have = [];
            foreach ($all('SELECT id, width_mm, drop_mm, price FROM price_table_rows WHERE price_table_id = ?', [$t['id']]) as $r) $have[$r['width_mm'] . 'x' . $r['drop_mm']] = $r;
            $n = 0; $off = 0; $missing = 0; $ex = null;
            foreach ($rows as $drop => $cells) foreach ($cells as $w => $trade) {
                // The penny list price whose trade price is closest to the spreadsheet's.
                $base = (int) round($trade / $f * 100);
                $best = null;
                foreach ([0, -1, 1, -2, 2] as $d) {
                    $l = ($base + $d) / 100;
                    $err = abs(round($l * $f, 2) - $trade);
                    if ($best === null || $err < $best[1] - 1e-9) $best = [$l, $err];
                }
                [$list, $err] = $best;
                if ($err > 0.0001) $off++;
                $cell = $have[$w . 'x' . $drop] ?? null;
                if (!$cell) { $missing++; continue; }
                if (abs((float) $cell['price'] - $list) >= 0.005) {
                    $plan[] = [(int) $cell['id'], $list]; $n++;
                    $ex ??= "{$w}x{$drop}: list £" . number_format((float) $cell['price'], 2) . ' → £' . number_format($list, 2) . ' (trade £' . number_format($trade, 2) . ')';
                }
            }
            echo "   $sysName band $band: $n cell(s) to change" . ($off ? ", $off can only land within 1p" : '') . ($missing ? ", $missing size(s) not in the grid" : '') . ($ex ? " (e.g. $ex)" : '') . "\n";
        }
        if ($apply && $plan) {
            pu_begin($pdo, $fac, 'system:' . $sid, $tabIds, "Night & Day $sysName from Night & day 2026.xlsx");
            $u = $pdo->prepare('UPDATE price_table_rows SET price = ? WHERE id = ?');
            $pdo->beginTransaction();
            foreach ($plan as [$id, $p]) $u->execute([$p, $id]);
            $pdo->commit();
            pu_finish_all();
            echo "   → $sysName: " . count($plan) . " cell(s) updated (Undo is on the price grid page)\n";
        }
    }
}
echo "\n";

// ---------------------------------------------------------------- 2. Pleated brackets per bracket
echo "2. Pleated face-fix brackets: per bracket\n";
$bt = $one("SELECT e.id FROM product_extras e JOIN products p ON p.id = e.product_id
             WHERE p.client_id = ? AND p.name = 'Bev Pleated' AND e.name = 'Bracket Type' AND e.active = 1 LIMIT 1", [$fac]);
if (!$bt) {
    echo "   Bev Pleated / Bracket Type not found — skipped.\n";
} else {
    foreach (['Face Fix' => 1.09, '40mm Face Fix' => 2.30, '70mm Face Fix' => 2.30] as $label => $each) {
        $c = $one('SELECT id, price_delta, price_per_unit, length_input_label FROM product_extra_choices WHERE product_extra_id = ? AND label = ? AND active = 1 LIMIT 1', [(int) $bt['id'], $label]);
        if (!$c) { echo "     choice \"$label\" not found — skipped\n"; continue; }
        $ok = abs((float) $c['price_delta']) < 0.005 && $c['price_per_unit'] !== null
            && abs((float) $c['price_per_unit'] - $each) < 0.005 && (string) $c['length_input_label'] !== '';
        if ($ok) { echo "     \"$label\" £" . number_format($each, 2) . " per bracket — already right\n"; continue; }
        echo "     ~ \"$label\": £" . number_format((float) $c['price_delta'], 2) . " per blind → £" . number_format($each, 2) . " per bracket (Qty box)\n";
        if ($apply) {
            $pdo->prepare("UPDATE product_extra_choices SET price_delta = 0, price_per_unit = ?, length_input_label = COALESCE(NULLIF(length_input_label, ''), 'Qty') WHERE id = ?")
                ->execute([$each, (int) $c['id']]);
        }
    }
}
echo "\n";

echo $apply
    ? "Done. Push the factory catalogue (Push updates → Bev → every account) so trade accounts get these prices.\n"
    : "Nothing changed. Run with ?apply=1 to make these changes.\n";
