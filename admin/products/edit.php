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

// Existing per-product markup / discount overrides (Phase 3.1).
// 0 / no row = use the tenant default. Any positive number = override.
$mStmt = db()->prepare(
    'SELECT markup_percent FROM client_markups
      WHERE client_id = ? AND product_id = ? LIMIT 1'
);
$mStmt->execute([$clientId, $id]);
$mVal = $mStmt->fetchColumn();

$dStmt = db()->prepare(
    'SELECT discount_percent FROM client_discounts
      WHERE client_id = ? AND product_id = ? LIMIT 1'
);
$dStmt->execute([$clientId, $id]);
$dVal = $dStmt->fetchColumn();

// Default markup from client_settings — shown as a hint so users know what
// the engine will fall back to when the per-product override is 0.
$defStmt = db()->prepare(
    'SELECT default_markup_percent FROM client_settings WHERE client_id = ? LIMIT 1'
);
$defStmt->execute([$clientId]);
$defaultMarkup = (float) ($defStmt->fetchColumn() ?: 0);

$f = [
    'name'             => (string) $product['name'],
    'active'           => (int)    $product['active'],
    'markup_percent'   => $mVal !== false ? (string) $mVal : '0.00',
    'discount_percent' => $dVal !== false ? (string) $dVal : '0.00',
];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $f['name']             = trim((string) ($_POST['name'] ?? ''));
    $f['active']           = !empty($_POST['active']) ? 1 : 0;
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

            // option_label is no longer set from the form — left at the
            // schema default. Force any stale 'Master Admin'-style values
            // back to 'Fabric' here too so display lines up immediately.
            // sort_order is intentionally not touched here — drag-and-drop
            // on the products list is the only writer.
            $u = $pdo->prepare(
                "UPDATE products
                    SET name = ?, active = ?, option_label = 'Fabric'
                  WHERE id = ? AND client_id = ?"
            );
            $u->execute([$f['name'], $f['active'], $id, $clientId]);

            // Markup: 0 = remove the override (engine will fall through to
            // the tenant default); >0 = upsert.
            $mp = (float) $f['markup_percent'];
            if ($mp > 0) {
                $pdo->prepare(
                    'INSERT INTO client_markups (client_id, product_id, markup_percent)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE markup_percent = VALUES(markup_percent)'
                )->execute([$clientId, $id, $mp]);
            } else {
                $pdo->prepare(
                    'DELETE FROM client_markups
                      WHERE client_id = ? AND product_id = ?'
                )->execute([$clientId, $id]);
            }

            // Discount: same pattern. 0 = no discount.
            $dp = (float) $f['discount_percent'];
            if ($dp > 0) {
                $pdo->prepare(
                    'INSERT INTO client_discounts (client_id, product_id, discount_percent)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE discount_percent = VALUES(discount_percent)'
                )->execute([$clientId, $id, $dp]);
            } else {
                $pdo->prepare(
                    'DELETE FROM client_discounts
                      WHERE client_id = ? AND product_id = ?'
                )->execute([$clientId, $id]);
            }

            $pdo->commit();
            $_SESSION['flash_success'] = 'Product updated.';
            header('Location: /admin/products/index.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
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
        .toggle-stack {
            display: flex; flex-direction: column; gap: 0.625rem;
            margin: 1.25rem 0;
        }
        .toggle-stack label {
            display: inline-flex; align-items: center; gap: 0.5rem;
            font-size: 0.9375rem; color: #111827; cursor: pointer;
            margin: 0; padding: 0;
        }
        .toggle-stack input[type="checkbox"] { width: 18px; height: 18px; }
        .toggle-stack small {
            color: #6b7280; font-size: 0.8125rem; margin-left: 0.375rem;
        }
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

                <fieldset style="border:1px solid #e5e7eb;border-radius:10px;padding:1rem 1.125rem;margin:1rem 0">
                    <legend style="padding:0 0.5rem;font-size:0.8125rem;font-weight:600;color:#1f3b5b;text-transform:uppercase;letter-spacing:0.05em">
                        Pricing overrides
                    </legend>
                    <p style="color:#6b7280;font-size:0.875rem;margin:0 0 0.75rem">
                        Per-product markup and discount, applied by the pricing engine on top of the price-table base.
                        <strong>Markup</strong>: leave at 0 to use the tenant default
                        (currently <strong><?= number_format($defaultMarkup, 2) ?>%</strong>, set in Settings).
                        <strong>Discount</strong>: leave at 0 for no discount.
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

                <div class="toggle-stack">
                    <label for="active">
                        <input type="checkbox" id="active" name="active" value="1"
                               <?= (int) $f['active'] === 1 ? 'checked' : '' ?>>
                        Active
                        <small>uncheck to hide from quote builder</small>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="/admin/products/index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Manage</h2>
            </div>
            <div class="actions-bar" style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <a href="/admin/products/options.php?product_id=<?= (int) $id ?>"
                   class="btn btn-secondary">Fabrics &rarr;</a>
                <a href="/admin/products/systems.php?product_id=<?= (int) $id ?>"
                   class="btn btn-secondary">Systems &rarr;</a>
                <a href="/admin/products/extras.php?product_id=<?= (int) $id ?>"
                   class="btn btn-secondary">Extras &rarr;</a>
            </div>
        </section>
    </main>
</div>

</body>
</html>
