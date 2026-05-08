<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$productId = (int) ($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
if ($productId <= 0) {
    header('Location: /admin/products/index.php');
    exit;
}

$pStmt = db()->prepare(
    'SELECT id, name FROM products WHERE id = ? AND client_id = ?'
);
$pStmt->execute([$productId, $clientId]);
$product = $pStmt->fetch();

if (!$product) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Product not found</h1>';
    exit;
}

$summary = null;
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Please choose a file to upload.';
    } elseif (filesize($_FILES['file']['tmp_name']) > 10 * 1024 * 1024) {
        $error = 'File too large (10 MB max).';
    } else {
        require __DIR__ . '/../../vendor/autoload.php';
        try {
            $ss     = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['file']['tmp_name']);
            $bands  = [];

            // We scan every sheet — some suppliers ship bands per-tab too.
            foreach ($ss->getAllSheets() as $sheet) {
                $rows = $sheet->toArray(null, true, true, true);
                $bands = array_merge($bands, parse_band_blocks($rows));
            }

            if (!$bands) {
                $error = 'No band sections detected. Each band block should start with a row containing "Band X" in column A.';
            } else {
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $insertedBands = [];
                    foreach ($bands as $band) {
                        $code = strtoupper($band['code']);
                        // Find or create the price_table for this product+band.
                        $find = $pdo->prepare(
                            'SELECT id FROM price_tables
                              WHERE client_id = ? AND product_id = ? AND band_code = ?'
                        );
                        $find->execute([$clientId, $productId, $code]);
                        $tableId = (int) ($find->fetchColumn() ?: 0);
                        if ($tableId === 0) {
                            $ins = $pdo->prepare(
                                'INSERT INTO price_tables
                                   (client_id, product_id, band_code, name, active)
                                 VALUES (?, ?, ?, ?, 1)'
                            );
                            $ins->execute([
                                $clientId,
                                $productId,
                                $code,
                                'Imported ' . date('Y-m-d'),
                            ]);
                            $tableId = (int) $pdo->lastInsertId();
                        }
                        // Wipe + insert this band's cells.
                        $del = $pdo->prepare('DELETE FROM price_table_rows WHERE price_table_id = ?');
                        $del->execute([$tableId]);
                        $cellIns = $pdo->prepare(
                            'INSERT INTO price_table_rows
                               (price_table_id, width_mm, drop_mm, price)
                             VALUES (?, ?, ?, ?)'
                        );
                        foreach ($band['cells'] as [$w, $d, $p]) {
                            $cellIns->execute([$tableId, $w, $d, $p]);
                        }
                        $insertedBands[] = [
                            'code'  => $code,
                            'cells' => count($band['cells']),
                        ];
                    }
                    $pdo->commit();
                    $summary = ['bands' => $insertedBands];
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        } catch (Throwable $e) {
            $error = 'Could not read the file: ' . $e->getMessage();
        }
    }
}

/**
 * Walk a sheet's rows looking for stacked "Band X" matrices.
 * Returns: [['code' => 'AAA', 'cells' => [[width_mm, drop_mm, price], ...]], ...]
 *
 * Format expected (loose — handles the typos / variants we've seen):
 *   Row:  A="Band X" (or "Bnad X" — tolerant of B-something + uppercase suffix)
 *   Row:  A="Metric" (or anything), C..N = widths in metres
 *   Row:  B="Ins"     (optional — inches reference, ignored)
 *   Rows: A = drop in metres, C..N = prices, until a blank A or another band header
 */
