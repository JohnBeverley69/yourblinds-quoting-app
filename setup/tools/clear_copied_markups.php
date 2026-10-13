<?php
declare(strict_types=1);

/**
 * Find (and, for chosen companies, remove) markups/discounts a company got
 * COPIED from the factory when it was set up.
 *
 * John, 2026-10-13: a company should only get our trade price list — no
 * markups, no discounts. New-company setup used to copy the factory's own
 * client_markups / client_discounts onto the company (both the old seed and the
 * push-based one from #1056). On the factory those are BUYING discounts and our
 * margin (e.g. 40% off Forest Wood's supplier list), so on the company they
 * became its SELLING discount and it sold below its trade cost. Setup no longer
 * copies them; this lists the ones already there.
 *
 * A row is flagged as a copy when either:
 *   - "made at setup": written within 10 minutes of the company being created
 *     (the seed runs in the same transaction as the new company), or
 *   - "= factory": same value as the factory's row for the same product/system
 *     (matched by source ids, else by product + system name).
 * Rows on the company's OWN products (not copies of factory products) are never
 * listed. Trade-account discounts (trade_discounts) are a different table and
 * are not touched.
 *
 * Super-admin, web-runnable:
 *   /setup/tools/clear_copied_markups.php                    → the list (changes nothing)
 *   /setup/tools/clear_copied_markups.php?client=185&apply=1 → remove the flagged rows
 *                                                              for those companies only
 *                                                              (comma list allowed)
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__, 2) . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$facId = (int) (function_exists('factory_client_id') ? factory_client_id() : 3);
if (PHP_SAPI === 'cli') parse_str(implode('&', array_slice($argv, 1)), $_GET);
$only  = array_values(array_filter(array_map('intval', explode(',', (string) ($_GET['client'] ?? '')))));
$apply = !empty($_GET['apply']);
if ($apply && !$only) { echo "Apply needs ?client=ID (one company, or a comma list).\n"; exit; }
if (in_array($facId, $only, true)) { echo "Refusing: client $facId is the factory itself.\n"; exit; }

// Factory products and systems, for matching by name.
$facProdByName = [];   // name => id
foreach ($pdo->query("SELECT id, name FROM products WHERE client_id = $facId") as $r) $facProdByName[(string) $r['name']] = (int) $r['id'];
$facSysName = [];      // system id => [product id, name]
foreach ($pdo->query("SELECT id, product_id, name FROM product_systems WHERE client_id = $facId") as $r) {
    $facSysName[(int) $r['id']] = [(int) $r['product_id'], (string) $r['name']];
}
$facSysByName = [];    // "pid|name" => id
foreach ($facSysName as $id => [$pid, $nm]) $facSysByName["$pid|$nm"] = $id;

$tables = ['client_markups' => ['markup_percent', 'markup'], 'client_discounts' => ['discount_percent', 'discount']];

// The factory's own values per (product, system).
$facVal = [];          // table => "pid|sid" => value
foreach ($tables as $t => [$col]) {
    foreach ($pdo->query("SELECT product_id, system_id, $col v FROM $t WHERE client_id = $facId") as $r) {
        $facVal[$t][(int) $r['product_id'] . '|' . (int) ($r['system_id'] ?? 0)] = (float) $r['v'];
    }
}

$hasSrcSys = (bool) $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                                  AND TABLE_NAME = 'product_systems' AND COLUMN_NAME = 'source_system_id'")->fetchColumn();
$srcSysCol = $hasSrcSys ? 's.source_system_id' : 'NULL';

$found = [];           // rows flagged as copies
$own   = [];           // rows on factory products that look deliberate
foreach ($tables as $t => [$col, $kind]) {
    $rows = $pdo->query(
        "SELECT x.id, x.client_id, x.product_id, x.system_id, x.$col v, x.created_at row_at,
                c.company_name, c.created_at client_at, c.account_ref, c.is_factory,
                p.name product, p.source_client_id, p.source_product_id,
                s.name system, $srcSysCol src_sys
           FROM $t x
           JOIN clients c  ON c.id = x.client_id
           JOIN products p ON p.id = x.product_id
      LEFT JOIN product_systems s ON s.id = x.system_id
          WHERE x.client_id <> $facId
       ORDER BY c.company_name, p.name, s.name"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        // Which factory product is this a copy of?
        $fp = ((int) $r['source_client_id'] === $facId && (int) $r['source_product_id'] > 0)
            ? (int) $r['source_product_id']
            : ($facProdByName[(string) $r['product']] ?? 0);
        if ($fp <= 0) continue;    // the company's own product — never ours to touch
        $fs = 0;
        if ($r['system_id'] !== null) {
            if ((int) $r['src_sys'] > 0 && isset($facSysName[(int) $r['src_sys']])) $fs = (int) $r['src_sys'];
            else $fs = $facSysByName[$fp . '|' . (string) $r['system']] ?? -1;
        }
        $fv       = $fs >= 0 ? ($facVal[$t]["$fp|$fs"] ?? null) : null;
        $sameVal  = $fv !== null && abs($fv - (float) $r['v']) < 0.005;
        $atSetup  = $r['row_at'] && $r['client_at'] && abs(strtotime((string) $r['row_at']) - strtotime((string) $r['client_at'])) <= 600;
        if (!$sameVal && !$atSetup) { $own[] = $r + ['kind' => $kind, 'fac_v' => $fv]; continue; }
        $found[] = $r + ['kind' => $kind, 'table' => $t, 'fac_v' => $fv, 'same' => $sameVal, 'at_setup' => $atSetup];
    }
}

// Report, grouped by company.
$by = [];
foreach ($found as $f) $by[(int) $f['client_id']][] = $f;
echo $apply ? "REMOVING copied markups/discounts for client(s) " . implode(', ', $only) . "\n\n"
            : "Markups/discounts companies got COPIED from the factory (nothing changed — list only)\n\n";
if (!$by) echo "None found.\n";
$deleted = 0;
foreach ($by as $cid => $list) {
    $c = $list[0];
    $tag = ($c['account_ref'] ? 'trade account ' . $c['account_ref'] : 'no account ref') . ((int) $c['is_factory'] ? ', factory' : '');
    echo "client $cid · {$c['company_name']} ($tag, set up " . substr((string) $c['client_at'], 0, 10) . ") — " . count($list) . " row(s)\n";
    foreach ($list as $f) {
        printf("    %-8s %6s%%  %-38s %-28s factory: %-7s %s\n",
            $f['kind'], number_format((float) $f['v'], 2), mb_strimwidth((string) $f['product'], 0, 38, '…'),
            mb_strimwidth((string) ($f['system'] ?? '(all systems)'), 0, 28, '…'),
            $f['fac_v'] === null ? 'none' : number_format((float) $f['fac_v'], 2) . '%',
            trim(($f['same'] ? '= factory' : '') . ($f['at_setup'] ? '  made at setup' : '')));
    }
    if ($apply && in_array($cid, $only, true)) {
        foreach ($list as $f) {
            $d = $pdo->prepare("DELETE FROM {$f['table']} WHERE id = ? AND client_id = ?");
            $d->execute([(int) $f['id'], $cid]);
            $deleted += $d->rowCount();
        }
        echo "    → removed\n";
    }
    echo "\n";
}
if ($own) {
    echo "Rows on factory products that look like the company's OWN choice (set after setup, different from ours) — left alone:\n";
    foreach ($own as $o) {
        printf("    client %-4d %-30s %-8s %6s%%  %-34s %s\n", (int) $o['client_id'], mb_strimwidth((string) $o['company_name'], 0, 30, '…'),
            $o['kind'], number_format((float) $o['v'], 2), mb_strimwidth((string) $o['product'], 0, 34, '…'), (string) ($o['system'] ?? '(all systems)'));
    }
    echo "\n";
}
if ($apply) {
    echo "Removed $deleted row(s). Those products now price at the trade price with no markup/discount of their own.\n";
} elseif ($by) {
    echo "To remove for one company: ?client=ID&apply=1\n";
}
