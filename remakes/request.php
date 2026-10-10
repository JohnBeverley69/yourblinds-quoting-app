<?php
declare(strict_types=1);

/**
 * Report a problem / ask for a remake — the TRADE ACCOUNT's side.
 *
 * From one of their own placed orders that we supplied: tick the blinds, pick a
 * reason, say what's wrong, add a photo if they like. It goes to the factory as
 * "waiting for approval" — nothing is made until the factory approves it (John's
 * rule). The account then sees the outcome on their order.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/remakes.php';
require_once __DIR__ . '/../_partials/remake_form.php';

requireLogin();

$user     = current_user();
$pdo      = db();
$clientId = (int) $user['client_id'];
$perms    = current_user_permissions();
$isAdmin  = ($user['role'] ?? '') === 'admin';
if (!$isAdmin && empty($perms['can_create_orders']) && empty($perms['can_create_quotes'])) {
    http_response_code(403);
    exit('You don’t have permission to report a problem on an order.');
}

$qid = (int) ($_GET['order'] ?? $_POST['order'] ?? 0);
// The factory that supplied it: the account's orders go to the one factory (env).
$factory = function_exists('factory_client_id') ? (int) factory_client_id() : 3;

$src = null;
if (rm_ready($pdo)) {
    $src = rm_source_order($pdo, $factory, $qid);
    if ($src && (int) $src['client_id'] !== $clientId && (int) ($src['account_client_id'] ?? 0) !== $clientId) $src = null;
}
$lines = $src ? rm_order_lines($pdo, $factory, $qid) : [];
$error = '';
$old   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $src) {
    csrf_check();
    $old = $_POST;
    $photo = '';   // tracked so a failed save can bin the upload
    try {
        $photo = rm_store_photo($_FILES['photo'] ?? []);
        rm_create($pdo, $factory, $qid, (array) ($_POST['items'] ?? []), (int) ($_POST['reason_id'] ?? 0),
                  (string) ($_POST['note'] ?? ''), $photo, 'account', (int) ($user['user_id'] ?? 0));
        $_SESSION['flash_success'] = 'Thanks — your remake request has gone to the factory. You’ll see their answer on this order.';
        header('Location: /quote-builder/edit.php?id=' . $qid);
        exit;
    } catch (RuntimeException $e) {
        rm_discard_photo($photo);   // nothing saved — do not leave the upload on disk
        $error = $e->getMessage();
    } catch (Throwable $e) {
        rm_discard_photo($photo);
        error_log('remake request: ' . $e->getMessage());
        $error = 'Something went wrong sending your request — nothing was sent. Please try again.';
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report a problem &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
      .rq { background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:1.1rem 1.25rem; max-width:48rem;
            display:flex; flex-direction:column; gap:1.25rem; }
    </style>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/../_partials/sidebar.php'; ?>
<main class="app-main">
  <div class="page-header">
    <div>
      <h1 class="page-title">Report a problem</h1>
      <?php if ($src): ?>
        <p class="page-subtitle">Order <b><?= e((string) $src['quote_number']) ?></b>. Tell the factory which blinds need remaking and why.
          They’ll check it and let you know on this order.</p>
      <?php endif; ?>
    </div>
    <?php if ($src): ?><a href="/quote-builder/edit.php?id=<?= (int) $qid ?>" class="btn">Back to the order</a><?php endif; ?>
  </div>
  <?php if (!rm_ready($pdo)): ?>
    <div class="alert alert-error" role="alert">This isn’t available yet.</div>
  <?php elseif (!$src): ?>
    <div class="alert alert-error" role="alert">You can report a problem on an order once it has been placed with the factory.</div>
  <?php else: ?>
    <?php if ($error !== ''): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="rq">
      <?= csrf_field() ?>
      <input type="hidden" name="order" value="<?= (int) $qid ?>">
      <?php rm_form_fields(array_map(static function ($l) { $l['unit_trade'] = 0; return $l; }, $lines), rm_reasons($pdo, $factory), $old); ?>
      <div><button class="btn btn-primary">Send to the factory</button></div>
    </form>
  <?php endif; ?>
</main>
</div>
</body>
</html>
