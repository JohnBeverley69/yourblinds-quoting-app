<?php
declare(strict_types=1);

/**
 * Factory Console · Calendar — edit one entry (note / reminder / callback):
 * change it, tick it done (or not), or delete it.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/office_calendar.php';

requireFactoryOffice();

$user    = current_user();
$pdo     = db();
$factory = ar_factory_id();
$id      = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$entry   = oc_get($pdo, $factory, $id);
$err     = '';

$weekOf = static fn (string $d): string => '/factory/calendar.php?week=' . date('Y-m-d', strtotime('monday this week', strtotime($d)));

if ($entry && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = (string) ($_POST['_action'] ?? 'save');
    try {
        if ($act === 'delete') {
            oc_delete($pdo, $factory, $id);
            $_SESSION['flash_success'] = 'Deleted “' . $entry['title'] . '”.';
            header('Location: ' . $weekOf((string) $entry['entry_date']));
            exit;
        }
        if ($act === 'done' || $act === 'undo') {
            oc_set_done($pdo, $factory, $id, $act === 'done', (int) ($user['user_id'] ?? 0));
            header('Location: /factory/calendar-entry.php?id=' . $id);
            exit;
        }
        oc_save($pdo, $factory, $_POST, (int) ($user['user_id'] ?? 0), $id);
        $_SESSION['flash_success'] = 'Saved.';
        header('Location: ' . $weekOf((string) $_POST['entry_date']));
        exit;
    } catch (RuntimeException $e) {
        $err = $e->getMessage();
        $entry = array_merge($entry, $_POST);
    }
}

$activeNav = 'office-calendar';
$accounts  = $entry ? oc_accounts($pdo, $factory) : [];
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Calendar entry &middot; Factory Console &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <?= oc_styles() ?>
    <style>
      .ce { background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:1rem 1.15rem; max-width:40rem;
            display:flex; flex-direction:column; gap:.9rem; }
      .ce-actions { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }
      .ce-status { color:var(--text-muted); }
    </style>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/../_partials/sidebar.php'; ?>
<main class="app-main">
  <div class="page-header">
    <div><h1 class="page-title"><?= $entry ? e((string) (oc_kinds()[$entry['kind']][0] ?? 'Entry')) : 'Calendar entry' ?></h1></div>
    <a class="btn" href="<?= e($entry ? $weekOf((string) $entry['entry_date']) : '/factory/calendar.php') ?>">Back to the calendar</a>
  </div>
  <?php if (!$entry): ?>
    <div class="alert alert-error" role="alert">That entry no longer exists. <a href="/factory/calendar.php">Back to the calendar</a></div>
  <?php else: ?>
    <?php if ($err !== ''): ?><div class="alert alert-error" role="alert"><?= e($err) ?></div><?php endif; ?>
    <?php if ($entry['kind'] !== 'note'): ?>
      <form method="post" class="ce-actions" style="margin:0 0 1rem">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <?php if (!empty($entry['done_at'])): ?>
          <span class="ce-status">&#10003; Done <?= e(date('j M Y, H:i', strtotime((string) $entry['done_at']))) ?></span>
          <button class="btn btn-sm" name="_action" value="undo">Mark not done</button>
        <?php else: ?>
          <span class="ce-status">Not done yet</span>
          <button class="btn btn-sm btn-primary" name="_action" value="done">&#10003; Mark done</button>
        <?php endif; ?>
      </form>
    <?php endif; ?>
    <form method="post" class="ce">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <?php oc_form_fields($entry, $accounts); ?>
      <?php if (!empty($entry['quote_id'])): ?>
        <div class="oc-faint">Order: <a href="<?= e(oc_order_url($pdo, $factory, (int) $entry['quote_id'])) ?>"><?= e((string) $entry['order_ref']) ?></a></div>
      <?php elseif (!empty($entry['order_ref'])): ?>
        <div class="oc-faint">No order found with that number<?= !empty($entry['account_client_id']) ? ' for this account' : '' ?> — it’s kept as text.</div>
      <?php endif; ?>
      <div class="ce-actions">
        <button class="btn btn-primary" name="_action" value="save">Save</button>
      </div>
    </form>
    <form method="post" data-confirm="Delete this from the calendar?" style="margin-top:.75rem">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <input type="hidden" name="_action" value="delete">
      <button class="btn btn-sm">Delete</button>
    </form>
  <?php endif; ?>
</main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
