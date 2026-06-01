<?php
declare(strict_types=1);

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
// Template download — generate a pre-populated XLSX matrix.
// Includes existing data if the table has any rows; otherwise seeds with
// a sensible default UK grid (800–4800 mm wide × 800–4000 mm drop, step 400).
// ---------------------------------------------------------------------------
if ($action === 'template' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/../../vendor/autoload.php';

    // Pull existing cells (if any) so we can pre-fill the template for editing.
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

    // Corner header + width row + drop column.
    $sheet->setCellValue('A1', 'Drop \\ Width (mm)');
    foreach ($widths as $i => $w) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 2);
        $sheet->setCellValue($col . '1', $w);
        $sheet->getColumnDimension($col)->setWidth(11);
    }
    foreach ($drops as $j => $d) {
        $sheet->setCellValue('A' . ($j + 2), $d);
    }
    // Fill price cells from existing data (if any).
    foreach ($drops as $j => $d) {
        foreach ($widths as $i => $w) {
            $key = "$w|$d";
            if (isset($byPair[$key])) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 2);
                $sheet->setCellValue($col . ($j + 2), (float) $byPair[$key]);
            }
        }
    }

    // Style the header row + column.
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
// File upload — parse + REPLACE (delete all existing rows, insert new).
//
// Two modes:
//   ?action=upload         — rigid template (widths in row 1, drops in column A,
//                            single-band, no header — what the Download template
//                            generates)
//   ?action=upload_flex    — flexible parser, any supplier sheet. Picks the band
//                            matching this table's band_code if present; otherwise
//                            takes the first band found.
// ---------------------------------------------------------------------------
$summary = null;
$error   = null;

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

            // Row 1 holds widths from column B onwards; column A holds drops
            // from row 2 down. A1 is the corner label and is ignored.
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
                    if ($rowNum === 1) continue; // header row
                    $dRaw = $row['A'] ?? null;
                    $d = is_numeric($dRaw) ? (int) round((float) $dRaw) : 0;
                    if ($d <= 0) {
                        // Skip rows whose A column doesn't contain a number.
                        continue;
                    }
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
                        // Replace strategy: wipe existing cells, then insert.
                        $del = $pdo->prepare('DELETE FROM price_table_rows WHERE price_table_id = ?');
                        $del->execute([$tableId]);
                        $ins = $pdo->prepare(
                            'INSERT INTO price_table_rows
                               (price_table_id, width_mm, drop_mm, price)
                             VALUES (?, ?, ?, ?)'
                        );
                        foreach ($bulk as $args) { $ins->execute($args); }
                        $pdo->commit();
                        $summary = [
                            'inserted' => $inserted,
                            'blank'    => $blank,
                            'errors'   => $rowErrs,
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
                // Prefer a band matching this table's band_code; otherwise use
                // the first band found.
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
                        $pdo->commit();
                        $summary = [
                            'inserted'        => count($picked['cells']),
                            'blank'           => 0,
                            'errors'          => [],
                            'flex_band'       => $picked['code'],
                            'flex_fallback'   => $usedFallback,
                            'flex_total_seen' => count($bands),
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

// Load existing cells for the matrix preview.
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
            background: #fff; padding: 0.0625rem 0.375rem; border-radius: 4px;
            border: 1px solid var(--border); font-size: 0.8125rem;
        }
        .matrix-table {
            border-collapse: collapse; font-size: 0.8125rem; width: auto;
            font-variant-numeric: tabular-nums;
        }
        .matrix-table th, .matrix-table td {
            border: 1px solid var(--border); padding: 0.375rem 0.625rem; text-align: right;
            white-space: nowrap;
        }
        .matrix-table thead th, .matrix-table tbody th {
            background: #1f3b5b; color: #fff; font-weight: 700; text-align: center;
        }
        .matrix-table tbody td.empty { color: var(--border-strong); }
        .summary-list { margin: 0; padding-left: 1.25rem; }
        .summary-list li { font-size: 0.9375rem; color: var(--text-muted); }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../../_partials/sidebar.php'; ?>

    <main class="app-main">
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
                    <a href="/admin/products/price-tables.php?system_id=<?= (int) $table['system_id'] ?>">
                        &larr; All <?= e((string) $table['system_name']) ?> price tables
                    </a>
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($summary !== null): ?>
            <div class="alert alert-success" role="status">
                Imported <strong><?= (int) $summary['inserted'] ?></strong> price cells.
                <?php if ($summary['blank'] > 0): ?>
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
            <?php if ($summary['errors']): ?>
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

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">1. Download the template</h2>
            </div>
            <div class="tip-box">
                Excel grid: widths across the top row in mm, drops down column A in mm,
                prices in £ in the cells. Header row and column auto-fill with sensible
                defaults if this is a new table; if there's data already, the template
                comes pre-populated so you can edit and re-upload.
                Blank cells are stored as "no price for that combination".
            </div>
            <p>
                <a class="btn btn-primary"
                   href="/admin/products/price-table.php?id=<?= (int) $tableId ?>&action=template">
                    Download template (.xlsx)
                </a>
            </p>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">2. Upload</h2>
            </div>
            <div class="tip-box">
                Importing <strong>replaces</strong> all cells in this price table with what's
                in the file. Add or remove columns / rows in the Excel file to change which
                widths and drops are stored.
            </div>
            <form method="post" action="/admin/products/price-table.php"
                  enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="id" value="<?= (int) $tableId ?>">

                <div class="form-row full">
                    <div class="form-group">
                        <label for="file">Filled template (.xlsx)</label>
                        <input id="file" name="file" type="file"
                               accept=".xlsx,.xlsm,.xls,.csv,.ods"
                               required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Upload &amp; replace</button>
                    <a href="/admin/products/price-tables.php?system_id=<?= (int) $table['system_id'] ?>"
                       class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Or: import a supplier file directly</h2>
            </div>
            <div class="tip-box">
                Skip the template if you already have a supplier sheet. The flexible parser handles:
                <ul style="margin:0.5rem 0 0;padding-left:1.25rem">
                    <li>"Band X" or "Price Band X" headers, with or without typos like "Bnad"</li>
                    <li>Widths in <strong>mm</strong> (e.g. <code>610mm</code>) or <strong>metres</strong> (e.g. <code>0.800</code>) — auto-detected per cell</li>
                    <li>Prices with <strong>£</strong>, commas, or bare numbers</li>
                    <li>Stray label rows ("DROP", "WIDTH", "Metric", inches references) get skipped</li>
                </ul>
                If the file contains multiple bands, the parser uses the one matching this table's
                <strong>Band <?= e((string) $table['band_code']) ?></strong>; failing that, the first band found.
                Replace strategy still applies — every cell in this table is wiped and reinserted.
            </div>
            <form method="post" action="/admin/products/price-table.php"
                  enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload_flex">
                <input type="hidden" name="id" value="<?= (int) $tableId ?>">

                <div class="form-row full">
                    <div class="form-group">
                        <label for="file_flex">Supplier file (.xlsx)</label>
                        <input id="file_flex" name="file" type="file"
                               accept=".xlsx,.xlsm,.xls,.csv,.ods"
                               required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Import &amp; replace</button>
                </div>
            </form>
        </section>

        <?php if ($matrixWidths && $matrixDrops): ?>
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title">
                        Current matrix (<?= count($cells) ?> cells)
                    </h2>
                </div>
                <div class="table-wrap">
                    <table class="matrix-table">
                        <thead>
                            <tr>
                                <th>Drop \ Width (mm)</th>
                                <?php foreach ($matrixWidths as $w): ?>
                                    <th><?= (int) $w ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matrixDrops as $d): ?>
                                <tr>
                                    <th><?= (int) $d ?></th>
                                    <?php foreach ($matrixWidths as $w): ?>
                                        <?php $price = $matrixByPair["$w|$d"] ?? null; ?>
                                        <?php if ($price === null): ?>
                                            <td class="empty">—</td>
                                        <?php else: ?>
                                            <td><?= number_format((float) $price, 2) ?></td>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php else: ?>
            <section class="section">
                <div class="placeholder">
                    <p class="placeholder-title">Empty price table</p>
                    <p class="placeholder-body">
                        Download the template, paste your prices into the matrix, and upload to populate this table.
                    </p>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>
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
