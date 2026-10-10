<?php
declare(strict_types=1);

/**
 * Factory Console · Remakes.
 *
 *   Waiting for approval — remakes accounts have reported from their side. Any
 *                          office user approves (deciding who pays + a due date)
 *                          or declines with a short reason the account sees.
 *   In progress          — approved remakes not dispatched yet.
 *   Done                 — dispatched or declined.
 *   Report               — a month at a time: how many, what they cost us (trade
 *                          price of the blinds), what was charged back, supplier
 *                          claims; by reason and by account.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/remakes.php';
require_once __DIR__ . '/../_partials/remake_form.php';

requireFactoryOffice();

$user    = current_user();
$pdo     = db();
$factory = ar_factory_id();
$ready   = rm_ready($pdo);

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['remake_id'] ?? 0);
    try {
        if (($_POST['_action'] ?? '') === 'approve') {
            rm_approve($pdo, $factory, $id, (string) ($_POST['charge_mode'] ?? ''), (float) ($_POST['charge_amount'] ?? 0),
                       (string) ($_POST['supplier_name'] ?? ''), (string) ($_POST['due_date'] ?? ''), (int) ($user['user_id'] ?? 0));
            $r = rm_get($pdo, $factory, $id);
            $sentTo = rm_notify_account($pdo, $factory, $id);
            $_SESSION['flash_success'] = 'Approved — remake ' . ($r['remake_number'] ?? '') . ' is in Incoming orders as a new order.'
                . ($sentTo !== '' ? ' The account has been emailed (' . $sentTo . ').' : '');
            header('Location: /factory/remakes.php');
            exit;
        }
        if (($_POST['_action'] ?? '') === 'decline') {
            rm_decline($pdo, $factory, $id, (string) ($_POST['decline_reason'] ?? ''), (int) ($user['user_id'] ?? 0));
            $sentTo = rm_notify_account($pdo, $factory, $id);
            $_SESSION['flash_success'] = 'Declined — the account sees your reason on their order'
                . ($sentTo !== '' ? ' and has been emailed (' . $sentTo . ').' : '.');
            header('Location: /factory/remakes.php');
            exit;
        }
    } catch (RuntimeException $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    } catch (Throwable $e) {
        error_log('remakes: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Something went wrong — nothing was changed. Please try again.';
    }
    header('Location: /factory/remakes.php#rm' . $id);
    exit;
}

// Default = Overview: everything at once (waiting, in progress, recently done),
// so the page never opens on an empty tab while remakes sit on another one.
$view = (string) ($_GET['view'] ?? 'overview');
if (!in_array($view, ['overview', 'waiting', 'open', 'done', 'report'], true)) $view = 'overview';

$lists = ['waiting' => [], 'open' => [], 'done' => []];
$items = [];
if ($ready) {
    foreach (array_keys($lists) as $k) $lists[$k] = rm_list($pdo, $factory, $k);
    $items = rm_items_for($pdo, $factory, array_merge(...array_values($lists)));
}
$waitingCount = count($lists['waiting']);
// Overview shows "done" for the last 30 days only (the Done tab has them all).
// Measured from when the remake FINISHED — rm_list()'s finished_at: the remake
// order's dispatch date, or the decision date for a decline. It used to read
// decided_at for both, i.e. the APPROVAL date, so a remake approved in August
// and dispatched yesterday was missing from "In progress" (it's dispatched) AND
// from "Done in the last 30 days" (approved too long ago) — it showed nowhere
// on the Overview at all.
$recentFrom = date('Y-m-d', strtotime('-30 days'));
$recentDone = array_values(array_filter(
    $lists['done'],
    static fn ($r) => substr((string) ($r['finished_at'] ?: ($r['decided_at'] ?: $r['created_at'])), 0, 10) >= $recentFrom
));

// This month at a glance (approved remakes raised this month).
$monthNow = ['n' => 0, 'blinds' => 0, 'cost' => 0.0];
foreach (array_merge($lists['open'], $lists['done']) as $r) {
    if ($r['status'] !== 'approved' || substr((string) $r['created_at'], 0, 7) !== date('Y-m')) continue;
    $monthNow['n']++; $monthNow['blinds'] += (int) $r['blinds'];
    $monthNow['cost'] += (float) $r['cost'] - (float) $r['charge_amount'] - ($r['charge_mode'] === 'supplier' ? (float) $r['cost'] : 0.0);
}

// Report — one month.
// Shape-checked AND real: '2026-13', '2026-00' and '9999-99' all satisfy the
// pattern, but strtotime('2026-13-01') is false and date('Y-m-t', false) is a
// TypeError under strict_types — a hand-edited URL or a stale bookmark turned
// the Report tab into a blank 500. checkdate() settles it before it's used.
$month = (string) ($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^(\d{4})-(\d{2})$/', $month, $mM)
    || !checkdate((int) $mM[2], 1, (int) $mM[1])) {
    $month = date('Y-m');
}
$rep = null;
if ($ready && $view === 'report') {
    $from = $month . '-01';
    $to   = date('Y-m-t', strtotime($from));
    $list = array_values(array_filter(rm_list($pdo, $factory, 'all', $from, $to), static fn ($r) => $r['status'] === 'approved'));
    $rep  = ['count' => count($list), 'blinds' => 0, 'cost' => 0.0, 'charged' => 0.0, 'claims' => 0.0,
             'reason' => [], 'account' => [], 'supplier' => [], 'list' => $list];
    foreach ($list as $r) {
        $c = (float) $r['cost']; $ch = (float) $r['charge_amount'];
        $rep['blinds'] += (int) $r['blinds']; $rep['cost'] += $c; $rep['charged'] += $ch;
        if ($r['charge_mode'] === 'supplier') {
            $rep['claims'] += $c;
            $k = (string) ($r['supplier_name'] ?: 'Supplier');
            $rep['supplier'][$k] ??= ['n' => 0, 'cost' => 0.0];
            $rep['supplier'][$k]['n']++; $rep['supplier'][$k]['cost'] += $c;
        }
        foreach (['reason' => (string) $r['reason_label'], 'account' => (string) ($r['account_name'] ?? '')] as $dim => $k) {
            $rep[$dim][$k] ??= ['n' => 0, 'blinds' => 0, 'cost' => 0.0, 'charged' => 0.0];
            $rep[$dim][$k]['n']++; $rep[$dim][$k]['blinds'] += (int) $r['blinds'];
            $rep[$dim][$k]['cost'] += $c; $rep[$dim][$k]['charged'] += $ch;
        }
    }
    foreach (['reason', 'account', 'supplier'] as $dim) uasort($rep[$dim], static fn ($a, $b) => $b['cost'] <=> $a['cost']);
    $rep['area'] = rm_area_breakdown($pdo, $factory, $list);
}

$money = static fn ($n) => '£' . number_format((float) $n, 2);
$modes = rm_charge_modes();
$activeNav = 'remakes';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Remakes &middot; Factory Console &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
      .rm-tabs { display:flex; gap:.5rem; flex-wrap:wrap; margin:0 0 1rem; }
      .rm-tabs a { padding:.3rem .75rem; font-size:.875rem; border-radius:999px; text-decoration:none;
                   background:var(--bg-subtle-2); color:var(--text-muted); border:1px solid transparent; }
      .rm-tabs a.active { background:var(--brand); color:#fff; }
      .rm-tabs .n { font-weight:700; opacity:.75; }
      .rm-tabs .n.hot, .rm-h .n.hot { background:#7c3aed; color:#fff; opacity:1; }
      .rm-h { font-size:1.05rem; margin:1.4rem 0 .6rem; color:var(--text-primary); display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
      .rm-h .n { font-size:.75rem; font-weight:700; border-radius:999px; padding:0 .5rem; background:var(--bg-subtle-2); color:var(--text-muted); }
      .rm-h .rm-more { margin-left:auto; font-size:.85rem; font-weight:600; }
      .rm-none { color:var(--text-faint); margin:0 0 .5rem; }
      .rm-glance { display:flex; gap:.5rem 1.4rem; flex-wrap:wrap; align-items:center; background:var(--bg-card); border:1px solid var(--border);
                   border-radius:12px; padding:.7rem 1rem; color:var(--text-muted); }
      .rm-glance b { color:var(--text-primary); font-variant-numeric:tabular-nums; }
      .rm-glance a { margin-left:auto; font-weight:600; }
      .rm-card { background:var(--bg-card); border:1px solid var(--border); border-left:4px solid #7c3aed; border-radius:12px;
                 padding:1rem 1.15rem; margin-bottom:1rem; display:grid; grid-template-columns:minmax(0,1.3fr) minmax(0,1fr); gap:1.25rem; }
      .rm-card h2 { font-size:1.05rem; margin:0 0 .25rem; color:var(--text-primary); }
      .rm-meta { color:var(--text-muted); font-size:.9rem; }
      .rm-items { margin:.6rem 0; padding-left:1.1rem; }
      .rm-note { background:var(--bg-subtle); border-radius:8px; padding:.5rem .7rem; white-space:pre-wrap; }
      .rm-photo img { max-width:220px; max-height:160px; border-radius:8px; border:1px solid var(--border); display:block; margin-top:.5rem; }
      .rm-actions { display:flex; flex-direction:column; gap:.9rem; }
      .rm-actions form { display:flex; flex-direction:column; gap:.6rem; }
      .rm-decline input { padding:.4rem .55rem; border:1px solid var(--border-strong); border-radius:8px; background:var(--bg-input); color:var(--text-body); font:inherit; }
      .rm-wrap { background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:.25rem .5rem; }
      .rm-empty { padding:1.6rem 1rem; text-align:center; color:var(--text-faint); }
      .rm-sub { color:var(--text-faint); font-size:.8125rem; }
      .rm-kpis { display:grid; gap:1rem; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); margin-bottom:1.25rem; }
      .rm-kpi { background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:.9rem 1rem; }
      .rm-kpi .l { font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-faint); font-weight:600; }
      .rm-kpi .v { font-size:1.6rem; font-weight:800; color:var(--text-primary); font-variant-numeric:tabular-nums; }
      .rm-cols { display:grid; gap:1.25rem; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); }
      .rm-cols h3 { font-size:1rem; margin:.2rem 0 .5rem; color:var(--text-primary); }
      .fc-pill { display:inline-block; padding:.0625rem .5rem; font-size:.6875rem; font-weight:700; border-radius:999px; white-space:nowrap;
                 background:var(--bg-subtle-2); color:var(--text-muted); }
      @media (max-width:820px){ .rm-card { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/../_partials/sidebar.php'; ?>
<main class="app-main">
  <div class="page-header">
    <div>
      <h1 class="page-title">Remakes</h1>
      <p class="page-subtitle ui-hint">Raise a remake from an order on <a href="/factory/orders.php">Orders</a> (open the order’s ↻ Remake).
        Accounts can report a fault from their own order; it waits here until someone approves it.</p>
    </div>
    <a href="/factory/remake-reasons.php" class="btn">Edit reasons</a>
  </div>

  <?php foreach (['flash_success' => 'alert-success', 'flash_error' => 'alert-error'] as $k => $cls): ?>
    <?php if (!empty($_SESSION[$k])): ?><div class="alert <?= $cls ?>" role="alert"><?= e((string) $_SESSION[$k]) ?></div><?php unset($_SESSION[$k]); endif; ?>
  <?php endforeach; ?>

  <?php if (!$ready): ?>
    <div class="alert alert-error" role="alert">Remakes need their migration — run <code>/setup/migrations/migrate_remakes.php</code> once, then reload.</div>
  <?php else: ?>

  <nav class="rm-tabs" aria-label="Remakes">
    <a href="/factory/remakes.php" class="<?= $view === 'overview' ? 'active' : '' ?>">Overview</a>
    <a href="?view=waiting" class="<?= $view === 'waiting' ? 'active' : '' ?>">Waiting for approval <span class="n<?= $waitingCount ? ' hot' : '' ?>"><?= $waitingCount ?></span></a>
    <a href="?view=open" class="<?= $view === 'open' ? 'active' : '' ?>">In progress <span class="n"><?= count($lists['open']) ?></span></a>
    <a href="?view=done" class="<?= $view === 'done' ? 'active' : '' ?>">Done <span class="n"><?= count($lists['done']) ?></span></a>
    <a href="?view=report" class="<?= $view === 'report' ? 'active' : '' ?>">Report</a>
  </nav>

  <?php
  // Waiting-for-approval cards (approve with who pays, or decline).
  $cards = static function (array $rows) use ($items, $money): void {
      foreach ($rows as $r): $rid = (int) $r['id']; ?>
      <section class="rm-card" id="rm<?= $rid ?>">
        <div>
          <h2><?= e((string) $r['account_name']) ?> · order <?= e((string) $r['source_number']) ?></h2>
          <div class="rm-meta">Reported <?= e(date('j M Y, H:i', strtotime((string) $r['created_at']))) ?>
            <?= trim((string) $r['source_ref']) !== '' ? ' · their ref ' . e((string) $r['source_ref']) : '' ?>
            · <a href="/factory/edit-order.php?order=<?= (int) $r['source_quote_id'] ?>">open order</a></div>
          <ul class="rm-items">
            <?php foreach ($items[$rid] ?? [] as $it): ?><li><?= (int) $it['quantity'] ?> × <?= e($it['label']) ?></li><?php endforeach; ?>
          </ul>
          <div><b><?= e((string) $r['reason_label']) ?></b></div>
          <?php if (trim((string) $r['note']) !== ''): ?><div class="rm-note"><?= e((string) $r['note']) ?></div><?php endif; ?>
          <?php if (!empty($r['photo_path'])): ?>
            <a class="rm-photo" href="/remakes/photo.php?id=<?= $rid ?>" target="_blank" rel="noopener">
              <img src="/remakes/photo.php?id=<?= $rid ?>" alt="Photo of the fault, sent by the account"></a>
          <?php endif; ?>
          <div class="rm-sub" style="margin-top:.5rem">Trade price of these blinds: <?= e($money($r['cost'])) ?></div>
        </div>
        <div class="rm-actions">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="approve">
            <input type="hidden" name="remake_id" value="<?= $rid ?>">
            <?php rm_decision_fields('a' . $rid, (float) $r['cost']); ?>
            <div><button class="btn btn-primary">Approve remake</button></div>
          </form>
          <form method="post" class="rm-decline">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="decline">
            <input type="hidden" name="remake_id" value="<?= $rid ?>">
            <label for="rmDec<?= $rid ?>">Or decline — why? <span class="rm-sub">(the account sees this and is emailed it)</span></label>
            <input type="text" id="rmDec<?= $rid ?>" name="decline_reason" maxlength="255" placeholder="e.g. Measured by you — size made as ordered">
            <div><button class="btn">Decline</button></div>
          </form>
        </div>
      </section>
  <?php endforeach;
  };

  // In progress / done table.
  $table = static function (array $rows) use ($items, $money, $modes): void { ?>
    <div class="rm-wrap"><div class="table-wrap"><table class="table">
      <thead><tr><th>Remake</th><th>Account</th><th>Blinds</th><th>Reason</th><th>Who pays</th><th class="num">Cost</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $rid = (int) $r['id']; ?>
        <tr>
          <td>
            <?php if ($r['remake_number']): ?>
              <a href="/factory/edit-order.php?order=<?= (int) $r['remake_quote_id'] ?>"><b><?= e((string) $r['remake_number']) ?></b></a>
            <?php else: ?><b>—</b><?php endif; ?>
            <div class="rm-sub">from <?= e((string) $r['source_number']) ?> · <?= e(date('j M', strtotime((string) $r['created_at']))) ?>
              <?= $r['due_date'] ? ' · due ' . e(date('j M', strtotime((string) $r['due_date']))) : '' ?></div>
          </td>
          <td><?= e((string) $r['account_name']) ?><?= $r['raised_by'] === 'account' ? '<div class="rm-sub">reported by them</div>' : '' ?></td>
          <td><?php foreach ($items[$rid] ?? [] as $it): ?><div><?= (int) $it['quantity'] ?> × <?= e($it['label']) ?></div><?php endforeach; ?></td>
          <td><?= e((string) $r['reason_label']) ?><?php if (!empty($r['photo_path'])): ?> · <a href="/remakes/photo.php?id=<?= $rid ?>" target="_blank" rel="noopener">photo</a><?php endif; ?></td>
          <td>
            <?php if ($r['status'] === 'declined'): ?>—
            <?php else: ?>
              <?= e($modes[$r['charge_mode']] ?? '') ?>
              <?= $r['charge_mode'] === 'charge' ? '<div class="rm-sub">' . e($money($r['charge_amount'])) . ' charged</div>' : '' ?>
              <?= $r['charge_mode'] === 'supplier' && $r['supplier_name'] ? '<div class="rm-sub">' . e((string) $r['supplier_name']) . '</div>' : '' ?>
            <?php endif; ?>
          </td>
          <td class="num"><?= e($money($r['cost'])) ?></td>
          <td>
            <?php if ($r['status'] === 'declined'): ?>
              <span class="fc-pill">Declined</span><div class="rm-sub"><?= e((string) $r['decline_reason']) ?></div>
            <?php else: ?>
              <span class="fc-pill"><?= e($r['remake_stage'] ? os_stage_label((string) $r['remake_stage']) : 'New') ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div></div>
  <?php };
  ?>

  <?php if ($view === 'overview'): ?>
    <div class="rm-glance" aria-label="This month">
      <span><b><?= (int) $monthNow['n'] ?></b> remake<?= $monthNow['n'] === 1 ? '' : 's' ?> this month</span>
      <span><b><?= (int) $monthNow['blinds'] ?></b> blind<?= $monthNow['blinds'] === 1 ? '' : 's' ?></span>
      <span><b><?= e($money($monthNow['cost'])) ?></b> cost to us</span>
      <a href="?view=report">Full report &rarr;</a>
    </div>

    <?php if (!$lists['waiting'] && !$lists['open'] && !$lists['done']): ?>
      <div class="rm-wrap"><div class="rm-empty">
        <b>No remakes yet.</b><br>
        To raise one, open the order on <a href="/factory/orders.php">Orders</a> and click <b>↻ Remake</b>.
        When an account reports a fault from their side, it appears here for you to approve.
      </div></div>
    <?php else: ?>
      <h2 class="rm-h">Waiting for approval <span class="n<?= $waitingCount ? ' hot' : '' ?>"><?= $waitingCount ?></span></h2>
      <?php if ($lists['waiting']): $cards($lists['waiting']); else: ?>
        <p class="rm-none">Nothing waiting — accounts’ fault reports appear here.</p>
      <?php endif; ?>

      <h2 class="rm-h">In progress <span class="n"><?= count($lists['open']) ?></span></h2>
      <?php if ($lists['open']): $table($lists['open']); else: ?>
        <p class="rm-none">No remakes being made right now.</p>
      <?php endif; ?>

      <h2 class="rm-h">Done in the last 30 days <span class="n"><?= count($recentDone) ?></span>
        <?php if (count($lists['done']) > count($recentDone)): ?><a href="?view=done" class="rm-more">All done (<?= count($lists['done']) ?>) &rarr;</a><?php endif; ?></h2>
      <?php if ($recentDone): $table($recentDone); else: ?>
        <p class="rm-none">Nothing finished in the last 30 days.</p>
      <?php endif; ?>
    <?php endif; ?>

  <?php elseif ($view === 'waiting'): ?>
    <?php if (!$lists['waiting']): ?>
      <div class="rm-wrap"><div class="rm-empty">Nothing waiting. When an account reports a fault on one of their orders, it appears here.</div></div>
    <?php else: $cards($lists['waiting']); endif; ?>

  <?php elseif ($view === 'open' || $view === 'done'): ?>
    <?php if (!$lists[$view]): ?>
      <div class="rm-wrap"><div class="rm-empty"><?= $view === 'open' ? 'No remakes in progress.' : 'Nothing finished yet.' ?></div></div>
    <?php else: $table($lists[$view]); endif; ?>

  <?php else: /* report */ ?>
    <form method="get" style="display:flex;gap:.5rem;align-items:center;margin:0 0 1rem;flex-wrap:wrap">
      <input type="hidden" name="view" value="report">
      <label for="rmMonth">Month</label>
      <input type="month" id="rmMonth" name="month" value="<?= e($month) ?>" onchange="this.form.submit()"
             style="padding:.35rem .5rem;border:1px solid var(--border-strong);border-radius:8px;background:var(--bg-input);color:var(--text-body);font:inherit">
      <noscript><button class="btn">Show</button></noscript>
    </form>
    <div class="rm-kpis">
      <div class="rm-kpi"><div class="l">Remakes</div><div class="v"><?= (int) $rep['count'] ?></div><div class="rm-sub"><?= (int) $rep['blinds'] ?> blinds</div></div>
      <div class="rm-kpi"><div class="l">Trade value remade</div><div class="v"><?= e($money($rep['cost'])) ?></div></div>
      <div class="rm-kpi"><div class="l">Charged back</div><div class="v"><?= e($money($rep['charged'])) ?></div></div>
      <div class="rm-kpi"><div class="l">Cost to us</div><div class="v"><?= e($money($rep['cost'] - $rep['charged'] - $rep['claims'])) ?></div>
        <div class="rm-sub">after charges and supplier claims</div></div>
      <div class="rm-kpi"><div class="l">Supplier claims</div><div class="v"><?= e($money($rep['claims'])) ?></div></div>
    </div>
    <div class="rm-cols">
      <?php foreach (['reason' => 'By reason', 'account' => 'By account'] as $dim => $title): ?>
        <section class="rm-wrap" style="padding:.75rem 1rem">
          <h3><?= e($title) ?></h3>
          <?php if (!$rep[$dim]): ?><p class="rm-sub">No remakes this month.</p><?php else: ?>
          <div class="table-wrap"><table class="table">
            <thead><tr><th><?= $dim === 'reason' ? 'Reason' : 'Account' ?></th><th class="num">Remakes</th><th class="num">Blinds</th><th class="num">Value</th><th class="num">Charged</th></tr></thead>
            <tbody>
            <?php foreach ($rep[$dim] as $k => $d): ?>
              <tr><td><?= e((string) $k) ?></td><td class="num"><?= (int) $d['n'] ?></td><td class="num"><?= (int) $d['blinds'] ?></td>
                  <td class="num"><?= e($money($d['cost'])) ?></td><td class="num"><?= e($money($d['charged'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>
      <section class="rm-wrap" style="padding:.75rem 1rem">
        <h3>By production area</h3>
        <?php if (!$rep['area']): ?><p class="rm-sub">No remakes this month.</p><?php else: ?>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Area</th><th class="num">Remakes</th><th class="num">Blinds</th><th class="num">Value</th></tr></thead>
          <tbody>
          <?php foreach ($rep['area'] as $k => $d): ?>
            <tr><td><?= e((string) $k) ?></td><td class="num"><?= (int) $d['n'] ?></td><td class="num"><?= (int) $d['blinds'] ?></td>
                <td class="num"><?= e($money($d['cost'])) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <p class="rm-sub" style="margin:.4rem 0 0">Where the original blind was made. A blind made across two areas (e.g. headrail and fabric)
          counts under each, with its value split between them.</p>
        <?php endif; ?>
      </section>
      <section class="rm-wrap" style="padding:.75rem 1rem">
        <h3>Supplier claims</h3>
        <?php if (!$rep['supplier']): ?><p class="rm-sub">No supplier claims this month.</p><?php else: ?>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Supplier</th><th class="num">Remakes</th><th class="num">Value to claim</th></tr></thead>
          <tbody>
          <?php foreach ($rep['supplier'] as $k => $d): ?>
            <tr><td><?= e((string) $k) ?></td><td class="num"><?= (int) $d['n'] ?></td><td class="num"><?= e($money($d['cost'])) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php endif; ?>
      </section>
    </div>
  <?php endif; ?>
  <?php endif; ?>
</main>
</div>
</body>
</html>
