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

// Tenant-scoped lookup of the choice + parents.
$loadStmt = db()->prepare(
    'SELECT c.id, c.product_extra_id, c.system_id, c.label,
            c.price_delta, c.price_percent, c.price_per_metre,
            c.is_default, c.sort_order, c.active, c.image_path,
            e.name AS extra_name, e.product_id, e.client_id,
            p.name AS product_name
       FROM product_extra_choices c
       JOIN product_extras e ON e.id = c.product_extra_id
       JOIN products p       ON p.id = e.product_id
      WHERE c.id = ? AND e.client_id = ?'
);
$loadStmt->execute([$id, $clientId]);
$choice = $loadStmt->fetch();

if (!$choice) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Choice not found</h1>';
    exit;
}

$f = [
    'label'           => (string) $choice['label'],
    'price_delta'     => (string) $choice['price_delta'],
    'price_percent'   => (string) $choice['price_percent'],
    'price_per_metre' => (string) $choice['price_per_metre'],
    'is_default'      => (int)    $choice['is_default'],
    'active'          => (int)    $choice['active'],
    'system_id'       => $choice['system_id'] !== null ? (int) $choice['system_id'] : 0,
];
$error = null;

$widthTablePasted = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['_action'] ?? '') === 'remove_image'
) {
    csrf_check();
    if (!empty($choice['image_path'])) {
        $abs = APP_ROOT . '/' . ltrim((string) $choice['image_path'], '/');
        if (is_file($abs)) @unlink($abs);
    }
    db()->prepare('UPDATE product_extra_choices SET image_path = NULL WHERE id = ?')
        ->execute([$id]);
    $_SESSION['flash_success'] = 'Thumbnail removed.';
    header('Location: /admin/products/extra-choice-edit.php?id=' . $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $f['label']           = trim((string) ($_POST['label'] ?? ''));
    $f['price_delta']     = trim((string) ($_POST['price_delta']     ?? '0'));
    $f['price_percent']   = trim((string) ($_POST['price_percent']   ?? '0'));
    $f['price_per_metre'] = trim((string) ($_POST['price_per_metre'] ?? '0'));
    $f['is_default']      = !empty($_POST['is_default']) ? 1 : 0;
    $f['active']          = !empty($_POST['active']) ? 1 : 0;
    $f['system_id']       = (int) ($_POST['system_id'] ?? 0);
    $widthTablePasted     = (string) ($_POST['width_price_table'] ?? '');

    if ($f['label'] === '') {
        $error = 'Label is required.';
    } elseif (strlen($f['label']) > 150) {
        $error = 'Label is too long (max 150 chars).';
    } elseif (!is_numeric($f['price_delta'])) {
        $error = 'Flat surcharge must be a number.';
    } elseif (!is_numeric($f['price_percent'])) {
        $error = 'Percent surcharge must be a number.';
    } elseif (!is_numeric($f['price_per_metre'])) {
        $error = 'Per-metre surcharge must be a number.';
    } else {
        require_once __DIR__ . '/../../_partials/price_table_parser.php';

        // Width-based price table — either a pasted textarea or an uploaded
        // .xlsx (file wins). Empty input + no file = clear all rows.
        $uploadedPath = null;
        if (isset($_FILES['width_price_file'])
            && ($_FILES['width_price_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = $_FILES['width_price_file']['tmp_name'];
            if (filesize($tmp) > 5 * 1024 * 1024) {
                $error = 'File too large (5 MB max).';
            } else {
                $uploadedPath = $tmp;
            }
        }

        $widthRows = [];
        if ($error === null) {
            $parsed = ptp_parse_width_price_input($widthTablePasted, $uploadedPath);
            if ($parsed['error'] !== null) {
                $error = $parsed['error'];
            } else {
                $widthRows = $parsed['rows'];
            }
        }

        if ($error === null) {
            try {
                $pdo = db();
                $pdo->beginTransaction();

                // Validate the chosen system (if any) belongs to this
                // product. 0 = "All systems" → store as NULL.
                $systemIdToStore = null;
                if ($f['system_id'] > 0) {
                    $vsSt = $pdo->prepare(
                        'SELECT id FROM product_systems
                          WHERE id = ? AND product_id = ? AND client_id = ?
                          LIMIT 1'
                    );
                    $vsSt->execute([$f['system_id'], (int) $choice['product_id'], $clientId]);
                    if ($vsSt->fetchColumn() !== false) {
                        $systemIdToStore = $f['system_id'];
                    }
                }

                // Default-clear scoped to the same system bucket — one
                // default per (extra, system) in the new model.
                if ($f['is_default'] === 1) {
                    if ($systemIdToStore === null) {
                        $clear = $pdo->prepare(
                            'UPDATE product_extra_choices SET is_default = 0
                              WHERE product_extra_id = ? AND id != ?
                                AND system_id IS NULL'
                        );
                        $clear->execute([(int) $choice['product_extra_id'], $id]);
                    } else {
                        $clear = $pdo->prepare(
                            'UPDATE product_extra_choices SET is_default = 0
                              WHERE product_extra_id = ? AND id != ?
                                AND system_id = ?'
                        );
                        $clear->execute([
                            (int) $choice['product_extra_id'],
                            $id,
                            $systemIdToStore,
                        ]);
                    }
                }

                $u = $pdo->prepare(
                    'UPDATE product_extra_choices
                        SET label = ?, system_id = ?,
                            price_delta = ?, price_percent = ?, price_per_metre = ?,
                            is_default = ?, active = ?
                      WHERE id = ?'
                );
                // sort_order is intentionally not touched — drag-and-drop
                // on the choices list is the only writer.
                $u->execute([
                    $f['label'],
                    $systemIdToStore,
                    (float) $f['price_delta'],
                    (float) $f['price_percent'],
                    (float) $f['price_per_metre'],
                    $f['is_default'],
                    $f['active'],
                    $id,
                ]);

                // Replace the width table.
                $del = $pdo->prepare(
                    'DELETE FROM extra_choice_price_rows WHERE product_extra_choice_id = ?'
                );
                $del->execute([$id]);
                if ($widthRows) {
                    $ins = $pdo->prepare(
                        'INSERT INTO extra_choice_price_rows
                           (product_extra_choice_id, width_mm, price)
                         VALUES (?, ?, ?)'
                    );
                    foreach ($widthRows as $w => $p) {
                        $ins->execute([$id, $w, $p]);
                    }
                }

                // Optional thumbnail upload. Same security pattern as the
                // company-logo upload in admin/settings.php — validate the
                // file is a real image via getimagesize() and pin the
                // extension from the detected type rather than the user's
                // filename. Replacing wipes any prior file for this choice.
                if (isset($_FILES['image_file'])
                    && ($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $tmp = $_FILES['image_file']['tmp_name'];
                    if (filesize($tmp) > 2 * 1024 * 1024) {
                        throw new RuntimeException('Thumbnail too large (2 MB max).');
                    }
                    $info = @getimagesize($tmp);
                    $ext  = null;
                    if ($info !== false) {
                        switch ($info[2]) {
                            case IMAGETYPE_JPEG: $ext = 'jpg'; break;
                            case IMAGETYPE_PNG:  $ext = 'png'; break;
                            case IMAGETYPE_GIF:  $ext = 'gif'; break;
                        }
                    }
                    if ($ext === null) {
                        throw new RuntimeException('Thumbnail must be a JPG, PNG, or GIF image.');
                    }
                    $dir = APP_ROOT . '/uploads/choice-images';
                    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                        throw new RuntimeException('Could not create uploads/choice-images directory.');
                    }
                    foreach (glob($dir . '/' . $id . '.*') ?: [] as $old) {
                        @unlink($old);
                    }
                    $dest = $dir . '/' . $id . '.' . $ext;
                    if (!move_uploaded_file($tmp, $dest)) {
                        throw new RuntimeException('Could not save the uploaded thumbnail.');
                    }
                    $webPath = '/uploads/choice-images/' . $id . '.' . $ext;
                    $pdo->prepare('UPDATE product_extra_choices SET image_path = ? WHERE id = ?')
                        ->execute([$webPath, $id]);
                }

                $pdo->commit();

                $_SESSION['flash_success'] = 'Choice updated.';
                header('Location: /admin/products/extra.php?id=' . (int) $choice['product_extra_id']);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Could not save: ' . $e->getMessage();
            }
        }
    }
}

// Pre-fill width table textarea with existing rows.
if ($widthTablePasted === '') {
    $existing = db()->prepare(
        'SELECT width_mm, price FROM extra_choice_price_rows
          WHERE product_extra_choice_id = ?
       ORDER BY width_mm'
    );
    $existing->execute([$id]);
    $lines = [];
    foreach ($existing->fetchAll() as $r) {
        $lines[] = $r['width_mm'] . ', ' . number_format((float) $r['price'], 2, '.', '');
    }
    $widthTablePasted = implode("\n", $lines);
}

// Systems available on the parent product.
$sysStmt = db()->prepare(
    'SELECT id, name FROM product_systems
      WHERE product_id = ? AND client_id = ?
   ORDER BY sort_order, name'
);
$sysStmt->execute([(int) $choice['product_id'], $clientId]);
$systems = $sysStmt->fetchAll();

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit choice &middot; YourBlinds</title>
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
                <h1 class="page-title">Edit choice</h1>
                <p class="page-subtitle">
                    <a href="/admin/products/extra.php?id=<?= (int) $choice['product_extra_id'] ?>">
                        &larr; Back to <?= e((string) $choice['product_name']) ?>
                        / <?= e((string) $choice['extra_name']) ?>
                    </a>
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <p style="color:#6b7280;font-size:0.9375rem;margin:0 0 1rem">
            Label, prices, system, default and active toggles are all editable inline on the
            <a href="/admin/products/extra.php?id=<?= (int) $choice['product_extra_id'] ?>">choices list</a>.
            This page is for the deeper edits — <strong>width-table pricing</strong> and
            <strong>thumbnail image upload</strong> — that don't fit the inline grid.
        </p>

        <section class="section">
            <form method="post" action="/admin/products/extra-choice-edit.php?id=<?= (int) $id ?>"
                  class="form" novalidate enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="label">Label <span class="required">*</span></label>
                        <input id="label" name="label" type="text"
                               required maxlength="150"
                               value="<?= e((string) $f['label']) ?>">
                    </div>
                </div>

                <div class="form-row cols-3">
                    <div class="form-group">
                        <label for="price_delta">Flat (£)</label>
                        <input id="price_delta" name="price_delta" type="number"
                               step="0.01" value="<?= e((string) $f['price_delta']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="price_percent">Percent (%)</label>
                        <input id="price_percent" name="price_percent" type="number"
                               step="0.01" value="<?= e((string) $f['price_percent']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="price_per_metre">Per metre (£/m)</label>
                        <input id="price_per_metre" name="price_per_metre" type="number"
                               step="0.01" value="<?= e((string) $f['price_per_metre']) ?>">
                    </div>
                </div>

                <?php if ($systems): ?>
                <div class="form-row full">
                    <div class="form-group">
                        <label for="system_id">Available on</label>
                        <select id="system_id" name="system_id">
                            <option value="0" <?= (int) $f['system_id'] === 0 ? 'selected' : '' ?>>
                                All systems
                            </option>
                            <?php foreach ($systems as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"
                                    <?= (int) $f['system_id'] === (int) $s['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $s['name']) ?> only
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:#6b7280;font-size:0.8125rem">
                            "All systems" = appears on every system on this product.
                            Pick a single system to limit it. To price the same choice
                            differently per system, use the <em>Duplicate</em> link
                            on the choices list.
                        </small>
                    </div>
                </div>
                <?php endif; ?>

                <fieldset style="border:1px solid #e5e7eb;border-radius:10px;padding:1rem 1.125rem;margin:1rem 0">
                    <legend style="padding:0 0.5rem;font-size:0.8125rem;font-weight:600;color:#1f3b5b;text-transform:uppercase;letter-spacing:0.05em">
                        Thumbnail image (optional)
                    </legend>
                    <p style="color:#6b7280;font-size:0.875rem;margin:0 0 0.75rem">
                        Shown to the customer in the quote builder when they pick this choice.
                        Useful for things like wand-control orientation where the words alone
                        ("Left", "Right", "Centre Left") aren't enough to communicate. JPG, PNG,
                        or GIF, up to 2&nbsp;MB.
                    </p>
                    <?php if (!empty($choice['image_path'])): ?>
                        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:0.75rem;margin-bottom:0.75rem">
                            <img src="<?= e((string) $choice['image_path']) ?>" alt="Current thumbnail"
                                 style="max-height:80px;max-width:160px;background:#fff;padding:0.25rem;border:1px solid #e5e7eb;border-radius:6px">
                            <small style="color:#6b7280;font-size:0.8125rem">
                                Current thumbnail. Upload a new file below to replace, or use the Remove button.
                            </small>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image_file" id="image_file"
                           accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif"
                           style="font:inherit">
                </fieldset>

                <?php if (!empty($choice['image_path'])): ?>
                    <form method="post" action="/admin/products/extra-choice-edit.php?id=<?= (int) $id ?>"
                          style="margin:0 0 1rem"
                          data-confirm="Remove the thumbnail for this choice?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="remove_image">
                        <button type="submit" class="btn btn-secondary btn-sm">Remove thumbnail</button>
                    </form>
                <?php endif; ?>

                <fieldset style="border:1px solid #e5e7eb;border-radius:10px;padding:1rem 1.125rem;margin:1rem 0">
                    <legend style="padding:0 0.5rem;font-size:0.8125rem;font-weight:600;color:#1f3b5b;text-transform:uppercase;letter-spacing:0.05em">
                        Width-based price table (optional)
                    </legend>
                    <p style="color:#6b7280;font-size:0.875rem;margin:0 0 0.5rem">
                        A fourth pricing mode for cases where the surcharge varies by width.
                        Pricing engine looks up the smallest entry &ge; the customer's width (round-up).
                        <strong>Combined</strong> with the flat / percent / per-metre fields above.
                    </p>

                    <p style="font-size:0.875rem;margin:0.75rem 0 0.25rem;color:#374151;font-weight:600">
                        Option A — paste rows
                    </p>
                    <p style="color:#6b7280;font-size:0.8125rem;margin:0 0 0.375rem">
                        One row per line: <strong>width then price</strong>, separated by space, comma, or tab.
                        Width in mm (<code>800</code>) or metres (<code>0.800</code>) — auto-detected.
                        Empty textarea + no file = clear the table.
                    </p>
                    <textarea name="width_price_table" id="width_price_table"
                              rows="6"
                              style="width:100%;font-family:ui-monospace,Consolas,monospace;font-size:0.875rem;padding:0.5625rem 0.75rem;border:1px solid #d1d5db;border-radius:8px;background:#fff;resize:vertical"
                              placeholder="800, 15.00&#10;1200, 22.50&#10;1600, 30.00"><?= e($widthTablePasted) ?></textarea>

                    <p style="font-size:0.875rem;margin:0.75rem 0 0.25rem;color:#374151;font-weight:600">
                        Option B — upload Excel
                    </p>
                    <p style="color:#6b7280;font-size:0.8125rem;margin:0 0 0.375rem">
                        Two-column .xlsx: width in column A, price in column B. Header row optional. If a file is provided, it overrides the textarea above.
                    </p>
                    <input type="file" name="width_price_file" id="width_price_file"
                           accept=".xlsx,.xlsm,.xls,.csv,.ods"
                           style="font:inherit">
                </fieldset>

                <div class="toggle-stack">
                    <label for="is_default">
                        <input type="checkbox" id="is_default" name="is_default" value="1"
                               <?= (int) $f['is_default'] === 1 ? 'checked' : '' ?>>
                        Default
                        <small>pre-selected for the customer</small>
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
                    <a href="/admin/products/extra.php?id=<?= (int) $choice['product_extra_id'] ?>"
                       class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../../_partials/confirm_modal.php'; ?>
</body>
</html>
