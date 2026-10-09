<?php
declare(strict_types=1);

/**
 * Factory Console · Orders — ONE list of everything placed with the factory.
 *
 * Every placed order with at least one factory-owned line, whichever account
 * placed it (their portal, a direct order, or keyed in here via "+ New"), plus our
 * own trade quotes not placed yet. Read-only list; filter chips by stage, an
 * account picker and a search. Built on fc_orders() (_partials/factory_console.php).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_console.php';

requireFactoryOffice();

$user    = current_user();
$pdo     = db();
$factory = ar_factory_id();

$all  = fc_orders($pdo, $factory);
$meta = fc_stage_meta();

$stage   = (string) ($_GET['stage'] ?? 'open');
$account = (int) ($_GET['account'] ?? -1);
$q       = trim((string) ($_GET['q'] ?? ''));
if ($stage !== 'open' && $stage !== 'all' && !isset($meta[$stage])) $stage = 'open';

// Chip counts across the whole set (before account/search filters).
$counts = ['open' => 0, 'all' => count($all)] + array_fill_keys(array_keys($meta), 0);
$accounts = [];
foreach ($all as $r) {
    $counts[$r['stage']]++;
    if ($r['stage'] !== 'dispatched') $counts['open']++;
    $accounts[$r['account_key']] = $r['account'];
}
asort($accounts, SORT_NATURAL | SORT_FLAG_CASE);

$rows = array_values(array_filter($all, static function (array $r) use ($stage, $account, $q): bool {
    if ($stage === 'open' && $r['stage'] === 'dispatched') return false;
    if ($stage !== 'open' && $stage !== 'all' && $r['stage'] !== $stage) return false;
    if ($account >= 0 && (int) $r['account_key'] !== $account) return false;
    if ($q !== '') {
        $hay = mb_strtolower($r['quote_number'] . ' ' . $r['account'] . ' ' . ($r['customer_reference'] ?? '')
             . ' ' . ($r['end_customer_name'] ?? '') . ' ' . $r['invoice']);
        if (mb_strpos($hay, mb_strtolower($q)) === false) return false;
    }
    return true;
}));

$qs = static function (array $over) use ($stage, $account, $q): string {
    $p = array_merge(['stage' => $stage, 'account' => $account, 'q' => $q], $over);
    if ($p['stage'] === 'open') unset($p['stage']);
    if ((int) $p['account'] < 0) unset($p['account']);
    if ($p['q'] === '') unset($p['q']);
    return '/factory/orders.php' . ($p ? '?' . http_build_query($p) : '');
};
$money = static fn ($n) => $n === null ? '' : '£' . number_format((float) $n, 2);

$activeNav = 'factory-orders';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orders &middot; Factory Console &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
      .fc-chips { display:flex; gap:.5rem; flex-wrap:wrap; margin:0 0 .75rem; }
      .fc-chips a { display:inline-flex; gap:.35rem; align-items:center; padding:.25rem .65rem; font-size:.8125rem;
                    border-radius:999px; text-decoration:none; background:var(--bg-subtle-2); color:var(--text-muted);
                    border:1px solid transparent; }
      .fc-chips a.active { background:var(--brand); color:#fff; }
      .fc-chips a:hover { border-color:var(--border-strong); }
      .fc-chips .n { font-variant-numeric:tabular-nums; opacity:.8; }
      .fc-filters { display:flex; gap:.5rem; flex-wrap:wrap; margin:0 0 .9rem; }
      .fc-filters input, .fc-filters select { padding:.45rem .7rem; border:1px solid var(--border-strong); border-radius:8px;
                    font:inherit; background:var(--bg-input); color:var(--text-body); }
      .fc-filters input { flex:1; min-width:12rem; }
      .fc-pill { display:inline-block; padding:.0625rem .5rem; font-size:.6875rem; font-weight:700; border-radius:999px; white-space:nowrap; }
      .fc-inv { display:inline-block; margin-left:.35rem; font-size:.6875rem; font-weight:700; color:var(--text-muted);
                border:1px solid var(--border-strong); border-radius:999px; padding:0 .45rem; white-space:nowrap; }
      .fc-sub { color:var(--text-faint); font-size:.8125rem; }
      .fc-card { background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:.25rem .5rem; }
      .fc-empty { padding:1.6rem 1rem; text-align:center; color:var(--text-faint); }
      a.fc-link { font-weight:600; color:var(--text-primary); text-decoration:none; }
      a.fc-link:hover { color:var(--link); text-decoration:underline; }
      .fc-rm { margin-left:.45rem; font-size:.75rem; color:var(--link); text-decoration:none; white-space:nowrap; }
      .fc-rm:hover { text-decoration:underline; }
      @media (max-width:720px){ .fc-hide-sm { display:none; } }
    </style>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/../_partials/sidebar.php'; ?>
<main class="app-main">
  <div class="page-header">
    <div>
      <h1 class="page-title">Orders</h1>
      <p class="page-subtitle ui-hint">Every order placed with us, whoever placed it: from their portal, as a direct order,
         or keyed in here. Our own trade quotes that aren&rsquo;t placed yet show as <b>Not placed</b>.</p>
    </div>
    <a href="/master-admin/new-order.php" class="btn btn-primary">+ New order</a>
  </div>

  <div class="fc-chips" role="navigation" aria-label="Filter by stage">
    <a href="<?= e($qs(['stage' => 'open'])) ?>" class="<?= $stage === 'open' ? 'active' : '' ?>">Open <span class="n"><?= $counts['open'] ?></span></a>
    <?php foreach ($meta as $k => [$label]): ?>
      <a href="<?= e($qs(['stage' => $k])) ?>" class="<?= $stage === $k ? 'active' : '' ?>"><?= e($label) ?> <span class="n"><?= $counts[$k] ?></span></a>
    <?php endforeach; ?>
    <a href="<?= e($qs(['stage' => 'all'])) ?>" class="<?= $stage === 'all' ? 'active' : '' ?>">All <span class="n"><?= $counts['all'] ?></span></a>
  </div>

  <form method="get" class="fc-filters" action="/factory/orders.php">
    <?php if ($stage !== 'open'): ?><input type="hidden" name="stage" value="<?= e($stage) ?>"><?php endif; ?>
    <input type="search" id="fcSearch" name="q" value="<?= e($q) ?>" placeholder="Search order number, account, their reference, label name or invoice">
    <select id="fcAccount" name="account" onchange="this.form.submit()" aria-label="Account">
      <option value="-1">All accounts</option>
      <?php foreach ($accounts as $aid => $aname): ?>
        <option value="<?= (int) $aid ?>"<?= $account === (int) $aid ? ' selected' : '' ?>><?= e($aname) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn">Search</button>
  </form>

  <div class="fc-card">
    <?php if (!$rows): ?>
      <div class="fc-empty">
        <?php if (!$all): ?>
          No orders yet. When an account places an order with us it appears here straight away.
        <?php else: ?>
          Nothing matches. <a href="/factory/orders.php">Show all open orders</a>.
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr>
            <th>Order</th><th>Account</th><th class="fc-hide-sm">Their ref</th><th class="fc-hide-sm">Date</th>
            <th class="num">Blinds</th><th class="num fc-hide-sm">Value</th><th>Stage</th>
          </tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td>
                <a class="fc-link" href="<?= e($r['open_url']) ?>"><?= e((string) $r['quote_number']) ?></a>
                <?php if ($r['remake_of'] !== ''): ?><?= rm_badge($r['remake_of']) ?><?php endif; ?>
                <?php if (trim((string) ($r['end_customer_name'] ?? '')) !== ''): ?>
                  <div class="fc-sub"><?= e((string) $r['end_customer_name']) ?></div>
                <?php endif; ?>
              </td>
              <td><?= e($r['account']) ?></td>
              <td class="fc-hide-sm"><?= e((string) ($r['customer_reference'] ?? '')) ?></td>
              <td class="fc-hide-sm"><?= e(date('j M Y', strtotime((string) $r['created_at']))) ?></td>
              <td class="num"><?= (int) $r['blinds'] ?></td>
              <td class="num fc-hide-sm"><?= e($money($r['value'])) ?></td>
              <td><?= fc_stage_pill($r['stage']) ?><?php if ($r['invoice'] !== ''): ?><span class="fc-inv" title="Invoiced"><?= e($r['invoice']) ?></span><?php endif; ?>
                <?php if ($r['stage'] !== 'notplaced'): ?><a class="fc-rm" href="/factory/remake-new.php?order=<?= (int) $r['id'] ?>" title="Raise a remake on this order">&#8635; Remake</a><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
  <?php if (count($all) >= 400): ?>
    <p class="ui-hint" style="margin-top:.6rem">Showing the newest 400 orders.</p>
  <?php endif; ?>
</main>
</div>
</body>
</html>