function parse_band_blocks(array $rows): array
{
    $bands     = [];
    $current   = null;
    $widths    = [];
    $cells     = [];
    $expecting = 'band';   // band | widths | maybeInches | data

    $finalise = function () use (&$bands, &$current, &$cells) {
        if ($current !== null && $cells) {
            $bands[] = ['code' => $current, 'cells' => $cells];
        }
        $current = null;
        $cells   = [];
    };

    foreach ($rows as $rowNum => $row) {
        $a = trim((string) ($row['A'] ?? ''));
        $b = trim((string) ($row['B'] ?? ''));

        // Tolerant band-header detection: "Band X", "Bnad X", "BAND XYZ" all match.
        // Captures the trailing uppercase letters as the band code.
        if (preg_match('/^B\w+\s+([A-Z]+)\s*$/i', $a, $m)) {
            $finalise();
            $current   = strtoupper($m[1]);
            $widths    = [];
            $expecting = 'widths';
            continue;
        }

        if ($current === null) {
            continue; // outside any band section
        }

        if ($expecting === 'widths') {
            // Capture all numeric values from column C onwards as widths (in metres).
            foreach ($row as $col => $val) {
                if ($col === 'A' || $col === 'B') continue;
                if (is_numeric($val) && (float) $val > 0) {
                    $widths[$col] = (int) round((float) $val * 1000); // m → mm
                }
            }
            if ($widths) {
                $expecting = 'maybeInches';
            }
            continue;
        }

        if ($expecting === 'maybeInches') {
            // Skip the inches-reference row if it's there. Detect by B="Ins" or
            // by the row containing things like 31.50''. If the row looks like
            // a real data row (A is numeric), treat as data instead.
            $aIsNumeric = is_numeric($a);
            if (!$aIsNumeric) {
                $expecting = 'data';
                continue;
            }
            // Fall through to data handling.
            $expecting = 'data';
        }

        if ($expecting === 'data') {
            if ($a === '' || !is_numeric($a)) {
                // End of this band's data block.
                $finalise();
                $widths    = [];
                $expecting = 'band';
                // Re-process this row in case it's the next band header.
                if (preg_match('/^B\w+\s+([A-Z]+)\s*$/i', $a, $m)) {
                    $current   = strtoupper($m[1]);
                    $widths    = [];
                    $expecting = 'widths';
                }
                continue;
            }
            $dropMm = (int) round((float) $a * 1000);
            foreach ($widths as $col => $widthMm) {
                $val = $row[$col] ?? null;
                if ($val === null || $val === '' || !is_numeric($val)) continue;
                $price = (float) $val;
                if ($price < 0) continue;
                $cells[] = [$widthMm, $dropMm, $price];
            }
        }
    }
    $finalise();
    return $bands;
}

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bulk import price tables &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .tip-box {
            background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;
            padding: 0.75rem 1rem; font-size: 0.9375rem; color: #4b5563;
            margin-bottom: 1rem;
        }
        .tip-box code {
            background: #fff; padding: 0.0625rem 0.375rem; border-radius: 4px;
            border: 1px solid #e5e7eb; font-size: 0.8125rem;
        }
        .summary-list { margin: 0; padding-left: 1.25rem; }
        .summary-list li { font-size: 0.9375rem; color: #4b5563; }
        .band-pill {
            display: inline-block; padding: 0.0625rem 0.5rem; font-weight: 700;
            font-size: 0.75rem; color: #fff; background: #1f3b5b; border-radius: 6px;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    Bulk import price tables &mdash; <?= e((string) $product['name']) ?>
                </h1>
                <p class="page-subtitle">
                    <a href="/admin/products/price-tables.php?product_id=<?= (int) $productId ?>">
                        &larr; All price tables
                    </a>
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($summary !== null): ?>
            <div class="alert alert-success" role="status">
                Imported <strong><?= count($summary['bands']) ?></strong>
                band<?= count($summary['bands']) === 1 ? '' : 's' ?>:
            </div>
            <div class="section">
                <ul class="summary-list">
                    <?php foreach ($summary['bands'] as $b): ?>
                        <li>
                            <span class="band-pill">Band <?= e((string) $b['code']) ?></span>
                            &mdash; <?= (int) $b['cells'] ?> cell<?= $b['cells'] === 1 ? '' : 's' ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="form-actions" style="margin-top:1rem">
                    <a href="/admin/products/price-tables.php?product_id=<?= (int) $productId ?>"
                       class="btn btn-primary">View price tables</a>
                </div>
            </div>
        <?php endif; ?>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">What this expects</h2>
            </div>
            <div class="tip-box">
                A multi-band Excel file with each band block looking like this:
                <ul style="margin:0.5rem 0 0;padding-left:1.25rem">
                    <li>One row with <code>Band X</code> (or <code>Band XYZ</code>) in column A — tolerant of typos like "Bnad"</li>
                    <li>Next row: widths in <strong>metres</strong> in columns C onwards (e.g. 0.800, 1.200, 1.600 …)</li>
                    <li>Optional inches reference row (skipped automatically)</li>
                    <li>Data rows: drop in metres in column A, prices in £ across the width columns</li>
                </ul>
                Multiple band blocks can be stacked vertically in one sheet, or spread across multiple sheets — both work.
                <strong>Re-importing replaces</strong> any existing rows for each band.
            </div>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Upload</h2>
            </div>
            <form method="post"
                  action="/admin/products/price-tables-bulk-import.php"
                  enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int) $productId ?>">

                <div class="form-row full">
                    <div class="form-group">
                        <label for="file">Multi-band file (.xlsx)</label>
                        <input id="file" name="file" type="file"
                               accept=".xlsx,.xlsm,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                               required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Upload &amp; import all bands</button>
                    <a href="/admin/products/price-tables.php?product_id=<?= (int) $productId ?>"
                       class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
