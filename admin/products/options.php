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

// Tenant-scoped product lookup. The label drives whether we say
// "Fabric" or "Slat type" throughout the page.
$pStmt = db()->prepare(
    'SELECT id, name, option_label FROM products WHERE id = ? AND client_id = ?'
);
$pStmt->execute([$productId, $clientId]);
$product = $pStmt->fetch();

if (!$product) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Product not found</h1>'
       . '<p><a href="/admin/products/index.php">Back to products</a></p>';
    exit;
}

$label  = (string) $product['option_label'];        // "Fabric" / "Slat type"
$labelL = strtolower($label);                       // "fabric" / "slat type"

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Sticky form values — last submitted band/supplier carry over so a user
// adding 30 fabrics in band A doesn't have to retype "A" each time.
$lastBand     = (string) ($_SESSION['_options_last_band']     ?? '');
$lastSupplier = (string) ($_SESSION['_options_last_supplier'] ?? '');

$f = [
    'band_code'     => $lastBand,
    'supplier_name' => $lastSupplier,
    'name'          => '',
    'colour'        => '',
    'code'          => '',
    'sort_order'    => 0,
    'active'        => 1,
];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_action'] ?? '') === 'create') {
    csrf_check();

    foreach (['band_code','supplier_name','name','colour','code'] as $k) {
        $f[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $f['sort_order'] = (int) ($_POST['sort_order'] ?? 0);
    $f['active']     = !empty($_POST['active']) ? 1 : 0;

    if ($f['band_code'] === '') {
        $error = 'Band code is required (e.g. A, B, C).';
    } elseif (strlen($f['band_code']) > 20) {
        $error = 'Band code is too long (max 20 chars).';
    } elseif ($f['name'] === '') {
        $error = ucfirst($labelL) . ' name is required.';
    } elseif (strlen($f['name']) > 150) {
        $error = ucfirst($labelL) . ' name is too long (max 150 chars).';
    } else {
        try {
            $stmt = db()->prepare(
                'INSERT INTO product_options
                   (client_id, product_id, band_code, supplier_name,
                    name, colour, code, sort_order, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $clientId,
                $productId,
                strtoupper($f['band_code']),
                $f['supplier_name'] !== '' ? $f['supplier_name'] : null,
                $f['name'],
                $f['colour'] !== '' ? $f['colour'] : null,
                $f['code']   !== '' ? $f['code']   : null,
                $f['sort_order'],
                $f['active'],
            ]);
            $_SESSION['_options_last_band']     = strtoupper($f['band_code']);
            $_SESSION['_options_last_supplier'] = $f['supplier_name'];
            $_SESSION['flash_success'] = ucfirst($labelL) . ' "' . $f['name'] . '" added.';
            header('Location: /admin/products/options.php?product_id=' . $productId);
            exit;
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'uniq_option_per_product')) {
                $error = 'A ' . $labelL . ' with that name + colour already exists for this product.';
            } else {
                $error = 'Could not add: ' . $e->getMessage();
            }
        }
    }
}

