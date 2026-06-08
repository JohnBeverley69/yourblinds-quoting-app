<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$f = ['name' => '', 'option_label' => 'Fabric'];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $f['name']         = trim((string) ($_POST['name']         ?? ''));
    $f['option_label'] = trim((string) ($_POST['option_label'] ?? '')) ?: 'Fabric';

    if ($f['name'] === '') {
        $error = 'Product name is required.';
    } elseif (strlen($f['name']) > 150) {
        $error = 'Product name is too long (max 150 characters).';
    } elseif (strlen($f['option_label']) > 40) {
        $error = 'Option label is too long (max 40 characters).';
    } else {
        try {
            // option_label is set per-product — controls what the "fabric"
            // axis is called in the quote builder and admin options pages.
            // Defaults to 'Fabric' when the user leaves it blank.
            //
            // sort_order = MAX+1 so new products append to the end of the
            // list (drag-and-drop owns ordering).
            // active hard-coded to 1 — new products always start active;
            // flip via the edit page if you need to hide one.
            //
            // Markup / discount aren't set here — they live per (product,
            // system) and a fresh product has no systems yet. Once the
            // user adds systems (or leaves it system-less), they set
            // margins on the Edit page.
            $pdo = db();
            $sortStmt = $pdo->prepare(
                'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM products WHERE client_id = ?'
            );
            $sortStmt->execute([$clientId]);
            $nextSort = (int) $sortStmt->fetchColumn();

            // Smart default for show_colour_field — see wizard.php
            // step 1 for the same heuristic.
            $scfDefault = preg_match('/colou?r/i', $f['option_label']) ? 0 : 1;
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO products
                        (client_id, name, option_label, show_colour_field, sort_order, active)
                     VALUES (?, ?, ?, ?, ?, 1)'
                );
                $stmt->execute([$clientId, $f['name'], $f['option_label'], $scfDefault, $nextSort]);
            } catch (Throwable $e) {
                $stmt = $pdo->prepare(
                    'INSERT INTO products (client_id, name, option_label, sort_order, active)
                     VALUES (?, ?, ?, ?, 1)'
                );
                $stmt->execute([$clientId, $f['name'], $f['option_label'], $nextSort]);
            }
            $newProductId = (int) $pdo->lastInsertId();

            // Audit
            require_once __DIR__ . '/../../_partials/catalogue_audit.php';
            catalogue_audit_log(
                'product', $newProductId, 'create',
                $f['name'],
                null,
                ['name' => $f['name'], 'option_label' => $f['option_label']],
                $newProductId
            );

            // Drop the user straight onto the new product's Edit page so
            // they can add systems / set margins / configure options
            // without an extra click.
            $_SESSION['flash_success'] =
                'Product "' . $f['name'] . '" added. Add systems and set '
                . 'margins below.';
            header('Location: /admin/products/edit.php?id=' . $newProductId);
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
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .form-group input[type="number"] {
            width: 100%; font: inherit; padding: 0.5625rem 0.75rem;
            border: 1px solid var(--border-strong); border-radius: 8px; background: #fff;
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

                <div class="form-row full">
                    <div class="form-group">
                        <label for="option_label">Option label</label>
                        <input id="option_label" name="option_label" type="text"
                               maxlength="40"
                               value="<?= e((string) $f['option_label']) ?>"
                               placeholder="Fabric">
                        <small style="color:var(--text-faint);font-size:0.8125rem">
                            What the per-blind option axis is called for this product.
                            Use <em>Fabric</em> for rollers/romans, <em>Colour</em> for
                            metal venetians, <em>Finish</em> for wood, etc. You can change
                            it later on the product's Edit page.
                        </small>
                    </div>
                </div>

                <p style="color:var(--text-faint);font-size:0.875rem;margin:0.5rem 0 1rem">
                    Once the product's saved, the Edit page lets you add
                    systems (Standard / Premium / Motorised / …), set a margin and
                    discount per system, upload options, and configure extras.
                </p>

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
