<?php
declare(strict_types=1);

/**
 * Factory Console · Pipeline — every open order as a card, left to right from
 * New to Paid, so "where's my order?" is one look (John, 2026-10-12).
 *
 * Columns follow the same words as everywhere else: Making (fc_stage_meta,
 * incl. Awaiting supplier) then Money (fc_money_meta). Each card lists the
 * order's lines with their own status (ot_order_tracks) and offers ONE next
 * step. The buttons post to the existing actions (set-status, boughtin-received,
 * order-suppliers) with back=pipeline, and the job rules are ot_next_jobs() —
 * the same ones Incoming orders uses — so nothing can skip a check.
 *
 * Cards move only by their buttons (no dragging). Paid cards drop off after 7
 * days and stay on Orders. The money columns are the accounts side, main
 * factory only (fc_money_visible, #1063). Built on fc_orders().
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_console.php';

requireFactoryOffice();

$user    = current_user();
$pdo     = db();
$factory = ar_factory_id();
$money   = fc_money_visible();

$all = array_values(array_filter(fc_orders($pdo, $factory), static fn ($r) => $r['stage'] !== 'notplaced'));

$q       = trim((string) ($_GET['q'] ?? ''));
$account = (int) ($_GET['account'] ?? -1);

// The columns, in order: key => [title, hint, colour].
$COLS = [
    'new'               => ['New',               'Placed, not taken in yet',                     '#b91c1c'],
    'confirmed'         => ['Received',          'In, not started on the floor',                 '#5b6b7f'],
    'in_production'     => ['In production',     'Being made on the floor',                      '#b5730f'],
    'awaiting_supplier' => ['Awaiting supplier', 'Our part is made, waiting on a bought-in item', '#7c3aed'],
    'ready'             => ['Ready to go',       'Everything made and in',                       '#245ea3'],
];
if ($money) {
    $COLS += [
        'to_invoice' => ['Dispatched · to invoice', 'Gone out, no invoice yet',               '#0d7a67'],
        'to_pay'     => ['Awaiting payment',        'Invoiced, not paid yet',                 '#1d4f8c'],
        'paid'       => ['Paid',                    'Last 7 days, then it leaves the board',  '#0d7a67'],
    ];
} else {
    $COLS['dispatched'] = ['Dispatched', 'Gone out in the last 7 days', '#0d7a67'];
}

$weekAgo = date('Y-m-d', strtotime('-7 days'));
$colFor = static function (array $r) use ($money, $weekAgo): ?string {
    if ($r['stage'] !== 'dispatched') return $r['stage'];
    if (!$money) return substr((string) $r['created_at'], 0, 10) >= $weekAgo ? 'dispatched' : null;
    if (in_array($r['money'], ['needs_invoicing', 'not_invoiced'], true)) return 'to_invoice';
    if ($r['money'] === 'paid') {
        $last = '';
        foreach ($r['invoices'] as $iv) if ($iv['paid_at'] > $last) $last = $iv['paid_at'];
        // A £0 invoice is paid the moment it's raised and has no payment date.
        if ($last === '') $last = substr((string) $r['created_at'], 0, 10);
        return $last >= $weekAgo ? 'paid' : null;
    }
    return 'to_pay';
};

// Accounts for the picker, then the search / account filter.
$accounts = [];
foreach ($all as $r) $accounts[$r['account_key']] = $r['account'];
asort($accounts, SORT_NATURAL | SORT_FLAG_CASE);
$shown = array_values(array_filter($all, static function (array $r) use ($q, $account): bool {
    if ($account >= 0 && (int) $r['account_key'] !== $account) return false;
    if ($q === '') return true;
    $hay = mb_strtolower($r['quote_number'] . ' ' . $r['account'] . ' ' . ($r['customer_reference'] ?? '')
         . ' ' . ($r['end_customer_name'] ?? '') . ' ' . $r['invoice']);
    return mb_strpos($hay, mb_strtolower($q)) !== false;
}));

// Each order's factory lines (name × qty) and due date, one query.
$ids = array_map(static fn ($r) => (int) $r['id'], $shown);
$linesBy = []; $dueBy = [];
if ($ids) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    try {
        $st = $pdo->prepare(
            "SELECT qi.id, qi.quote_id, qi.product_name_snapshot, qi.quantity
               FROM quote_items qi JOIN products p ON p.id = qi.product_id
              WHERE qi.quote_id IN ($ph) AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
           ORDER BY qi.line_no, qi.id"
        );
        $st->execute([...$ids, $factory]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) $linesBy[(int) $l['quote_id']][] = $l;
    } catch (Throwable $e) { /* no lines shown */ }
    try {
        $st = $pdo->prepare("SELECT id, due_date FROM quotes WHERE id IN ($ph)");
        $st->execute($ids);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $d) $dueBy[(int) $d['id']] = (string) ($d['due_date'] ?? '');
    } catch (Throwable $e) { /* pre due-date schema */ }
}

