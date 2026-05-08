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
    'SELECT e.id, e.product_id, e.parent_choice_id, e.name, e.is_required,
            e.sort_order, e.active,
            p.name AS product_name
       FROM product_extras e
       JOIN products p ON p.id = e.product_id
      WHERE e.id = ? AND e.client_id = ?'
);
$loadStmt->execute([$id, $clientId]);
$extra = $loadStmt->fetch();

if (!$extra) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Extra not found</h1>';
    exit;
}

$f = [
    'name'             => (string) $extra['name'],
    'is_required'      => (int)    $extra['is_required'],
    'sort_order'       => (int)    $extra['sort_order'],
    'active'           => (int)    $extra['active'],
    'parent_choice_id' => (int) ($extra['parent_choice_id'] ?? 0),
];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $f['name']             = trim((string) ($_POST['name'] ?? ''));
    $f['is_required']      = !empty($_POST['is_required']) ? 1 : 0;
    $f['sort_order']       = (int) ($_POST['sort_order'] ?? 0);
    $f['active']           = !empty($_POST['active']) ? 1 : 0;
    $f['parent_choice_id'] = (int) ($_POST['parent_choice_id'] ?? 0);

    if ($f['name'] === '') {
        $error = 'Name is required.';
    } elseif (strlen($f['name']) > 150) {
        $error = 'Name is too long (max 150 chars).';
    } else {
        try {
            $u = db()->prepare(
                'UPDATE product_extras
                    SET name = ?, is_required = ?, sort_order = ?, active = ?,
                        parent_choice_id = ?
                  WHERE id = ? AND client_id = ?'
            );
            $u->execute([
                $f['name'], $f['is_required'], $f['sort_order'], $f['active'],
                $f['parent_choice_id'] > 0 ? $f['parent_choice_id'] : null,
                $id, $clientId,
            ]);
            $_SESSION['flash_success'] = 'Extra updated.';
            header('Location: /admin/products/extras.php?product_id=' . (int) $extra['product_id']);
            exit;
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'uniq_extra_per_product')) {
                $error = 'An extra with that name already exists for this product.';
            } else {
                $error = 'Could not save: ' . $e->getMessage();
            }
        }
    }
}

// All choices in this product (excluding self's own choices, to avoid loops).
$choiceStmt = db()->prepare(
    'SELECT c.id, c.label, e.name AS extra_name
       FROM product_extra_choices c
       JOIN product_extras e ON e.id = c.product_extra_id
      WHERE e.product_id = ? AND e.client_id = ? AND e.id != ?
   ORDER BY e.name, c.sort_order, c.label'
);
$choiceStmt->execute([(int) $extra['product_id'], $clientId, $id]);
$availableChoices = $choiceStmt->fetchAll();

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit extra &middot; YourBlinds</title>
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
                <h1 class="page-title">Edit extra</h1>
                <p class="page-subtitle">
                    <a href="/admin/products/extras.php?product_id=<?= (int) $extra['product_id'] ?>">
                        &larr; Back to <?= e((string) $extra['product_name']) ?> extras
                    </a>
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section">
            <form method="post" action="/admin/products/extra-edit.php?id=<?= (int) $id ?>"
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

                <div class="form-row">
                    <div class="form-group">
                        <label for="sort_order">Sort order</label>
                        <input id="sort_order" name="sort_order" type="number"
                               value="<?= (int) $f['sort_order'] ?>">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="parent_choice_id">Appears when</label>
                        <select id="parent_choice_id" name="parent_choice_id">
                            <option value="0">— Always visible —</option>
                            <?php foreach ($availableChoices as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"
                                    <?= ((int) $f['parent_choice_id']) === (int) $c['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $c['extra_name']) ?> = <?= e((string) $c['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:#6b7280;font-size:0.8125rem">
                            Optional — pick a choice from elsewhere in this product to make this extra only show when that choice is selected.
                        </small>
                    </div>
                </div>

                <label class="checkbox-row" for="is_required">
                    <input type="checkbox" id="is_required" name="is_required" value="1"
                           <?= (int) $f['is_required'] === 1 ? 'checked' : '' ?>>
                    Required (customer must pick a choice)
                </label>
                <br>
                <label class="checkbox-row" for="active">
                    <input type="checkbox" id="active" name="active" value="1"
                           <?= (int) $f['active'] === 1 ? 'checked' : '' ?>>
                    Active
                </label>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="/admin/products/extras.php?product_id=<?= (int) $extra['product_id'] ?>"
                       class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
