<?php
declare(strict_types=1);

/**
 * Single price-table editor.
 *
 * Primary UX (Phase-3 redesign): inline-editable matrix. Each cell
 * is a numeric input; "+ Width" and "+ Drop" controls extend the
 * grid; the "×" beside each header removes that column/row on save.
 * Saving REPLACES every cell — same atomic semantics as the XLSX
 * upload paths.
 *
 * Secondary UX (kept for bulk operations): download a pre-populated
 * XLSX template, edit in Excel, upload — and the "flexible parser"
 * import for raw supplier sheets. Both live in a collapsed
 * "Advanced" section so they don't clutter the page for the common
 * case of "tweak one or two prices".
 *
 * Tenant-scoped throughout — load query, save handler, and audit
 * trail all key on client_id.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$tableId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($tableId <= 0) {
    header('Location: /admin/products/index.php');
    exit;
}

// Tenant-scoped lookup of the table + its parent system + product.
$loadStmt = db()->prepare(
    'SELECT t.id, t.product_id, t.system_id, t.band_code, t.name, t.notes, t.active,
            p.name AS product_name,
            s.name AS system_name
       FROM price_tables t
       JOIN products        p ON p.id = t.product_id
       JOIN product_systems s ON s.id = t.system_id
      WHERE t.id = ? AND t.client_id = ?'
);
$loadStmt->execute([$tableId, $clientId]);
$table = $loadStmt->fetch();

if (!$table) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Price table not found</h1>';
    exit;
}

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

// ---------------------------------------------------------------------------
// Inline-grid save — replaces every cell with what came up the wire.
// Posted shape:
//   action      = save_grid
//   cells[W_D]  = price string (blank = no price for that combination)
// W/D are mm integers. Blank or non-numeric values are skipped (so
// "delete a column" works by removing the inputs client-side and
// resubmitting — those keys simply don't appear in $_POST).
// ---------------------------------------------------------------------------
$summary = null;
$error   = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_grid') {
    csrf_check();

    $cells = $_POST['cells'] ?? [];
    if (!is_array($cells)) $cells = [];

    $bulk    = [];
    $rowErrs = [];

    foreach ($cells as $key => $val) {
        if ($val === '' || $val === null) continue;
        if (!preg_match('/^(\d+)_(\d+)$/', (string) $key, $m)) continue;
        $w = (int) $m[1];
        $d = (int) $m[2];
        if ($w <= 0 || $d <= 0) continue;
        if (!is_numeric($val)) {
            $rowErrs[] = "Non-numeric value at {$w}×{$d}: " . substr((string) $val, 0, 30);
            continue;
        }
        $price = (float) $val;
        if ($price < 0) {
            $rowErrs[] = "Negative price at {$w}×{$d}: " . $price;
            continue;
        }
        $bulk[] = [$tableId, $w, $d, $price];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $del = $pdo->prepare('DELETE FROM price_table_rows WHERE price_table_id = ?');
        $del->execute([$tableId]);
        if ($bulk) {
            $ins = $pdo->prepare(
                'INSERT INTO price_table_rows
                   (price_table_id, width_mm, drop_mm, price)
                 VALUES (?, ?, ?, ?)'
            );
            foreach ($bulk as $args) { $ins->execute($args); }
        }
        // Bump the updated_at on the parent table so the products
        // list reflects the change in its "Updated" column.
        $pdo->prepare('UPDATE price_tables SET updated_at = NOW() WHERE id = ?')
            ->execute([$tableId]);
        $pdo->commit();
        $summary = [
            'inserted' => count($bulk),
            'blank'    => 0,
            'errors'   => $rowErrs,
            'mode'     => 'inline',
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();
        $error = 'Database error: ' . $e->getMessage();
    }
}

// ---------------------------------------------------------------------------
// Template download — generate a pre-populated XLSX matrix.
// Includes existing data if the table has any rows; otherwise seeds with
// a sensible default UK grid (800–4800 mm wide × 800–4000 mm drop, step 400).
// ---------------------------------------------------------------------------
if ($action === 'template' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/../../vendor/autoload.php';

    $cellStmt = db()->prepare(
        'SELECT width_mm, drop_mm, price FROM price_table_rows WHERE price_table_id = ?'
    );
    $cellStmt->execute([$tableId]);
    $cells = $cellStmt->fetchAll();

    if ($cells) {
        $widths = $drops = [];
        $byPair = [];
        foreach ($cells as $c) {
            $w = (int) $c['width_mm'];
            $d = (int) $c['drop_mm'];
            $widths[$w] = true;
            $drops[$d]  = true;
            $byPair["$w|$d"] = $c['price'];
        }
        $widths = array_keys($widths); sort($widths);
        $drops  = array_keys($drops);  sort($drops);
    } else {
        $widths = [800, 1200, 1600, 2000, 2400, 2800, 3200, 3600, 4000, 4400, 4800];
        $drops  = [800, 1200, 1600, 2000, 2400, 2800, 3200, 3600, 4000];
        $byPair = [];
    }

    $ss    = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $ss->getActiveSheet();
    $sheet->setTitle('Band ' . $table['band_code']);

    $sheet->setCellValue('A1', 'Drop \\ Width (mm)');
    foreach ($widths as $i => $w) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 2);
        $sheet->setCellValue($col . '1', $w);
        $sheet->getColumnDimension($col)->setWidth(11);
    }
    foreach ($drops as $j => $d) {
        $sheet->setCellValue('A' . ($j + 2), $d);
    }
    foreach ($drops as $j => $d) {
        foreach ($widths as $i => $w) {
            $key = "$w|$d";
            if (isset($byPair[$key])) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 2);
                $sheet->setCellValue($col . ($j + 2), (float) $byPair[$key]);
            }
        }
    }

    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($widths) + 1);
    $headerRange = "A1:{$lastCol}1";
    $headerCol   = 'A1:A' . (count($drops) + 1);
    foreach ([$headerRange, $headerCol] as $r) {
        $sheet->getStyle($r)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($r)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1F3B5B');
    }
    $sheet->getColumnDimension('A')->setWidth(18);
    $sheet->freezePane('B2');

    $filename = preg_replace('/[^A-Za-z0-9_\- ]/', '', (string) $table['product_name'])
              . ' - ' . preg_replace('/[^A-Za-z0-9_\- ]/', '', (string) $table['system_name'])
              . ' - Band ' . $table['band_code'] . '.xlsx';

    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
    $writer->save('php://output');
    exit;
}

// ---------------------------------------------------------------------------
// Rigid-template upload — widths in row 1, drops in column A, single band.
// REPLACE strategy: wipe all existing cells, insert new.
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'upload') {
    csrf_check();

    if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Please choose a file to upload.';
    } elseif (filesize($_FILES['file']['tmp_name']) > 5 * 1024 * 1024) {
        $error = 'File too large (5 MB max).';
    } else {
        require __DIR__ . '/../../vendor/autoload.php';
        try {
            $ss     = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['file']['tmp_name']);
            $sheet  = $ss->getActiveSheet();
            $rows   = $sheet->toArray(null, true, true, true);

            $headerRow = $rows[1] ?? [];
            $widths    = [];
            foreach ($headerRow as $col => $val) {
                if ($col === 'A') continue;
                $w = is_numeric($val) ? (int) round((float) $val) : 0;
                if ($w > 0) $widths[$col] = $w;
            }
            if (!$widths) {
                $error = 'No width values detected in row 1 (B onwards).';
            } else {
                $inserted = 0;
                $blank    = 0;
                $rowErrs  = [];
                $bulk     = [];

                foreach ($rows as $rowNum => $row) {
                    if ($rowNum === 1) continue;
                    $dRaw = $row['A'] ?? null;
                    $d = is_numeric($dRaw) ? (int) round((float) $dRaw) : 0;
                    if ($d <= 0) continue;
                    foreach ($widths as $col => $w) {
                        $val = $row[$col] ?? null;
                        if ($val === null || $val === '' || !is_numeric($val)) {
                            $blank++;
                            continue;
                        }
                        $price = (float) $val;
                        if ($price < 0) {
                            $rowErrs[] = "Row $rowNum col $col: negative price ($price)";
                            continue;
                        }
                        $bulk[] = [$tableId, $w, $d, $price];
                        $inserted++;
                    }
                }

                if (!$bulk) {
                    $error = 'No valid price cells found in the uploaded file.';
                } else {
                    $pdo = db();
                    $pdo->beginTransaction();
                    try {
                        $del = $pdo->prepare('DELETE FROM price_table_rows WHERE price_table_id = ?');
                        $del->execute([$tableId]);
                        $ins = $pdo->prepare(
                            'INSERT INTO price_table_rows
                               (price_table_id, width_mm, drop_mm, price)
                             VALUES (?, ?, ?, ?)'
                        );
                        foreach ($bulk as $args) { $ins->execute($args); }
                        $pdo->prepare('UPDATE price_tables SET updated_at = NOW() WHERE id = ?')
                            ->execute([$tableId]);
                        $pdo->commit();
                        $summary = [
                            'inserted' => $inserted,
                            'blank'    => $blank,
                            'errors'   => $rowErrs,
                            'mode'     => 'upload',
                        ];
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
            }
        } catch (Throwable $e) {
            $error = 'Could not read the file: ' . $e->getMessage();
        }
    }
}

// ---------------------------------------------------------------------------
// Flexible single-table import — uses the shared parser that the bulk
// importer uses. Picks the band whose code matches this table's band_code if
// present in the file; otherwise falls back to the only band found.
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'upload_flex') {
    csrf_check();

    if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Please choose a file to upload.';
    } elseif (filesize($_FILES['file']['tmp_name']) > 10 * 1024 * 1024) {
        $error = 'File too large (10 MB max).';
    } else {
        require __DIR__ . '/../../vendor/autoload.php';
        require __DIR__ . '/../../_partials/price_table_parser.php';
        try {
            $ss    = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['file']['tmp_name']);
            $bands = [];
            foreach ($ss->getAllSheets() as $sheet) {
                $rows = $sheet->toArray(null, true, true, true);
                $bands = array_merge($bands, ptp_parse_band_blocks($rows));
            }

            if (!$bands) {
                $error = 'Could not detect any band data in the file. '
                       . 'For multi-band files, use the bulk-import page instead. '
                       . 'For a single-band file, the parser still expects a "Band X" header row.';
            } else {
                $myCode = strtoupper((string) $table['band_code']);
                $picked = null;
                foreach ($bands as $b) {
                    if (strtoupper($b['code']) === $myCode) {
                        $picked = $b;
                        break;
                    }
                }
                $usedFallback = false;
                if ($picked === null) {
                    $picked = $bands[0];
                    $usedFallback = true;
                }

                if (!$picked['cells']) {
                    $error = 'The matched band had no price cells.';
                } else {
                    $pdo = db();
                    $pdo->beginTransaction();
                    try {
                        $del = $pdo->prepare('DELETE FROM price_table_rows WHERE price_table_id = ?');
                        $del->execute([$tableId]);
                        $ins = $pdo->prepare(
                            'INSERT INTO price_table_rows
                               (price_table_id, width_mm, drop_mm, price)
                             VALUES (?, ?, ?, ?)'
                        );
                        foreach ($picked['cells'] as [$w, $d, $p]) {
                            $ins->execute([$tableId, $w, $d, $p]);
                        }
                        $pdo->prepare('UPDATE price_tables SET updated_at = NOW() WHERE id = ?')
                            ->execute([$tableId]);
                        $pdo->commit();
                        $summary = [
                            'inserted'        => count($picked['cells']),
                            'blank'           => 0,
                            'errors'          => [],
                            'flex_band'       => $picked['code'],
                            'flex_fallback'   => $usedFallback,
                            'flex_total_seen' => count($bands),
                            'mode'            => 'flex',
                        ];
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
            }
        } catch (Throwable $e) {
            $error = 'Could not read the file: ' . $e->getMessage();
        }
    }
}

// ---------------------------------------------------------------------------
// Load current cells for the editor.
// "Quick start" default grid (UK standard sizes) is only suggested when the
// table is empty — once populated we always render exactly what's stored.
// ---------------------------------------------------------------------------
$cellStmt = db()->prepare(
    'SELECT width_mm, drop_mm, price FROM price_table_rows WHERE price_table_id = ?'
);
$cellStmt->execute([$tableId]);
$cells = $cellStmt->fetchAll();

$matrixWidths = $matrixDrops = [];
$matrixByPair = [];
foreach ($cells as $c) {
    $w = (int) $c['width_mm'];
    $d = (int) $c['drop_mm'];
    $matrixWidths[$w] = true;
    $matrixDrops[$d]  = true;
    $matrixByPair["$w|$d"] = $c['price'];
}
$matrixWidths = array_keys($matrixWidths); sort($matrixWidths);
$matrixDrops  = array_keys($matrixDrops);  sort($matrixDrops);

$isEmpty = !$matrixWidths && !$matrixDrops;

// "Quick start" grid for empty tables.
$DEFAULT_WIDTHS = [800, 1200, 1600, 2000, 2400, 2800, 3200, 3600, 4000];
$DEFAULT_DROPS  = [800, 1200, 1600, 2000, 2400, 2800, 3200, 3600, 4000];

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) $table['product_name']) ?> &middot; Band <?= e((string) $table['band_code']) ?> &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .tip-box {
            background: var(--bg-subtle); border: 1px solid var(--border); border-radius: 8px;
            padding: 0.75rem 1rem; font-size: 0.9375rem; color: var(--text-muted);
            margin-bottom: 1rem;
        }
        .tip-box code {
            background: var(--bg-card); padding: 0.0625rem 0.375rem; border-radius: 4px;
            border: 1px solid var(--border); font-size: 0.8125rem;
        }
        .summary-list { margin: 0; padding-left: 1.25rem; }
        .summary-list li { font-size: 0.9375rem; color: var(--text-muted); }

        /* Editable matrix.
           Sticky first column + first row so big grids stay
           navigable. Compact inputs (right-aligned numerics) so a
           20×20 grid fits a normal screen without horizontal scroll. */
        .grid-wrap {
            overflow: auto;
            max-height: 70vh;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-card);
        }
        table.grid-table {
            border-collapse: separate; border-spacing: 0;
            font-size: 0.8125rem;
            font-variant-numeric: tabular-nums;
        }
        table.grid-table th, table.grid-table td {
            border-right: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 0;
            white-space: nowrap;
            background: var(--bg-card);
        }
        table.grid-table thead th {
            position: sticky; top: 0;
            background: var(--brand); color: #fff;
            font-weight: 700;
            padding: 0.375rem 0.5rem;
            text-align: center;
            z-index: 2;
        }
        table.grid-table thead th.corner {
            left: 0; z-index: 3;
            background: var(--brand-hover);
            font-size: 0.6875rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        table.grid-table tbody th {
            position: sticky; left: 0;
            background: var(--brand); color: #fff;
            font-weight: 700;
            padding: 0.375rem 0.5rem;
            text-align: center;
            z-index: 1;
        }
        table.grid-table tbody th.adder,
        table.grid-table thead th.adder {
            background: var(--bg-subtle-2);
            color: var(--text-primary);
        }
        .grid-cell-input {
            width: 5.5rem;
            border: 0; outline: 0;
            background: transparent;
            padding: 0.375rem 0.5rem;
            font: inherit; font-variant-numeric: tabular-nums;
            text-align: right;
            color: var(--text-primary);
        }
        .grid-cell-input:focus {
            background: #fef9c3;
            color: var(--text-primary);
        }
        [data-theme="dark"] .grid-cell-input:focus { background: rgba(254,243,123,0.18); }
        .grid-cell-input::-webkit-outer-spin-button,
        .grid-cell-input::-webkit-inner-spin-button {
            -webkit-appearance: none; margin: 0;
        }
        .grid-cell-input { -moz-appearance: textfield; }
        .grid-axis-rm {
            background: transparent; border: 0;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            font-size: 0.75rem; font-weight: 700;
            margin-left: 0.25rem;
            padding: 0 0.25rem;
            line-height: 1;
        }
        .grid-axis-rm:hover { color: #fff; }
        .grid-add-btn {
            background: transparent; border: 0;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 0.8125rem; font-weight: 600;
            padding: 0.5rem 0.75rem;
            width: 100%; height: 100%;
        }
        .grid-add-btn:hover { color: var(--brand); }
        .grid-toolbar {
            display: flex; gap: 0.625rem; align-items: center;
            flex-wrap: wrap;
            margin-top: 0.875rem;
        }
        .grid-toolbar .muted {
            color: var(--text-faint); font-size: 0.8125rem;
        }

        /* "Quick start" empty-state hero — only shown when the table
           has no cells yet. Big primary button to populate with the
           UK default grid; secondary "build from scratch" link. */
        .qs-tile {
            background: var(--bg-subtle); border: 1px solid var(--border);
            border-radius: 12px; padding: 1.5rem 1.25rem;
            text-align: center;
        }
        .qs-tile h3 { margin: 0 0 0.375rem; color: var(--text-primary); font-size: 1.125rem; }
        .qs-tile p  { margin: 0 0 1rem; color: var(--text-muted); font-size: 0.9375rem; }
        .qs-tile .qs-secondary {
            display: block; margin-top: 0.625rem;
            color: var(--text-faint); font-size: 0.8125rem; text-decoration: underline;
        }

        /* Advanced section — closed by default. Discoverable but out
           of the way for the common case. */
        details.advanced {
            margin-top: 1.25rem;
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: var(--shadow-sm);
        }
        details.advanced > summary {
            cursor: pointer;
            padding: 0.875rem 1.125rem;
            font-weight: 600; color: var(--text-primary);
            list-style: none;
        }
        details.advanced > summary::-webkit-details-marker { display: none; }
        details.advanced > summary::before {
            content: '▸';
            display: inline-block;
            margin-right: 0.5rem;
            color: var(--text-faint);
            transition: transform 150ms;
        }
        details.advanced[open] > summary::before { transform: rotate(90deg); }
        details.advanced > .advanced-body {
            border-top: 1px solid var(--border);
            padding: 1rem 1.125rem;
        }
        details.advanced .sub-section { margin-bottom: 1.25rem; }
        details.advanced .sub-section:last-child { margin-bottom: 0; }
        details.advanced .sub-section h3 {
            margin: 0 0 0.5rem; font-size: 0.9375rem; color: var(--text-primary);
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../../_partials/sidebar.php'; ?>

    <main class="app-main">
        <?php
            // "from=wizard" indicates the user clicked through here from
            // the setup wizard's step 4 checklist. Surface a "back to
            // wizard" link so they can return without rummaging through
            // the sidebar. product_id is required so we know which
            // wizard run to resume.
            $fromWizard = ($_GET['from'] ?? '') === 'wizard';
            $wizardBackId = $fromWizard ? (int) ($_GET['product_id'] ?? 0) : 0;
        ?>
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <?= e((string) $table['product_name']) ?>
                    / <?= e((string) $table['system_name']) ?>
                    &mdash; Band <?= e((string) $table['band_code']) ?>
                    <?php if (!empty($table['name'])): ?>
                        <span style="color:var(--text-faint);font-weight:400;font-size:1rem">&mdash; <?= e((string) $table['name']) ?></span>
                    <?php endif; ?>
                </h1>
                <p class="page-subtitle">
                    <?php if ($wizardBackId > 0): ?>
                        <a href="/admin/products/wizard.php?id=<?= $wizardBackId ?>&step=4">
                            &larr; Back to setup wizard
                        </a>
                    <?php else: ?>
                        <a href="/admin/products/price-tables.php?system_id=<?= (int) $table['system_id'] ?>">
                            &larr; All <?= e((string) $table['system_name']) ?> price tables
                        </a>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($summary !== null): ?>
            <div class="alert alert-success" role="status">
                Saved <strong><?= (int) $summary['inserted'] ?></strong> price cells.
                <?php if (!empty($summary['blank']) && $summary['blank'] > 0): ?>
                    Skipped <?= (int) $summary['blank'] ?> blank cell<?= $summary['blank'] === 1 ? '' : 's' ?>.
                <?php endif; ?>
                <?php if (!empty($summary['flex_band'])): ?>
                    Picked <strong>Band <?= e((string) $summary['flex_band']) ?></strong>
                    out of <?= (int) $summary['flex_total_seen'] ?> band<?= $summary['flex_total_seen'] === 1 ? '' : 's' ?> in the file.
                    <?php if (!empty($summary['flex_fallback']) && strtoupper($summary['flex_band']) !== strtoupper((string) $table['band_code'])): ?>
                        <em>(No "Band <?= e((string) $table['band_code']) ?>" header in the file — used the first band instead.)</em>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($summary['errors'])): ?>
                <div class="alert alert-error" role="alert">
                    <strong>Some cells had problems:</strong>
                    <ul class="summary-list">
                        <?php foreach (array_slice($summary['errors'], 0, 25) as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                        <?php if (count($summary['errors']) > 25): ?>
                            <li>… and <?= count($summary['errors']) - 25 ?> more</li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- ============== INLINE-EDITABLE GRID ============== -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">
                    Edit prices
                    <?php if (!$isEmpty): ?>
                        <span style="color:var(--text-faint);font-weight:400;font-size:0.875rem">
                            — <?= count($cells) ?> cell<?= count($cells) === 1 ? '' : 's' ?>,
                            <?= count($matrixWidths) ?> width<?= count($matrixWidths) === 1 ? '' : 's' ?>
                            × <?= count($matrixDrops) ?> drop<?= count($matrixDrops) === 1 ? '' : 's' ?>
                        </span>
                    <?php endif; ?>
                </h2>
            </div>

            <?php if ($isEmpty): ?>
                <!-- Empty state — offer Quick Start or Start blank.
                     Both routes land you on the same editor; "Quick start"
                     pre-fills the UK default grid client-side (no DB write
                     yet, just inputs ready for filling). -->
                <div class="qs-tile">
                    <h3>This price table is empty</h3>
                    <p>
                        Start with a standard UK grid (widths 800&ndash;4000mm,
                        drops 800&ndash;4000mm in 400mm steps), then type
                        prices straight into the cells. Or build the grid
                        column by column.
                    </p>
                    <button type="button" id="qs-fill" class="btn btn-primary">
                        Quick start &mdash; default grid
                    </button>
                    <a href="#" id="qs-blank" class="qs-secondary">
                        Or build from scratch — start blank
                    </a>
                </div>
                <!-- Hidden defaults so the JS knows what to expand. -->
                <script id="qs-defaults" type="application/json">{
                    "widths": <?= json_encode($DEFAULT_WIDTHS) ?>,
                    "drops":  <?= json_encode($DEFAULT_DROPS) ?>
                }</script>
            <?php endif; ?>

            <form method="post" action="/admin/products/price-table.php<?= $fromWizard ? '?from=wizard&product_id=' . $wizardBackId : '' ?>"
                  id="grid-form"
                  <?= $isEmpty ? 'style="display:none"' : '' ?>>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_grid">
                <input type="hidden" name="id" value="<?= (int) $tableId ?>">

                <p style="color:var(--text-faint);font-size:0.8125rem;margin:0 0 0.625rem">
                    Click any cell, type the new price, hit <kbd>Tab</kbd> to move on.
                    Leave a cell blank to skip that width × drop combo (no price quoted).
                    Use the <strong>×</strong> next to a header to drop a column or row;
                    <strong>+ Width</strong> / <strong>+ Drop</strong> extends the grid.
                    <strong>Paste from Excel:</strong> click a cell and
                    paste &mdash; a range copied from a spreadsheet spreads across
                    rows and columns starting from that cell.
                </p>

                <div class="grid-wrap">
                    <table class="grid-table" id="grid-table">
                        <thead>
                            <tr>
                                <th class="corner">Drop \ Width (mm)</th>
                                <?php foreach ($matrixWidths as $w): ?>
                                    <th data-w="<?= (int) $w ?>">
                                        <?= (int) $w ?>
                                        <button type="button" class="grid-axis-rm"
                                                data-rm-w="<?= (int) $w ?>" title="Remove this width">×</button>
                                    </th>
                                <?php endforeach; ?>
                                <th class="adder">
                                    <button type="button" class="grid-add-btn" id="add-width">+ Width</button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matrixDrops as $d): ?>
                                <tr data-d="<?= (int) $d ?>">
                                    <th>
                                        <?= (int) $d ?>
                                        <button type="button" class="grid-axis-rm"
                                                data-rm-d="<?= (int) $d ?>" title="Remove this drop">×</button>
                                    </th>
                                    <?php foreach ($matrixWidths as $w):
                                        $val = $matrixByPair["$w|$d"] ?? null;
                                    ?>
                                        <td>
                                            <input class="grid-cell-input"
                                                   type="number"
                                                   step="0.01"
                                                   min="0"
                                                   inputmode="decimal"
                                                   name="cells[<?= (int) $w ?>_<?= (int) $d ?>]"
                                                   value="<?= $val === null ? '' : e(number_format((float) $val, 2, '.', '')) ?>">
                                        </td>
                                    <?php endforeach; ?>
                                    <td></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <th class="adder">
                                    <button type="button" class="grid-add-btn" id="add-drop">+ Drop</button>
                                </th>
                                <?php for ($i = 0, $n = count($matrixWidths) + 1; $i < $n; $i++): ?>
                                    <td></td>
                                <?php endfor; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid-toolbar">
                    <button type="submit" class="btn btn-primary">Save grid</button>
                    <a href="/admin/products/price-tables.php?system_id=<?= (int) $table['system_id'] ?>"
                       class="btn btn-secondary">Cancel</a>
                    <span class="muted">
                        Saving replaces every cell in this table with what's on screen.
                    </span>
                </div>
            </form>
        </section>

        <!-- ============== ADVANCED (XLSX template + supplier import) ============== -->
        <details class="advanced">
            <summary>Advanced — XLSX import / export</summary>
            <div class="advanced-body">
                <div class="sub-section">
                    <h3>Download as XLSX</h3>
                    <p style="color:var(--text-muted);font-size:0.875rem;margin:0 0 0.625rem">
                        Pre-populated Excel matrix — useful for backup, sharing with suppliers,
                        or making lots of edits in Excel before re-uploading.
                    </p>
                    <a class="btn btn-secondary"
                       href="/admin/products/price-table.php?id=<?= (int) $tableId ?>&action=template">
                        Download template (.xlsx)
                    </a>
                </div>

                <div class="sub-section">
                    <h3>Upload — rigid template</h3>
                    <p style="color:var(--text-muted);font-size:0.875rem;margin:0 0 0.625rem">
                        Widths across row 1, drops down column A, prices in the cells.
                        <strong>Replaces every cell</strong> in this table on import.
                    </p>
                    <form method="post" action="/admin/products/price-table.php<?= $fromWizard ? '?from=wizard&product_id=' . $wizardBackId : '' ?>"
                          enctype="multipart/form-data" style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:end">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="upload">
                        <input type="hidden" name="id" value="<?= (int) $tableId ?>">
                        <input type="file" name="file"
                               accept=".xlsx,.xlsm,.xls,.csv,.ods"
                               required style="flex:1;min-width:18rem">
                        <button type="submit" class="btn btn-secondary">Upload &amp; replace</button>
                    </form>
                </div>

                <div class="sub-section">
                    <h3>Upload — supplier file (flexible parser)</h3>
                    <p style="color:var(--text-muted);font-size:0.875rem;margin:0 0 0.625rem">
                        Drop a supplier sheet straight in. Detects "Band X" headers, widths in mm or metres,
                        prices with £ / commas. Picks the band matching this table's
                        <strong>Band <?= e((string) $table['band_code']) ?></strong>;
                        falls back to the first band found.
                    </p>
                    <form method="post" action="/admin/products/price-table.php<?= $fromWizard ? '?from=wizard&product_id=' . $wizardBackId : '' ?>"
                          enctype="multipart/form-data" style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:end">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="upload_flex">
                        <input type="hidden" name="id" value="<?= (int) $tableId ?>">
                        <input type="file" name="file"
                               accept=".xlsx,.xlsm,.xls,.csv,.ods"
                               required style="flex:1;min-width:18rem">
                        <button type="submit" class="btn btn-secondary">Import &amp; replace</button>
                    </form>
                </div>
            </div>
        </details>
    </main>
</div>

<script>
(function () {
    // Grid editor — pure DOM manipulation of the cell inputs. Server
    // sees the form as a flat cells[<width>_<drop>] map; widths and
    // drops are inferred from the keys, so adding/removing rows or
    // columns is just adding/removing inputs on the client.
    'use strict';

    var qsFill  = document.getElementById('qs-fill');
    var qsBlank = document.getElementById('qs-blank');
    var qsData  = document.getElementById('qs-defaults');
    var form    = document.getElementById('grid-form');
    var table   = document.getElementById('grid-table');

    // ----- empty-state Quick Start handlers ----- //
    if (qsFill && qsData) {
        var defaults = JSON.parse(qsData.textContent);
        qsFill.addEventListener('click', function () {
            // Populate the empty grid with default widths and drops.
            defaults.widths.forEach(function (w) { addWidth(w); });
            defaults.drops.forEach(function (d)  { addDrop(d);  });
            showGrid();
        });
    }
    if (qsBlank) {
        qsBlank.addEventListener('click', function (e) {
            e.preventDefault();
            showGrid();
        });
    }

    function showGrid() {
        var tile = document.querySelector('.qs-tile');
        if (tile) tile.style.display = 'none';
        if (form) form.style.display = '';
    }

    // ----- helpers to read / mutate the current matrix ----- //

    // Read the current width column ids from the header row.
    function currentWidths() {
        var ths = table.querySelectorAll('thead th[data-w]');
        var ws  = [];
        ths.forEach(function (th) { ws.push(parseInt(th.getAttribute('data-w'), 10)); });
        return ws;
    }
    function currentDrops() {
        var rows = table.querySelectorAll('tbody tr[data-d]');
        var ds   = [];
        rows.forEach(function (r) { ds.push(parseInt(r.getAttribute('data-d'), 10)); });
        return ds;
    }

    function makeCellInput(w, d) {
        var inp = document.createElement('input');
        inp.className = 'grid-cell-input';
        inp.type = 'number';
        inp.step = '0.01';
        inp.min  = '0';
        inp.inputMode = 'decimal';
        inp.name = 'cells[' + w + '_' + d + ']';
        inp.value = '';
        return inp;
    }

    // Add a new width column.
    function addWidth(w) {
        if (!w && w !== 0) {
            var ws = currentWidths();
            var suggested = ws.length ? Math.max.apply(null, ws) + 400 : 800;
            var raw = window.prompt('New width (mm):', String(suggested));
            if (raw === null) return;
            w = parseInt(raw, 10);
            if (!w || w <= 0) return;
        }
        if (currentWidths().indexOf(w) !== -1) {
            alert('Width ' + w + 'mm already exists.');
            return;
        }

        // Insert the new <th> in the right position (sorted ascending).
        var headerRow = table.querySelector('thead tr');
        var adderTh   = headerRow.querySelector('th.adder');
        var newTh = document.createElement('th');
        newTh.setAttribute('data-w', String(w));
        newTh.innerHTML = w +
            ' <button type="button" class="grid-axis-rm" data-rm-w="' + w + '" title="Remove this width">×</button>';
        // Insert before the first existing width that's larger, or before the adder.
        var inserted = false;
        headerRow.querySelectorAll('th[data-w]').forEach(function (th) {
            if (inserted) return;
            if (parseInt(th.getAttribute('data-w'), 10) > w) {
                headerRow.insertBefore(newTh, th);
                inserted = true;
            }
        });
        if (!inserted) headerRow.insertBefore(newTh, adderTh);

        // For each existing drop row, insert a matching empty cell at
        // the same column index.
        var newIdx = Array.prototype.indexOf.call(headerRow.children, newTh);
        table.querySelectorAll('tbody tr[data-d]').forEach(function (row) {
            var d = parseInt(row.getAttribute('data-d'), 10);
            var td = document.createElement('td');
            td.appendChild(makeCellInput(w, d));
            // newIdx counts from 0 in the header (corner = 0). In the
            // body row, the row header is the first child too, so the
            // same index works.
            var ref = row.children[newIdx] || null;
            row.insertBefore(td, ref);
        });
        // Pad the trailing "+ Drop" row.
        var lastRow = table.querySelector('tbody tr:last-child');
        if (lastRow && !lastRow.hasAttribute('data-d')) {
            var blank = document.createElement('td');
            var ref2  = lastRow.children[newIdx] || null;
            lastRow.insertBefore(blank, ref2);
        }
    }

    function addDrop(d) {
        if (!d && d !== 0) {
            var ds = currentDrops();
            var suggested = ds.length ? Math.max.apply(null, ds) + 400 : 800;
            var raw = window.prompt('New drop (mm):', String(suggested));
            if (raw === null) return;
            d = parseInt(raw, 10);
            if (!d || d <= 0) return;
        }
        if (currentDrops().indexOf(d) !== -1) {
            alert('Drop ' + d + 'mm already exists.');
            return;
        }

        var tbody = table.querySelector('tbody');
        var trailingRow = table.querySelector('tbody tr:last-child');
        var ws = currentWidths();

        var tr = document.createElement('tr');
        tr.setAttribute('data-d', String(d));
        var th = document.createElement('th');
        th.innerHTML = d +
            ' <button type="button" class="grid-axis-rm" data-rm-d="' + d + '" title="Remove this drop">×</button>';
        tr.appendChild(th);
        ws.forEach(function (w) {
            var td = document.createElement('td');
            td.appendChild(makeCellInput(w, d));
            tr.appendChild(td);
        });
        var endTd = document.createElement('td');
        tr.appendChild(endTd);

        // Insert sorted by drop value.
        var inserted = false;
        tbody.querySelectorAll('tr[data-d]').forEach(function (row) {
            if (inserted) return;
            if (parseInt(row.getAttribute('data-d'), 10) > d) {
                tbody.insertBefore(tr, row);
                inserted = true;
            }
        });
        if (!inserted) {
            // Insert just before the trailing "+ Drop" row.
            tbody.insertBefore(tr, trailingRow);
        }
    }

    function removeWidth(w) {
        if (!confirm('Remove the ' + w + 'mm width column? Any prices in it will be lost on next save.')) return;
        var headerRow = table.querySelector('thead tr');
        var ths = Array.prototype.slice.call(headerRow.children);
        var idx = -1;
        ths.forEach(function (th, i) {
            if (th.getAttribute && th.getAttribute('data-w') === String(w)) idx = i;
        });
        if (idx < 0) return;
        headerRow.removeChild(headerRow.children[idx]);
        table.querySelectorAll('tbody tr').forEach(function (row) {
            if (row.children[idx]) row.removeChild(row.children[idx]);
        });
    }

    function removeDrop(d) {
        if (!confirm('Remove the ' + d + 'mm drop row? Any prices in it will be lost on next save.')) return;
        var row = table.querySelector('tbody tr[data-d="' + d + '"]');
        if (row) row.parentNode.removeChild(row);
    }

    // ----- click-delegated event handlers ----- //
    var addW = document.getElementById('add-width');
    var addD = document.getElementById('add-drop');
    if (addW) addW.addEventListener('click', function () { addWidth(); });
    if (addD) addD.addEventListener('click', function () { addDrop();  });

    if (table) {
        table.addEventListener('click', function (e) {
            var t = e.target;
            if (!t || !t.getAttribute) return;
            var rmW = t.getAttribute('data-rm-w');
            var rmD = t.getAttribute('data-rm-d');
            if (rmW) { e.preventDefault(); removeWidth(parseInt(rmW, 10)); }
            if (rmD) { e.preventDefault(); removeDrop(parseInt(rmD, 10));  }
        });

        // ----- Excel-style paste handler ----- //
        //
        // When the user copies a range from a spreadsheet, the clipboard
        // contains TSV (rows separated by \n or \r\n, columns by \t).
        // We catch the paste at the table level, parse the clipboard
        // text, and spread the values across cells starting from the
        // focused cell going right and down. Single-cell pastes (no
        // tabs or newlines) fall through to the browser's default so
        // straight typing isn't disrupted.
        table.addEventListener('paste', function (e) {
            var target = e.target;
            if (!target || target.tagName !== 'INPUT'
                || !target.classList.contains('grid-cell-input')) {
                return;
            }
            var clipboard = (e.clipboardData || window.clipboardData);
            if (!clipboard) return;
            var text = clipboard.getData('text/plain') || clipboard.getData('text') || '';
            if (text === '') return;

            // Single-cell paste? Let the browser handle it normally.
            // The cheap check is "no tabs, no newlines, no carriage returns".
            if (text.indexOf('\t') === -1
             && text.indexOf('\n') === -1
             && text.indexOf('\r') === -1) {
                return;
            }
            e.preventDefault();

            // Parse the TSV. Strip a single trailing newline (Excel
            // adds one) then split. Each row gets split by \t.
            var rows = text.replace(/\r/g, '').replace(/\n$/, '').split('\n');
            var grid = rows.map(function (r) { return r.split('\t'); });

            // Locate the paste anchor — which (width, drop) is the
            // focused cell at? Use its name attribute "cells[W_D]".
            var m = target.name && target.name.match(/^cells\[(\d+)_(\d+)\]$/);
            if (!m) return;
            var anchorW = parseInt(m[1], 10);
            var anchorD = parseInt(m[2], 10);

            var ws = currentWidths();
            var ds = currentDrops();
            var startWIdx = ws.indexOf(anchorW);
            var startDIdx = ds.indexOf(anchorD);
            if (startWIdx < 0 || startDIdx < 0) return;

            // Walk the parsed grid. Row i corresponds to drop
            // ds[startDIdx + i]; column j corresponds to width
            // ws[startWIdx + j]. Cells beyond the existing grid are
            // ignored — keeps the user in control of grid shape.
            var filled = 0;
            var skipped = 0;
            grid.forEach(function (row, i) {
                var di = startDIdx + i;
                if (di >= ds.length) { skipped += row.length; return; }
                var d  = ds[di];
                row.forEach(function (raw, j) {
                    var wi = startWIdx + j;
                    if (wi >= ws.length) { skipped++; return; }
                    var w = ws[wi];
                    var input = form.querySelector(
                        'input[name="cells[' + w + '_' + d + ']"]'
                    );
                    if (!input) { skipped++; return; }
                    // Clean up: strip currency symbols, commas,
                    // whitespace. Empty after that = blank cell
                    // (treated as "no price").
                    var clean = String(raw)
                        .replace(/[£$€]/g, '')
                        .replace(/,/g, '')
                        .trim();
                    if (clean === '') {
                        input.value = '';
                    } else if (/^-?[0-9.]+$/.test(clean)) {
                        input.value = clean;
                    } else {
                        skipped++;
                        return;
                    }
                    filled++;
                });
            });

            // Briefly flash the affected cells so the user can see
            // what landed. Pure visual feedback — no behaviour change.
            if (filled > 0) {
                target.style.transition = 'background-color 800ms';
                target.style.backgroundColor = '#a7f3d0';
                setTimeout(function () { target.style.backgroundColor = ''; }, 800);
            }
            if (skipped > 0) {
                console.log('Paste: filled ' + filled + ', skipped ' + skipped
                    + ' (outside grid or non-numeric).');
            }
        });
    }
})();
</script>
<?php
    // Floating "Fix next →" pill — drops the user into the next
    // catalogue-health issue without going back to the product
    // page first. Same helper used on extra.php / extra-edit.php.
    require_once __DIR__ . '/../../_partials/catalogue_validator.php';
    echo catalogue_render_fix_next_pill(
        (int) $table['product_id'],
        (int) $clientId,
        (string) ($_SERVER['REQUEST_URI'] ?? ''),
        (string) ($table['product_name'] ?? '')
    );
?>
</body>
</html>
