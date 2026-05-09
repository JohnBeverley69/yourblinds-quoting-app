<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$extraId = (int) ($_GET['id'] ?? $_POST['extra_id'] ?? 0);
if ($extraId <= 0) {
    header('Location: /admin/products/index.php');
    exit;
}

// Tenant-scoped lookup of the extra + its parent product.
$loadStmt = db()->prepare(
    'SELECT e.id, e.product_id, e.name, e.is_required, e.active,
            p.name AS product_name
       FROM product_extras e
       JOIN products p ON p.id = e.product_id
      WHERE e.id = ? AND e.client_id = ?'
);
$loadStmt->execute([$extraId, $clientId]);
$extra = $loadStmt->fetch();

if (!$extra) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Extra not found</h1>';
    exit;
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$f = [
    'label'           => '',
    'price_delta'     => '0.00',
    'price_percent'   => '0.00',
    'price_per_metre' => '0.00',
    'is_default'      => 0,
    'system_id'       => 0,
];
$error            = null;
$widthTablePasted = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_action'] ?? '') === 'create_choice') {
    csrf_check();

    $f['label']           = trim((string) ($_POST['label'] ?? ''));
    $f['price_delta']     = trim((string) ($_POST['price_delta']     ?? '0'));
    $f['price_percent']   = trim((string) ($_POST['price_percent']   ?? '0'));
    $f['price_per_metre'] = trim((string) ($_POST['price_per_metre'] ?? '0'));
    $f['is_default']      = !empty($_POST['is_default']) ? 1 : 0;
    $f['system_id']       = (int) ($_POST['system_id']  ?? 0);
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

        // Width-based price table — optional. Either a pasted textarea or an
        // uploaded .xlsx; file wins. Empty input = no rows added.
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

                // If marking this as default, clear default on all sibling choices first.
                if ($f['is_default'] === 1) {
                    $clear = $pdo->prepare(
                        'UPDATE product_extra_choices SET is_default = 0 WHERE product_extra_id = ?'
                    );
                    $clear->execute([$extraId]);
                }

                // sort_order = MAX+1 so new choices append to the end of
                // the list (drag-and-drop owns ordering after that).
                $sortStmt = $pdo->prepare(
                    'SELECT COALESCE(MAX(sort_order), -1) + 1
                       FROM product_extra_choices
                      WHERE product_extra_id = ?'
                );
                $sortStmt->execute([$extraId]);
                $nextSort = (int) $sortStmt->fetchColumn();

                $ins = $pdo->prepare(
                    'INSERT INTO product_extra_choices
                       (product_extra_id, system_id, label,
                        price_delta, price_percent, price_per_metre,
                        is_default, sort_order, active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
                );
                $ins->execute([
                    $extraId,
                    $f['system_id'] > 0 ? $f['system_id'] : null,
                    $f['label'],
                    (float) $f['price_delta'],
                    (float) $f['price_percent'],
                    (float) $f['price_per_metre'],
                    $f['is_default'],
                    $nextSort,
                ]);
                $newChoiceId = (int) $pdo->lastInsertId();

                if ($widthRows) {
                    $rowIns = $pdo->prepare(
                        'INSERT INTO extra_choice_price_rows
                           (product_extra_choice_id, width_mm, price)
                         VALUES (?, ?, ?)'
                    );
                    foreach ($widthRows as $w => $p) {
                        $rowIns->execute([$newChoiceId, $w, $p]);
                    }
                }

                $pdo->commit();
                $_SESSION['flash_success'] = 'Choice "' . $f['label'] . '" added.';
                header('Location: /admin/products/extra.php?id=' . $extraId);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Could not add: ' . $e->getMessage();
            }
        }
    }
}

// Mark a choice as default — only one default per extra.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_action'] ?? '') === 'set_default_choice') {
    csrf_check();
    $targetId = (int) ($_POST['choice_id'] ?? 0);
    if ($targetId > 0) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $clear = $pdo->prepare(
                'UPDATE product_extra_choices SET is_default = 0 WHERE product_extra_id = ?'
            );
            $clear->execute([$extraId]);
            $set = $pdo->prepare(
                'UPDATE product_extra_choices SET is_default = 1
                  WHERE id = ? AND product_extra_id = ?'
            );
            $set->execute([$targetId, $extraId]);
            $pdo->commit();
            $_SESSION['flash_success'] = 'Default choice updated.';
            header('Location: /admin/products/extra.php?id=' . $extraId);
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not set default: ' . $e->getMessage();
        }
    }
}

