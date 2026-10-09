<?php
declare(strict_types=1);

/**
 * Factory Console · Raise a remake on an order (the office's own remake).
 *
 * Pick the blinds, a reason, a note and optionally a photo, decide who pays and a
 * due date — it is approved there and then and becomes a REMAKE order in Incoming
 * orders. (An account's own request comes in via /remakes/request.php instead and
 * waits for approval on the Remakes page.)
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/remakes.php';
require_once __DIR__ . '/../_partials/remake_form.php';

requireFactoryOffice();

$user    = current_user();
$pdo     = db();
$factory = ar_factory_id();
$qid     = (int) ($_GET['order'] ?? $_POST['order'] ?? 0);

$error = '';
$old   = [];
$src   = rm_ready($pdo) ? rm_source_order($pdo, $factory, $qid) : null;
$lines = $src ? rm_order_lines($pdo, $factory, $qid) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $src) {
    csrf_check();
    $old = $_POST;
    try {
        $photo = rm_store_photo($_FILES['photo'] ?? []);
        $pdo->beginTransaction();
        $rid = rm_create($pdo, $factory, $qid, (array) ($_POST['items'] ?? []), (int) ($_POST['reason_id'] ?? 0),
                         (string) ($_POST['note'] ?? ''), $photo, 'factory', (int) ($user['user_id'] ?? 0));
        $newId = rm_approve($pdo, $factory, $rid, (string) ($_POST['charge_mode'] ?? ''), (float) ($_POST['charge_amount'] ?? 0),
                            (string) ($_POST['supplier_name'] ?? ''), (string) ($_POST['due_date'] ?? ''), (int) ($user['user_id'] ?? 0));
        $pdo->commit();
        $num = (string) (rm_get($pdo, $factory, $rid)['remake_number'] ?? '');
        $_SESSION['flash_success'] = 'Remake ' . $num . ' raised — it’s in Incoming orders as a new order.';
        header('Location: /factory/remakes.php?view=open');
        exit;
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('remake-new: ' . $e->getMessage());
        $error = 'Something went wrong raising the remake — nothing was saved. Please try again.';
    }
}

$activeNav = 'remakes';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Raise a remake &middot; Factory Console &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
      .rn-card { background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:1.1rem 1.25rem; max-width:48rem;
                 display:flex; flex-direction:column; gap:1.25rem; }
      .rn-meta { color:var(--text-muted); }
    </style>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/../_partials/sidebar.php'; ?>
<main class="app-main">
  <div class="page-header">
    <div>
      <h1 class="page-title">Raise a remake</h1>
      <?php if ($src): ?>
        <p class="page-subtitle rn-meta">On order <b><?= e((string) $src['quote_number']) ?></b>
          for <?= e((string) (rm_get_account_name($pdo, (int) $src['_account_id']))) ?>
          <?= trim((string) ($src['customer_reference'] ?? '')) !== '' ? ' · their ref ' . e((string) $src['customer_reference']) : '' ?></p>
      <?php endif; ?>
    </div>
    <a href="/factory/remakes.php" class="btn">All remakes</a>
  </div>

  <?php if (!rm_ready($pdo)): ?>
    <div class="alert alert-error" role="alert">Remakes need their migration — run <code>/migrate_remakes.php</code> once, then reload.</div>
  <?php elseif (!$src): ?>
    <div class="alert alert-error" role="alert">That order can’t have a remake — it isn’t a placed order with our blinds on it.
      <a href="/factory/orders.php">Back to orders</a></div>
  <?php else: ?>
    <?php if ($error !== ''): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="rn-card">
      <?= csrf_field() ?>
      <input type="hidden" name="order" value="<?= (int) $qid ?>">
      <?php rm_form_fields($lines, rm_reasons($pdo, $factory), $old); ?>
      <?php rm_decision_fields('new', 0.0, $old); ?>
      <div><button class="btn btn-primary">Raise remake</button></div>
    </form>
  <?php endif; ?>
</main>
</div>
</body>
</html>
