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

$loadStmt = db()->prepare(
    'SELECT id, name, option_label, sort_order, active
       FROM products WHERE id = ? AND client_id = ?'
);
$loadStmt->execute([$id, $clientId]);
$product = $loadStmt->fetch();

if (!$product) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Product not found</h1>'
       . '<p><a href="/admin/products/index.php">Back to products</a></p>';
    exit;
}

$f = [
    'name'         => (string) $product['name'],
    'option_label' => (string) $product['option_label'],
    'sort_order'   => (int)    $product['sort_order'],
    'active'       => (int)    $product['active'],
];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $f['name']         = trim((string) ($_POST['name']         ?? ''));
    $f['option_label'] = trim((string) ($_POST['option_label'] ?? 'Fabric'));
    $f['sort_order']   = (int) ($_POST['sort_order'] ?? 0);
    $f['active']       = !empty($_POST['active']) ? 1 : 0;

    if ($f['name'] === '') {
        $error = 'Product name is required.';
    } elseif (strlen($f['name']) > 150) {
        $error = 'Product name is too long (max 150 characters).';
    } elseif ($f['option_label'] === '') {
        $error = 'Option label is required.';
    } elseif (strlen($f['option_label']) > 50) {
        $error = 'Option label is too long (max 50 characters).';
    } else {
        try {
            $u = db()->prepare(
                'UPDATE products
                    SET name = ?, option_label = ?, sort_order = ?, active = ?
                  WHERE id = ? AND client_id = ?'
            );
            $u->execute([
                $f['name'],
                $f['option_label'],
                $f['sort_order'],
                $f['active'],
                $id,
                $clientId,
            ]);
            $_SESSION['flash_success'] = 'Product updated.';
            header('Location: /admin/products/index.php');
            exit;
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'uniq_product_client_name')) {
                $error = 'A product with that name already exists.';
            } else {
                $error = 'Could not save product: ' . $e->getMessage();
            }
        }
    }
}

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit product &middot; YourBlinds</title>
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
                <h1 class="page-title">Edit product</h1>
                <p class="page-subtitle">
                    <a href="/admin/products/index.php">&larr; Back to products</a>
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section">
            <form method="post" action="/admin/products/edit.php?id=<?= (int) $id ?>"
                  class="form" novalidate>
                <?= csrf_field() ?>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="name">Name <span class="required">*</span></label>
                        <input id="name" name="name" type="text"
                               required maxlength="150"
                               value="<?= e((string) $f['name']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="option_label">Option label <span class="required">*</span></label>
                        <?php
                            $stdLabels = ['Fabric', 'Slat type'];
                            $current   = $f['option_label'];
                            $isCustom  = !in_array($current, $stdLabels, true) && $current !== '';
                        ?>
                        <select id="option_label" name="option_label">
                            <option value="Fabric"    <?= $current === 'Fabric'    ? 'selected' : '' ?>>Fabric</option>
                            <option value="Slat type" <?= $current === 'Slat type' ? 'selected' : '' ?>>Slat type</option>
                            <option value="__custom"  <?= $isCustom ? 'selected' : '' ?>>Other (specify)…</option>
                        </select>
                        <input type="text" id="option_label_custom" name="option_label_custom"
                               maxlength="50" style="margin-top: 0.5rem;<?= $isCustom ? '' : 'display:none;' ?>"
                               value="<?= $isCustom ? e((string) $current) : '' ?>"
                               placeholder="Custom label">
                    </div>
                    <div class="form-group">
                        <label for="sort_order">Sort order</label>
                        <input id="sort_order" name="sort_order" type="number" value="<?= (int) $f['sort_order'] ?>">
                    </div>
                </div>

                <label class="checkbox-row" for="active">
                    <input type="checkbox" id="active" name="active" value="1"
                           <?= (int) $f['active'] === 1 ? 'checked' : '' ?>>
                    Active
                </label>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="/admin/products/index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>

<script>
(function () {
    var sel    = document.getElementById('option_label');
    var custom = document.getElementById('option_label_custom');
    if (!sel || !custom) return;
    function sync() {
        custom.style.display = sel.value === '__custom' ? '' : 'none';
        custom.required = sel.value === '__custom';
    }
    sel.form.addEventListener('submit', function () {
        if (sel.value === '__custom') {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'option_label';
            hidden.value = (custom.value || '').trim() || 'Other';
            sel.form.appendChild(hidden);
            sel.disabled = true;
        }
    });
    sel.addEventListener('change', sync);
    sync();
})();
</script>
</body>
</html>
