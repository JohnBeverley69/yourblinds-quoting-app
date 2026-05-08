<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$f = [
    'name'         => '',
    'option_label' => 'Fabric',
    'sort_order'   => 0,
    'active'       => 1,
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
        $error = 'Option label is required (e.g. "Fabric" or "Slat type").';
    } elseif (strlen($f['option_label']) > 50) {
        $error = 'Option label is too long (max 50 characters).';
    } else {
        try {
            $stmt = db()->prepare(
                'INSERT INTO products (client_id, name, option_label, sort_order, active)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $clientId,
                $f['name'],
                $f['option_label'],
                $f['sort_order'],
                $f['active'],
            ]);
            $_SESSION['flash_success'] = 'Product "' . $f['name'] . '" added.';
            header('Location: /admin/products/index.php');
            exit;
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'uniq_product_client_name')) {
                $error = 'A product with that name already exists.';
            } else {
                $error = 'Could not add product: ' . $e->getMessage();
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
    <title>New product &middot; YourBlinds</title>
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
                <h1 class="page-title">New product</h1>
                <p class="page-subtitle">
                    <a href="/admin/products/index.php">&larr; Back to products</a>
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section">
            <form method="post" action="/admin/products/new.php" class="form" novalidate>
                <?= csrf_field() ?>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="name">Name <span class="required">*</span></label>
                        <input id="name" name="name" type="text"
                               required maxlength="150" autofocus
                               value="<?= e((string) $f['name']) ?>"
                               placeholder="e.g. Vertical Blinds">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="option_label">Option label <span class="required">*</span></label>
                        <input id="option_label" name="option_label" type="text"
                               required maxlength="50"
                               value="<?= e((string) $f['option_label']) ?>"
                               placeholder="Fabric">
                        <small style="color:#6b7280;font-size:0.8125rem;">
                            Drives the picker label in the quote builder. Usually "Fabric"; use "Slat type" for woods/faux venetians.
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="sort_order">Sort order</label>
                        <input id="sort_order" name="sort_order" type="number" value="<?= (int) $f['sort_order'] ?>">
                    </div>
                </div>

                <label class="checkbox-row" for="active">
                    <input type="checkbox" id="active" name="active" value="1"
                           <?= (int) $f['active'] === 1 ? 'checked' : '' ?>>
                    Active (uncheck to hide from quote builder without deleting)
                </label>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add product</button>
                    <a href="/admin/products/index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>

</body>
</html>
