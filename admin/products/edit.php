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

// Markup / discount are now per (product, system). A product with
// systems carries one row per system; one without systems carries a
// single row keyed by system_id IS NULL.
$sysStmt = db()->prepare(
    'SELECT id, name FROM product_systems
      WHERE product_id = ? AND client_id = ? AND active = 1
   ORDER BY sort_order, name'
);
$sysStmt->execute([$id, $clientId]);
$systems = $sysStmt->fetchAll(PDO::FETCH_ASSOC);

$mStmt = db()->prepare(
    'SELECT system_id, markup_percent FROM client_markups
      WHERE client_id = ? AND product_id = ?'
);
$mStmt->execute([$clientId, $id]);
$markupsBySystem = [];   // string key: '' for NULL, otherwise system_id
foreach ($mStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $key = $r['system_id'] === null ? '' : (string) (int) $r['system_id'];
    $markupsBySystem[$key] = (string) $r['markup_percent'];
}

$dStmt = db()->prepare(
    'SELECT system_id, discount_percent FROM client_discounts
      WHERE client_id = ? AND product_id = ?'
);
$dStmt->execute([$clientId, $id]);
$discountsBySystem = [];
foreach ($dStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $key = $r['system_id'] === null ? '' : (string) (int) $r['system_id'];
    $discountsBySystem[$key] = (string) $r['discount_percent'];
}

