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
// Rename / edit the table's metadata. Band code, name, notes. Used when
// a tenant realises the band they typed at create time (e.g. "FW35ML
// String") wasn't quite right and needs tweaking — without this, they'd
// have to delete the whole table and rebuild.
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_meta') {
    csrf_check();

    $newBand  = trim((string) ($_POST['band_code'] ?? ''));
    $newName  = trim((string) ($_POST['name']      ?? ''));
    $newNotes = trim((string) ($_POST['notes']     ?? ''));
    // Strip a leading "Band " — users sometimes type "Band A" out
    // of habit; the prefix is added back at render time.
    $newBand  = (string) preg_replace('/^band\s+/i', '', $newBand);

    if ($newBand === '') {
        $_SESSION['flash_error'] = 'Band code is required.';
    } elseif (strlen($newBand) > 20) {
        $_SESSION['flash_error'] = 'Band code too long (max 20 chars).';
    } elseif (strlen($newName) > 150) {
        $_SESSION['flash_error'] = 'Name too long (max 150 chars).';
    } elseif (strlen($newNotes) > 255) {
        $_SESSION['flash_error'] = 'Notes too long (max 255 chars).';
    } else {
        try {
            db()->prepare(
                'UPDATE price_tables
                    SET band_code = ?, name = ?, notes = ?, updated_at = NOW()
                  WHERE id = ? AND client_id = ?'
            )->execute([
                $newBand,
                $newName  !== '' ? $newName  : null,
                $newNotes !== '' ? $newNotes : null,
                $tableId,
                $clientId,
            ]);
            $_SESSION['flash_success'] = 'Saved.';
        } catch (Throwable $e) {
            // UNIQUE(product_id, system_id, band_code) — the only
            // realistic constraint trip. Surface it clearly so the
            // user knows the band code collides with a sibling table
            // on this system.
            if (str_contains($e->getMessage(), 'uniq_price_table_product_system_band')) {
                $_SESSION['flash_error'] = 'A price table for that band already exists on this system. Pick a different band code.';
            } else {
                $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage();
            }
        }
    }

    // PRG so the user sees the result on a clean reload.
    $back = '/admin/products/price-table.php?id=' . (int) $tableId;
    if (($_GET['from'] ?? '') === 'wizard') {
        $back .= '&from=wizard&product_id=' . (int) ($_GET['product_id'] ?? 0);
    }
    header('Location: ' . $back, true, 303);
    exit;
}

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
        // POST-Redirect-GET. A bare re-render leaves the browser
        // scrolled to where the user clicked "Save" — at the bottom
        // — so they never see the success banner at the top. A
        // proper 303 redirect scrolls to the top of the fresh page
        // and the flash message lands somewhere they can see it.
        $_SESSION['flash_success'] = 'Saved ' . count($bulk) . ' price cell'
            . (count($bulk) === 1 ? '' : 's') . '.'
            . ($rowErrs ? ' ' . count($rowErrs) . ' had problems (see below).' : '');
        if ($rowErrs) {
            $_SESSION['flash_errors_detail'] = array_slice($rowErrs, 0, 25);
        }
        $rt = $_SERVER['REQUEST_URI'] ?? ('/admin/products/price-table.php?id=' . $tableId);
        // Strip any &saved=… we may have appended previously, then
        // add it so the page knows to show the "next table" prompt.
        $rt = preg_replace('/[&?]saved=[01]/', '', (string) $rt);
        $sep = strpos($rt, '?') === false ? '?' : '&';
        header('Location: ' . $rt . $sep . 'saved=1', true, 303);
        exit;
    } catch (Throwable $e) {
        $pdo->rollBack();
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Read & clear flash messages set by the save handler above (after
// the 303 redirect). $summary is reconstituted from the flash so the
// existing render logic below still works.
$flashMsg = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
$flashErrsDetail = $_SESSION['flash_errors_detail'] ?? [];
unset($_SESSION['flash_errors_detail']);
$justSaved = !empty($_GET['saved']);

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
// If there's already a populated price table elsewhere on this same
// product, we use ITS widths and drops as the default — most tenants
// use the same dimensions across every band of a product, so this
// removes the need to re-enter them. Falls back to the UK standard
// grid if no other populated table exists.
$DEFAULT_WIDTHS = [800, 1200, 1600, 2000, 2400, 2800, 3200, 3600, 4000];
$DEFAULT_DROPS  = [800, 1200, 1600, 2000, 2400, 2800, 3200, 3600, 4000];
$shapeSource    = null;  // info about which sibling table we copied from
if ($isEmpty) {
    $sibStmt = db()->prepare(
        'SELECT t.id, t.band_code, s.name AS system_name
           FROM price_tables t
           JOIN product_systems s ON s.id = t.system_id
          WHERE t.product_id = ? AND t.client_id = ? AND t.id != ?
            AND EXISTS (SELECT 1 FROM price_table_rows r WHERE r.price_table_id = t.id)
       ORDER BY t.id LIMIT 1'
    );
    $sibStmt->execute([$table['product_id'], $clientId, $tableId]);
    $sibling = $sibStmt->fetch();
    if ($sibling) {
        $wSt = db()->prepare(
            'SELECT DISTINCT width_mm FROM price_table_rows WHERE price_table_id = ? ORDER BY width_mm'
        );
        $wSt->execute([$sibling['id']]);
        $sibWidths = array_map('intval', $wSt->fetchAll(PDO::FETCH_COLUMN));

        $dSt = db()->prepare(
            'SELECT DISTINCT drop_mm FROM price_table_rows WHERE price_table_id = ? ORDER BY drop_mm'
        );
        $dSt->execute([$sibling['id']]);
        $sibDrops = array_map('intval', $dSt->fetchAll(PDO::FETCH_COLUMN));

        if ($sibWidths && $sibDrops) {
            $DEFAULT_WIDTHS = $sibWidths;
            $DEFAULT_DROPS  = $sibDrops;
            $shapeSource    = [
                'system_name' => (string) $sibling['system_name'],
                'band_code'   => (string) $sibling['band_code'],
            ];
        }
    }
}

// "Next price table to fill" — the empty (or smallest-cell) sibling
// of the current table. Shown after a save so the user has a clear
// next step. NULL when there's nothing else to do on this product.
$nextTableHint = null;
$nextStmt = db()->prepare(
    "SELECT t.id, t.band_code, s.name AS system_name,
            (SELECT COUNT(*) FROM price_table_rows r WHERE r.price_table_id = t.id) AS cells
       FROM price_tables t
       JOIN product_systems s ON s.id = t.system_id
      WHERE t.product_id = ? AND t.client_id = ? AND t.id != ?
        AND t.active = 1
   ORDER BY cells ASC, t.id ASC
      LIMIT 1"
);
$nextStmt->execute([$table['product_id'], $clientId, $tableId]);
$nextRow = $nextStmt->fetch();
// Only suggest it if the candidate is genuinely emptier than this
// table — otherwise "next" would point sideways.
if ($nextRow && (int) $nextRow['cells'] === 0) {
    $nextTableHint = $nextRow;
}

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

        /* "Add widths / drops" dialog — shown when the user clicks
           "+ Width" or "+ Drop". A textarea lets them paste a range
           from Excel (tabs/newlines/commas all accepted) so they
           can build the grid axes from a supplier sheet in one go. */
        dialog.axis-dialog {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
            background: var(--bg-card);
            color: var(--text-body);
            box-shadow: 0 8px 24px rgba(0,0,0,0.18);
            width: min(28rem, 90vw);
        }
        dialog.axis-dialog::backdrop {
            background: rgba(0,0,0,0.4);
        }
        .axis-dialog h3 {
            margin: 0 0 0.375rem;
            font-size: 1.0625rem;
            color: var(--text-primary);
        }
        .axis-dialog p {
            margin: 0 0 0.625rem;
            font-size: 0.8125rem;
            color: var(--text-muted);
            line-height: 1.5;
        }
        .axis-dialog textarea {
            width: 100%;
            border: 1px solid var(--border-strong);
            border-radius: 6px;
            padding: 0.5rem 0.625rem;
            font: inherit;
            font-family: ui-monospace, Menlo, Consolas, monospace;
            background: var(--bg-input);
            color: var(--text-body);
            resize: vertical;
            min-height: 6rem;
        }
        .axis-dialog .axis-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 0.875rem;
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
            <div style="flex:1">
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
                    &middot;
                    <a href="#edit-meta" id="edit-meta-link"
                       style="color:var(--link);font-weight:600">
                        Edit band / name / notes
                    </a>
                </p>
            </div>
        </div>

        <!-- Inline edit form for the table's metadata. Hidden by
             default; the "Edit band / name / notes" link above
             toggles it. Saves via PRG to the same page. -->
        <details id="edit-meta-details"
                 style="margin:0 0 1.25rem;background:var(--bg-card);border:1px solid var(--border);border-radius:10px;box-shadow:var(--shadow-sm)">
            <summary style="list-style:none;cursor:pointer;padding:0.75rem 1.125rem;font-weight:600;color:var(--text-primary);display:flex;align-items:center;gap:0.5rem">
                <span style="color:var(--text-faint);font-size:0.8125rem">▸</span>
                Table details
                <span style="color:var(--text-faint);font-weight:400;font-size:0.8125rem;margin-left:0.5rem">
                    Band code, name, notes
                </span>
            </summary>
            <form method="post" action="/admin/products/price-table.php<?= $fromWizard ? '?from=wizard&product_id=' . $wizardBackId : '' ?>"
                  style="border-top:1px solid var(--border);padding:1rem 1.125rem">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_meta">
                <input type="hidden" name="id" value="<?= (int) $tableId ?>">

                <div style="display:grid;grid-template-columns:8rem 1fr 1fr;gap:0.75rem;align-items:end">
                    <div>
                        <label for="meta-band" style="display:block;font-size:0.75rem;font-weight:600;color:var(--text-faint);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.25rem">
                            Band *
                        </label>
                        <input id="meta-band" name="band_code" type="text"
                               required maxlength="20"
                               value="<?= e((string) $table['band_code']) ?>"
                               style="width:100%;padding:0.5rem 0.625rem;border:1px solid var(--border-strong);border-radius:6px;background:var(--bg-input);color:var(--text-body);font:inherit">
                    </div>
                    <div>
                        <label for="meta-name" style="display:block;font-size:0.75rem;font-weight:600;color:var(--text-faint);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.25rem">
                            Name (optional)
                        </label>
                        <input id="meta-name" name="name" type="text" maxlength="150"
                               value="<?= e((string) ($table['name'] ?? '')) ?>"
                               placeholder="e.g. 2026 Slim Line Band A"
                               style="width:100%;padding:0.5rem 0.625rem;border:1px solid var(--border-strong);border-radius:6px;background:var(--bg-input);color:var(--text-body);font:inherit">
                    </div>
                    <div>
                        <label for="meta-notes" style="display:block;font-size:0.75rem;font-weight:600;color:var(--text-faint);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.25rem">
                            Notes (optional)
                        </label>
                        <input id="meta-notes" name="notes" type="text" maxlength="255"
                               value="<?= e((string) ($table['notes'] ?? '')) ?>"
                               placeholder="Anything to remember about this sheet"
                               style="width:100%;padding:0.5rem 0.625rem;border:1px solid var(--border-strong);border-radius:6px;background:var(--bg-input);color:var(--text-body);font:inherit">
                    </div>
                </div>
                <div style="margin-top:0.75rem">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <span style="color:var(--text-faint);font-size:0.8125rem;margin-left:0.625rem">
                        Changing the band code keeps all existing prices intact.
                    </span>
                </div>
            </form>
        </details>
        <style>
            #edit-meta-details > summary::-webkit-details-marker { display: none; }
            #edit-meta-details[open] > summary > span:first-child {
                display: inline-block; transform: rotate(90deg);
            }
        </style>
        <script>
            // Clicking "Edit band / name / notes" in the subtitle opens
            // the details panel and scrolls it into view.
            (function () {
                var link = document.getElementById('edit-meta-link');
                var d    = document.getElementById('edit-meta-details');
                if (link && d) {
                    link.addEventListener('click', function (e) {
                        e.preventDefault();
                        d.open = true;
                        d.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        var first = document.getElementById('meta-band');
                        if (first) setTimeout(function () { first.focus(); first.select(); }, 200);
                    });
                }
            })();
        </script>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($justSaved || $flashMsg !== null): ?>
            <div class="alert alert-success" role="status"
                 style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap">
                <span style="flex:1">
                    &check; <?= e((string) ($flashMsg ?? 'Saved.')) ?>
                </span>
                <?php if ($nextTableHint): ?>
                    <a href="/admin/products/price-table.php?id=<?= (int) $nextTableHint['id'] ?><?= $fromWizard ? '&from=wizard&product_id=' . $wizardBackId : '' ?>"
                       class="btn btn-primary btn-sm">
                        Next: <?= e((string) $nextTableHint['system_name']) ?>
                        — Band <?= e((string) $nextTableHint['band_code']) ?> &rarr;
                    </a>
                <?php elseif ($wizardBackId > 0): ?>
                    <a href="/admin/products/wizard.php?id=<?= $wizardBackId ?>&step=4"
                       class="btn btn-primary btn-sm">
                        &larr; Back to setup wizard
                    </a>
                <?php endif; ?>
            </div>
            <?php if (!empty($flashErrsDetail)): ?>
                <div class="alert alert-error" role="alert">
                    <strong>Some cells had problems:</strong>
                    <ul class="summary-list">
                        <?php foreach ($flashErrsDetail as $err): ?>
                            <li><?= e((string) $err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($summary !== null): /* upload flows still re-render with $summary */ ?>
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
                        <?php if ($shapeSource !== null): ?>
                            Start with the same widths and drops as
                            <strong>
                                <?= e($shapeSource['system_name']) ?>
                                — Band <?= e($shapeSource['band_code']) ?>
                            </strong>
                            (already filled in on this product), then paste
                            your prices straight into the cells.
                        <?php else: ?>
                            Start with a standard UK grid (widths 800&ndash;4000mm,
                            drops 800&ndash;4000mm in 400mm steps), then type
                            prices straight into the cells. Or build the grid
                            column by column.
                        <?php endif; ?>
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

        <!-- Add-axis dialog (widths or drops). Native <dialog> so we
             get proper modal behaviour, Esc-to-close, and focus
             trapping for free. -->
        <dialog id="axis-dialog" class="axis-dialog">
            <form method="dialog" id="axis-form">
                <h3 id="axis-title">Add widths</h3>
                <p id="axis-help">
                    Paste from Excel (a row OR a column), or type values
                    separated by commas, spaces, tabs or newlines.
                    Duplicates and non-numeric values are ignored.
                </p>
                <textarea id="axis-input" rows="6" autofocus
                          placeholder="800&#10;1200&#10;1600&#10;2000&#10;…"></textarea>
                <div class="axis-actions">
                    <button type="button" id="axis-cancel" class="btn btn-secondary">Cancel</button>
                    <button type="button" id="axis-confirm" class="btn btn-primary">Add</button>
                </div>
            </form>
        </dialog>

        <!-- "Quick start" dialog — collects widths AND drops in one
             go so the user can paste both straight from Excel before
             we build the grid. Defaults are pre-filled as suggestions
             that the user can replace wholesale. -->
        <dialog id="qs-dialog" class="axis-dialog" style="width:min(34rem,92vw)">
            <form method="dialog">
                <h3>Start your grid</h3>
                <p>
                    Type or paste the widths and drops your supplier sells in.
                    Each line / cell is one value. Defaults are filled in for
                    you — clear them if you want to start from scratch.
                </p>
                <div style="display:flex;justify-content:space-between;align-items:baseline;margin:0.625rem 0 0.25rem">
                    <label for="qs-widths" style="font-weight:600;font-size:0.8125rem">
                        Widths (mm)
                    </label>
                    <button type="button" id="qs-clear-widths"
                            style="background:transparent;border:0;color:var(--link);cursor:pointer;font:inherit;font-size:0.75rem;text-decoration:underline">
                        Clear
                    </button>
                </div>
                <textarea id="qs-widths" rows="3"
                          style="width:100%;border:1px solid var(--border-strong);border-radius:6px;padding:0.5rem 0.625rem;font:inherit;font-family:ui-monospace,Menlo,Consolas,monospace;background:var(--bg-input);color:var(--text-body);resize:vertical"></textarea>
                <div style="display:flex;justify-content:space-between;align-items:baseline;margin:0.625rem 0 0.25rem">
                    <label for="qs-drops" style="font-weight:600;font-size:0.8125rem">
                        Drops (mm)
                    </label>
                    <button type="button" id="qs-clear-drops"
                            style="background:transparent;border:0;color:var(--link);cursor:pointer;font:inherit;font-size:0.75rem;text-decoration:underline">
                        Clear
                    </button>
                </div>
                <textarea id="qs-drops" rows="6"
                          style="width:100%;border:1px solid var(--border-strong);border-radius:6px;padding:0.5rem 0.625rem;font:inherit;font-family:ui-monospace,Menlo,Consolas,monospace;background:var(--bg-input);color:var(--text-body);resize:vertical"></textarea>
                <div class="axis-actions">
                    <button type="button" id="qs-cancel" class="btn btn-secondary">Cancel</button>
                    <button type="button" id="qs-confirm" class="btn btn-primary">Build grid</button>
                </div>
            </form>
        </dialog>

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
    //
    // Quick start opens a dialog with two textareas — widths and
    // drops — pre-filled with sensible UK defaults. The user can
    // wholesale replace either by pasting from Excel (a single row
    // for widths, a column for drops). On confirm we parse both,
    // build the grid in one go, then reveal it.
    var qsDialog = document.getElementById('qs-dialog');
    var qsWidths = document.getElementById('qs-widths');
    var qsDrops  = document.getElementById('qs-drops');
    var qsCancel = document.getElementById('qs-cancel');
    var qsConfirm = document.getElementById('qs-confirm');

    if (qsFill && qsData && qsDialog) {
        var defaults = JSON.parse(qsData.textContent);
        qsFill.addEventListener('click', function () {
            qsWidths.value = defaults.widths.join('\n');
            qsDrops.value  = defaults.drops.join('\n');
            if (typeof qsDialog.showModal === 'function') {
                qsDialog.showModal();
            } else {
                qsDialog.setAttribute('open', '');
            }
            setTimeout(function () { qsWidths.focus(); qsWidths.select(); }, 0);
        });
    }
    if (qsCancel) {
        qsCancel.addEventListener('click', function () {
            if (qsDialog.close) qsDialog.close();
            else qsDialog.removeAttribute('open');
        });
    }
    var qsClearW = document.getElementById('qs-clear-widths');
    var qsClearD = document.getElementById('qs-clear-drops');
    if (qsClearW) qsClearW.addEventListener('click', function () {
        qsWidths.value = ''; qsWidths.focus();
    });
    if (qsClearD) qsClearD.addEventListener('click', function () {
        qsDrops.value = ''; qsDrops.focus();
    });
    if (qsConfirm) {
        qsConfirm.addEventListener('click', function () {
            var ws = parseAxisValues(qsWidths.value);
            var ds = parseAxisValues(qsDrops.value);
            if (!ws.length || !ds.length) {
                alert('Add at least one width and one drop.');
                return;
            }
            ws.forEach(function (w) { addWidth(w); });
            ds.forEach(function (d) { addDrop(d);  });
            if (qsDialog.close) qsDialog.close();
            else qsDialog.removeAttribute('open');
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

    // Add a new width column. Returns true if added, false if it
    // already existed. Called from the bulk dialog (so duplicates
    // don't show alerts; the dialog summarises at the end).
    function addWidth(w) {
        if (!w || w <= 0) return false;
        if (currentWidths().indexOf(w) !== -1) return false;

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
            var td = document.createElement('td');
            td.appendChild(makeCellInput(w, parseInt(row.getAttribute('data-d'), 10)));
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
        return true;
    }

    function addDrop(d) {
        if (!d || d <= 0) return false;
        if (currentDrops().indexOf(d) !== -1) return false;

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
        return true;
    }

    // ----- "Add widths / drops" dialog ----- //
    //
    // Parses a textarea full of separators-of-any-kind into a unique
    // sorted array of positive integers. Then adds them via
    // addWidth / addDrop. Used by both "+ Width" and "+ Drop"
    // buttons; the only difference is which adder it calls.
    function parseAxisValues(raw) {
        // Split on whitespace, tabs, newlines, commas, semicolons.
        var parts = String(raw || '').split(/[\s,;]+/).filter(Boolean);
        var seen  = {};
        var out   = [];
        parts.forEach(function (p) {
            // Tolerate stray "mm" suffixes from supplier sheets.
            var clean = p.replace(/mm$/i, '').trim();
            var n = parseInt(clean, 10);
            if (!n || n <= 0) return;
            if (seen[n]) return;
            seen[n] = true;
            out.push(n);
        });
        return out;
    }

    var dialog       = document.getElementById('axis-dialog');
    var dialogTitle  = document.getElementById('axis-title');
    var dialogHelp   = document.getElementById('axis-help');
    var dialogInput  = document.getElementById('axis-input');
    var dialogConfirm = document.getElementById('axis-confirm');
    var dialogCancel = document.getElementById('axis-cancel');
    var currentKind  = null;   // 'width' or 'drop'

    function openAxisDialog(kind) {
        currentKind = kind;
        var label = kind === 'width' ? 'widths' : 'drops';
        var ws    = currentWidths();
        var ds    = currentDrops();
        var existing = kind === 'width' ? ws : ds;
        var suggested = existing.length
            ? Math.max.apply(null, existing) + 400
            : 800;
        dialogTitle.textContent = 'Add ' + label + ' (mm)';
        dialogHelp.innerHTML = 'Paste from Excel (a row OR a column), or type values'
            + ' separated by commas, spaces, tabs or newlines.'
            + ' Duplicates and non-numeric values are ignored.';
        dialogInput.value = '';
        dialogInput.placeholder = String(suggested);
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
        }
        setTimeout(function () { dialogInput.focus(); }, 0);
    }

    function closeDialog() {
        if (dialog.close) dialog.close(); else dialog.removeAttribute('open');
    }

    if (dialogConfirm) {
        dialogConfirm.addEventListener('click', function () {
            var values = parseAxisValues(dialogInput.value);
            if (!values.length) { closeDialog(); return; }
            var added = 0;
            var dups  = 0;
            values.forEach(function (v) {
                if (currentKind === 'width' ? addWidth(v) : addDrop(v)) added++;
                else dups++;
            });
            closeDialog();
            if (added === 0 && dups > 0) {
                alert('All ' + dups + ' values already existed in the grid.');
            }
        });
    }
    if (dialogCancel) {
        dialogCancel.addEventListener('click', function () { closeDialog(); });
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
    if (addW) addW.addEventListener('click', function () { openAxisDialog('width'); });
    if (addD) addD.addEventListener('click', function () { openAxisDialog('drop');  });

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
