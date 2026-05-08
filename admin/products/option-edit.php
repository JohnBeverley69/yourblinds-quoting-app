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

// Tenant-scoped lookup of the option + its parent product.
$loadStmt = db()->prepare(
    'SELECT o.id, o.product_id, o.band_code, o.supplier_name, o.name,
            o.colour, o.code, o.sort_order, o.active,
            p.name AS product_name, p.option_label
       FROM product_options o
       JOIN products p ON p.id = o.product_id
      WHERE o.id = ? AND o.client_id = ?'
);
$loadStmt->execute([$id, $clientId]);
$option = $loadStmt->fetch();

if (!$option) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Option not found</h1>'
       . '<p><a href="/admin/products/index.php">Back to products</a></p>';
    exit;
}

$label  = 'Fabric';
$labelL = 'fabric';

$f = [
    'band_code'     => (string) $option['band_code'],
    'supplier_name' => (string) ($option['supplier_name'] ?? ''),
    'name'          => (string) $option['name'],
    'colour'        => (string) ($option['colour'] ?? ''),
    'code'          => (string) ($option['code'] ?? ''),
    'sort_order'    => (int)    $option['sort_order'],
    'active'        => (int)    $option['active'],
];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    foreach (['band_code','supplier_name','name','colour','code'] as $k) {
        $f[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $f['sort_order'] = (int) ($_POST['sort_order'] ?? 0);
    $f['active']     = !empty($_POST['active']) ? 1 : 0;

    if ($f['band_code'] === '') {
        $error = 'Band code is required.';
    } elseif (strlen($f['band_code']) > 20) {
        $error = 'Band code is too long (max 20 chars).';
    } elseif ($f['name'] === '') {
        $error = ucfirst($labelL) . ' name is required.';
    } elseif (strlen($f['name']) > 150) {
        $error = ucfirst($labelL) . ' name is too long (max 150 chars).';
    } else {
        try {
            $u = db()->prepare(
                'UPDATE product_options
                    SET band_code = ?, supplier_name = ?, name = ?, colour = ?,
                        code = ?, sort_order = ?, active = ?
                  WHERE id = ? AND client_id = ?'
            );
            $u->execute([
                strtoupper($f['band_code']),
                $f['supplier_name'] !== '' ? $f['supplier_name'] : null,
                $f['name'],
                $f['colour'] !== '' ? $f['colour'] : null,
                $f['code']   !== '' ? $f['code']   : null,
                $f['sort_order'],
                $f['active'],
                $id,
                $clientId,
            ]);
            $_SESSION['flash_success'] = ucfirst($labelL) . ' updated.';
            header('Location: /admin/products/options.php?product_id=' . (int) $option['product_id']);
            exit;
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'uniq_option_per_product')) {
                $error = 'A ' . $labelL . ' with that name + colour already exists for this product.';
            } else {
                $error = 'Could not save: ' . $e->getMessage();
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
    <title>Edit <?= e($labelL) ?> &middot; YourBlinds</title>
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
                <h1 class="page-title">Edit <?= e($labelL) ?></h1>
                <p class="page-subtitle">
                    <a href="/admin/products/options.php?product_id=<?= (int) $option['product_id'] ?>">
                        &larr; Back to <?= e((string) $option['product_name']) ?> &mdash; <?= e($label) ?>s
                    </a>
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section">
            <form method="post" action="/admin/products/option-edit.php?id=<?= (int) $id ?>"
                  class="form" novalidate>
                <?= csrf_field() ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="band_code">Band <span class="required">*</span></label>
                        <input id="band_code" name="band_code" type="text"
                               required maxlength="20" value="<?= e((string) $f['band_code']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="supplier_name">Supplier</label>
                        <input id="supplier_name" name="supplier_name" type="text" maxlength="150"
                               value="<?= e((string) $f['supplier_name']) ?>">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="name"><?= e($label) ?> name <span class="required">*</span></label>
                        <input id="name" name="name" type="text" required maxlength="150"
                               value="<?= e((string) $f['name']) ?>">
                    </div>
                </div>

                <div class="form-row cols-3">
                    <div class="form-group">
                        <label for="colour">Colour</label>
                        <input id="colour" name="colour" type="text" maxlength="150"
                               value="<?= e((string) $f['colour']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="code">Code</label>
                        <input id="code" name="code" type="text" maxlength="50"
                               value="<?= e((string) $f['code']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="sort_order">Sort order</label>
                        <input id="sort_order" name="sort_order" type="number"
                               value="<?= (int) $f['sort_order'] ?>">
                    </div>
                </div>

                <label class="checkbox-row" for="active">
                    <input type="checkbox" id="active" name="active" value="1"
                           <?= (int) $f['active'] === 1 ? 'checked' : '' ?>>
                    Active
                </label>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="/admin/products/options.php?product_id=<?= (int) $option['product_id'] ?>"
                       class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
