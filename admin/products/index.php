<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$rows = db()->prepare(
    'SELECT p.id, p.name, p.option_label, p.sort_order, p.active, p.updated_at,
            (SELECT COUNT(*) FROM product_options o WHERE o.product_id = p.id) AS option_count,
            (SELECT COUNT(*) FROM product_extras  e WHERE e.product_id = p.id) AS extra_count,
            (SELECT COUNT(*) FROM price_tables    t WHERE t.product_id = p.id) AS table_count
       FROM products p
      WHERE p.client_id = ?
   ORDER BY p.sort_order, p.name'
);
$rows->execute([$clientId]);
$products = $rows->fetchAll();

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Products &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .meta-cell { font-size: 0.8125rem; color: #6b7280; white-space: nowrap; }
        .row-actions { white-space: nowrap; }
        .row-actions a { font-size: 0.875rem; margin-left: 0.5rem; }
        .row-actions form { display: inline; margin: 0; }
        .row-actions button {
            font-size: 0.875rem; color: #b91c1c; background: transparent;
            border: 0; cursor: pointer; padding: 0; margin-left: 0.5rem;
        }
        .row-actions button:hover { text-decoration: underline; }
        .product-name { font-weight: 600; color: #111827; }
        .inactive-pill {
            display: inline-block; padding: 0.0625rem 0.5rem;
            font-size: 0.6875rem; font-weight: 600; color: #6b7280;
            background: #f3f4f6; border-radius: 999px; margin-left: 0.5rem;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Products</h1>
                <p class="page-subtitle">
                    The product types you sell. Each product has its own options
                    (fabrics/slats), extras (control side, lining, etc.) and
                    price tables.
                </p>
            </div>
            <a href="/admin/products/new.php" class="btn btn-primary">+ New product</a>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <section class="section">
            <?php if (!$products): ?>
                <div class="placeholder">
                    <p class="placeholder-title">No products yet</p>
                    <p class="placeholder-body">
                        Add your first product to start building options, extras and price tables.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Option label</th>
                                <th class="num">Options</th>
                                <th class="num">Extras</th>
                                <th class="num">Price tables</th>
                                <th>Updated</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td>
                                        <span class="product-name"><?= e((string) $p['name']) ?></span>
                                        <?php if ((int) $p['active'] !== 1): ?>
                                            <span class="inactive-pill">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e((string) $p['option_label']) ?></td>
                                    <td class="num">
                                        <a href="/admin/products/options.php?product_id=<?= (int) $p['id'] ?>">
                                            <?= (int) $p['option_count'] ?>
                                        </a>
                                    </td>
                                    <td class="num"><?= (int) $p['extra_count']  ?></td>
                                    <td class="num"><?= (int) $p['table_count']  ?></td>
                                    <td class="meta-cell">
                                        <?= e((string) $p['updated_at']) ?>
                                    </td>
                                    <td class="row-actions">
                                        <a href="/admin/products/edit.php?id=<?= (int) $p['id'] ?>">Edit</a>
                                        <form method="post"
                                              action="/admin/products/delete.php"
                                              onsubmit="return confirm('Delete <?= e(addslashes((string) $p['name'])) ?>? This removes all options, extras, and price tables linked to it. Cannot be undone.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <button type="submit">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
