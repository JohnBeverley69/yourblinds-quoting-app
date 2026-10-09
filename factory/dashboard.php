<?php
declare(strict_types=1);

/**
 * Factory Console · Dashboard — where factory office staff land after login.
 *
 * Orders by stage (from the same set as Factory Console · Orders, so ABC's portal
 * orders count), what went out today, what's been invoiced this week, what's owed,
 * the busiest accounts and the latest orders. Read-only.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_console.php';

requireFactoryOffice();

$user    = current_user();
$pdo     = db();
$factory = ar_factory_id();

$orders = fc_orders($pdo, $factory);
$meta   = fc_stage_meta();

$byStage = array_fill_keys(array_keys($meta), 0);
$blindsIn = 0;
$weekStart = date('Y-m-d', strtotime('monday this week'));
$monthAgo  = date('Y-m-d', strtotime('-30 days'));
$inThisWeek = 0;
$busiest = [];
foreach ($orders as $o) {
    $byStage[$o['stage']]++;
    if (in_array($o['stage'], ['new', 'confirmed', 'in_production'], true)) $blindsIn += (int) $o['blinds'];
    $day = substr((string) $o['created_at'], 0, 10);
    if ($o['stage'] !== 'notplaced') {
        if ($day >= $weekStart) $inThisWeek++;
        if ($day >= $monthAgo) {
            $k = $o['account'];
            $busiest[$k] ??= ['orders' => 0, 'blinds' => 0, 'value' => 0.0];
            $busiest[$k]['orders']++;
            $busiest[$k]['blinds'] += (int) $o['blinds'];
            $busiest[$k]['value']  += (float) ($o['value'] ?? 0);
        }
    }
}
uasort($busiest, static fn ($a, $b) => $b['value'] <=> $a['value'] ?: $b['orders'] <=> $a['orders']);
$busiest = array_slice($busiest, 0, 5, true);
$recent  = array_slice(array_values(array_filter($orders, static fn ($o) => $o['stage'] !== 'notplaced')), 0, 8);

// Delivery notes out today.
$outToday = null;
if (ar_table_ready($pdo, 'factory_ar_delivery_notes')) {
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM factory_ar_delivery_notes
                              WHERE factory_client_id = ? AND status = 'dispatched' AND DATE(dispatched_at) = CURDATE()");
        $st->execute([$factory]);
        $outToday = (int) $st->fetchColumn();
    } catch (Throwable $e) { $outToday = null; }
}

// Invoiced this week (net) and owed now.
$invWeek = null; $owed = null;
if (ar_table_ready($pdo, 'factory_ar_invoices')) {
    try {
        $st = $pdo->prepare("SELECT COALESCE(SUM(subtotal), 0) FROM factory_ar_invoices
                              WHERE factory_client_id = ? AND status NOT IN ('draft','void') AND issue_date >= ?");
        $st->execute([$factory, $weekStart]);
        $invWeek = (float) $st->fetchColumn();
        $st = $pdo->prepare("SELECT COALESCE(SUM(total - amount_paid), 0) FROM factory_ar_invoices
                              WHERE factory_client_id = ? AND status IN ('raised','sent','part_paid')");
        $st->execute([$factory]);
        $owed = (float) $st->fetchColumn();
    } catch (Throwable $e) { $invWeek = $owed = null; }
}

require_once __DIR__ . '/../_partials/remakes.php';
$rmWaiting = rm_waiting_count($pdo, $factory);
$rmOpen    = rm_ready($pdo) ? count(rm_list($pdo, $factory, 'open')) : 0;

$money = static fn ($n) => $n === null ? '—' : '£' . number_format((float) $n, 2);
$tiles = [
    'new'           => ['New',              'Not received on the floor yet',     '/factory/incoming-orders.php'],
    'confirmed'     => ['Received',         'In, not started',                    '/factory/orders.php?stage=confirmed'],
    'in_production' => ['In production',    'Being made',                         '/factory/orders.php?stage=in_production'],
    'ready'         => ['Ready to dispatch','Everything made and in',             '/master-admin/dispatch.php'],
];
$firstName = trim(explode(' ', (string) ($user['full_name'] ?? ''))[0] ?? '');

$activeNav = 'factory-dashboard';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard &middot; Factory Console &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
      .fd-grid { display:grid; gap:1rem; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); margin-bottom:1.25rem; }
      .fd-tile { display:block; background:var(--bg-card); border:1px solid var(--border); border-radius:12px;
                 padding:1rem 1.1rem; text-decoration:none; color:inherit; border-top:4px solid var(--fd-c, var(--border)); }
      a.fd-tile:hover { border-color:var(--border-strong); border-top-color:var(--fd-c, var(--border-strong)); }
      .fd-label { font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-faint); font-weight:600; }
      .fd-value { font-size:1.9rem; font-weight:800; color:var(--text-primary); line-height:1.15; margin-top:.25rem;
                  font-variant-numeric:tabular-nums; }
      .fd-sub { color:var(--text-faint); font-size:.8125rem; margin-top:.2rem; }
      .fd-cols { display:grid; gap:1.25rem; grid-template-columns:minmax(0, 3fr) minmax(0, 2fr); }
      .fd-panel { background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:1rem 1.15rem; min-width:0; }
      .fd-panel h2 { font-size:1rem; margin:0 0 .6rem; color:var(--text-primary); display:flex; justify-content:space-between; gap:.5rem; }
      .fd-panel h2 a { font-size:.8125rem; font-weight:600; color:var(--link); text-decoration:none; }
      .fd-empty { color:var(--text-faint); font-size:.9rem; padding:.4rem 0; }
      .fc-pill { display:inline-block; padding:.0625rem .5rem; font-size:.6875rem; font-weight:700; border-radius:999px; white-space:nowrap; }
      .fd-notplaced { margin:-.4rem 0 1.25rem; font-size:.9rem; color:var(--text-muted); }
      a.fc-link { font-weight:600; color:var(--text-primary); text-decoration:none; }
      a.fc-link:hover { color:var(--link); text-decoration:underline; }
      @media (max-width:900px){ .fd-cols { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/../_partials/sidebar.php'; ?>
<main class="app-main">
  <div class="page-header">
    <div>
      <h1 class="page-title"><?php $h = (int) date('G'); $greet = $h < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening'); ?><?= e($greet . ($firstName !== '' ? ', ' . $firstName : '')) ?></h1>
      <p class="page-subtitle"><?= e(date('l j F Y')) ?></p>
    </div>
    <a href="/factory/orders.php" class="btn">All orders</a>
  </div>

  <div class="fd-grid">
    <?php foreach ($tiles as $k => [$label, $sub, $href]): ?>
      <a class="fd-tile" href="<?= e($href) ?>" style="--fd-c:<?= e($meta[$k][1]) ?>">
        <div class="fd-label"><?= e($label) ?></div>
        <div class="fd-value"><?= (int) $byStage[$k] ?></div>
        <div class="fd-sub"><?= e($sub) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if ($byStage['notplaced'] > 0): ?>
    <p class="fd-notplaced"><a href="/factory/orders.php?stage=notplaced"><?= (int) $byStage['notplaced'] ?> of our own trade quote<?= $byStage['notplaced'] === 1 ? '' : 's' ?></a> not placed yet.</p>
  <?php endif; ?>

  <div class="fd-grid">
    <a class="fd-tile" href="/factory/remakes.php" style="--fd-c:#7c3aed"><div class="fd-label">Remakes</div><div class="fd-value"><?= $rmOpen ?></div><div class="fd-sub"><?= $rmWaiting > 0 ? '<b style="color:#7c3aed">' . $rmWaiting . ' waiting for approval</b>' : 'In progress · none waiting' ?></div></a>
    <div class="fd-tile"><div class="fd-label">Blinds to make</div><div class="fd-value"><?= $blindsIn ?></div><div class="fd-sub">On orders not yet ready</div></div>
    <a class="fd-tile" href="/master-admin/dispatch.php"><div class="fd-label">Out today</div><div class="fd-value"><?= $outToday === null ? '—' : $outToday ?></div><div class="fd-sub">Delivery notes dispatched</div></a>
    <div class="fd-tile"><div class="fd-label">Orders this week</div><div class="fd-value"><?= $inThisWeek ?></div><div class="fd-sub">Placed since Monday</div></div>
    <a class="fd-tile" href="/master-admin/wholesale.php"><div class="fd-label">Invoiced this week</div><div class="fd-value" style="font-size:1.5rem"><?= e($money($invWeek)) ?></div><div class="fd-sub">Net, since Monday &middot; owed <?= e($money($owed)) ?></div></a>
  </div>

  <div class="fd-cols">
    <section class="fd-panel">
      <h2>Latest orders <a href="/factory/orders.php?stage=all">See all</a></h2>
      <?php if (!$recent): ?>
        <p class="fd-empty">No orders yet. When an account places an order with us it shows here.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Order</th><th>Account</th><th class="num">Blinds</th><th>Stage</th></tr></thead>
          <tbody>
          <?php foreach ($recent as $o): ?>
            <tr>
              <td><a class="fc-link" href="<?= e($o['open_url']) ?>"><?= e((string) $o['quote_number']) ?></a></td>
              <td><?= e($o['account']) ?></td>
              <td class="num"><?= (int) $o['blinds'] ?></td>
              <td><?= fc_stage_pill($o['stage']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </section>
    <section class="fd-panel">
      <h2>Busiest accounts <span class="fd-sub" style="margin:0">last 30 days</span></h2>
      <?php if (!$busiest): ?>
        <p class="fd-empty">No orders in the last 30 days.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Account</th><th class="num">Orders</th><th class="num">Value</th></tr></thead>
          <tbody>
          <?php foreach ($busiest as $name => $b): ?>
            <tr><td><?= e((string) $name) ?></td><td class="num"><?= (int) $b['orders'] ?></td><td class="num"><?= e($money($b['value'])) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </section>
  </div>
</main>
</div>
</body>
</html>
