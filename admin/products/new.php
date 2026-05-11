<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$f = [
    'name'             => '',
    'markup_percent'   => '0.00',
    'discount_percent' => '0.00',
];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $f['name']             = trim((string) ($_POST['name'] ?? ''));
    $f['markup_percent']   = trim((string) ($_POST['markup_percent']   ?? '0'));
    $f['discount_percent'] = trim((string) ($_POST['discount_percent'] ?? '0'));

    if ($f['name'] === '') {
        $error = 'Product name is required.';
    } elseif (strlen($f['name']) > 150) {
        $error = 'Product name is too long (max 150 characters).';
    } elseif (!is_numeric($f['markup_percent']) || (float) $f['markup_percent'] < 0) {
        $error = 'Markup % must be a non-negative number.';
    } elseif (!is_numeric($f['discount_percent']) || (float) $f['discount_percent'] < 0) {
        $error = 'Discount % must be a non-negative number.';
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            // option_label uses the schema default ('Fabric') — no longer
            // settable from the form. sort_order = MAX+1 so new products
            // append to the end of the list (drag-and-drop owns ordering).
            // active hard-coded to 1 — new products always start active;
            // flip via the edit page if you need to hide one.
            $sortStmt = $pdo->prepare(
                'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM products WHERE client_id = ?'
            );
            $sortStmt->execute([$clientId]);
            $nextSort = (int) $sortStmt->fetchColumn();

            $stmt = $pdo->prepare(
                'INSERT INTO products (client_id, name, sort_order, active)
                 VALUES (?, ?, ?, 1)'
            );
            $stmt->execute([
                $clientId,
                $f['name'],
                $nextSort,
            ]);
            $newProductId = (int) $pdo->lastInsertId();

            // Markup row goes in straight away so the pricing engine has
            // an explicit value for every product — no NULL fall-backs.
            $pdo->prepare(
                'INSERT INTO client_markups (client_id, product_id, markup_percent)
                 VALUES (?, ?, ?)'
            )->execute([$clientId, $newProductId, (float) $f['markup_percent']]);

            // Discount only if the user actually entered one.
            $dp = (float) $f['discount_percent'];
            if ($dp > 0) {
                $pdo->prepare(
                    'INSERT INTO client_discounts (client_id, product_id, discount_percent)
                     VALUES (?, ?, ?)'
                )->execute([$clientId, $newProductId, $dp]);
            }

            $pdo->commit();
            $_SESSION['flash_success'] = 'Product "' . $f['name'] . '" added.';
            header('Location: /admin/products/index.php');
            exit;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
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

                <fieldset style="border:1px solid #e5e7eb;border-radius:10px;padding:1rem 1.125rem;margin:1rem 0">
                    <legend style="padding:0 0.5rem;font-size:0.8125rem;font-weight:600;color:#1f3b5b;text-transform:uppercase;letter-spacing:0.05em">
                        Pricing
                    </legend>
                    <p style="color:#6b7280;font-size:0.875rem;margin:0 0 0.75rem">
                        Markup is added on top of the price-table base to get the sell
                        price. Discount comes off after that. You can change these any
                        time from the product Edit page.
                    </p>
                    <div class="form-row cols-2">
                        <div class="form-group">
                            <label for="markup_percent">Markup %</label>
                            <input id="markup_percent" name="markup_percent" type="number"
                                   step="0.01" min="0"
                                   value="<?= e((string) $f['markup_percent']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="discount_percent">Discount %</label>
                            <input id="discount_percent" name="discount_percent" type="number"
                                   step="0.01" min="0"
                                   value="<?= e((string) $f['discount_percent']) ?>">
                        </div>
                    </div>
                </fieldset>

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