$board = array_fill_keys(array_keys($COLS), []);
foreach ($shown as $r) {
    $c = $colFor($r);
    if ($c !== null && isset($board[$c])) $board[$c][] = $r;
}
// Oldest first inside a column: the one waiting longest is at the top.
foreach ($board as &$cards) usort($cards, static fn ($a, $b) => strcmp((string) $a['created_at'], (string) $b['created_at']));
unset($cards);

// What each line says on its card.
$lineStatus = static function (array $r, array $l): array {
    if ($r['stage'] === 'dispatched') return ['✓ Dispatched', 'ok'];
    $t = $r['tracks']['lines'][(int) $l['id']] ?? null;
    if (!$t) return ['', 'muted'];
    $s = (string) $t['status'];
    if ($t['track'] === 'make') {
        if ($s === 'Made')    return ['✓ Made', 'ok'];
        if ($s === 'Queued')  return ['Not started', 'muted'];
        if (preg_match('/\((\d+)\/(\d+)\)/', $s, $m)) return ['Being made · ' . $m[1] . ' of ' . $m[2], 'amber'];
        return [$s, 'amber'];
    }
    $sup = (string) $t['supplier'];
    if ($s === 'Received') return ['✓ Received from ' . $sup, 'ok'];
    if ($s === 'To order') return [$sup . ' · not ordered yet', 'bad'];
    return ['Waiting on ' . $sup . ' · ' . lcfirst($s), 'bad'];
};

$gbp = static fn ($n) => $n === null ? '' : '£' . number_format((float) $n, 2);
$csrf = csrf_field() ;

// The one next step on a card: [html, extra classes].
$action = static function (array $r) use ($money, $csrf): string {
    $qid = (int) $r['id'];
    $h   = $csrf . '<input type="hidden" name="quote_id" value="' . $qid . '"><input type="hidden" name="back" value="pipeline">';
    $btn = static fn (string $label, string $cls, string $extra = '') => '<button type="submit" class="pl-btn ' . $cls . '"' . $extra . '>' . e($label) . '</button>';
    if ($r['stage'] !== 'dispatched') {
        $t    = $r['tracks'] ?? ['make' => ['units' => 0, 'made' => 0, 'released' => false], 'buy' => []];
        $jobs = ot_next_jobs((string) ($r['factory_status'] ?? ''), $t, (string) ($r['fulfilment_stage'] ?? ''));
        $j    = $jobs[0] ?? null;
        if ($j) {
            $sup = (string) ($j['supplier'] ?? '');
            switch ($j['kind']) {
                case 'accept':
                    return '<form method="post" action="/factory/set-status.php">' . $h . '<input type="hidden" name="accept" value="1">'
                         . '<button type="submit" name="status" value="received" class="pl-btn">Mark received</button></form>';
                case 'start':
                    return '<form method="post" action="/factory/set-status.php">' . $h
                         . '<button type="submit" name="status" value="in_production" class="pl-btn">Start production</button></form>';
                case 'order':
                    return '<a class="pl-btn bad" href="/factory/order-suppliers.php?id=' . $qid . '&amp;back=pipeline">Order from ' . e($sup) . '</a>';
                case 'receive':
                    return '<form method="post" action="/factory/boughtin-received.php">' . $h
                         . '<input type="hidden" name="supplier_name" value="' . e($sup) . '">'
                         . $btn('Received from ' . $sup, 'purple') . '</form>';
                case 'dispatch':
                    if ($money) return '<a class="pl-btn blue" href="/master-admin/dispatch.php">Add to today&rsquo;s run &rarr;</a>';
                    return '<form method="post" action="/factory/set-status.php">' . $h
                         . '<button type="submit" name="status" value="dispatched" class="pl-btn blue">Dispatch</button></form>';
                case 'floor':
                    return '<a class="pl-btn ghost" href="/factory/order-areas.php?order=' . $qid . '">View on floor</a>';
            }
        }
        return '';
    }
    if (!$money) return '';
    $acc = (int) $r['account_key'];
    switch ($r['money']) {
        case 'needs_invoicing':
            return $r['billed_here']
                ? '<a class="pl-btn green" href="/master-admin/dispatch.php#to-invoice">Raise invoice</a>'
                : '<a class="pl-btn green" href="/quote-builder/edit.php?id=' . $qid . '">Raise invoice</a>';
        case 'overdue':
            return $acc > 0 ? '<a class="pl-btn bad" href="/master-admin/account.php?id=' . $acc . '">Chase payment</a>' : '';
        case 'awaiting':
        case 'part_paid':
            return $acc > 0 ? '<a class="pl-btn" href="/master-admin/record-payment.php?account_id=' . $acc . '">Record payment</a>' : '';
    }
    return '';
};