// Sort by sort_order alone — drag-and-drop controls position. The 'default'
// pill still shows but doesn't override the user's drag.
$rows = db()->prepare(
    'SELECT c.id, c.label, c.system_id,
            c.price_delta, c.price_percent, c.price_per_metre,
            c.is_default, c.sort_order, c.active,
            s.name AS system_name,
            (SELECT COUNT(*) FROM extra_choice_price_rows r
              WHERE r.product_extra_choice_id = c.id) AS width_table_size
       FROM product_extra_choices c
       LEFT JOIN product_systems s ON s.id = c.system_id
      WHERE c.product_extra_id = ?
   ORDER BY c.sort_order, c.label'
);
$rows->execute([$extraId]);
$choices = $rows->fetchAll();

// Systems available on this product, for the system dropdown.
$sysStmt = db()->prepare(
    'SELECT id, name FROM product_systems
      WHERE product_id = ? AND client_id = ?
   ORDER BY sort_order, name'
);
$sysStmt->execute([(int) $extra['product_id'], $clientId]);
$systems = $sysStmt->fetchAll();

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) $extra['name']) ?> &middot; Choices &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .form-row.cols-5 { grid-template-columns: 2fr 1fr 1fr 1fr 0.75fr; align-items: end; }
        @media (max-width: 900px) {
            .form-row.cols-5 { grid-template-columns: 1fr; }
        }
        .form-group input[type="number"] {
            width: 100%; font: inherit; padding: 0.5625rem 0.75rem;
            border: 1px solid #d1d5db; border-radius: 8px; background: #fff;
        }
        .checkbox-row {
            display: inline-flex; align-items: center; gap: 0.5rem;
            font-size: 0.9375rem; color: #111827; cursor: pointer;
            padding: 0.5625rem 0; margin: 0;
        }
        .checkbox-row input { width: 18px; height: 18px; }
        .row-actions { white-space: nowrap; }
        .row-actions a { font-size: 0.875rem; margin-left: 0.5rem; }
        .row-actions form { display: inline; margin: 0; }
        .row-actions button {
            font-size: 0.875rem; color: #b91c1c; background: transparent;
            border: 0; cursor: pointer; padding: 0; margin-left: 0.5rem;
        }
        .row-actions button.set-default {
            color: #1f3b5b;
        }
        .row-actions button:hover { text-decoration: underline; }
        .default-pill {
            display: inline-block; padding: 0.0625rem 0.5rem; font-size: 0.6875rem;
            font-weight: 700; color: #fff; background: #16a34a;
            border-radius: 999px; margin-left: 0.5rem;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .system-pill {
            display: inline-block; padding: 0.0625rem 0.5rem; font-size: 0.6875rem;
            font-weight: 700; color: #fff; background: #1f3b5b;
            border-radius: 999px; margin-left: 0.5rem;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        a.choice-label { font-weight: 600; color: #111827; text-decoration: none; }
        a.choice-label:hover { color: #1f3b5b; text-decoration: underline; }
        .price-impact { color: #6b7280; font-size: 0.875rem; }
        .price-impact strong { color: #111827; font-weight: 600; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <?= e((string) $extra['product_name']) ?> / <?= e((string) $extra['name']) ?>
                </h1>
                <p class="page-subtitle">
                    <a href="/admin/products/extras.php?product_id=<?= (int) $extra['product_id'] ?>">
                        &larr; All extras for <?= e((string) $extra['product_name']) ?>
                    </a>
                    &middot;
                    <a href="/admin/products/extra-edit.php?id=<?= (int) $extraId ?>">Edit extra</a>
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
                <h2 class="section-title">Add choice</h2>
            </div>
            <p style="color:#6b7280;font-size:0.9375rem;margin:0 0 1rem">
                Four independent surcharge modes; any (or none) can apply per choice.
                <strong>Flat £</strong> = fixed add-on (e.g. Motor +£45).
                <strong>Percent %</strong> = on the base price (e.g. Blackout +15%).
                <strong>£/metre</strong> = multiplied by the blind width in metres
                (e.g. Champagne headrail at £8/m → on a 2.4m wide blind = +£19.20).
                <strong>Width table</strong> (below) = lookup by width for stepped surcharges.
                Use <strong>System</strong> to limit a choice to one system (e.g. Champagne only on Vogue).
            </p>
            <form method="post" action="/admin/products/extra.php?id=<?= (int) $extraId ?>"
                  class="form" novalidate enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="create_choice">

                <div class="form-row cols-5">
                    <div class="form-group">
                        <label for="label">Label <span class="required">*</span></label>
                        <input id="label" name="label" type="text"
                               required maxlength="150" autofocus
                               value="<?= e((string) $f['label']) ?>" placeholder="e.g. Left">
                    </div>
                    <div class="form-group">
                        <label for="price_delta">Flat £</label>
                        <input id="price_delta" name="price_delta" type="number"
                               step="0.01" value="<?= e((string) $f['price_delta']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="price_percent">Percent %</label>
                        <input id="price_percent" name="price_percent" type="number"
                               step="0.01" value="<?= e((string) $f['price_percent']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="price_per_metre">£/metre</label>
                        <input id="price_per_metre" name="price_per_metre" type="number"
                               step="0.01" value="<?= e((string) $f['price_per_metre']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="checkbox-row" for="is_default">
                            <input type="checkbox" id="is_default" name="is_default" value="1"
                                   <?= $f['is_default'] === 1 ? 'checked' : '' ?>>
                            Default
                        </label>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="system_id">System (optional)</label>
                        <select id="system_id" name="system_id">
                            <option value="0">— All systems —</option>
                            <?php foreach ($systems as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"
                                    <?= ((int) $f['system_id']) === (int) $s['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

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
                           accept=".xlsx,.xlsm,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                           style="font:inherit">
                </fieldset>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add choice</button>
                </div>
            </form>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Choices (<?= count($choices) ?>)</h2>
            </div>

            <?php if (!$choices): ?>
                <div class="placeholder">
                    <p class="placeholder-title">No choices yet</p>
                    <p class="placeholder-body">
                        Use the form above to add the options customers can pick from.
                    </p>
                </div>
            <?php else: ?>
                <p style="color:#6b7280;font-size:0.9375rem;margin:0 0 0.5rem">
                    Drag the <strong>⋮⋮</strong> handle to reorder.
                    <span class="reorder-status">Saving…</span>
                </p>
                <div class="table-wrap">
                    <table class="table sortable-list" data-reorder-type="choices">
                        <thead>
                            <tr>
                                <th class="drag-col"></th>
                                <th>Label</th>
                                <th>Price impact</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($choices as $c): ?>
                                <tr data-id="<?= (int) $c['id'] ?>">
                                    <td class="drag-col" title="Drag to reorder">⋮⋮</td>
                                    <td>
                                        <a href="/admin/products/extra-choice-edit.php?id=<?= (int) $c['id'] ?>"
                                           class="choice-label">
                                            <?= e((string) $c['label']) ?>
                                        </a>
                                        <?php if ((int) $c['is_default'] === 1): ?>
                                            <span class="default-pill">Default</span>
                                        <?php endif; ?>
                                        <?php if (!empty($c['system_name'])): ?>
                                            <span class="system-pill"><?= e((string) $c['system_name']) ?> only</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="price-impact">
                                        <?php
                                            $delta    = (float) $c['price_delta'];
                                            $percent  = (float) $c['price_percent'];
                                            $perMetre = (float) $c['price_per_metre'];
                                            $widthN   = (int)   $c['width_table_size'];
                                            $bits = [];
                                            if ($delta    != 0) { $bits[] = ($delta > 0 ? '+' : '') . '£' . number_format($delta, 2); }
                                            if ($perMetre != 0) { $bits[] = ($perMetre > 0 ? '+' : '') . '£' . number_format($perMetre, 2) . '/m'; }
                                            if ($percent  != 0) { $bits[] = ($percent > 0 ? '+' : '') . number_format($percent, 2) . '%'; }
                                            if ($widthN   > 0)  { $bits[] = 'width table (' . $widthN . ' size' . ($widthN === 1 ? '' : 's') . ')'; }
                                        ?>
                                        <?php if ($bits): ?>
                                            <strong><?= e(implode(' and ', $bits)) ?></strong>
                                        <?php else: ?>
                                            Free
                                        <?php endif; ?>
                                    </td>
                                    <td class="row-actions">
                                        <?php if ((int) $c['is_default'] !== 1): ?>
                                            <form method="post"
                                                  action="/admin/products/extra.php?id=<?= (int) $extraId ?>"
                                                  style="display:inline;margin:0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_action" value="set_default_choice">
                                                <input type="hidden" name="choice_id" value="<?= (int) $c['id'] ?>">
                                                <button type="submit" class="set-default">Set default</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="/admin/products/extra-choice-delete.php"
                                              onsubmit="return confirm('Delete choice <?= e(addslashes((string) $c['label'])) ?>?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                            <input type="hidden" name="extra_id" value="<?= (int) $extraId ?>">
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
<?php if ($choices): require __DIR__ . '/../../_partials/sortable_init.php'; endif; ?>
</body>
</html>
