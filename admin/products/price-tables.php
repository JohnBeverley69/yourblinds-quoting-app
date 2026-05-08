<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$productId = (int) ($_GET['product_id'] ?? 0);
if ($productId <= 0) {
    header('Location: /admin/products/index.php');
    exit;
}

$pStmt = db()->prepare(
    'SELECT id, name FROM products WHERE id = ? AND client_id = ?'
);
$pStmt->execute([$productId, $clientId]);
$product = $pStmt->fetch();

if (!$product) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Product not found</h1>';
    exit;
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$f = [
    'band_code' => '',
    'name'      => '',
    'notes'     => '',
];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_action'] ?? '') === 'create') {
    csrf_check();

    foreach (['band_code','name','notes'] as $k) {
        $f[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    // Normalise: strip leading 'Band ' if user typed it.
    $f['band_code'] = preg_replace('/^band\s+/i', '', $f['band_code']);

    if ($f['band_code'] === '') {
        $error = 'Band code is required (e.g. A, B, C).';
    } elseif (strlen($f['band_code']) > 20) {
        $error = 'Band code is too long (max 20 chars).';
    } else {
        try {
            $stmt = db()->prepare(
                'INSERT INTO price_tables
                   (client_id, product_id, band_code, name, notes, active)
                 VALUES (?, ?, ?, ?, ?, 1)'
            );
            $stmt->execute([
                $clientId,
                $productId,
                strtoupper($f['band_code']),
                $f['name']  !== '' ? $f['name']  : null,
                $f['notes'] !== '' ? $f['notes'] : null,
            ]);
            $newId = (int) db()->lastInsertId();
            $_SESSION['flash_success'] = 'Price table for band "' . strtoupper($f['band_code']) . '" created.';
            header('Location: /admin/products/price-table.php?id=' . $newId);
            exit;
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'uniq_price_table_product_band')) {
                $error = 'A price table for that band already exists for this product.';
            } else {
                $error = 'Could not create: ' . $e->getMessage();
            }
        }
    }
}

// List existing tables, sorted with the same AAA → AA → A → B → C... rule
// used on the fabrics page.
$rows = db()->prepare(
    "SELECT t.id, t.band_code, t.name, t.notes, t.active, t.updated_at,
            (SELECT COUNT(*) FROM price_table_rows r WHERE r.price_table_id = t.id) AS row_count
       FROM price_tables t
      WHERE t.product_id = ? AND t.client_id = ?
   ORDER BY
        CASE
            WHEN t.band_code = 'AAA' THEN 1
            WHEN t.band_code = 'AA'  THEN 2
            WHEN t.band_code = 'A'   THEN 3
            ELSE 100
        END,
        t.band_code"
);
$rows->execute([$productId, $clientId]);
$tables = $rows->fetchAll();

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) $product['name']) ?> &middot; Price tables &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .form-row.cols-3-narrow { grid-template-columns: 1fr 2fr 3fr; }
        @media (max-width: 800px) { .form-row.cols-3-narrow { grid-template-columns: 1fr; } }
        .band-pill {
            display: inline-block; text-align: center;
            padding: 0.125rem 0.625rem; font-weight: 700; font-size: 0.8125rem;
            color: #fff; background: #1f3b5b; border-radius: 6px; white-space: nowrap;
        }
        .row-actions { white-space: nowrap; }
        .row-actions a { font-size: 0.875rem; margin-left: 0.5rem; }
        .row-actions form { display: inline; margin: 0; }
        .row-actions button {
            font-size: 0.875rem; color: #b91c1c; background: transparent;
            border: 0; cursor: pointer; padding: 0; margin-left: 0.5rem;
        }
        .row-actions button:hover { text-decoration: underline; }
        .empty-cells { color: #b45309; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <?= e((string) $product['name']) ?> &mdash; Price tables
                </h1>
                <p class="page-subtitle">
                    <a href="/admin/products/index.php">&larr; All products</a>
                    &middot;
                    <a href="/admin/products/edit.php?id=<?= (int) $productId ?>">Edit product</a>
                    &middot;
                    <a href="/admin/products/options.php?product_id=<?= (int) $productId ?>">Fabrics</a>
                </p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>
        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Add price table</h2>
            </div>
            <form method="post"
                  action="/admin/products/price-tables.php?product_id=<?= (int) $productId ?>"
                  class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="create">

                <div class="form-row cols-3-narrow">
                    <div class="form-group">
                        <label for="band_code">Band <span class="required">*</span></label>
                        <input id="band_code" name="band_code" type="text"
                               required maxlength="20" autofocus
                               value="<?= e((string) $f['band_code']) ?>" placeholder="A">
                    </div>
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input id="name" name="name" type="text" maxlength="150"
                               value="<?= e((string) $f['name']) ?>"
                               placeholder="e.g. 2026 Vertical Band A">
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <input id="notes" name="notes" type="text" maxlength="255"
                               value="<?= e((string) $f['notes']) ?>"
                               placeholder="Anything to remember about this sheet">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create price table</button>
                </div>
            </form>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Price tables (<?= count($tables) ?>)</h2>
            </div>

            <?php if (!$tables): ?>
                <div class="placeholder">
                    <p class="placeholder-title">No price tables yet</p>
                    <p class="placeholder-body">
                        Create one per fabric band. Each price table holds a width × drop matrix of prices.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Band</th>
                                <th>Name</th>
                                <th>Notes</th>
                                <th class="num">Cells</th>
                                <th>Updated</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tables as $t): ?>
                                <tr>
                                    <td><span class="band-pill">Band <?= e((string) $t['band_code']) ?></span></td>
                                    <td><?= e((string) ($t['name'] ?? '')) ?></td>
                                    <td><?= e((string) ($t['notes'] ?? '')) ?></td>
                                    <td class="num<?= ((int) $t['row_count']) === 0 ? ' empty-cells' : '' ?>">
                                        <?= (int) $t['row_count'] ?>
                                    </td>
                                    <td style="font-size:0.8125rem;color:#6b7280;white-space:nowrap">
                                        <?= e((string) $t['updated_at']) ?>
                                    </td>
                                    <td class="row-actions">
                                        <a href="/admin/products/price-table.php?id=<?= (int) $t['id'] ?>">Open</a>
                                        <form method="post" action="/admin/products/price-table-delete.php"
                                              onsubmit="return confirm('Delete the Band <?= e(addslashes((string) $t['band_code'])) ?> price table? This wipes its <?= (int) $t['row_count'] ?> cells too.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                            <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
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