$qs = static function (array $over) use ($q, $account): string {
    $p = array_merge(['q' => $q, 'account' => $account], $over);
    if ($p['q'] === '') unset($p['q']);
    if ((int) $p['account'] < 0) unset($p['account']);
    return '/factory/pipeline.php' . ($p ? '?' . http_build_query($p) : '');
};

$flashOk  = (string) ($_SESSION['flash_success'] ?? ''); unset($_SESSION['flash_success']);
$flashErr = (string) ($_SESSION['flash_error']   ?? ''); unset($_SESSION['flash_error']);

$counts = array_map('count', $board);
$open   = 0;
foreach ($board as $k => $cards) if ($k !== 'paid' && $k !== 'dispatched') $open += count($cards);

$activeNav = 'factory-pipeline';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pipeline &middot; Factory Console &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
      .pl-top { display:flex; flex-wrap:wrap; gap:.6rem 1rem; align-items:flex-end; justify-content:space-between; margin-bottom:.8rem; }
      .pl-find { display:flex; gap:.5rem; flex-wrap:wrap; }
      .pl-find input, .pl-find select { padding:.5rem .75rem; border:1px solid var(--border-strong); border-radius:8px; font:inherit; background:var(--bg-input); color:var(--text-body); }
      .pl-find input { width:24rem; max-width:100%; }
      .pl-sum { display:flex; flex-wrap:wrap; gap:.5rem; margin:0 0 .9rem; font-size:.8125rem; }
      .pl-sum span { padding:.2rem .65rem; border-radius:999px; background:var(--bg-card); border:1px solid var(--border); color:var(--text-muted); }
      .pl-sum b { color:var(--text-primary); }
      .pl-board { display:grid; grid-template-columns:repeat(<?= count($COLS) ?>, minmax(0, 1fr)); gap:.4rem; align-items:start; }
      .pl-col { background:var(--bg-subtle-2); border-radius:10px; padding:.4rem; display:flex; flex-direction:column; gap:.2rem; min-width:0; }
      @media (max-width:1100px){ .pl-scroll { overflow-x:auto; } .pl-board { grid-template-columns:repeat(<?= count($COLS) ?>, 150px); width:max-content; } }
      .pl-scroll { padding-bottom:.5rem; }
      .pl-col > header { display:flex; justify-content:space-between; align-items:center; padding:.15rem .2rem .35rem; border-bottom:3px solid var(--pl-c); }
      .pl-col h2 { margin:0; font-size:.875rem; font-weight:700; color:var(--text-primary); }
      .pl-n { font-size:.75rem; font-weight:700; color:var(--pl-c); background:var(--bg-card); border-radius:999px; padding:0 .5rem; }
      .pl-hint { margin:0 .15rem .15rem; font-size:.6875rem; line-height:1.3; color:var(--text-faint); }
      .pl-empty { margin:.2rem; font-size:.8125rem; color:var(--text-faint); }
      /* Collapsed: one line with the order number. Click it to open the card. */
      .pl-card { background:var(--bg-card); border:1px solid var(--border); border-radius:7px; }
      .pl-card > summary { list-style:none; cursor:pointer; display:flex; align-items:center; gap:.35rem; padding:.18rem .45rem;
                           font-weight:700; font-size:.78rem; line-height:1.35; color:var(--text-primary); white-space:nowrap; overflow:hidden; }
      .pl-card > summary::-webkit-details-marker { display:none; }
      .pl-card > summary::before { content:"\25B8"; font-size:.7rem; color:var(--text-faint); }
      .pl-card[open] > summary::before { content:"\25BE"; }
      .pl-card > summary:hover { color:var(--link); }
      .pl-card[open] { box-shadow:0 2px 8px rgba(15,23,42,.12); }
      .pl-sn { overflow:hidden; text-overflow:ellipsis; }
      .pl-dot { width:.5rem; height:.5rem; border-radius:50%; background:#b91c1c; flex:none; margin-left:auto; }
      .pl-body { padding:0 .55rem .55rem; display:flex; flex-direction:column; gap:.35rem; }
      .pl-tools { display:flex; gap:.4rem; margin-left:auto; }
      .pl-row { display:flex; justify-content:space-between; gap:.5rem; align-items:baseline; }
      .pl-num { font-weight:700; font-size:.875rem; color:var(--text-primary); text-decoration:none; }
      .pl-num:hover { color:var(--link); text-decoration:underline; }
      .pl-val { font-size:.8125rem; font-weight:600; color:var(--text-muted); white-space:nowrap; }
      .pl-acc { font-size:.8125rem; color:var(--text-body); }
      .pl-meta { font-size:.75rem; color:var(--text-faint); }
      .pl-lines { list-style:none; margin:0; padding:.4rem 0 0; border-top:1px solid var(--border); display:flex; flex-direction:column; gap:.35rem; }
      .pl-lines li { font-size:.78rem; line-height:1.35; display:flex; flex-direction:column; }
      .pl-lines .what { color:var(--text-body); font-weight:500; }
      .pl-lines .ok { color:#0d7a67; font-weight:600; }
      .pl-lines .bad { color:#b91c1c; font-weight:700; }
      .pl-lines .amber { color:#9a5b00; font-weight:600; }
      .pl-lines .muted { color:var(--text-faint); }
      .pl-money { font-size:.78rem; }
      .pl-card form { margin:0; }
      .pl-btn { display:block; width:100%; box-sizing:border-box; text-align:center; font:inherit; font-size:.8125rem; font-weight:600;
                padding:.55rem .7rem; min-height:2.5rem; border-radius:8px; border:0; cursor:pointer; background:#1f3b5b; color:#fff; text-decoration:none; }
      .pl-btn:hover { filter:brightness(1.1); color:#fff; }
      .pl-btn.bad { background:#b91c1c; } .pl-btn.purple { background:#7c3aed; } .pl-btn.blue { background:#245ea3; } .pl-btn.green { background:#0d7a67; }
      .pl-btn.ghost { background:transparent; color:var(--link); border:1px solid var(--border-strong); }
      .pl-btn.ghost:hover { color:var(--link); }
      .pl-foot { margin:.8rem 0 0; font-size:.8125rem; color:var(--text-faint); }
    </style>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/../_partials/sidebar.php'; ?>
<main class="app-main">
  <?php if ($flashOk  !== ''): ?><div class="alert alert-success" role="status"><?= e($flashOk) ?></div><?php endif; ?>
  <?php if ($flashErr !== ''): ?><div class="alert alert-error"   role="alert"><?= e($flashErr) ?></div><?php endif; ?>

  <div class="pl-top">
    <div>
      <h1 class="page-title">Pipeline</h1>
      <p class="page-subtitle ui-hint">Every open order, left to right from new to <?= $money ? 'paid' : 'dispatched' ?>. Each card says what it&rsquo;s waiting on and gives the next step.</p>
    </div>
    <form method="get" class="pl-find" action="/factory/pipeline.php" role="search">
      <label for="plQ" class="visually-hidden" style="position:absolute;left:-9999px">Find an order</label>
      <input type="search" id="plQ" name="q" value="<?= e($q) ?>" placeholder="Where's my order? Account, order no., their ref, name&hellip;">
      <label for="plAcc" style="position:absolute;left:-9999px">Account</label>
      <select id="plAcc" name="account" onchange="this.form.submit()">
        <option value="-1">All accounts</option>
        <?php foreach ($accounts as $aid => $aname): ?>
          <option value="<?= (int) $aid ?>"<?= $account === (int) $aid ? ' selected' : '' ?>><?= e($aname) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn">Find</button>
      <?php if ($q !== '' || $account >= 0): ?><a class="btn" href="/factory/pipeline.php">Clear</a><?php endif; ?>
    </form>
  </div>

  <div class="pl-sum">
    <span><b><?= $open ?></b> open order<?= $open === 1 ? '' : 's' ?></span>
    <?php if ($counts['awaiting_supplier'] > 0): ?><span><b><?= $counts['awaiting_supplier'] ?></b> waiting on suppliers</span><?php endif; ?>
    <?php if ($counts['ready'] > 0): ?><span><b><?= $counts['ready'] ?></b> ready to go</span><?php endif; ?>
    <?php if ($money): $od = count(array_filter($board['to_pay'], static fn ($r) => $r['money'] === 'overdue')); ?>
      <?php if ($od > 0): ?><span style="background:#fee2e2;color:#991b1b;border-color:#fecaca"><b style="color:#991b1b"><?= $od ?></b> overdue</span><?php endif; ?>
    <?php endif; ?>
    <div class="pl-tools">
      <button type="button" class="btn btn-sm" onclick="document.querySelectorAll('.pl-card').forEach(function(d){d.open=true})">Open all</button>
      <button type="button" class="btn btn-sm" onclick="document.querySelectorAll('.pl-card').forEach(function(d){d.open=false})">Close all</button>
    </div>
  </div>

  <div class="pl-scroll">
    <div class="pl-board">
      <?php foreach ($COLS as $key => [$title, $hint, $col]): $cards = $board[$key]; ?>
        <section class="pl-col" style="--pl-c:<?= e($col) ?>" aria-label="<?= e($title) ?>">
          <header><h2><?= e($title) ?></h2><span class="pl-n"><?= count($cards) ?></span></header>
          <p class="pl-hint"><?= e($hint) ?></p>
          <?php if (!$cards): ?><p class="pl-empty">Nothing here.</p><?php endif; ?>
          <?php foreach ($cards as $r): $qid = (int) $r['id']; $due = $dueBy[$qid] ?? ''; ?>
            <?php
              // Red dot on the collapsed line when something needs chasing: a
              // supplier item not ordered / not in yet, or money that's due.
              $urgent = in_array($r['money'], ['needs_invoicing', 'overdue'], true);
              foreach ($linesBy[$qid] ?? [] as $l) if ($lineStatus($r, $l)[1] === 'bad') { $urgent = true; break; }
            ?>
            <details class="pl-card"<?= ($q !== '' || $account >= 0) ? ' open' : '' ?>>
              <summary title="<?= e($r['account']) ?>"><span class="pl-sn"><?= e((string) $r['quote_number']) ?></span><?php if ($urgent): ?><span class="pl-dot" aria-label="Needs attention"></span><?php endif; ?></summary>
              <div class="pl-body">
              <div class="pl-row">
                <a class="pl-num" href="<?= e($r['open_url']) ?>">Open order &rarr;</a>
                <?php if ($money): ?><span class="pl-val"><?= e($gbp($r['value'])) ?></span><?php endif; ?>
              </div>
              <div class="pl-acc"><?= e($r['account']) ?><?php if (trim((string) ($r['customer_reference'] ?? '')) !== ''): ?> <span class="pl-meta">&middot; <?= e((string) $r['customer_reference']) ?></span><?php endif; ?>
                <?php if ($r['remake_of'] !== ''): ?> <?= rm_badge($r['remake_of']) ?><?php endif; ?></div>
              <?php if ($due !== '' && $r['stage'] !== 'dispatched'): ?><div class="pl-meta">Due <?= e(date('j M', strtotime($due))) ?></div><?php endif; ?>
              <?php if (!empty($linesBy[$qid])): ?>
                <ul class="pl-lines">
                  <?php foreach ($linesBy[$qid] as $l): [$st, $tone] = $lineStatus($r, $l); ?>
                    <li><span class="what"><?= e((string) $l['product_name_snapshot']) ?> &times; <?= (int) $l['quantity'] ?></span>
                        <?php if ($st !== ''): ?><span class="<?= e($tone) ?>"><?= e($st) ?></span><?php endif; ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
              <?php if ($money && $r['invoices']): ?>
                <div class="pl-money"><?= fc_money_pill($r['money']) ?>
                  <?php foreach ($r['invoices'] as $iv): ?>
                    <div class="pl-meta"><?= e($iv['number']) ?><?php if ($iv['outstanding'] > 0.004): ?> &middot; <?= e($gbp($iv['outstanding'])) ?> owed<?php if ($iv['due'] !== ''): ?>, due <?= e(date('j M', strtotime($iv['due']))) ?><?php endif; ?><?php elseif ($iv['paid_at'] !== ''): ?> &middot; paid <?= e(date('j M', strtotime($iv['paid_at']))) ?><?php endif; ?></div>
                  <?php endforeach; ?>
                </div>
              <?php elseif ($money && $key === 'to_invoice'): ?>
                <div class="pl-money"><?= fc_money_pill($r['money']) ?></div>
              <?php endif; ?>
              <?= $action($r) ?>
              </div>
            </details>
          <?php endforeach; ?>
        </section>
      <?php endforeach; ?>
    </div>
  </div>

  <p class="pl-foot">Cards move only with their buttons, so nothing skips a check (no dispatch before every bought-in item is in).
    <?php if ($money): ?>Paid orders leave the board after 7 days and stay on <a href="/factory/orders.php?stage=done">Orders</a>.
    The <a href="/master-admin/dispatch.php">Dispatch tray</a> is still where the van run is ticked and printed.<?php else: ?>Dispatched orders leave after 7 days and stay on <a href="/factory/orders.php?stage=all">Orders</a>.<?php endif; ?></p>
</main>
</div>
</body>
</html>
