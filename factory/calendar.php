<?php
declare(strict_types=1);

/**
 * Factory Console · Calendar — the office's shared calendar (stage 4).
 *
 * A week at a time: notes, reminders and callbacks, plus remake due dates
 * (automatic). Above it, "Due" lists every open reminder and callback dated today
 * or earlier, so anything not ticked carries forward until it is. Add from the
 * form; tick from the list or the week; click a title to edit or delete.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/office_calendar.php';

requireFactoryOffice();

$user    = current_user();
$pdo     = db();
$factory = ar_factory_id();
$ready   = oc_ready($pdo);

$monday = static function (string $d): string { return date('Y-m-d', strtotime('monday this week', strtotime($d))); };
$week   = (string) ($_GET['week'] ?? '');
$week   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $week) && strtotime($week) ? $monday($week) : $monday(date('Y-m-d'));

$back = '/factory/calendar.php' . ($week !== $monday(date('Y-m-d')) ? '?week=' . $week : '');
$old  = [];
$err  = '';

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = (string) ($_POST['_action'] ?? '');
    $id  = (int) ($_POST['id'] ?? 0);
    try {
        if ($act === 'add') {
            oc_save($pdo, $factory, $_POST, (int) ($user['user_id'] ?? 0));
            $_SESSION['flash_success'] = 'Added to the calendar.';
            $to = $monday((string) $_POST['entry_date']);
            header('Location: /factory/calendar.php' . ($to !== $monday(date('Y-m-d')) ? '?week=' . $to : ''));
            exit;
        }
        if ($act === 'done' || $act === 'undo') {
            oc_set_done($pdo, $factory, $id, $act === 'done', (int) ($user['user_id'] ?? 0));
            header('Location: ' . (string) ($_POST['back'] ?? $back));
            exit;
        }
    } catch (RuntimeException $e) {
        $err = $e->getMessage();
        $old = $_POST;
    }
}

$days = [];
for ($i = 0; $i < 7; $i++) $days[] = date('Y-m-d', strtotime($week . " +$i day"));
$entries = $ready ? oc_range($pdo, $factory, $days[0], $days[6]) : [];
$remakes = oc_remakes_due($pdo, $factory, $days[0], $days[6]);
$today   = date('Y-m-d');
$due     = $ready ? oc_open($pdo, $factory, $today) : [];
$dueRm   = oc_remakes_due($pdo, $factory, '2000-01-01', $today);
$accounts = $ready ? oc_accounts($pdo, $factory) : [];

// Pre-fill from a link (e.g. an account page's "Add callback").
if (!$old) {
    $old = ['kind' => (string) ($_GET['add'] ?? 'reminder'), 'account_client_id' => (int) ($_GET['account'] ?? 0),
            'order_ref' => (string) ($_GET['order'] ?? ''),
            'entry_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date'] ?? '')) ? (string) $_GET['date'] : $today];
}
$openAdd = isset($_GET['add']) || $err !== '';

$flashOk = (string) ($_SESSION['flash_success'] ?? ''); unset($_SESSION['flash_success']);
$activeNav = 'office-calendar';

$tickForm = static function (array $r, string $back): string {
    $done = !empty($r['done_at']);
    return '<form method="post" class="oc-tick">' . csrf_field()
         . '<input type="hidden" name="_action" value="' . ($done ? 'undo' : 'done') . '">'
         . '<input type="hidden" name="id" value="' . (int) $r['id'] . '">'
         . '<input type="hidden" name="back" value="' . e($back) . '">'
         . '<button type="submit" class="oc-box' . ($done ? ' on' : '') . '" aria-label="' . ($done ? 'Mark not done' : 'Mark done') . ': ' . e((string) $r['title']) . '">'
         . ($done ? '&#10003;' : '') . '</button></form>';
};
$when = static function (array $r): string {
    return $r['entry_time'] ? substr((string) $r['entry_time'], 0, 5) . ' ' : '';
};
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Calendar &middot; Factory Console &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <?= oc_styles() ?>
    <style>
      .oc-top { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,22rem); gap:1.25rem; margin-bottom:1.25rem; align-items:start; }
      .oc-panel { background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:.9rem 1.05rem; min-width:0; }
      .oc-panel h2 { font-size:1rem; margin:0 0 .6rem; color:var(--text-primary); }
      .oc-due { display:flex; flex-direction:column; gap:.45rem; }
      .oc-item { display:grid; grid-template-columns:auto minmax(0,1fr); gap:.55rem; align-items:start; }
      .oc-item .t a { color:var(--text-primary); font-weight:600; text-decoration:none; }
      .oc-item .t a:hover { text-decoration:underline; }
      .oc-item .m { color:var(--text-faint); font-size:.8125rem; }
      .oc-late { color:#b91c1c; font-weight:700; }
      .oc-tick { margin:0; }
      .oc-box { width:1.35rem; height:1.35rem; border:2px solid var(--border-strong); border-radius:6px; background:var(--bg-input);
                color:#fff; font-weight:800; line-height:1; cursor:pointer; padding:0; margin-top:.1rem; }
      .oc-box.on { background:#16a34a; border-color:#16a34a; }
      .oc-box:hover { border-color:#16a34a; }
      .oc-nav { display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; margin:0 0 .75rem; }
      .oc-nav b { margin-right:.5rem; color:var(--text-primary); }
      .oc-week { display:grid; grid-template-columns:repeat(7, minmax(0,1fr)); gap:.5rem; }
      .oc-day { background:var(--bg-card); border:1px solid var(--border); border-radius:10px; min-height:9rem; padding:.5rem; min-width:0;
                display:flex; flex-direction:column; gap:.4rem; }
      .oc-day.today { border-color:var(--brand); box-shadow:0 0 0 1px var(--brand); }
      .oc-day .d { font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-faint); font-weight:700; display:flex; justify-content:space-between; }
      .oc-day .d a { color:var(--link); text-decoration:none; font-size:1rem; line-height:1; }
      .oc-e { font-size:.8125rem; border-radius:8px; padding:.3rem .4rem; background:var(--bg-subtle); display:grid;
              grid-template-columns:auto minmax(0,1fr); gap:.35rem; align-items:start; overflow-wrap:anywhere; }
      .oc-e.note { grid-template-columns:minmax(0,1fr); }
      .oc-e.done .x { text-decoration:line-through; color:var(--text-faint); }
      .oc-e .x a { color:var(--text-primary); text-decoration:none; }
      .oc-e .x a:hover { text-decoration:underline; }
      .oc-e .a { color:var(--text-faint); display:block; }
      .oc-e.remake { background:rgba(124,58,237,.1); grid-template-columns:minmax(0,1fr); }
      .oc-e .oc-box { width:1.1rem; height:1.1rem; font-size:.7rem; }
      .oc-empty { color:var(--text-faint); font-size:.875rem; }
      details.oc-add > summary { list-style:none; cursor:pointer; }
      details.oc-add > summary::-webkit-details-marker { display:none; }
      @media (max-width:1000px){ .oc-top { grid-template-columns:1fr; } .oc-week { grid-template-columns:1fr; } .oc-day { min-height:0; } }
    </style>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/../_partials/sidebar.php'; ?>
<main class="app-main">
  <div class="page-header">
    <div>
      <h1 class="page-title">Calendar</h1>
      <p class="page-subtitle ui-hint">The office’s shared calendar: notes, reminders and callbacks. Anything not ticked carries forward
         until it’s done. Remake due dates appear here by themselves. Only the office sees this.</p>
    </div>
  </div>
  <?php if ($flashOk !== ''): ?><div class="alert alert-success" role="status"><?= e($flashOk) ?></div><?php endif; ?>

  <?php if (!$ready): ?>
    <div class="alert alert-error" role="alert">The calendar needs its migration — run <code>/setup/migrations/migrate_office_calendar.php</code> once, then reload.</div>
  <?php else: ?>

  <div class="oc-top">
    <section class="oc-panel" aria-labelledby="ocDueH">
      <h2 id="ocDueH">Due now <span class="oc-faint">(today and anything carried forward)</span></h2>
      <?php if (!$due && !$dueRm): ?>
        <p class="oc-empty">Nothing due. Nice.</p>
      <?php else: ?>
        <div class="oc-due">
          <?php foreach ($due as $r): $late = $r['entry_date'] < $today; ?>
            <div class="oc-item">
              <?= $tickForm($r, $back) ?>
              <div>
                <div class="t"><?= oc_chip((string) $r['kind']) ?> <a href="/factory/calendar-entry.php?id=<?= (int) $r['id'] ?>"><?= e($when($r) . $r['title']) ?></a></div>
                <div class="m">
                  <?php if ($late): ?><span class="oc-late">from <?= e(date('D j M', strtotime((string) $r['entry_date']))) ?></span><?php else: ?>Today<?php endif; ?>
                  <?php if ($r['account_name']): ?> · <?= e((string) $r['account_name']) ?><?php endif; ?>
                  <?php if ($r['order_ref']): ?> · <?php if ($r['quote_id']): ?><a href="<?= e(oc_order_url($pdo, $factory, (int) $r['quote_id'])) ?>"><?= e((string) $r['order_ref']) ?></a><?php else: ?><?= e((string) $r['order_ref']) ?><?php endif; ?><?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
          <?php foreach ($dueRm as $day => $list): foreach ($list as $rm): ?>
            <div class="oc-item">
              <span aria-hidden="true" style="width:1.35rem"></span>
              <div>
                <div class="t"><?= oc_chip('remake') ?> <a href="/factory/edit-order.php?order=<?= (int) $rm['remake_quote_id'] ?>">Remake <?= e((string) $rm['remake_number']) ?> due</a></div>
                <div class="m"><?= $day < $today ? '<span class="oc-late">due ' . e(date('D j M', strtotime($day))) . '</span>' : 'Due today' ?> · <?= e((string) $rm['account_name']) ?> · <?= e((string) $rm['reason_label']) ?></div>
              </div>
            </div>
          <?php endforeach; endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="oc-panel">
      <details class="oc-add"<?= $openAdd ? ' open' : '' ?>>
        <summary class="btn btn-primary">+ Add to the calendar</summary>
        <form method="post" style="margin-top:.8rem;display:flex;flex-direction:column;gap:.7rem">
          <?= csrf_field() ?>
          <input type="hidden" name="_action" value="add">
          <?php if ($err !== ''): ?><div class="alert alert-error" role="alert" style="margin:0"><?= e($err) ?></div><?php endif; ?>
          <?php oc_form_fields($old, $accounts); ?>
          <div><button class="btn btn-primary">Add</button></div>
        </form>
      </details>
    </section>
  </div>

  <nav class="oc-nav" aria-label="Week">
    <b>Week of <?= e(date('j F Y', strtotime($week))) ?></b>
    <a class="btn btn-sm" href="?week=<?= e(date('Y-m-d', strtotime($week . ' -7 day'))) ?>">&lsaquo; Previous</a>
    <a class="btn btn-sm" href="/factory/calendar.php">This week</a>
    <a class="btn btn-sm" href="?week=<?= e(date('Y-m-d', strtotime($week . ' +7 day'))) ?>">Next &rsaquo;</a>
  </nav>

  <div class="oc-week">
    <?php foreach ($days as $d): ?>
      <div class="oc-day<?= $d === $today ? ' today' : '' ?>">
        <div class="d"><span><?= e(date('D j M', strtotime($d))) ?></span>
          <a href="?week=<?= e($week) ?>&add=reminder&date=<?= e($d) ?>#ocTitle" title="Add on <?= e(date('D j M', strtotime($d))) ?>" aria-label="Add on <?= e(date('D j M', strtotime($d))) ?>">+</a></div>
        <?php foreach ($remakes[$d] ?? [] as $rm): ?>
          <div class="oc-e remake"><div class="x"><a href="/factory/edit-order.php?order=<?= (int) $rm['remake_quote_id'] ?>"><b>Remake due</b> <?= e((string) $rm['remake_number']) ?></a>
            <span class="a"><?= e((string) $rm['account_name']) ?></span></div></div>
        <?php endforeach; ?>
        <?php foreach ($entries[$d] ?? [] as $r): $isNote = $r['kind'] === 'note'; ?>
          <div class="oc-e <?= e((string) $r['kind']) ?><?= !empty($r['done_at']) ? ' done' : '' ?>">
            <?php if (!$isNote): ?><?= $tickForm($r, '/factory/calendar.php?week=' . $week) ?><?php endif; ?>
            <div class="x"><?= oc_chip((string) $r['kind']) ?>
              <a href="/factory/calendar-entry.php?id=<?= (int) $r['id'] ?>"><?= e($when($r) . $r['title']) ?></a>
              <?php if ($r['account_name']): ?><span class="a"><?= e((string) $r['account_name']) ?><?= $r['order_ref'] ? ' · ' . e((string) $r['order_ref']) : '' ?></span><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
</div>
</body>
</html>
