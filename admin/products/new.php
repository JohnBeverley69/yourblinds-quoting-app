<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$f = ['name' => ''];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $f['name'] = trim((string) ($_POST['name'] ?? ''));

    if ($f['name'] === '') {
        $error = 'Product name is required.';
    } elseif (strlen($f['name']) > 150) {
        $error = 'Product name is too long (max 150 characters).';
    } else {
        try {
            // option_label uses the schema default ('Fabric') — no longer
            // settable from the form. sort_order = MAX+1 so new products
            // append to the end of the list (drag-and-drop owns ordering).
            // active hard-coded to 1 — new products always start active;
            // flip via the edit page if you need to hide one.
            $sortStmt = db()->prepare(
                'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM products WHERE client_id = ?'
            );
            $sortStmt->execute([$clientId]);
            $nextSort = (int) $sortStmt->fetchColumn();

            $stmt = db()->prepare(
                'INSERT INTO products (client_id, name, sort_order, active)
                 VALUES (?, ?, ?, 1)'
            );
            $stmt->execute([
                $clientId,
                $f['name'],
                $nextSort,
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
