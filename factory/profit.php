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
require_once __DIR__ . '/../_partials/factory_costing.php';

requireSuperAdmin();

$pdo    = db();
$MASTER = current_factory_id();

// Period: today (the default) / this month / this year / a From–To range you type
// yourself. No "all time" (John: going back 30-odd years it's a meaningless figure).
$period  = (string) ($_GET['period'] ?? 'today');
$fromIn  = trim((string) ($_GET['from'] ?? ''));
$toIn    = trim((string) ($_GET['to']   ?? ''));
$since   = null;
$until   = null;
$rangeNote = null;

$realDate = static function (string $s): bool {
    return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)
        && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
};
// A From–To with no FROM would be "all time" by the back door. This used to
// test for both bounds being blank, so ?period=custom&to=... with no from left
// $since null (it is only assigned when $fromIn !== ''), $dateSql carried no
// lower bound, and the query walked every placed factory-owned line ever
// written — presented as the chosen range. Both inputs are optional, so
// pressing Show with only a To date is an ordinary thing to do.
if ($period === 'custom' && $fromIn === '') $period = 'today';

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
} elseif ($period === 'year') {
    $since = date('Y-01-01 00:00:00');
} else {
    $period = 'today';
    $since  = date('Y-m-d 00:00:00');
    $until  = date('Y-m-d 23:59:59');
}

// ?why=1 collects, for every line we could NOT cost, which link in the chain is
// missing. Read-only, super-admin like the rest of the page, off unless asked.
$why     = isset($_GET['why']);
$whyRows = [];

// The costing itself is shared with the Factory Console Dashboard
// (_partials/factory_costing.php) so both pages cost every line the same way.
$tot = [
    'lines' => 0, 'blinds' => 0,
    'rev_costed' => 0.0, 'cost' => 0.0,      // matched to a cost
    'rev_uncosted' => 0.0,                    // Beverley lines with no cost yet
    'extras' => 0.0,                          // options revenue — not costed
];
$byProduct = [];     // product => [rev, cost, blinds, costed_blinds]
$uncostedProducts = [];

foreach (fcost_lines($pdo, $MASTER, $since, $until, $why) as $ln) {
    $qty  = $ln['qty'];
    $rev  = $ln['rev'];
    $prod = $ln['product'];
    $tot['lines']++; $tot['blinds'] += $qty;
    $tot['extras'] += $ln['extras'];

    $p = &$byProduct[$prod];
    if ($p === null) $p = ['rev' => 0.0, 'rev_costed' => 0.0, 'cost' => 0.0, 'blinds' => 0, 'costed' => 0];
    $p['rev'] += $rev; $p['blinds'] += $qty;

    if ($ln['cost'] !== null) {
        $tot['rev_costed'] += $rev; $tot['cost'] += $ln['cost'];
        $p['cost'] += $ln['cost']; $p['costed'] += $qty; $p['rev_costed'] += $rev;
    } else {
        $tot['rev_uncosted'] += $rev;
        $uncostedProducts[$prod] = true;
        // ?why=1 — for when a line you expected to be costed isn't, and you need
        // to know WHICH link in the chain is missing rather than guess at it.
        if ($ln['why'] !== null) $whyRows[] = $ln['why'];
    }
    unset($p);
}

$profit    = $tot['rev_costed'] - $tot['cost'];
$marginPct = $tot['rev_costed'] > 0 ? $profit / $tot['rev_costed'] * 100 : null;
uasort($byProduct, static fn ($a, $b) => $b['rev'] <=> $a['rev']);   // biggest earners first

$money = static fn ($v) => '£' . number_format((float) $v, 2);
// Lives in the Factory Console (Accounts → Profit), not the factory's top bar
// (John 2026-10-09) — it's a money page, next to Invoices and Statements.
$activeNav = 'factory-dashboard';   // reached from the Dashboard; its own menu item is gone
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>The Numbers &middot; Factory Console &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/../_partials/sidebar.php'; ?>
<main class="app-main">
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

<h1 class="pf-h">The Numbers</h1>
<p class="pf-sub">What you charge for the blinds you make, minus what they cost you to make &mdash; across every trade customer's orders. Your eyes only.</p>