// List existing options. Custom band sort: AAA → AA → A → B → C → ...
// (premium "A" tiers in descending length, then alphabetical for the rest).
$rows = db()->prepare(
    "SELECT id, band_code, supplier_name, name, colour, code, sort_order, active
       FROM product_options
      WHERE product_id = ? AND client_id = ?
   ORDER BY
        CASE
            WHEN band_code = 'AAA' THEN 1
            WHEN band_code = 'AA'  THEN 2
            WHEN band_code = 'A'   THEN 3
            ELSE 100
        END,
        band_code,
        sort_order, name, colour"
);
$rows->execute([$productId, $clientId]);
$options = $rows->fetchAll();

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) $product['name']) ?> &middot; <?= e($label) ?>s &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .form-row.cols-5 { grid-template-columns: 1fr 1fr 2fr 1fr 0.75fr; }
        @media (max-width: 800px) {
            .form-row.cols-5 { grid-template-columns: 1fr; }
        }
        .form-group input[type="number"] {
            width: 100%; font: inherit; padding: 0.5625rem 0.75rem;
            border: 1px solid #d1d5db; border-radius: 8px; background: #fff;
        }
        .checkbox-row {
            display: inline-flex; align-items: center; gap: 0.5rem;
            font-size: 0.9375rem; color: #111827; cursor: pointer; margin-right: 0.75rem;
        }
        .checkbox-row input { width: 18px; height: 18px; }
        .band-pill {
            display: inline-block; text-align: center;
            padding: 0.125rem 0.625rem; font-weight: 700; font-size: 0.8125rem;
            color: #fff; background: #1f3b5b; border-radius: 6px;
            white-space: nowrap;
        }
        .row-actions { white-space: nowrap; }
        .row-actions a { font-size: 0.875rem; margin-left: 0.5rem; }
        .row-actions form { display: inline; margin: 0; }
        .row-actions button {
            font-size: 0.875rem; color: #b91c1c; background: transparent;
            border: 0; cursor: pointer; padding: 0; margin-left: 0.5rem;
        }
        .row-actions button:hover { text-decoration: underline; }
        .inactive-pill {
            display: inline-block; padding: 0.0625rem 0.5rem; font-size: 0.6875rem;
            font-weight: 600; color: #6b7280; background: #f3f4f6;
            border-radius: 999px; margin-left: 0.5rem;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .bulk-bar {
            display: flex; align-items: center; gap: 0.75rem;
            margin-bottom: 0.75rem; flex-wrap: wrap;
        }
        .bulk-bar .selected-count { font-size: 0.875rem; color: #6b7280; }
        .row-check { width: 1%; text-align: center; }
        .row-check input { width: 18px; height: 18px; cursor: pointer; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <?= e((string) $product['name']) ?> &mdash; <?= e($label) ?>s
                </h1>
                <p class="page-subtitle">
                    <a href="/admin/products/index.php">&larr; All products</a>
                    &middot;
                    <a href="/admin/products/edit.php?id=<?= (int) $productId ?>">Edit product</a>
                </p>
            </div>
            <a href="/admin/products/options-import.php?product_id=<?= (int) $productId ?>"
               class="btn btn-secondary">Import from Excel</a>
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
                <h2 class="section-title">Add <?= e($labelL) ?></h2>
            </div>
            <form method="post" action="/admin/products/options.php?product_id=<?= (int) $productId ?>"
                  class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="create">

                <div class="form-row cols-5">
                    <div class="form-group">
                        <label for="band_code">Band <span class="required">*</span></label>
                        <input id="band_code" name="band_code" type="text"
                               required maxlength="20" autofocus
                               value="<?= e((string) $f['band_code']) ?>" placeholder="A">
                    </div>
                    <div class="form-group">
                        <label for="name"><?= e($label) ?> name <span class="required">*</span></label>
                        <input id="name" name="name" type="text" required maxlength="150"
                               value="<?= e((string) $f['name']) ?>"
                               placeholder="<?= $label === 'Slat type' ? 'e.g. 25mm Faux Wood' : 'e.g. Cream Slats' ?>">
                    </div>
                    <div class="form-group">
                        <label for="colour">Colour</label>
                        <input id="colour" name="colour" type="text" maxlength="150"
                               value="<?= e((string) $f['colour']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="supplier_name">Supplier</label>
                        <input id="supplier_name" name="supplier_name" type="text" maxlength="150"
                               value="<?= e((string) $f['supplier_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="code">Code</label>
                        <input id="code" name="code" type="text" maxlength="50"
                               value="<?= e((string) $f['code']) ?>">
                    </div>
                </div>

                <label class="checkbox-row" for="active">
                    <input type="checkbox" id="active" name="active" value="1" checked>
                    Active
                </label>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add <?= e($labelL) ?></button>
                </div>
            </form>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title"><?= e($label) ?>s (<?= count($options) ?>)</h2>
            </div>

            <?php if (!$options): ?>
                <div class="placeholder">
                    <p class="placeholder-title">No <?= e($labelL) ?>s yet</p>
                    <p class="placeholder-body">
                        Use the form above to add your first <?= e($labelL) ?> for this product.
                    </p>
                </div>
            <?php else: ?>
                <div class="bulk-bar">
                    <button type="button" id="bulk-delete-btn"
                            class="btn btn-secondary btn-sm" disabled>
                        Delete selected
                    </button>
                    <span class="selected-count" id="bulk-count">No rows selected</span>
                </div>
                <form id="bulk-form" method="post" action="/admin/products/option-delete.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="row-check">
                                        <input type="checkbox" id="check-all"
                                               aria-label="Select all">
                                    </th>
                                    <th>Band</th>
                                    <th><?= e($label) ?></th>
                                    <th>Colour</th>
                                    <th>Supplier</th>
                                    <th>Code</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($options as $o): ?>
                                    <tr>
                                        <td class="row-check">
                                            <input type="checkbox" class="row-checkbox"
                                                   name="ids[]" value="<?= (int) $o['id'] ?>"
                                                   aria-label="Select <?= e((string) $o['name']) ?>">
                                        </td>
                                        <td><span class="band-pill">Band <?= e((string) $o['band_code']) ?></span></td>
                                        <td>
                                            <?= e((string) $o['name']) ?>
                                            <?php if ((int) $o['active'] !== 1): ?>
                                                <span class="inactive-pill">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e((string) ($o['colour'] ?? '')) ?></td>
                                        <td><?= e((string) ($o['supplier_name'] ?? '')) ?></td>
                                        <td><?= e((string) ($o['code'] ?? '')) ?></td>
                                        <td class="row-actions">
                                            <a href="/admin/products/option-edit.php?id=<?= (int) $o['id'] ?>">Edit</a>
                                            <button type="button" class="row-delete"
                                                    data-id="<?= (int) $o['id'] ?>"
                                                    data-name="<?= e((string) $o['name']) ?>"
                                                    style="font-size:0.875rem;color:#b91c1c;background:transparent;border:0;cursor:pointer;padding:0;margin-left:0.5rem;">
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </main>
</div>

<script>
(function () {
    var form = document.getElementById('bulk-form');
    if (!form) return;
    var checkAll = document.getElementById('check-all');
    var rowBoxes = form.querySelectorAll('.row-checkbox');
    var btn      = document.getElementById('bulk-delete-btn');
    var counter  = document.getElementById('bulk-count');

    function checkedIds() {
        var ids = [];
        rowBoxes.forEach(function (cb) { if (cb.checked) ids.push(cb.value); });
        return ids;
    }

    function refresh() {
        var ids = checkedIds();
        var n   = ids.length;
        btn.disabled = n === 0;
        if (n === 0) {
            counter.textContent = 'No rows selected';
        } else {
            counter.textContent = n + ' row' + (n === 1 ? '' : 's') + ' selected';
        }
        if (checkAll) {
            checkAll.checked       = (n > 0 && n === rowBoxes.length);
            checkAll.indeterminate = (n > 0 && n < rowBoxes.length);
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            rowBoxes.forEach(function (cb) { cb.checked = checkAll.checked; });
            refresh();
        });
    }
    rowBoxes.forEach(function (cb) { cb.addEventListener('change', refresh); });

    btn.addEventListener('click', function () {
        var n = checkedIds().length;
        if (n === 0) return;
        if (confirm('Delete ' + n + ' selected row' + (n === 1 ? '' : 's') + '? This cannot be undone.')) {
            form.submit();
        }
    });

    // Per-row Delete buttons reuse the same bulk form: clear all checkboxes,
    // tick just the target, confirm, submit.
    document.querySelectorAll('.row-delete').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id   = btn.getAttribute('data-id');
            var name = btn.getAttribute('data-name');
            if (!confirm('Delete ' + name + '?')) return;
            rowBoxes.forEach(function (cb) { cb.checked = (cb.value === id); });
            form.submit();
        });
    });

    refresh();
})();
</script>
</body>
</html>
