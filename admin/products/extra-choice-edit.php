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
// cost_price is added by migrate_product_costs.php — try-fallback so
// the page still works pre-migration.
try {
    $loadStmt = db()->prepare(
        'SELECT c.id, c.product_extra_id, c.system_id, c.label,
                c.price_delta, c.price_percent, c.price_per_metre,
                c.cost_price,
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
    $hasCostColumn = true;
} catch (Throwable $e) {
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
    $hasCostColumn = false;
}

if (!$choice) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Choice not found</h1>';
    exit;
}

// Per-choice band scoping (migrate_choice_band_scoping.php). The
// junction table is optional — older installs won't have it, in
// which case the band-picker UI is hidden and the choice applies
// to every band by default.
$hasBandScopingTbl = false;
try {
    $hasBandScopingTbl = (bool) db()->query(
        "SELECT 1 FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'product_extra_choice_bands'"
    )->fetchColumn();
} catch (Throwable $e) { /* keep false */ }

$existingBands = [];
if ($hasBandScopingTbl) {
    $bSt = db()->prepare(
        'SELECT band_code FROM product_extra_choice_bands WHERE choice_id = ?'
    );
    $bSt->execute([$id]);
    $existingBands = array_map(
        static fn ($v) => (string) $v,
        $bSt->fetchAll(PDO::FETCH_COLUMN)
    );
}

// Known bands across this product — same union as options.php, so
// the picker offers exactly the bands the tenant has actually
// defined (no free-text). Empty list → no bands defined yet,
// scoping section just shows a hint.
$kbSt = db()->prepare(
    "SELECT DISTINCT band_code FROM (
        SELECT band_code FROM product_options
         WHERE product_id = ? AND client_id = ?
        UNION
        SELECT band_code FROM price_tables
         WHERE product_id = ? AND client_id = ?
     ) x
     WHERE band_code IS NOT NULL AND band_code != ''
     ORDER BY band_code"
);
$kbSt->execute([
    (int) $choice['product_id'], $clientId,
    (int) $choice['product_id'], $clientId,
]);
$knownBands = array_map(
    static fn ($v) => (string) $v,
    $kbSt->fetchAll(PDO::FETCH_COLUMN)
);

// per_metre_basis (migrate_per_metre_basis.php) — the length a per-metre
// charge runs along. Optional column: probe once; absent ⇒ width-based,
// the historic default. Allowed values shared by validation + the UI.
$perMetreBases = [
    'width'           => 'Width',
    'drop'            => 'Drop',
    'width_plus_drop' => 'Width + Drop',
    'perimeter'       => 'Perimeter (2 × W + 2 × D)',
];
$hasBasisColumn = false;
try {
    $hasBasisColumn = (bool) db()->query(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'product_extra_choices'
            AND COLUMN_NAME = 'per_metre_basis'"
    )->fetchColumn();
} catch (Throwable $e) { /* keep false */ }

$perMetreBasis = 'width';
if ($hasBasisColumn) {
    $pbSt = db()->prepare('SELECT per_metre_basis FROM product_extra_choices WHERE id = ?');
    $pbSt->execute([$id]);
    $pbVal = (string) $pbSt->fetchColumn();
    if (isset($perMetreBases[$pbVal])) $perMetreBasis = $pbVal;
}

// Per-choice number input label (migrate_choice_length_input.php). Loaded
// on its own so it doesn't have to be threaded through the cost-column
// try-fallback above. Absent column = feature off for this install.
$hasChoiceLenColumn = false;
$choiceLenLabel     = '';
try {
    $clSt = db()->prepare('SELECT length_input_label FROM product_extra_choices WHERE id = ?');
    $clSt->execute([$id]);
    $choiceLenLabel     = (string) ($clSt->fetchColumn() ?: '');
    $hasChoiceLenColumn = true;
} catch (Throwable $e) { /* column absent — keep feature off */ }

