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
       . '<h1>Option not found</h1>';
    exit;
}

// Load existing parent choices from the junction table.
$pcSt = db()->prepare(
    'SELECT product_extra_choice_id
       FROM product_extra_parent_choices
      WHERE product_extra_id = ?'
);
$pcSt->execute([$id]);
$existingParents = array_map('intval', $pcSt->fetchAll(PDO::FETCH_COLUMN));

$f = [
    'name'              => (string) $extra['name'],
    'is_required'       => (int)    $extra['is_required'],
    'active'            => (int)    $extra['active'],
    'parent_choice_ids' => $existingParents,
];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $f['name']              = trim((string) ($_POST['name'] ?? ''));
    $f['is_required']       = !empty($_POST['is_required']) ? 1 : 0;
    $f['active']            = !empty($_POST['active']) ? 1 : 0;
    $f['parent_choice_ids'] = array_values(array_unique(array_filter(array_map(
        'intval',
        is_array($_POST['parent_choice_ids'] ?? null) ? $_POST['parent_choice_ids'] : []
    ))));

    if ($f['name'] === '') {
        $error = 'Name is required.';
    } elseif (strlen($f['name']) > 150) {
        $error = 'Name is too long (max 150 chars).';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Legacy single column gets the first ticked id (or NULL)
            // so any code still reading it stays sensible. The junction
            // is the source of truth.
            $legacyParent = $f['parent_choice_ids'][0] ?? null;
            $u = $pdo->prepare(
                'UPDATE product_extras
                    SET name = ?, is_required = ?, active = ?,
                        parent_choice_id = ?
                  WHERE id = ? AND client_id = ?'
            );
            $u->execute([
                $f['name'], $f['is_required'], $f['active'],
                $legacyParent,
                $id, $clientId,
            ]);

            // Replace the junction rows. Validate ids belong to this
            // product's catalogue first (POST inputs aren't trustworthy).
            $pdo->prepare(
                'DELETE FROM product_extra_parent_choices WHERE product_extra_id = ?'
            )->execute([$id]);
            if ($f['parent_choice_ids']) {
                $ph = implode(',', array_fill(0, count($f['parent_choice_ids']), '?'));
                $vps = $pdo->prepare(
                    "SELECT c.id FROM product_extra_choices c
                       JOIN product_extras e ON e.id = c.product_extra_id
                      WHERE c.id IN ($ph)
                        AND e.product_id = ? AND e.client_id = ?
                        AND e.id != ?"
                );
                $vps->execute([...$f['parent_choice_ids'], (int) $extra['product_id'], $clientId, $id]);
                $validParents = array_map('intval', $vps->fetchAll(PDO::FETCH_COLUMN));
                if ($validParents) {
                    $jIns = $pdo->prepare(
                        'INSERT INTO product_extra_parent_choices
                           (product_extra_id, product_extra_choice_id)
                         VALUES (?, ?)'
                    );
                    foreach ($validParents as $cid) {
                        $jIns->execute([$id, $cid]);
                    }
                }
            }

            $pdo->commit();
            $_SESSION['flash_success'] = 'Option updated.';
            header('Location: /admin/products/extras.php?product_id=' . (int) $extra['product_id']);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Could not save: ' . $e->getMessage();
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
    <title>Edit option &middot; YourBlinds</title>
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
                <h1 class="page-title">Edit option</h1>
                <p class="page-subtitle">
                    <a href="/admin/products/extras.php?product_id=<?= (int) $extra['product_id'] ?>">
                        &larr; Back to <?= e((string) $extra['product_name']) ?> options
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

                <div class="form-row full">
                    <div class="form-group">
                        <label>Appears when (optional)</label>
                        <?php if (!$availableChoices): ?>
                            <p style="color:#6b7280;font-size:0.8125rem;margin:0">
                                No other choices on this product yet.
                            </p>
                        <?php else: ?>
                            <div style="display:flex;flex-direction:column;gap:0.375rem;padding:0.5rem 0;max-height:240px;overflow-y:auto">
                                <?php foreach ($availableChoices as $c): ?>
                                    <label style="display:inline-flex;align-items:center;gap:0.4rem;font-weight:400;cursor:pointer">
                                        <input type="checkbox" name="parent_choice_ids[]"
                                               value="<?= (int) $c['id'] ?>"
                                               <?= in_array((int) $c['id'], $f['parent_choice_ids'], true) ? 'checked' : '' ?>>
                                        <?= e((string) $c['extra_name']) ?> = <?= e((string) $c['label']) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <small style="color:#6b7280;font-size:0.8125rem">
                                Tick one or more choices and this option will show in the quote builder
                                when <strong>any</strong> of them is selected. Leave all unticked to
                                make it always visible.
                            </small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="toggle-stack">
                    <label for="is_required">
                        <input type="checkbox" id="is_required" name="is_required" value="1"
                               <?= (int) $f['is_required'] === 1 ? 'checked' : '' ?>>
                        Required
                        <small>customer must pick a choice</small>
                    </label>
                    <label for="active">
                        <input type="checkbox" id="active" name="active" value="1"
                               <?= (int) $f['active'] === 1 ? 'checked' : '' ?>>
                        Active
                        <small>uncheck to hide from quote builder</small>
                    </label>
                </div>

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
