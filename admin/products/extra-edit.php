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

// Existing system bindings via the junction table.
$scopeSt = db()->prepare(
    'SELECT product_system_id FROM product_extra_systems
      WHERE product_extra_id = ?'
);
$scopeSt->execute([$id]);
$existingSystemIds = array_map('intval', $scopeSt->fetchAll(PDO::FETCH_COLUMN));

$f = [
    'name'             => (string) $extra['name'],
    'is_required'      => (int)    $extra['is_required'],
    'active'           => (int)    $extra['active'],
    'parent_choice_id' => (int) ($extra['parent_choice_id'] ?? 0),
    'system_ids'       => $existingSystemIds,
];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $f['name']             = trim((string) ($_POST['name'] ?? ''));
    $f['is_required']      = !empty($_POST['is_required']) ? 1 : 0;
    $f['active']           = !empty($_POST['active']) ? 1 : 0;
    $f['parent_choice_id'] = (int) ($_POST['parent_choice_id'] ?? 0);
    $f['system_ids']       = array_values(array_unique(array_filter(array_map(
        'intval',
        is_array($_POST['system_ids'] ?? null) ? $_POST['system_ids'] : []
    ))));

    if ($f['name'] === '') {
        $error = 'Name is required.';
    } elseif (strlen($f['name']) > 150) {
        $error = 'Name is too long (max 150 chars).';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // sort_order is intentionally not touched — drag-and-drop on
            // the extras list is the only writer.
            $u = $pdo->prepare(
                'UPDATE product_extras
                    SET name = ?, is_required = ?, active = ?,
                        parent_choice_id = ?
                  WHERE id = ? AND client_id = ?'
            );
            $u->execute([
                $f['name'], $f['is_required'], $f['active'],
                $f['parent_choice_id'] > 0 ? $f['parent_choice_id'] : null,
                $id, $clientId,
            ]);

            // Replace the system-scope junction rows. Validate ids belong
            // to this product's catalogue first.
            $pdo->prepare(
                'DELETE FROM product_extra_systems WHERE product_extra_id = ?'
            )->execute([$id]);

            if ($f['system_ids']) {
                $ph = implode(',', array_fill(0, count($f['system_ids']), '?'));
                $vsSt = $pdo->prepare(
                    "SELECT id FROM product_systems
                      WHERE id IN ($ph) AND product_id = ? AND client_id = ?"
                );
                $vsSt->execute([...$f['system_ids'], (int) $extra['product_id'], $clientId]);
                $validSystemIds = array_map('intval', $vsSt->fetchAll(PDO::FETCH_COLUMN));

                // Auto-collapse: if every available system is ticked, treat
                // it as "no scope" (zero rows). Same runtime effect, but
                // cleaner data and future-proof against new systems being
                // added later (which would otherwise NOT inherit this row).
                $totalSt = $pdo->prepare(
                    'SELECT COUNT(*) FROM product_systems
                      WHERE product_id = ? AND client_id = ?'
                );
                $totalSt->execute([(int) $extra['product_id'], $clientId]);
                $totalSystems = (int) $totalSt->fetchColumn();
                if ($totalSystems > 0 && count($validSystemIds) === $totalSystems) {
                    $validSystemIds = [];
                }

                if ($validSystemIds) {
                    $jIns = $pdo->prepare(
                        'INSERT INTO product_extra_systems
                           (product_extra_id, product_system_id) VALUES (?, ?)'
                    );
                    foreach ($validSystemIds as $sid) {
                        $jIns->execute([$id, $sid]);
                    }
                }
            }

            $pdo->commit();
            $_SESSION['flash_success'] = 'Option updated.';
            header('Location: /admin/products/extras.php?product_id=' . (int) $extra['product_id']);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (str_contains($e->getMessage(), 'uniq_extra_per_product')) {
                $error = 'An option with that name already exists for this product.';
            } else {
                $error = 'Could not save: ' . $e->getMessage();
            }
        }
    }
}

// Systems available on this product, for the system-scope checkboxes.
$sysStmt = db()->prepare(
    'SELECT id, name FROM product_systems
      WHERE product_id = ? AND client_id = ?
   ORDER BY sort_order, name'
);
$sysStmt->execute([(int) $extra['product_id'], $clientId]);
$systems = $sysStmt->fetchAll();

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
                            Optional — pick a choice from elsewhere in this product to make this option only show when that choice is selected.
                        </small>
                    </div>
                </div>

                <?php if ($systems): ?>
                <div class="form-row full">
                    <div class="form-group">
                        <label>System scope (optional)</label>
                        <div style="display:flex;flex-wrap:wrap;gap:0.75rem 1.25rem;padding:0.5rem 0">
                            <?php foreach ($systems as $s): ?>
                                <label style="display:inline-flex;align-items:center;gap:0.4rem;font-weight:400;cursor:pointer">
                                    <input type="checkbox" name="system_ids[]"
                                           value="<?= (int) $s['id'] ?>"
                                           <?= in_array((int) $s['id'], $f['system_ids'], true) ? 'checked' : '' ?>>
                                    <?= e((string) $s['name']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <small style="color:#6b7280;font-size:0.8125rem">
                            Tick one or more to limit this whole option to specific systems.
                            Leave all unticked to make it available on every system.
                        </small>
                    </div>
                </div>
                <?php endif; ?>

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
