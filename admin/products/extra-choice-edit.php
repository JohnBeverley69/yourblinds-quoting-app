<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /admin/products/index.php');
    exit;
}

// Tenant-scoped lookup of the choice + parents.
$loadStmt = db()->prepare(
    'SELECT c.id, c.product_extra_id, c.label, c.price_delta, c.price_percent,
            c.is_default, c.sort_order, c.active,
            e.name AS extra_name, e.product_id, e.client_id,
            p.name AS product_name
       FROM product_extra_choices c
       JOIN product_extras e ON e.id = c.product_extra_id
       JOIN products p       ON p.id = e.product_id
      WHERE c.id = ? AND e.client_id = ?'
);
$loadStmt->execute([$id, $clientId]);
$choice = $loadStmt->fetch();

if (!$choice) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Choice not found</h1>';
    exit;
}

$f = [
    'label'         => (string) $choice['label'],
    'price_delta'   => (string) $choice['price_delta'],
    'price_percent' => (string) $choice['price_percent'],
    'is_default'    => (int)    $choice['is_default'],
    'sort_order'    => (int)    $choice['sort_order'],
    'active'        => (int)    $choice['active'],
];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $f['label']         = trim((string) ($_POST['label'] ?? ''));
    $f['price_delta']   = trim((string) ($_POST['price_delta']   ?? '0'));
    $f['price_percent'] = trim((string) ($_POST['price_percent'] ?? '0'));
    $f['is_default']    = !empty($_POST['is_default']) ? 1 : 0;
    $f['sort_order']    = (int) ($_POST['sort_order'] ?? 0);
    $f['active']        = !empty($_POST['active']) ? 1 : 0;

    if ($f['label'] === '') {
        $error = 'Label is required.';
    } elseif (strlen($f['label']) > 150) {
        $error = 'Label is too long (max 150 chars).';
    } elseif (!is_numeric($f['price_delta'])) {
        $error = 'Flat surcharge must be a number.';
    } elseif (!is_numeric($f['price_percent'])) {
        $error = 'Percent surcharge must be a number.';
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            if ($f['is_default'] === 1) {
                $clear = $pdo->prepare(
                    'UPDATE product_extra_choices SET is_default = 0
                      WHERE product_extra_id = ? AND id != ?'
                );
                $clear->execute([(int) $choice['product_extra_id'], $id]);
            }

            $u = $pdo->prepare(
                'UPDATE product_extra_choices
                    SET label = ?, price_delta = ?, price_percent = ?,
                        is_default = ?, sort_order = ?, active = ?
                  WHERE id = ?'
            );
            $u->execute([
                $f['label'],
                (float) $f['price_delta'],
                (float) $f['price_percent'],
                $f['is_default'],
                $f['sort_order'],
                $f['active'],
                $id,
            ]);
            $pdo->commit();

            $_SESSION['flash_success'] = 'Choice updated.';
            header('Location: /admin/products/extra.php?id=' . (int) $choice['product_extra_id']);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Could not save: ' . $e->getMessage();
        }
    }
}

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit choice &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .form-group input[type="number"] {
            width: 100%; font: inherit; padding: 0.5625rem 0.75rem;
            border: 1px solid #d1d5db; border-radius: 8px; background: #fff;
        }
        .checkbox-row {
            display: inline-flex; align-items: center; gap: 0.5rem;
            margin-bottom: 1rem; font-size: 0.9375rem; color: #111827; cursor: pointer;
        }
        .checkbox-row input { width: 18px; height: 18px; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Edit choice</h1>
                <p class="page-subtitle">
                    <a href="/admin/products/extra.php?id=<?= (int) $choice['product_extra_id'] ?>">
                        &larr; Back to <?= e((string) $choice['product_name']) ?>
                        / <?= e((string) $choice['extra_name']) ?>
                    </a>
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section">
            <form method="post" action="/admin/products/extra-choice-edit.php?id=<?= (int) $id ?>"
                  class="form" novalidate>
                <?= csrf_field() ?>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="label">Label <span class="required">*</span></label>
                        <input id="label" name="label" type="text"
                               required maxlength="150"
                               value="<?= e((string) $f['label']) ?>">
                    </div>
                </div>

                <div class="form-row cols-3">
                    <div class="form-group">
                        <label for="price_delta">Flat surcharge (£)</label>
                        <input id="price_delta" name="price_delta" type="number"
                               step="0.01" value="<?= e((string) $f['price_delta']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="price_percent">Percent surcharge (%)</label>
                        <input id="price_percent" name="price_percent" type="number"
                               step="0.01" value="<?= e((string) $f['price_percent']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="sort_order">Sort order</label>
                        <input id="sort_order" name="sort_order" type="number"
                               value="<?= (int) $f['sort_order'] ?>">
                    </div>
                </div>

                <label class="checkbox-row" for="is_default">
                    <input type="checkbox" id="is_default" name="is_default" value="1"
                           <?= (int) $f['is_default'] === 1 ? 'checked' : '' ?>>
                    Default (pre-selected for the customer)
                </label>
                <br>
                <label class="checkbox-row" for="active">
                    <input type="checkbox" id="active" name="active" value="1"
                           <?= (int) $f['active'] === 1 ? 'checked' : '' ?>>
                    Active
                </label>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="/admin/products/extra.php?id=<?= (int) $choice['product_extra_id'] ?>"
                       class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
