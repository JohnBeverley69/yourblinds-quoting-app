<?php
declare(strict_types=1);

/**
 * Factory Console · Remake reasons — the factory's own list (John: "we can make
 * changes to the remake reasons ourselves"). Rename, reorder, add, retire.
 * Retiring keeps the reason on past remakes (they store the label too).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/remakes.php';

requireFactoryOffice();

$pdo     = db();
$factory = ar_factory_id();
$ready   = rm_ready($pdo);

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        rm_reasons($pdo, $factory, false);   // make sure the defaults exist
        $upd = $pdo->prepare('UPDATE factory_remake_reasons SET label = ?, sort_order = ?, active = ? WHERE id = ? AND factory_client_id = ?');
        foreach ((array) ($_POST['label'] ?? []) as $id => $label) {
            $label = mb_substr(trim((string) $label), 0, 120);
            if ($label === '') continue;
            $upd->execute([$label, (int) ($_POST['sort'][$id] ?? 0), empty($_POST['active'][$id]) ? 0 : 1, (int) $id, $factory]);
        }
        $new = mb_substr(trim((string) ($_POST['new_label'] ?? '')), 0, 120);
        if ($new !== '') {
            $mx = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM factory_remake_reasons WHERE factory_client_id = ?');
            $mx->execute([$factory]);
            $pdo->prepare('INSERT INTO factory_remake_reasons (factory_client_id, label, sort_order) VALUES (?, ?, ?)')
                ->execute([$factory, $new, (int) $mx->fetchColumn() + 10]);
        }
        $_SESSION['flash_success'] = 'Reasons saved.';
    } catch (Throwable $e) {
        error_log('remake-reasons: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Couldn’t save the reasons — please try again.';
    }
    header('Location: /factory/remake-reasons.php');
    exit;
}

$rows = $ready ? rm_reasons($pdo, $factory, false) : [];
$activeNav = 'remakes';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Remake reasons &middot; Factory Console &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
      .rr { background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:1rem 1.15rem; max-width:40rem;
            display:flex; flex-direction:column; gap:.6rem; }
      .rr-row { display:grid; grid-template-columns:4.5rem 1fr auto; gap:.6rem; align-items:center; }
      .rr input[type=text], .rr input[type=number] { width:100%; padding:.45rem .6rem; border:1px solid var(--border-strong); border-radius:8px;
            background:var(--bg-input); color:var(--text-body); font:inherit; }
      .rr-head { font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-faint); font-weight:600; }
      .rr-off input[type=text] { color:var(--text-faint); }
    </style>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/../_partials/sidebar.php'; ?>
<main class="app-main">
  <div class="page-header">
    <div>
      <h1 class="page-title">Remake reasons</h1>
      <p class="page-subtitle ui-hint">The reasons offered when a remake is raised — by you or by an account. Untick <b>In use</b> to retire one;
        remakes that already used it keep it.</p>
    </div>
    <a href="/factory/remakes.php" class="btn">Back to remakes</a>
  </div>
  <?php foreach (['flash_success' => 'alert-success', 'flash_error' => 'alert-error'] as $k => $cls): ?>
    <?php if (!empty($_SESSION[$k])): ?><div class="alert <?= $cls ?>" role="alert"><?= e((string) $_SESSION[$k]) ?></div><?php unset($_SESSION[$k]); endif; ?>
  <?php endforeach; ?>
  <?php if (!$ready): ?>
    <div class="alert alert-error" role="alert">Remakes need their migration — run <code>/setup/migrations/migrate_remakes.php</code> once, then reload.</div>
  <?php else: ?>
    <form method="post" class="rr">
      <?= csrf_field() ?>
      <div class="rr-row rr-head"><span>Order</span><span>Reason</span><span>In use</span></div>
      <?php foreach ($rows as $r): $id = (int) $r['id']; ?>
        <div class="rr-row<?= (int) $r['active'] ? '' : ' rr-off' ?>">
          <input type="number" name="sort[<?= $id ?>]" value="<?= (int) $r['sort_order'] ?>" aria-label="Order">
          <input type="text" name="label[<?= $id ?>]" value="<?= e((string) $r['label']) ?>" maxlength="120" aria-label="Reason">
          <input type="checkbox" name="active[<?= $id ?>]" value="1"<?= (int) $r['active'] ? ' checked' : '' ?> aria-label="In use">
        </div>
      <?php endforeach; ?>
      <div class="rr-row"><span class="rr-head">New</span>
        <input type="text" name="new_label" maxlength="120" placeholder="Add a reason, e.g. Damaged in transit" aria-label="New reason"><span></span></div>
      <div><button class="btn btn-primary">Save reasons</button></div>
    </form>
  <?php endif; ?>
</main>
</div>
</body>
</html>
