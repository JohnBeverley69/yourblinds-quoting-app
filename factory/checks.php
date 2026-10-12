<?php
declare(strict_types=1);

/**
 * Factory Console · Order checks — every order whose trackers disagree
 * (_partials/order_checks.php). Super-admin only, like the Dashboard. Read-only
 * apart from one button that re-rolls out-of-date stages. The same list is
 * mailed each morning from cron_messages.php when anything is wrong.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/order_checks.php';

requireFactoryOffice();
if (!is_super_admin()) {
    header('Location: /factory/pipeline.php');
    exit;
}

$pdo     = db();
$factory = (int) current_factory_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if ((string) ($_POST['_action'] ?? '') === 'fix_stages') {
        $n = oc_fix_stale_stages($pdo, $factory);
        $_SESSION['flash_success'] = $n . ' stage' . ($n === 1 ? '' : 's') . ' brought up to date.';
    }
    header('Location: /factory/checks.php');
    exit;
}

$findings = oc_findings($pdo, $factory);
$checks   = oc_checks();
$by = [];
foreach ($findings as $r) $by[$r['check']][] = $r;

$flashOk  = (string) ($_SESSION['flash_success'] ?? ''); unset($_SESSION['flash_success']);
$flashErr = (string) ($_SESSION['flash_error']   ?? ''); unset($_SESSION['flash_error']);
$activeNav = 'factory-checks';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order checks &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
      .oc-ok { padding:1.2rem; color:var(--text-muted); }
      .oc-group { margin:0 0 1.2rem; }
      .oc-group h2 { font-size:1rem; margin:0 0 .25rem; }
      .oc-what { font-size:.85rem; color:var(--text-muted); margin:0 0 .5rem; }
      .oc-detail { color:var(--text-muted); font-size:.85rem; }
    </style>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/../_partials/sidebar.php'; ?>
<main class="app-main">
  <?php if ($flashOk  !== ''): ?><div class="alert alert-success" role="status"><?= e($flashOk) ?></div><?php endif; ?>
  <?php if ($flashErr !== ''): ?><div class="alert alert-error"   role="alert"><?= e($flashErr) ?></div><?php endif; ?>

  <div class="page-header">
    <div>
      <h1 class="page-title">Order checks</h1>
      <p class="page-subtitle">Orders whose records don&rsquo;t agree with each other &mdash; for example dispatched with no
         delivery note, or invoiced but never sent out. Orders placed in the last <?= (int) OC_DAYS ?> days.
         You&rsquo;re emailed this list each morning when there&rsquo;s anything on it.</p>
    </div>
    <a href="/factory/pipeline.php" class="btn">Pipeline</a>
  </div>

  <?php if (!$findings): ?>
    <div class="card"><div class="oc-ok">✓ Everything adds up. No order has records that disagree.</div></div>
  <?php else: ?>
    <?php foreach ($checks as $k => [$title, $what]): if (empty($by[$k])) continue; ?>
      <section class="oc-group">
        <h2><?= e($title) ?> <span class="ui-hint">(<?= count($by[$k]) ?>)</span></h2>
        <p class="oc-what"><b>What to do:</b> <?= e($what) ?></p>
        <?php if ($k === 'stage_stale'): ?>
          <form method="post" style="margin:0 0 .5rem">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="fix_stages">
            <button class="btn btn-sm">Bring stages up to date</button>
          </form>
        <?php endif; ?>
        <div class="card"><div class="table-wrap">
          <table class="table">
            <thead><tr><th>Order</th><th>Account</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach ($by[$k] as $r): ?>
              <tr>
                <td><a href="/factory/pipeline.php?q=<?= urlencode($r['quote_number']) ?>"><?= e($r['quote_number']) ?></a></td>
                <td><?= e($r['account']) ?></td>
                <td class="oc-detail"><?= e($r['detail']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div></div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</main>
</div>
</body>
</html>
