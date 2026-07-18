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
 * Honest by design:
 *   - Only products whose cost grid you've imported contribute a cost. A line on
 *     an un-costed product shows as revenue with no cost, kept separate so the
 *     margin can't look falsely huge.
 *   - Each order size is costed at the next grid cell up (a 1240×1680 blind costs
 *     at the 1600×2000 cell) — the same round-up your pricing uses.
 *   - Revenue is the list trade price; any per-customer discount you give isn't
 *     netted off yet.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/pricing_engine.php';

requireSuperAdmin();

$pdo    = db();
$MASTER = factory_client_id();

// Period: this month / this year / all. Default this year.
$period = (string) ($_GET['period'] ?? 'year');
$since  = null;
if ($period === 'month') $since = date('Y-m-01 00:00:00');
elseif ($period === 'year') $since = date('Y-01-01 00:00:00');

$PLACED = ['ordered', 'fitted', 'invoiced', 'paid'];
$inPl   = "'" . implode("','", $PLACED) . "'";

$dateSql = $since !== null ? 'AND q.created_at >= ?' : '';
$args    = [$MASTER];
if ($since !== null) $args[] = $since;

// Every Beverley-product line on a placed order. band + system name come from the
// tenant's own price table (its cost is stripped, but its band/system identify
// the master grid to price against).
$sql =
    "SELECT qi.width_mm, qi.drop_mm, qi.quantity, qi.base_price, qi.extras_total,
            qi.product_name_snapshot,
            COALESCE(p.source_product_id, p.id) AS master_pid,
            tpt.band_code AS band, tsys.name AS system_name
       FROM quote_items qi
       JOIN quotes q   ON q.id = qi.quote_id
       JOIN products p ON p.id = qi.product_id
       LEFT JOIN price_tables tpt   ON tpt.id = qi.price_table_id
       LEFT JOIN product_systems tsys ON tsys.id = tpt.system_id
      WHERE p.source_client_id = ?
        AND q.status IN ($inPl)
        $dateSql";
$st = $pdo->prepare($sql);
$st->execute($args);
$lines = $st->fetchAll(PDO::FETCH_ASSOC);

// --- Master price-table lookup, cached by (product, system name, band) --------
$tableCache = [];
$findMasterTable = function (int $mpid, ?string $systemName, ?string $band) use ($pdo, $MASTER, &$tableCache): ?int {
    if ($systemName === null || $band === null) return null;
    $key = $mpid . '|' . $systemName . '|' . $band;
    if (array_key_exists($key, $tableCache)) return $tableCache[$key];
    $q = $pdo->prepare(
        'SELECT pt.id FROM price_tables pt
           JOIN product_systems s ON s.id = pt.system_id
          WHERE pt.client_id = ? AND pt.product_id = ? AND s.name = ? AND pt.band_code = ?
          LIMIT 1'
    );
    $q->execute([$MASTER, $mpid, $systemName, $band]);
    $id = $q->fetchColumn();
    return $tableCache[$key] = ($id === false ? null : (int) $id);
};

// Cost for a size on a master table — round up to the next grid cell, like pricing.
$costCell = $pdo->prepare(
    'SELECT cost FROM price_table_rows
      WHERE price_table_id = ? AND width_mm >= ? AND drop_mm >= ? AND cost IS NOT NULL
      ORDER BY width_mm ASC, drop_mm ASC LIMIT 1'
);

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
    $tid = $findMasterTable((int) $ln['master_pid'], $ln['system_name'], $ln['band']);
    if ($tid !== null) {
        $costCell->execute([$tid, (int) $ln['width_mm'], (int) $ln['drop_mm']]);
        $c = $costCell->fetchColumn();
        if ($c !== false && $c !== null) $cost = (float) $c;
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
  .pf-periods { display:flex; gap:.4rem; margin:0 0 1.2rem; }
  .pf-periods a { text-decoration:none; font-size:.9rem; font-weight:600; padding:.35rem .8rem; border-radius:8px; background:var(--bg-subtle,#f1f5f9); color:#334155; }
  .pf-periods a.on { background:#1f2a37; color:#fff; }
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

<?php if ($tot['rev_uncosted'] > 0): ?>
    <div class="pf-warn"><strong><?= $money($tot['rev_uncosted']) ?></strong> of orders are on products with no cost grid imported yet (<?= e(implode(', ', array_keys($uncostedProducts))) ?>) — their profit isn't counted. Import those costs to include them.</div>
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
