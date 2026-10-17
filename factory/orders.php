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
require_once __DIR__ . '/../_partials/process_order.php';   // the shared Process order button + summary

requireFactoryOffice();

$user    = current_user();
$pdo     = db();
$factory = ar_factory_id();

$all    = fc_orders($pdo, $factory);
$groups = fc_filter_groups();
$keys   = array_merge(...array_values(array_map('array_keys', $groups)));

// ?stage= keeps its old name so existing links (dashboard tiles, the Quotes menu
// item) still land; 'dispatched' is still understood though it has no chip now.
$stage   = (string) ($_GET['stage'] ?? 'open');
$account = (int) ($_GET['account'] ?? -1);
$q       = trim((string) ($_GET['q'] ?? ''));
if (!in_array($stage, $keys, true) && $stage !== 'dispatched') $stage = 'open';

// Chip counts across the whole set (before account/search filters).
$counts = array_fill_keys($keys, 0);
$accounts = [];
foreach ($all as $r) {
    foreach ($keys as $k) if (fc_filter_match($r, $k)) $counts[$k]++;
    $accounts[$r['account_key']] = $r['account'];
}
asort($accounts, SORT_NATURAL | SORT_FLAG_CASE);

$rows = array_values(array_filter($all, static function (array $r) use ($stage, $account, $q): bool {
    if (!fc_filter_match($r, $stage)) return false;
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
// Invoice numbers link to the PDF only for someone allowed on the accounts pages (#1057).
$canAccounts = fc_money_visible();

// Highlight Quotes in the sidebar when that is the stage being shown, so the
// new tab lights up rather than Orders staying lit for both.
$activeNav = $stage === 'notplaced' ? 'factory-quotes' : 'factory-orders';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $stage === 'notplaced' ? 'Quotes' : 'Orders' ?> &middot; Factory Console &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
      .fc-chips { display:flex; gap:.5rem; flex-wrap:wrap; margin:0 0 .75rem; }
      .fc-chips a { display:inline-flex; gap:.35rem; align-items:center; padding:.25rem .65rem; font-size:.8125rem;
                    border-radius:999px; text-decoration:none; background:var(--bg-subtle-2); color:var(--text-muted);
                    border:1px solid transparent; }
      .fc-chips a.active { background:var(--brand); color:#fff; }
      .fc-chips a:hover { border-color:var(--border-strong); }
      .fc-chips .n { font-variant-numeric:tabular-nums; opacity:.8; }
      .fc-chips { gap:.5rem 1.1rem; }
      .fc-group { display:inline-flex; gap:.35rem; align-items:center; flex-wrap:wrap; }
      .fc-gl { font-size:.6875rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--text-faint); margin-right:.1rem; }
      .fc-chips a.warn:not(.active) { color:#b91c1c; background:#fee2e2; }
      .fc-next { font-size:.8125rem; font-weight:600; color:var(--text-muted); white-space:nowrap; text-decoration:none; }
      a.fc-next { color:var(--link); }
      a.fc-next:hover { text-decoration:underline; }
      .fc-next.urgent { color:#b91c1c; }
      .fc-next.done { color:#0d7a67; }
      td .po-form { display:inline; margin:0; }
      button.fc-po { font:inherit; font-size:.8125rem; font-weight:600; border:0; border-radius:6px; padding:.3rem .65rem; background:#1f3b5b; color:#fff; cursor:pointer; white-space:nowrap; }
      button.fc-po:hover { filter:brightness(1.1); }
      .fc-sub a { color:inherit; }
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
      a.fc-link { font-weight:600; color:var(--text-primary); text-decoration:none; white-space:nowrap; }
      a.fc-link:hover { color:var(--link); text-decoration:underline; }
      .fc-rm { margin-left:.45rem; font-size:.75rem; color:var(--link); text-decoration:none; white-space:nowrap; }
      .fc-rm:hover { text-decoration:underline; }
      @media (max-width:1100px){ .fc-hide-md { display:none; } }
      @media (max-width:720px){ .fc-hide-sm { display:none; } }
    </style>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/../_partials/sidebar.php'; ?>
<main class="app-main">
  <?php
  // Reads the flash. Stage 5 (#922) pointed orders/index.php at this page with
  // factory_console_redirect(), so a Console user never lands on the screen that
  // used to show it: quote-builder/delete.php sets flash_success and redirects
  // to /orders/index.php, which bounces here — and this page had no flash
  // handling at all. The confirmation sat in the session and then appeared on
  // whatever console page they opened next, so deleting a quote looked like it
  // had done nothing and then "Quote BEV-2026-0031 deleted." turned up ten
  // minutes later on Remakes. Same two lines as factory/calendar.php:83.
  $flashOk  = (string) ($_SESSION['flash_success'] ?? ''); unset($_SESSION['flash_success']);
  $flashErr = (string) ($_SESSION['flash_error']   ?? ''); unset($_SESSION['flash_error']);
  ?>
  <?php if ($flashOk  !== ''): ?><div class="alert alert-success" role="status"><?= e($flashOk) ?></div><?php endif; ?>
  <?php if ($flashErr !== ''): ?><div class="alert alert-error"   role="alert"><?= e($flashErr) ?></div><?php endif; ?>
  <div class="page-header">
    <div>
      <?php
      // The Quotes sidebar tab lands on this same page filtered to the quotes
      // stage, so the heading follows the filter — otherwise clicking Quotes
      // gives you a page headed "Orders".
      $onQuotes = $stage === 'notplaced';
      ?>
      <h1 class="page-title"><?= $onQuotes ? 'Quotes' : 'Orders' ?></h1>
      <?php if ($onQuotes): ?>
      <p class="page-subtitle ui-hint">Our own quotes &mdash; trade and retail &mdash; that haven&rsquo;t been placed as
         orders yet. Open one to add blinds or place it.</p>
      <?php else: ?>
      <p class="page-subtitle ui-hint">Every order placed with us, whoever placed it: from their portal, as a direct order,
         or keyed in here. <b>Making</b> is where the blinds are<?php if ($canAccounts): ?>, <b>Money</b> is where the bill is, and <b>Open</b>
         is anything not yet both dispatched and paid<?php else: ?>; <b>Open</b> is anything not dispatched yet<?php endif; ?>.</p>
      <?php endif; ?>
    </div>
    <a href="/master-admin/new-order.php" class="btn btn-primary">+ New order</a>
  </div>

  <div class="fc-chips" role="navigation" aria-label="Filter orders">
    <?php foreach ($groups as $gname => $chips): ?>
      <span class="fc-group">
        <?php if (trim($gname) !== ''): ?><span class="fc-gl"><?= e($gname) ?></span><?php endif; ?>
        <?php foreach ($chips as $k => $label): ?>
          <a href="<?= e($qs(['stage' => $k])) ?>" class="<?= $stage === $k ? 'active' : '' ?><?= in_array($k, ['needs_invoicing', 'overdue'], true) && $counts[$k] > 0 ? ' warn' : '' ?>"><?= e($label) ?> <span class="n"><?= $counts[$k] ?></span></a>
        <?php endforeach; ?>
      </span>
    <?php endforeach; ?>
    <?php if ($stage === 'dispatched'): ?>
      <a href="<?= e($qs(['stage' => 'dispatched'])) ?>" class="active">Dispatched</a>
    <?php endif; ?>
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
            <th>Order</th><th>Account</th><th class="fc-hide-md">Their ref</th><th class="fc-hide-md">Date</th>
            <th class="num">Blinds</th><th class="num fc-hide-sm">Value</th><th>Making</th><?php if ($canAccounts): ?><th>Money</th><?php endif; ?><th>Next</th>
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
              <td class="fc-hide-md"><?= e((string) ($r['customer_reference'] ?? '')) ?></td>
              <td class="fc-hide-md" style="white-space:nowrap"><?= e(date('j M Y', strtotime((string) $r['created_at']))) ?></td>
              <td class="num"><?= (int) $r['blinds'] ?></td>
              <td class="num fc-hide-sm"><?= e($money($r['value'])) ?></td>
              <td><?= fc_stage_pill($r['stage']) ?></td>
              <?php if ($canAccounts): ?><td><?= fc_money_pill($r['money']) ?>
                <?php foreach ($r['invoices'] as $iv): ?>
                  <div class="fc-sub"><?php if ($canAccounts): ?><a href="/master-admin/invoice-pdf.php?id=<?= (int) $iv['id'] ?>" target="_blank" rel="noopener"><?= e($iv['number']) ?></a><?php else: ?><?= e($iv['number']) ?><?php endif; ?><?php if ($iv['outstanding'] > 0.004): ?> &middot; <?= e($money($iv['outstanding'])) ?> owed<?php if ($iv['due'] !== ''): ?>, due <?= e(date('j M', strtotime($iv['due']))) ?><?php endif; ?><?php endif; ?></div>
                <?php endforeach; ?></td><?php endif; ?>
              <td>
                <?php [$nLabel, $nUrl, $nUrgent] = $r['next']; ?>
                <?php if ($r['stage'] === 'new' && $nLabel === 'Process order'): ?>
                  <?php /* Pressable here (John, 2026-10-17: "it takes you to the Pipeline
                     where you have to re-click it") — the shared button, same as the Pipeline. */ ?>
                  <?= po_button((int) $r['id'], (string) $r['quote_number'],
                        $r['tracks'] ?? ['make' => ['units' => 0, 'made' => 0, 'released' => false], 'buy' => []],
                        'orders', 'fc-next fc-po', 'Process order', null, 'Take the order in, put its blinds on the floor and email its suppliers') ?>
                <?php elseif ($nUrl !== ''): ?><a class="fc-next<?= $nUrgent ? ' urgent' : '' ?>" href="<?= e($nUrl) ?>"><?= e($nLabel) ?></a>
                <?php else: ?><span class="fc-next<?= $nUrgent ? ' urgent' : '' ?><?= $nLabel === 'Done' ? ' done' : '' ?>"><?= e($nLabel) ?></span><?php endif; ?>
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
<?= po_modal(po_skip_summary($pdo, current_factory_id())) ?>
</div>
<?php require_once __DIR__ . '/../_partials/factory_poll_banner.php'; fx_poll_banner(db(), 'incoming', current_factory_id()); ?>
</body>
</html>