// $f['markup'] and $f['discount'] are keyed by system_id (or '' for
// products without systems). Default missing entries to '0.00'.
$f = [
    'name'     => (string) $product['name'],
    'active'   => (int)    $product['active'],
    'markup'   => [],
    'discount' => [],
];
if ($systems) {
    foreach ($systems as $s) {
        $key = (string) (int) $s['id'];
        $f['markup'][$key]   = $markupsBySystem[$key]   ?? '0.00';
        $f['discount'][$key] = $discountsBySystem[$key] ?? '0.00';
    }
} else {
    $f['markup']['']   = $markupsBySystem['']   ?? '0.00';
    $f['discount'][''] = $discountsBySystem[''] ?? '0.00';
}
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $f['name']     = trim((string) ($_POST['name'] ?? ''));
    $f['active']   = !empty($_POST['active']) ? 1 : 0;

    // Two posting shapes depending on whether the product has systems:
    //   With systems:    markup[<sysid>] / discount[<sysid>]  (arrays)
    //   Without systems: markup / discount                    (scalars)
    // Keeping them shape-distinct avoids HTML-form gymnastics (you can't
    // easily post `array[''=>v]` from <input name="..."> markup).
    $f['markup']   = [];
    $f['discount'] = [];
    $validKeys     = [];
    if ($systems) {
        $postedMarkup   = is_array($_POST['markup']   ?? null) ? $_POST['markup']   : [];
        $postedDiscount = is_array($_POST['discount'] ?? null) ? $_POST['discount'] : [];
        foreach ($systems as $s) {
            $k = (string) (int) $s['id'];
            $f['markup'][$k]   = trim((string) ($postedMarkup[$k]   ?? '0'));
            $f['discount'][$k] = trim((string) ($postedDiscount[$k] ?? '0'));
            $validKeys[]       = $k;
        }
    } else {
        $f['markup']['']   = trim((string) ($_POST['markup']   ?? '0'));
        $f['discount'][''] = trim((string) ($_POST['discount'] ?? '0'));
        $validKeys[]       = '';
    }

    $validateNum = static function (string $v, string $label) {
        if (!is_numeric($v) || (float) $v < 0) {
            return "$label must be a non-negative number.";
        }
        return null;
    };

    if ($f['name'] === '') {
        $error = 'Product name is required.';
    } elseif (strlen($f['name']) > 150) {
        $error = 'Product name is too long (max 150 characters).';
    } else {
        foreach ($validKeys as $k) {
            $error = $validateNum($f['markup'][$k],   'Markup %');
            if ($error) break;
            $error = $validateNum($f['discount'][$k], 'Discount %');
            if ($error) break;
        }
    }

    if ($error === null) {
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

            // Markup: upsert one row per system (or one NULL-system row
            // for products without systems). The unique key uses a
            // generated system_id_key (IFNULL(system_id, 0)) so the
            // ON DUPLICATE KEY UPDATE catches the NULL case too.
            $insMarkup = $pdo->prepare(
                'INSERT INTO client_markups (client_id, product_id, system_id, markup_percent)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE markup_percent = VALUES(markup_percent)'
            );
            // Discount: same shape, but 0 deletes the row (no need to
            // store an explicit zero — missing row also reads as 0).
            $insDiscount = $pdo->prepare(
                'INSERT INTO client_discounts (client_id, product_id, system_id, discount_percent)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE discount_percent = VALUES(discount_percent)'
            );
            $delDiscount = $pdo->prepare(
                'DELETE FROM client_discounts
                  WHERE client_id = ? AND product_id = ?
                    AND ((system_id IS NULL AND ? IS NULL) OR system_id = ?)'
            );

            foreach ($validKeys as $k) {
                $sysId = $k === '' ? null : (int) $k;
                $insMarkup->execute([$clientId, $id, $sysId, (float) $f['markup'][$k]]);

                $dp = (float) $f['discount'][$k];
                if ($dp > 0) {
                    $insDiscount->execute([$clientId, $id, $sysId, $dp]);
                } else {
                    // The "IS NULL ... = ?" trick needs the same value
                    // twice so the prepared statement covers both
                    // branches without juggling SQL strings.
                    $delDiscount->execute([$clientId, $id, $sysId, $sysId]);
                }
            }

            $pdo->commit();
            $_SESSION['flash_success'] = 'Product updated.';
            header('Location: /admin/products/index.php');
            exit;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
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
        .pricing-table {
            width: 100%; border-collapse: collapse;
            font-size: 0.9375rem;
        }
        .pricing-table th, .pricing-table td {
            padding: 0.5rem 0.625rem; text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }
        .pricing-table th {
            font-size: 0.75rem; text-transform: uppercase;
            letter-spacing: 0.04em; color: #6b7280; font-weight: 600;
        }
        .pricing-table td.system-name { font-weight: 500; color: #111827; }
        .pricing-table td.num { width: 7rem; }
        .pricing-table td.num input {
            width: 100%; padding: 0.4375rem 0.625rem;
            border: 1px solid #d1d5db; border-radius: 6px; background: #fff;
            font: inherit; box-sizing: border-box;
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
                        Pricing per system
                    </legend>
                    <p style="color:#6b7280;font-size:0.875rem;margin:0 0 0.75rem">
                        Margin and discount can be tuned per system (premium / motorised /
                        standard are usually priced differently). Markup is applied on top
                        of the price-table base; discount comes off after that. Leave at 0
                        to skip.
                        <?php if (!$systems): ?>
                            <br><em>No systems on this product yet — values below apply to
                            every quote. Add systems on the
                            <a href="/admin/products/systems.php?product_id=<?= (int) $id ?>"
                               style="color:#1f3b5b">Systems</a> page to split them out.</em>
                        <?php endif; ?>
                    </p>

                    <?php if ($systems): ?>
                        <table class="pricing-table">
                            <thead>
                                <tr>
                                    <th>System</th>
                                    <th style="width:7rem">Markup %</th>
                                    <th style="width:7rem">Discount %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($systems as $s):
                                    $key = (string) (int) $s['id'];
                                ?>
                                    <tr>
                                        <td class="system-name"><?= e((string) $s['name']) ?></td>
                                        <td class="num">
                                            <input type="number" step="0.01" min="0"
                                                   name="markup[<?= (int) $s['id'] ?>]"
                                                   value="<?= e((string) ($f['markup'][$key] ?? '0.00')) ?>">
                                        </td>
                                        <td class="num">
                                            <input type="number" step="0.01" min="0"
                                                   name="discount[<?= (int) $s['id'] ?>]"
                                                   value="<?= e((string) ($f['discount'][$key] ?? '0.00')) ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="form-row cols-2">
                            <div class="form-group">
                                <label for="markup_no_sys">Markup %</label>
                                <input id="markup_no_sys" name="markup" type="number"
                                       step="0.01" min="0"
                                       value="<?= e((string) ($f['markup'][''] ?? '0.00')) ?>">
                            </div>
                            <div class="form-group">
                                <label for="discount_no_sys">Discount %</label>
                                <input id="discount_no_sys" name="discount" type="number"
                                       step="0.01" min="0"
                                       value="<?= e((string) ($f['discount'][''] ?? '0.00')) ?>">
                            </div>
                        </div>
                    <?php endif; ?>
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
                   class="btn btn-secondary">Options &rarr;</a>
            </div>
        </section>
    </main>
</div>

</body>
</html>
