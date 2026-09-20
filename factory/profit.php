<?php
declare(strict_types=1);

/**
 * Factory · Manufacturing profit (super-admin only).
 *
 * Your real profit as the maker: for every Beverley-product line that any trade
 * customer has ordered, what you charged them (the trade price on their order)
 * minus what it costs you to make it (your master cost grid). Summed across all
 * accounts.
 *
 * Cost is MASTER-ADMIN ONLY, so this whole page is. The cost is looked up here,
 * on your own master price tables — it is never stored on a tenant's order, so
 * no cost ever leaves your side.
 *
 * Cost comes from whichever of the two pricing models the product uses:
 *   'own'      we make it — cost is the parallel cost grid you import alongside
 *              the selling grid (price_table_rows.cost).
 *   'supplier' we buy it in — the grid IS the supplier's list price, so the cost
 *              is simply list − our buying discount. There is no cost grid to
 *              import and there never will be, which is why bought-in ranges
 *              (Forest Wood, 25mm Venetian) used to sit in the "no cost grid
 *              imported" bucket showing no profit at all.
 *
 * Honest by design:
 *   - A line we still can't cost shows as revenue with no cost, kept separate so
 *     the margin can't look falsely huge.
 *   - Each order size is costed at the same cell your pricing charged at — next
 *     cell up, and on whichever axes the product actually uses (full grid,
 *     width-only, per-slat, or a £/m² rate).
 *   - Revenue is base_price: the trade price after our buying discount, before
 *     the tenant's own markup. Options revenue is shown but never costed.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/pricing_engine.php';
require_once __DIR__ . '/../_partials/price_source.php';

requireSuperAdmin();

$pdo    = db();
$MASTER = current_factory_id();

// Period: this month / this year / all time / a From–To range you type yourself.
$period  = (string) ($_GET['period'] ?? 'year');
$fromIn  = trim((string) ($_GET['from'] ?? ''));
$toIn    = trim((string) ($_GET['to']   ?? ''));
$since   = null;
$until   = null;
$rangeNote = null;

$realDate = static function (string $s): bool {
    return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)
        && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
};

if ($period === 'custom') {
    // A bad bound is ignored rather than silently changing the window to
    // something you didn't ask for — you're told which one was dropped.
    if ($fromIn !== '' && !$realDate($fromIn)) { $rangeNote = 'That "from" date isn\'t a real date, so it\'s been left off.'; $fromIn = ''; }
    if ($toIn   !== '' && !$realDate($toIn))   { $rangeNote = 'That "to" date isn\'t a real date, so it\'s been left off.';   $toIn   = ''; }
    if ($fromIn !== '' && $toIn !== '' && $fromIn > $toIn) {
        [$fromIn, $toIn] = [$toIn, $fromIn];
        $rangeNote = 'The dates were the wrong way round, so they\'ve been swapped.';
    }
    if ($fromIn !== '') $since = $fromIn . ' 00:00:00';
    if ($toIn   !== '') $until = $toIn   . ' 23:59:59';   // the "to" day is included
} elseif ($period === 'month') {
    $since = date('Y-m-01 00:00:00');
} elseif ($period === 'all') {
    $since = null;
} else {
    $period = 'year';
    $since  = date('Y-01-01 00:00:00');
}

$PLACED = ['ordered', 'fitted', 'invoiced', 'paid'];
$inPl   = "'" . implode("','", $PLACED) . "'";

$dateSql = '';
$args    = [$MASTER];
if ($since !== null) { $dateSql .= ' AND q.created_at >= ?'; $args[] = $since; }
if ($until !== null) { $dateSql .= ' AND q.created_at <= ?'; $args[] = $until; }

// Every Beverley-product line on a placed order, with whatever we have to point
// the line back at the master grid: the tenant table's own identity link first,
// its band + system name only as a last resort.
$hasTableSrc = pe_col_exists($pdo, 'price_tables', 'source_table_id');
$srcTableSel = $hasTableSrc ? 'tpt.source_table_id AS src_table_id,' : 'NULL AS src_table_id,';
$sql =
    "SELECT qi.width_mm, qi.drop_mm, qi.quantity, qi.base_price, qi.extras_total,
            qi.product_name_snapshot,
            COALESCE(p.source_product_id, p.id) AS master_pid,
            $srcTableSel
            tpt.id AS tenant_table_id, tpt.client_id AS tenant_table_client,
            tpt.system_id AS tenant_system_id,
            tpt.band_code AS band, tsys.name AS system_name
       FROM quote_items qi
       JOIN quotes q   ON q.id = qi.quote_id
       JOIN products p ON p.id = qi.product_id
       LEFT JOIN price_tables tpt   ON tpt.id = qi.price_table_id
       LEFT JOIN product_systems tsys ON tsys.id = tpt.system_id
      WHERE COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
        AND q.status IN ($inPl)
        $dateSql";
$st = $pdo->prepare($sql);
$st->execute($args);
$lines = $st->fetchAll(PDO::FETCH_ASSOC);

// --- Which master grid does this line price against? --------------------------
// Three ways, best first:
//   1. The tenant's copy of the table carries source_table_id — the master
//      table's own id. Rename-proof, which matters: matching on the SYSTEM NAME
//      is exactly what left the 25mm Venetian uncosted, because the master's
//      system had since been renamed from "25mm Slat" to "Standard Slat" while
//      the tenant's copy still said the old name.
//   2. The line was raised here, so its table IS a master table.
//   3. Last resort, the old behaviour: match on system name + band.
$byIdCache = [];
$masterTableById = function (int $tid, int $mpid) use ($pdo, $MASTER, &$byIdCache): ?array {
    $key = $tid . '|' . $mpid;
    if (array_key_exists($key, $byIdCache)) return $byIdCache[$key];
    $q = $pdo->prepare('SELECT id, system_id FROM price_tables WHERE id = ? AND client_id = ? AND product_id = ? LIMIT 1');
    $q->execute([$tid, $MASTER, $mpid]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    return $byIdCache[$key] = ($row ? ['id' => (int) $row['id'], 'system_id' => $row['system_id'] !== null ? (int) $row['system_id'] : null] : null);
};

$nameCache = [];
$masterTableByName = function (int $mpid, ?string $systemName, ?string $band) use ($pdo, $MASTER, &$nameCache): ?array {
    if ($systemName === null || $band === null) return null;
    $key = $mpid . '|' . $systemName . '|' . $band;
    if (array_key_exists($key, $nameCache)) return $nameCache[$key];
    $q = $pdo->prepare(
        'SELECT pt.id, pt.system_id FROM price_tables pt
           JOIN product_systems s ON s.id = pt.system_id
          WHERE pt.client_id = ? AND pt.product_id = ? AND s.name = ? AND pt.band_code = ?
          LIMIT 1'
    );
    $q->execute([$MASTER, $mpid, $systemName, $band]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    return $nameCache[$key] = ($row ? ['id' => (int) $row['id'], 'system_id' => $row['system_id'] !== null ? (int) $row['system_id'] : null] : null);
};

$findMasterTable = function (array $ln) use ($MASTER, $masterTableById, $masterTableByName): ?array {
    $mpid = (int) $ln['master_pid'];

    if (!empty($ln['src_table_id'])) {
        $hit = $masterTableById((int) $ln['src_table_id'], $mpid);
        if ($hit !== null) return $hit;
    }
    if (!empty($ln['tenant_table_id']) && (int) ($ln['tenant_table_client'] ?? 0) === $MASTER) {
        return [
            'id'        => (int) $ln['tenant_table_id'],
            'system_id' => $ln['tenant_system_id'] !== null ? (int) $ln['tenant_system_id'] : null,
        ];
    }
    return $masterTableByName($mpid, $ln['system_name'], $ln['band']);
};

// --- Master product facts: which pricing model, and which shape of grid -------
// Schema-tolerant, same as the pricing engine: a column a migration hasn't added
// yet simply reads as "off".
$prodCols = ['id'];
foreach (['price_source', 'width_only', 'price_per_slat', 'price_per_sqm', 'min_area_m2'] as $c) {
    if (pe_col_exists($pdo, 'products', $c)) $prodCols[] = $c;
}
$prodSt    = $pdo->prepare('SELECT ' . implode(', ', $prodCols) . ' FROM products WHERE id = ? LIMIT 1');
$prodCache = [];
$masterProduct = function (int $mpid) use ($prodSt, &$prodCache): array {
    if (isset($prodCache[$mpid])) return $prodCache[$mpid];
    $prodSt->execute([$mpid]);
    $r = $prodSt->fetch(PDO::FETCH_ASSOC);
    return $prodCache[$mpid] = ($r ?: []);
};

// Cost of one blind at the master grid, or null if we genuinely can't cost it.
$rowCost = $pdo->prepare('SELECT cost FROM price_table_rows WHERE id = ? LIMIT 1');
// Fallback for a patchy cost grid: the next cell up that actually carries a cost.
$costCell = $pdo->prepare(
    'SELECT cost FROM price_table_rows
      WHERE price_table_id = ? AND width_mm >= ? AND drop_mm >= ? AND cost IS NOT NULL
      ORDER BY width_mm ASC, drop_mm ASC LIMIT 1'
);

$unitCost = function (int $mpid, int $tableId, ?int $sysId, int $w, int $d)
    use ($pdo, $MASTER, $masterProduct, $rowCost, $costCell): ?float {

    $prod      = $masterProduct($mpid);
    $widthOnly = (int) ($prod['width_only']    ?? 0) === 1;
    $perSlat   = !$widthOnly && (int) ($prod['price_per_slat'] ?? 0) === 1;
    $perSqm    = !$widthOnly && !$perSlat && (int) ($prod['price_per_sqm'] ?? 0) === 1;

    // The same cell the pricing engine would have charged at.
    if     ($widthOnly) $row = pe_find_matrix_row_width_only($pdo, $tableId, $w, true);
    elseif ($perSlat)   $row = pe_find_matrix_row_by_drop   ($pdo, $tableId, $d, true);
    elseif ($perSqm)    $row = pe_find_rate_per_sqm         ($pdo, $tableId);
    else                $row = pe_find_matrix_row           ($pdo, $tableId, $w, $d, true);
    if ($row === null) return null;

    if (ps_normalise($prod['price_source'] ?? null) === PRICE_SOURCE_SUPPLIER) {
        // Bought in: the grid is the supplier's list, our cost is list less the
        // buying discount we've negotiated on this product/system.
        $disc = pe_discount_for_system($pdo, $MASTER, $mpid, $sysId);
        $unit = (float) $row['price'] * (1 - $disc / 100);
    } else {
        // Ours to make: the cost grid, at the cell we priced from — falling back
        // to the next cell up that carries a cost if that one was left blank.
        $rowCost->execute([(int) $row['id']]);
        $c = $rowCost->fetchColumn();
        if ($c === null || $c === false) {
            $costCell->execute([$tableId, $w, $d]);
            $c = $costCell->fetchColumn();
        }
        if ($c === null || $c === false) return null;
        $unit = (float) $c;
    }

    // A £/m² rate is a rate, not a price — it still needs the area.
    if ($perSqm) {
        $unit *= max(($w / 1000.0) * ($d / 1000.0), (float) ($prod['min_area_m2'] ?? 0));
    }
    return $unit;
};

$tot = [
    'lines' => 0, 'blinds' => 0,
    'rev_costed' => 0.0, 'cost' => 0.0,      // matched to a cost
    'rev_uncosted' => 0.0,                    // Beverley lines with no cost yet
    'extras' => 0.0,                          // options revenue — not costed
];
$byProduct = [];     // product => [rev, cost, blinds, costed_blinds]
$uncostedProducts = [];

foreach ($lines as $ln) {
    $qty = max(1, (int) $ln['quantity']);
    $rev = (float) $ln['base_price'] * $qty;
    $prod = (string) ($ln['product_name_snapshot'] ?: 'product ' . $ln['master_pid']);
    $tot['lines']++; $tot['blinds'] += $qty;
    $tot['extras'] += (float) ($ln['extras_total'] ?? 0) * $qty;

    $p = &$byProduct[$prod];
    if ($p === null) $p = ['rev' => 0.0, 'cost' => 0.0, 'blinds' => 0, 'costed' => 0];
    $p['rev'] += $rev; $p['blinds'] += $qty;

    $cost = null;
    $tbl  = $findMasterTable($ln);
    if ($tbl !== null) {
        $cost = $unitCost(
            (int) $ln['master_pid'], $tbl['id'], $tbl['system_id'],
            (int) $ln['width_mm'], (int) $ln['drop_mm']
        );
    }

    if ($cost !== null) {
        $tot['rev_costed'] += $rev; $tot['cost'] += $cost * $qty;
        $p['cost'] += $cost * $qty; $p['costed'] += $qty;
    } else {
        $tot['rev_uncosted'] += $rev;
        $uncostedProducts[$prod] = true;
    }
    unset($p);
}

$profit    = $tot['rev_costed'] - $tot['cost'];
$marginPct = $tot['rev_costed'] > 0 ? $profit / $tot['rev_costed'] * 100 : null;
uasort($byProduct, static fn ($a, $b) => $b['rev'] <=> $a['rev']);   // biggest earners first

$money = static fn ($v) => '£' . number_format((float) $v, 2);
$factoryTitle = 'Manufacturing profit';
$factoryNav   = '';
$factoryWide  = true;
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
  .pf-h { font-size:1.5rem; font-weight:700; margin:0 0 .2rem; }
  .pf-sub { color:var(--text-muted,#667); margin:0 0 1rem; font-size:.92rem; }
  .pf-periods { display:flex; gap:.4rem; margin:0 0 .6rem; flex-wrap:wrap; align-items:center; }
  .pf-periods a, .pf-periods button { text-decoration:none; font-size:.9rem; font-weight:600; padding:.35rem .8rem; border-radius:8px; background:var(--bg-subtle,#f1f5f9); color:#334155; border:0; cursor:pointer; font-family:inherit; }
  .pf-periods a.on, .pf-periods.range button { background:#1f2a37; color:#fff; }
  .pf-periods.range { margin:0 0 1.2rem; }
  .pf-periods label { font-size:.8rem; color:var(--text-muted,#667); font-weight:600; }
  .pf-periods input[type=date] { font:inherit; font-size:.85rem; padding:.3rem .5rem; border:1px solid var(--border,#e5e7eb); border-radius:8px; background:var(--bg-card,#fff); color:inherit; }
  .pf-note { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; padding:.5rem .9rem; border-radius:10px; margin:0 0 1rem; font-size:.85rem; }
  .pf-cards { display:flex; gap:1rem; flex-wrap:wrap; margin:0 0 1.4rem; }
  .pf-card { flex:1 1 12rem; background:var(--bg-card,#fff); border:1px solid var(--border,#e5e7eb); border-radius:12px; padding:1rem 1.2rem; box-shadow:0 1px 2px rgba(0,0,0,.04); }
  .pf-card .k { font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-faint,#94a3b8); font-weight:700; }
  .pf-card .v { font-size:1.7rem; font-weight:800; letter-spacing:-.01em; margin-top:.2rem; font-variant-numeric:tabular-nums; }
  .pf-card.profit .v { color:#166534; }
  .pf-card .sub { font-size:.8rem; color:var(--text-muted,#667); margin-top:.15rem; }
  .pf-warn { background:#fef3c7; border:1px solid #fde68a; color:#92600a; padding:.7rem 1rem; border-radius:10px; margin:0 0 1.2rem; font-size:.9rem; }
  table.pf { width:100%; border-collapse:collapse; background:var(--bg-card,#fff); border:1px solid var(--border,#e5e7eb); border-radius:12px; overflow:hidden; }
  .pf th { text-align:left; font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-faint,#94a3b8); font-weight:700; padding:.5rem .7rem; background:var(--bg-subtle,#f8fafc); border-bottom:1px solid var(--border,#e5e7eb); }
  .pf td { padding:.5rem .7rem; border-bottom:1px solid var(--border,#eef1f5); font-size:.9rem; font-variant-numeric:tabular-nums; }
  .pf td.r, .pf th.r { text-align:right; }
  .pf tr:last-child td { border-bottom:none; }
  .pf .muted { color:var(--text-faint,#94a3b8); }
  .pf .pos { color:#166534; font-weight:700; }
</style>

<h1 class="pf-h">Manufacturing profit</h1>
<p class="pf-sub">What you charge for the blinds you make, minus what they cost you to make &mdash; across every trade customer's orders. Your eyes only.</p>

<div class="pf-periods">
    <?php foreach (['month' => 'This month', 'year' => 'This year', 'all' => 'All time'] as $k => $lbl): ?>
        <a class="<?= $period === $k ? 'on' : '' ?>" href="?period=<?= $k ?>"><?= e($lbl) ?></a>
    <?php endforeach; ?>
</div>

<form class="pf-periods <?= $period === 'custom' ? 'range' : '' ?>" method="get" action="">
    <input type="hidden" name="period" value="custom">
    <label for="pfFrom">From</label>
    <input type="date" id="pfFrom" name="from" max="<?= date('Y-m-d') ?>" value="<?= e($fromIn) ?>">
    <label for="pfTo">to</label>
    <input type="date" id="pfTo" name="to" max="<?= date('Y-m-d') ?>" value="<?= e($toIn) ?>">
    <button type="submit">Show</button>
</form>

<?php if ($rangeNote !== null): ?>
    <div class="pf-note"><?= e($rangeNote) ?></div>
<?php endif; ?>

<?php if ($tot['rev_uncosted'] > 0): ?>
    <div class="pf-warn"><strong><?= $money($tot['rev_uncosted']) ?></strong> of orders are on products we can't cost yet (<?= e(implode(', ', array_keys($uncostedProducts))) ?>) — their profit isn't counted. A product we make needs its cost grid importing; a product we buy in needs its buying discount setting on Pricing.</div>
<?php endif; ?>

<div class="pf-cards">
    <div class="pf-card profit">
        <div class="k">Profit (costed orders)</div>
        <div class="v"><?= $money($profit) ?></div>
        <div class="sub"><?= $marginPct !== null ? number_format($marginPct, 1) . '% margin' : '—' ?></div>
    </div>
    <div class="pf-card">
        <div class="k">Revenue (costed)</div>
        <div class="v"><?= $money($tot['rev_costed']) ?></div>
        <div class="sub">what you charged, on costed blinds</div>
    </div>
    <div class="pf-card">
        <div class="k">Cost to make</div>
        <div class="v"><?= $money($tot['cost']) ?></div>
        <div class="sub"><?= (int) $tot['blinds'] ?> blind<?= $tot['blinds'] === 1 ? '' : 's' ?> across <?= (int) $tot['lines'] ?> line<?= $tot['lines'] === 1 ? '' : 's' ?></div>
    </div>
    <?php if ($tot['extras'] > 0): ?>
    <div class="pf-card">
        <div class="k">Options revenue</div>
        <div class="v"><?= $money($tot['extras']) ?></div>
        <div class="sub">not costed (add-ons)</div>
    </div>
    <?php endif; ?>
</div>

<?php if (!$byProduct): ?>
    <p class="pf-sub">No placed orders in this period.</p>
<?php else: ?>
    <table class="pf">
        <thead><tr><th>Product</th><th class="r">Blinds</th><th class="r">Revenue</th><th class="r">Cost</th><th class="r">Profit</th><th class="r">Margin</th></tr></thead>
        <tbody>
        <?php foreach ($byProduct as $prod => $r):
            $hasCost = $r['costed'] > 0;
            $pr = $hasCost ? $r['rev'] - $r['cost'] : null;   // rev here is all lines; profit only meaningful if fully costed
            // Only show profit/margin against the COSTED portion to stay honest.
            $costedRev = $r['costed'] === $r['blinds'] ? $r['rev'] : null;
        ?>
            <tr>
                <td><?= e($prod) ?><?php if ($hasCost && $r['costed'] < $r['blinds']): ?> <span class="muted">(<?= (int) $r['costed'] ?>/<?= (int) $r['blinds'] ?> costed)</span><?php endif; ?></td>
                <td class="r"><?= (int) $r['blinds'] ?></td>
                <td class="r"><?= $money($r['rev']) ?></td>
                <td class="r"><?= $hasCost ? $money($r['cost']) : '<span class="muted">—</span>' ?></td>
                <td class="r"><?= $hasCost ? '<span class="pos">' . $money($r['rev'] - $r['cost']) . '</span>' : '<span class="muted">no cost</span>' ?></td>
                <td class="r"><?= ($hasCost && $r['rev'] > 0) ? number_format(($r['rev'] - $r['cost']) / $r['rev'] * 100, 0) . '%' : '<span class="muted">—</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="pf-sub" style="margin-top:.6rem">Profit and margin are shown against costed blinds only. A part-costed product shows its ratio; import the rest of its cost grid for the full picture.</p>
<?php endif; ?>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