$f = [
    'label'           => (string) $choice['label'],
    'length_input_label' => $choiceLenLabel,
    'price_delta'     => (string) $choice['price_delta'],
    'price_percent'   => (string) $choice['price_percent'],
    'price_per_metre' => (string) $choice['price_per_metre'],
    'per_metre_basis' => $perMetreBasis,
    'cost_price'      => isset($choice['cost_price']) && $choice['cost_price'] !== null
                            ? (string) $choice['cost_price']
                            : '',
    'is_default'      => (int)    $choice['is_default'],
    'active'          => (int)    $choice['active'],
    'system_id'       => $choice['system_id'] !== null ? (int) $choice['system_id'] : 0,
    'bands'           => $existingBands,
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
    $f['length_input_label'] = trim((string) ($_POST['length_input_label'] ?? ''));
    $f['price_delta']     = trim((string) ($_POST['price_delta']     ?? '0'));
    $f['price_percent']   = trim((string) ($_POST['price_percent']   ?? '0'));
    $f['price_per_metre'] = trim((string) ($_POST['price_per_metre'] ?? '0'));
    $f['per_metre_basis'] = (string) ($_POST['per_metre_basis'] ?? 'width');
    if (!isset($perMetreBases[$f['per_metre_basis']])) $f['per_metre_basis'] = 'width';
    $f['cost_price']      = trim((string) ($_POST['cost_price']      ?? ''));
    $f['is_default']      = !empty($_POST['is_default']) ? 1 : 0;
    $f['active']          = !empty($_POST['active']) ? 1 : 0;
    $f['system_id']       = (int) ($_POST['system_id'] ?? 0);
    $widthTablePasted     = (string) ($_POST['width_price_table'] ?? '');

    // band_filter[] — checkbox group. Only bands that appear in the
    // current product's known list are accepted; an unticked-everything
    // submit means "applies to all bands" and we clear the junction.
    // Cross-check against $knownBands so a tampered form can't insert
    // garbage band codes.
    $submittedBands  = is_array($_POST['band_filter'] ?? null) ? $_POST['band_filter'] : [];
    $knownBandsLower = array_map('strtolower', $knownBands);
    $f['bands'] = [];
    foreach ($submittedBands as $b) {
        $bs = trim((string) $b);
        if ($bs === '') continue;
        // Snap to canonical case from $knownBands if a CI match exists,
        // so the junction never holds a band that no longer exists in
        // any canonical form (band rename via price-table.php updates
        // the canonical case; this keeps the scope tracking it).
        $idx = array_search(strtolower($bs), $knownBandsLower, true);
        if ($idx !== false) $f['bands'][] = $knownBands[$idx];
    }
    $f['bands'] = array_values(array_unique($f['bands']));

    if ($f['label'] === '') {
        $error = 'Label is required.';
    } elseif (strlen($f['label']) > 150) {
        $error = 'Label is too long (max 150 chars).';
    } elseif (strlen($f['length_input_label']) > 60) {
        $error = 'Number-input label is too long (max 60 chars).';
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

                // cost_price: empty = NULL (= unentered, treat as 0).
                $costValue = ($f['cost_price'] === '' || !is_numeric($f['cost_price']))
                    ? null
                    : (float) $f['cost_price'];

                if ($hasCostColumn) {
                    $u = $pdo->prepare(
                        'UPDATE product_extra_choices
                            SET label = ?, system_id = ?,
                                price_delta = ?, price_percent = ?, price_per_metre = ?,
                                cost_price = ?,
                                is_default = ?, active = ?
                          WHERE id = ?'
                    );
                    $u->execute([
                        $f['label'],
                        $systemIdToStore,
                        (float) $f['price_delta'],
                        (float) $f['price_percent'],
                        (float) $f['price_per_metre'],
                        $costValue,
                        $f['is_default'],
                        $f['active'],
                        $id,
                    ]);
                } else {
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
                }

                // per_metre_basis lives on its own small UPDATE so it doesn't
                // have to be threaded through both branches above. Skipped on
                // schemas without the column (defaults to width everywhere).
                if ($hasBasisColumn) {
                    $pdo->prepare(
                        'UPDATE product_extra_choices SET per_metre_basis = ? WHERE id = ?'
                    )->execute([$f['per_metre_basis'], $id]);
                }

                // Per-choice number-input label — own small UPDATE, same
                // pattern. Empty string → NULL ("no number box on this choice").
                if ($hasChoiceLenColumn) {
                    $lenVal = $f['length_input_label'] !== '' ? $f['length_input_label'] : null;
                    $pdo->prepare(
                        'UPDATE product_extra_choices SET length_input_label = ? WHERE id = ?'
                    )->execute([$lenVal, $id]);
                }

                // Replace the per-choice band scope. Empty $f['bands']
                // = "applies to all bands" (clear all rows). At least
                // one band = "only when fabric's band matches one of
                // these". Skipped on installs where the migration
                // hasn't been run.
                if ($hasBandScopingTbl) {
                    $pdo->prepare(
                        'DELETE FROM product_extra_choice_bands WHERE choice_id = ?'
                    )->execute([$id]);
                    if ($f['bands']) {
                        $insB = $pdo->prepare(
                            'INSERT INTO product_extra_choice_bands
                                (choice_id, band_code) VALUES (?, ?)'
                        );
                        foreach ($f['bands'] as $b) {
                            $insB->execute([$id, $b]);
                        }
                    }
                }

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

                // Smart post-save routing:
                //   - If the user touched the width-based price table
                //     (uploaded a file OR pasted into the textarea),
                //     stay on this page with a "X rows imported"
                //     message so they can verify the data landed
                //     correctly. Bouncing back to the choices list
                //     hides the imported rows behind another click.
                //   - Otherwise (plain label/price/system edit), go
                //     back to the choices list as before — the user
                //     can see their change in the inline grid.
                $touchedWidthTable = $uploadedPath !== null
                                  || trim($widthTablePasted) !== '';
                if ($touchedWidthTable) {
                    $rowCount = count($widthRows);
                    if ($rowCount > 0) {
                        $_SESSION['flash_success'] = 'Choice updated. '
                            . $rowCount . ' width-priced row'
                            . ($rowCount === 1 ? '' : 's')
                            . ' imported — check the table below to verify.';
                    } else {
                        // The width-table was cleared (empty textarea +
                        // no file = "clear the table" per the parser).
                        $_SESSION['flash_success'] = 'Choice updated. Width-based price table cleared.';
                    }
                    header('Location: /admin/products/extra-choice-edit.php?id=' . $id);
                    exit;
                }

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
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .form-group input[type="number"] {
            width: 100%; font: inherit; padding: 0.5625rem 0.75rem;
            border: 1px solid var(--border-strong); border-radius: 8px; background: #fff;
        }
        .toggle-stack {
            display: flex; flex-direction: column; gap: 0.625rem;
            margin: 1.25rem 0;
        }
        .toggle-stack label {
            display: inline-flex; align-items: center; gap: 0.5rem;
            font-size: 0.9375rem; color: var(--text-primary); cursor: pointer;
            margin: 0; padding: 0;
        }
        .toggle-stack input[type="checkbox"] { width: 18px; height: 18px; }
        .toggle-stack small {
            color: var(--text-faint); font-size: 0.8125rem; margin-left: 0.375rem;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <?php
                    require_once __DIR__ . '/../../_partials/breadcrumb.php';
                    echo render_breadcrumb([
                        ['Products',                                 '/admin/products/index.php'],
                        [(string) $choice['product_name'],           '/admin/products/edit.php?id='   . (int) $choice['product_id']],
                        ['Options',                                  '/admin/products/extras.php?product_id=' . (int) $choice['product_id']],
                        [(string) $choice['extra_name'],             '/admin/products/extra.php?id='  . (int) $choice['product_extra_id']],
                        [(string) $choice['label'],                  null],
                    ]);
                ?>
                <h1 class="page-title">Edit choice: <?= e((string) $choice['label']) ?></h1>
            </div>
        </div>

        <?php
            // Flash messages set by the save handler (e.g. "N rows
            // imported") get rendered here, then immediately cleared
            // so a refresh doesn't show them again.
            $flashMsg = $_SESSION['flash_success'] ?? null;
            unset($_SESSION['flash_success']);
        ?>
        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <p style="color:var(--text-faint);font-size:0.9375rem;margin:0 0 1rem">
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

                <?php if ($hasChoiceLenColumn):
                    $hasChoiceLen = $f['length_input_label'] !== '';
                ?>
                <!--
                    Per-choice number input. Same tickbox+label pattern as the
                    option editor, but it lives on THIS choice — so e.g. an
                    "Offset" option can have Top / Bottom / Left / Right choices,
                    each with its own mm box, or a "Mid rail" choice can capture
                    its height. Empty label = no box on this choice.
                -->
                <fieldset style="border:1px solid var(--border);border-radius:10px;padding:1rem 1.125rem;margin:0 0 1rem">
                    <legend style="padding:0 0.5rem;font-size:0.8125rem;font-weight:600;color:#1f3b5b;text-transform:uppercase;letter-spacing:0.05em">
                        Ask for a number on this choice
                    </legend>
                    <label style="display:inline-flex;align-items:flex-start;gap:0.5rem;font-weight:500;cursor:pointer;margin-bottom:0.625rem">
                        <input type="checkbox" id="show_choice_len"
                               <?= $hasChoiceLen ? 'checked' : '' ?>
                               style="margin-top:0.25rem">
                        <span>
                            Show a number input when this choice is picked
                            <small style="display:block;color:var(--text-faint);font-weight:400;font-size:0.8125rem;margin-top:0.125rem">
                                For measurements like an offset size or mid-rail height &mdash;
                                the salesperson types a value next to this choice. Recorded on
                                the quote line; it doesn't change the price.
                            </small>
                        </span>
                    </label>
                    <div id="choice_len_wrap" style="<?= $hasChoiceLen ? '' : 'display:none' ?>;margin-left:1.625rem">
                        <label for="length_input_label" style="font-size:0.8125rem;font-weight:600;color:var(--text-secondary)">
                            What to call this field
                        </label>
                        <input id="length_input_label" name="length_input_label" type="text"
                               maxlength="60"
                               value="<?= e((string) $f['length_input_label']) ?>"
                               placeholder="e.g. Top offset (mm)"
                               style="width:100%;font:inherit;padding:0.5625rem 0.75rem;border:1px solid var(--border-strong);border-radius:8px;background:#fff;box-sizing:border-box;margin-top:0.25rem">
                        <small style="color:var(--text-faint);font-size:0.8125rem;display:block;margin-top:0.25rem">
                            Include the unit (e.g. <em>mm</em>) so the salesperson knows what to type.
                        </small>
                    </div>
                </fieldset>
                <script>
                (function () {
                    var tick = document.getElementById('show_choice_len');
                    var wrap = document.getElementById('choice_len_wrap');
                    var lbl  = document.getElementById('length_input_label');
                    if (!tick || !wrap || !lbl) return;
                    tick.addEventListener('change', function () {
                        if (tick.checked) {
                            wrap.style.display = '';
                            if (lbl.value.trim() === '') lbl.value = 'Size (mm)';
                            setTimeout(function () { lbl.focus(); lbl.select(); }, 0);
                        } else {
                            wrap.style.display = 'none';
                            lbl.value = '';
                        }
                    });
                })();
                </script>
                <?php endif; ?>

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

                <?php if ($hasBasisColumn): ?>
                <div class="form-row full">
                    <div class="form-group">
                        <label for="per_metre_basis">Per-metre length is measured along</label>
                        <select id="per_metre_basis" name="per_metre_basis">
                            <?php foreach ($perMetreBases as $bKey => $bLabel): ?>
                                <option value="<?= e($bKey) ?>"
                                    <?= $f['per_metre_basis'] === $bKey ? 'selected' : '' ?>>
                                    <?= e($bLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:var(--text-faint);font-size:0.8125rem">
                            Only matters when <strong>Per metre (£/m)</strong> is set. Width is the
                            usual choice; pick <strong>Perimeter</strong> for trims that run all the
                            way around the blind (e.g. a magnetic strip) &mdash; charged on
                            2&nbsp;&times;&nbsp;width&nbsp;+&nbsp;2&nbsp;&times;&nbsp;drop.
                        </small>
                    </div>
                </div>
                <?php endif; ?>

                <?php /* Per-extra-choice wholesale cost field removed —
                         cost is captured by the price/percent/per-metre
                         fields above (they're treated as the cost basis;
                         Markup % on the product adds sell margin on top).
                         The product_extra_choices.cost_price column still
                         exists in the schema but no UI for now. */ ?>

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
                        <small style="color:var(--text-faint);font-size:0.8125rem">
                            "All systems" = appears on every system on this product.
                            Pick a single system to limit it. To price the same choice
                            differently per system, use the <em>Duplicate</em> link
                            on the choices list.
                        </small>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($hasBandScopingTbl): ?>
                <div class="form-row full">
                    <div class="form-group">
                        <label>Available for bands</label>
                        <?php if (!$knownBands): ?>
                            <p style="color:var(--text-faint);font-size:0.8125rem;margin:0">
                                No bands defined on this product yet — add price tables first,
                                then this choice can be restricted to specific bands.
                            </p>
                        <?php else: ?>
                            <div style="display:flex;flex-wrap:wrap;gap:0.5rem 1rem;padding:0.5rem 0">
                                <?php foreach ($knownBands as $b): ?>
                                    <label style="display:inline-flex;align-items:center;gap:0.4rem;font-weight:400;cursor:pointer">
                                        <input type="checkbox" name="band_filter[]"
                                               value="<?= e($b) ?>"
                                               <?= in_array($b, $f['bands'], true) ? 'checked' : '' ?>>
                                        <?= e($b) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <small style="color:var(--text-faint);font-size:0.8125rem">
                                Tick the bands this choice should appear for. Leave them all
                                unticked = "appears for every band" (the default). Useful when
                                a tape colour, bracket size, or similar isn't available across
                                every fabric tier.
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <fieldset style="border:1px solid var(--border);border-radius:10px;padding:1rem 1.125rem;margin:1rem 0">
                    <legend style="padding:0 0.5rem;font-size:0.8125rem;font-weight:600;color:#1f3b5b;text-transform:uppercase;letter-spacing:0.05em">
                        Thumbnail image (optional)
                    </legend>
                    <p style="color:var(--text-faint);font-size:0.875rem;margin:0 0 0.75rem">
                        Shown to the customer in the quote builder when they pick this choice.
                        Useful for things like wand-control orientation where the words alone
                        ("Left", "Right", "Centre Left") aren't enough to communicate. JPG, PNG,
                        or GIF, up to 2&nbsp;MB.
                    </p>
                    <?php if (!empty($choice['image_path'])): ?>
                        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;background:var(--bg-subtle);border:1px solid var(--border);border-radius:8px;padding:0.75rem;margin-bottom:0.75rem">
                            <?php /* asset() appends ?v=<file-mtime> so a re-upload (same
                                     filename, new contents) busts the browser's image cache —
                                     otherwise the old/broken thumbnail lingers until a hard refresh. */ ?>
                            <img src="<?= e(asset((string) $choice['image_path'])) ?>" alt="Current thumbnail"
                                 style="max-height:80px;max-width:160px;background:#fff;padding:0.25rem;border:1px solid var(--border);border-radius:6px">
                            <small style="color:var(--text-faint);font-size:0.8125rem">
                                Current thumbnail. Upload a new file below to replace, or use the Remove button.
                            </small>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image_file" id="image_file"
                           accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif"
                           style="font:inherit">
                </fieldset>

                <?php if (!empty($choice['image_path'])): ?>
                    <?php /* Submit button INSIDE the main form. A nested form element here is
                             invalid HTML and silently closes the main form early, which killed
                             "Save changes" on any choice that already had an image.
                             name=_action routes this submit to the remove-image handler;
                             formnovalidate so the empty-label guard can't block a removal. */ ?>
                    <div style="margin:0 0 1rem">
                        <button type="submit" name="_action" value="remove_image" formnovalidate
                                class="btn btn-secondary btn-sm"
                                onclick="return confirm('Remove the thumbnail for this choice?');">
                            Remove thumbnail
                        </button>
                    </div>
                <?php endif; ?>

                <fieldset style="border:1px solid var(--border);border-radius:10px;padding:1rem 1.125rem;margin:1rem 0">
                    <legend style="padding:0 0.5rem;font-size:0.8125rem;font-weight:600;color:#1f3b5b;text-transform:uppercase;letter-spacing:0.05em">
                        Width-based price table (optional)
                    </legend>
                    <p style="color:var(--text-faint);font-size:0.875rem;margin:0 0 0.5rem">
                        A fourth pricing mode for cases where the surcharge varies by width.
                        Pricing engine looks up the smallest entry &ge; the customer's width (round-up).
                        <strong>Combined</strong> with the flat / percent / per-metre fields above.
                    </p>

                    <p style="font-size:0.875rem;margin:0.75rem 0 0.25rem;color:var(--text-secondary);font-weight:600">
                        Option A — paste rows
                    </p>
                    <p style="color:var(--text-faint);font-size:0.8125rem;margin:0 0 0.375rem">
                        One row per line: <strong>width then price</strong>, separated by space, comma, or tab.
                        Width in mm (<code>800</code>) or metres (<code>0.800</code>) — auto-detected.
                        Empty textarea + no file = clear the table.
                    </p>
                    <textarea name="width_price_table" id="width_price_table"
                              rows="6"
                              style="width:100%;font-family:ui-monospace,Consolas,monospace;font-size:0.875rem;padding:0.5625rem 0.75rem;border:1px solid var(--border-strong);border-radius:8px;background:#fff;resize:vertical"
                              placeholder="800, 15.00&#10;1200, 22.50&#10;1600, 30.00"><?= e($widthTablePasted) ?></textarea>

                    <p style="font-size:0.875rem;margin:0.75rem 0 0.25rem;color:var(--text-secondary);font-weight:600">
                        Option B — upload Excel
                    </p>
                    <p style="color:var(--text-faint);font-size:0.8125rem;margin:0 0 0.375rem">
                        Either layout works (auto-detected): <strong>vertical</strong> — two columns,
                        width in column A and price in column B, one row per width;
                        <strong>or horizontal</strong> — widths across row 1, prices across row 2
                        (the layout many supplier sheets use). Widths can be in mm or metres.
                        Header row is optional. If a file is provided, it overrides the textarea above.
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