<div class="pf-periods">
    <?php foreach (['today' => 'Today', 'month' => 'This month', 'year' => 'This year'] as $k => $lbl): ?>
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
    <div class="pf-warn"><strong><?= $money($tot['rev_uncosted']) ?></strong> of orders are on products we can't cost yet (<?= e(implode(', ', array_keys($uncostedProducts))) ?>) — their profit isn't counted, rather than guessed at. Usually one of two things: a product we make whose cost grid hasn't been imported, or an order raised against a system or band that has since been rebuilt on the master, so there's no telling which grid it priced against. <a href="?<?= e(http_build_query(array_merge($_GET, ['why' => 1]))) ?>">Show me which</a>.</div>
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
    <div class="table-wrap">
    <table class="pf">
        <thead><tr><th>Product</th><th class="r">Blinds</th><th class="r">Revenue</th><th class="r">Cost</th><th class="r">Profit</th><th class="r">Margin</th></tr></thead>
        <tbody>
        <?php foreach ($byProduct as $prod => $r):
            $hasCost = $r['costed'] > 0;
            $part    = $hasCost && $r['costed'] < $r['blinds'];
            // Profit and margin come off the COSTED revenue only. Taking the
            // whole product's revenue and only the costed blinds' cost is how
            // a part-costed row used to claim a 41% margin on blinds that were
            // really running at 29%.
            $profitP = $hasCost ? $r['rev_costed'] - $r['cost'] : null;
        ?>
            <tr>
                <td><?= e($prod) ?><?php if ($part): ?> <span class="muted">(<?= (int) $r['costed'] ?>/<?= (int) $r['blinds'] ?> costed)</span><?php endif; ?></td>
                <td class="r"><?= (int) $r['blinds'] ?></td>
                <td class="r"><?= $money($r['rev']) ?><?php if ($part): ?><br><span class="muted" style="font-size:.78rem"><?= $money($r['rev_costed']) ?> costed</span><?php endif; ?></td>
                <td class="r"><?= $hasCost ? $money($r['cost']) : '<span class="muted">—</span>' ?></td>
                <td class="r"><?= $hasCost ? '<span class="pos">' . $money($profitP) . '</span>' : '<span class="muted">no cost</span>' ?></td>
                <td class="r"><?= ($hasCost && $r['rev_costed'] > 0) ? number_format($profitP / $r['rev_costed'] * 100, 0) . '%' : '<span class="muted">—</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="pf-sub" style="margin-top:.6rem">Profit and margin are shown against costed blinds only. A part-costed product shows its ratio; import the rest of its cost grid for the full picture.</p>
<?php endif; ?>

<?php if ($why): ?>
    <h2 class="pf-h" style="font-size:1.05rem;margin:1.6rem 0 .2rem">Why these lines aren't costed</h2>
    <p class="pf-sub">Add <code>?why=1</code> to this page's address to see it. <b>src table</b> is the identity link from the order's price table back to your master grid — blank there is usually the whole story.</p>
    <?php if (!$whyRows): ?>
        <p class="pf-sub">Every line in this window is costed.</p>
    <?php else: ?>
        <div class="table-wrap">
        <table class="pf">
            <thead><tr><th>Product</th><th>Size</th><th class="r">Master id</th><th class="r">Src table</th><th class="r">Line table</th><th class="r">Owner</th><th class="r">Cell</th><th class="r">Sys id</th><th class="r">→ master</th><th>System</th><th>Band</th><th class="r">Matched</th><th>Reason</th></tr></thead>
            <tbody>
            <?php foreach ($whyRows as $w): ?>
                <tr>
                    <td><?= e((string) $w['product']) ?></td>
                    <td><?= e($w['size']) ?></td>
                    <td class="r"><?= (int) $w['master_pid'] ?></td>
                    <td class="r"><?= $w['src_table']  !== null ? (int) $w['src_table']  : '<span class="muted">—</span>' ?></td>
                    <td class="r"><?= $w['line_table'] !== null ? (int) $w['line_table'] : '<span class="muted">—</span>' ?></td>
                    <td class="r"><?= $w['table_owner'] !== null ? (int) $w['table_owner'] : '<span class="muted">—</span>' ?></td>
                    <td class="r"><?= $w['line_row'] !== null ? (int) $w['line_row'] : '<span class="muted">—</span>' ?></td>
                    <td class="r"><?= $w['line_sys'] !== null ? (int) $w['line_sys'] : '<span class="muted">—</span>' ?></td>
                    <td class="r"><?= $w['master_sys'] !== null ? (int) $w['master_sys'] : '<span class="muted">—</span>' ?></td>
                    <td><?= $w['system'] !== null ? e((string) $w['system']) : '<span class="muted">—</span>' ?></td>
                    <td><?= $w['band']   !== null ? e((string) $w['band'])   : '<span class="muted">—</span>' ?></td>
                    <td class="r"><?= $w['master_tbl'] !== null ? (int) $w['master_tbl'] : '<span class="muted">—</span>' ?></td>
                    <td><?= e($w['reason']) ?><?= $w['source'] !== null ? ' <span class="muted">(' . e(ps_label((string) $w['source'])) . ')</span>' : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

</main>
</div>
</body>
</html>
