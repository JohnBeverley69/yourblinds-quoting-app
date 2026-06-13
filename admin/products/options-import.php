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
    'SELECT id, name, option_label FROM products WHERE id = ? AND client_id = ?'
);
$pStmt->execute([$productId, $clientId]);
$product = $pStmt->fetch();

if (!$product) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Product not found</h1>';
    exit;
}

// Per-product option label (set on the product Edit page).
// Falls back to "Fabric" for legacy products.
$label  = (string) ($product['option_label'] ?? 'Fabric');
if ($label === '') $label = 'Fabric';
$labelL = strtolower($label);

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

// ---------------------------------------------------------------------------
// Template download — generate an empty XLSX with the right headers and
// stream it back as an attachment. Header label adapts to the product's
// option_label so it reads naturally.
// ---------------------------------------------------------------------------
if ($action === 'template' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/../../vendor/autoload.php';

    $ss    = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $ss->getActiveSheet();
    $sheet->setTitle($label . 's');
    $sheet->fromArray([
        ['Band*', $label . ' name*', 'Colour', 'Supplier', 'Code'],
    ], null, 'A1');
    $sheet->getStyle('A1:E1')->getFont()->setBold(true);
    $sheet->getStyle('A1:E1')->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setRGB('1F3B5B');
    $sheet->getStyle('A1:E1')->getFont()->getColor()->setRGB('FFFFFF');
    $sheet->getColumnDimension('A')->setWidth(10);
    $sheet->getColumnDimension('B')->setWidth(30);
    $sheet->getColumnDimension('C')->setWidth(20);
    $sheet->getColumnDimension('D')->setWidth(20);
    $sheet->getColumnDimension('E')->setWidth(15);
    $sheet->freezePane('A2');

    // A short instruction stub on row 2 (italic grey, only visible while empty).
    $hint = '* = required. Bands like A, B, C, AA, AAA — case is normalised. '
          . 'Duplicate (band + name + colour) rows are skipped on import.';
    $sheet->setCellValue('A3', $hint);
    $sheet->mergeCells('A3:E3');
    $sheet->getStyle('A3')->getFont()->setItalic(true)->getColor()->setRGB('6B7280');
    $sheet->getStyle('A3')->getAlignment()->setWrapText(true);
    $sheet->getRowDimension(3)->setRowHeight(36);

    $filename = preg_replace('/[^A-Za-z0-9_\- ]/', '', (string) $product['name'])
              . ' - ' . $label . ' template.xlsx';

    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
    $writer->save('php://output');
    exit;
}

// ---------------------------------------------------------------------------
// File upload — parse + insert.
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
            $ss      = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['file']['tmp_name']);
            $sheet   = $ss->getActiveSheet();
            $rows    = $sheet->toArray(null, true, true, true);

            // Detect header row → column letter map.
            $headerRow = $rows[1] ?? [];
            $colMap = [];
            foreach ($headerRow as $col => $raw) {
                $h = strtolower(trim(rtrim((string) $raw, '*')));
                if ($h === '') continue;
                if (in_array($h, ['band','band code','bandcode'], true))         $colMap['band']     = $col;
                elseif (in_array($h, ['supplier','supplier name'], true))        $colMap['supplier'] = $col;
                elseif (in_array($h, ['colour','color'], true))                  $colMap['colour']   = $col;
                elseif ($h === 'code')                                            $colMap['code']     = $col;
                elseif (str_contains($h, 'name') || $h === 'fabric'
                     || $h === 'slat' || $h === 'slat type')                      $colMap['name']     = $col;
            }

            // Headerless fallback — many supplier files (e.g. Decora) ship the
            // raw data in row 1 with no header row at all. If we couldn't pin
            // down both Band and Name from row 1, assume positional columns:
            //   A = band, B = name, C = colour, D = supplier, E = code
            // and process every row (including row 1) as data.
            $hasHeaders = isset($colMap['band']) && isset($colMap['name']);
            if (!$hasHeaders) {
                $colMap = [
                    'band'     => 'A',
                    'name'     => 'B',
                    'colour'   => 'C',
                    'supplier' => 'D',
                    'code'     => 'E',
                ];
            }

            // Either path — header-driven or positional — we now have a usable
            // column map and proceed with the insert loop.
            $insert = db()->prepare(
                'INSERT INTO product_options
                   (client_id, product_id, band_code, supplier_name,
                    name, colour, code, sort_order, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1)'
            );

            $inserted = 0;
            $skipped  = 0;
            $blank    = 0;
            $rowErrs  = [];
            // Header-ish strings that should never end up as a data row, even
            // if they appear past row 1 (e.g. when the user pasted multiple
            // sub-header rows along with their data).
            $headerLikeBand = ['BAND', 'BAND CODE', 'BANDCODE'];
            $headerLikeName = ['FABRIC', 'FABRIC NAME', 'NAME', 'SLAT', 'SLAT TYPE'];

            foreach ($rows as $rowNum => $row) {
                // Header-driven mode skips row 1 (it's the header). Positional
                // (headerless) mode treats every row as data — including row 1.
                if ($hasHeaders && $rowNum === 1) continue;

                $band = trim((string) ($row[$colMap['band']] ?? ''));
                $name = trim((string) ($row[$colMap['name']] ?? ''));
                if ($band === '' && $name === '') { $blank++; continue; }
                if ($band === '' || $name === '') {
                    $rowErrs[] = "Row $rowNum: missing " . ($band === '' ? 'band' : 'name');
                    continue;
                }
                // Strip a leading "Band " / "BAND " prefix if the user
                // typed/pasted it. We want just "AAA", not "BAND AAA",
                // so the sort + display logic stays clean.
                $band = preg_replace('/^band\s+/i', '', $band);
                if ($band === '') {
                    $rowErrs[] = "Row $rowNum: band code was just 'Band' with nothing after it";
                    continue;
                }
                // Sub-header detection: skip rows where the band or name looks
                // like a column title (common when pasting from multi-section
                // supplier sheets that have repeated 'Fabric / Band' rows).
                if (in_array(strtoupper($band), $headerLikeBand, true)
                 || in_array(strtoupper($name), $headerLikeName, true)) {
                    $blank++;
                    continue;
                }
                $supplier = isset($colMap['supplier']) ? trim((string) ($row[$colMap['supplier']] ?? '')) : '';
                $colour   = isset($colMap['colour'])   ? trim((string) ($row[$colMap['colour']]   ?? '')) : '';
                $code     = isset($colMap['code'])     ? trim((string) ($row[$colMap['code']]     ?? '')) : '';

                try {
                    $insert->execute([
                        $clientId,
                        $productId,
                        strtoupper($band),
                        $supplier !== '' ? $supplier : null,
                        $name,
                        $colour !== '' ? $colour : null,
                        $code   !== '' ? $code   : null,
                    ]);
                    $inserted++;
                } catch (PDOException $e) {
                    if (str_contains($e->getMessage(), 'uniq_option_per_product')) {
                        $skipped++;
                    } else {
                        $rowErrs[] = "Row $rowNum: " . $e->getMessage();
                    }
                }
            }

            $summary = [
                'inserted' => $inserted,
                'skipped'  => $skipped,
                'blank'    => $blank,
                'errors'   => $rowErrs,
                'mode'     => $hasHeaders ? 'header' : 'positional',
            ];
        } catch (Throwable $e) {
            $error = 'Could not read the file: ' . $e->getMessage();
        }
    }
}

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Import <?= e($labelL) ?>s &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .tip-box {
            background: var(--bg-subtle);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.9375rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        .tip-box code {
            background: var(--bg-card);
            padding: 0.0625rem 0.375rem;
            border-radius: 4px;
            border: 1px solid var(--border);
            font-size: 0.8125rem;
        }
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
                    Import <?= e($labelL) ?>s &mdash; <?= e((string) $product['name']) ?>
                </h1>
                <p class="page-subtitle">
                    <a href="/admin/products/options.php?product_id=<?= (int) $productId ?>">
                        &larr; Back to <?= e($label) ?>s
                    </a>
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($summary !== null): ?>
            <div class="alert alert-success" role="status">
                Imported <strong><?= (int) $summary['inserted'] ?></strong> <?= e($labelL) ?>s
                (<?= $summary['mode'] === 'header'
                    ? 'header row detected'
                    : 'no headers — used positional A=Band B=Name C=Colour D=Supplier E=Code' ?>).
                <?php if ($summary['skipped'] > 0): ?>
                    Skipped <?= (int) $summary['skipped'] ?> duplicate<?= $summary['skipped'] === 1 ? '' : 's' ?>.
                <?php endif; ?>
                <?php if ($summary['blank'] > 0): ?>
                    Ignored <?= (int) $summary['blank'] ?> blank row<?= $summary['blank'] === 1 ? '' : 's' ?>.
                <?php endif; ?>
            </div>
            <?php if ($summary['errors']): ?>
                <div class="alert alert-error" role="alert">
                    <strong>Some rows had problems:</strong>
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
            <p>
                <a href="/admin/products/options.php?product_id=<?= (int) $productId ?>" class="btn btn-primary">
                    View imported <?= e($labelL) ?>s
                </a>
            </p>
        <?php endif; ?>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">1. Download the template</h2>
            </div>
            <div class="tip-box">
                Columns: <code>Band*</code>, <code><?= e($label) ?> name*</code>,
                <code>Colour</code>, <code>Supplier</code>, <code>Code</code>. Asterisks = required.
                Each row becomes one <?= e($labelL) ?>.
            </div>
            <p>
                <a class="btn btn-primary"
                   href="/admin/products/options-import.php?product_id=<?= (int) $productId ?>&action=template">
                    Download blank template (.xlsx)
                </a>
            </p>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">2. Fill it in</h2>
            </div>
            <div class="tip-box">
                Open the file in Excel, paste your data into the columns, save.
                You can leave Supplier / Colour / Code blank if you don't have them.
                Bands like <code>A</code>, <code>B</code>, <code>AA</code>, <code>AAA</code> are
                normalised to uppercase on import. A leading <code>Band&nbsp;</code> prefix
                on the code (e.g. <code>Band AA</code>) is stripped automatically.
                <br><br>
                <strong>Headerless files also work:</strong> if row 1 has no recognisable
                header (just data), the importer falls back to positional columns —
                A&nbsp;=&nbsp;Band, B&nbsp;=&nbsp;Name, C&nbsp;=&nbsp;Colour,
                D&nbsp;=&nbsp;Supplier, E&nbsp;=&nbsp;Code.
            </div>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">3. Upload</h2>
            </div>
            <form method="post" action="/admin/products/options-import.php"
                  enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="product_id" value="<?= (int) $productId ?>">

                <div class="form-row full">
                    <div class="form-group">
                        <label for="file">Filled template (.xlsx)</label>
                        <input id="file" name="file" type="file"
                               accept=".xlsx,.xlsm,.xls,.csv,.ods"
                               required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Upload &amp; import</button>
                    <a href="/admin/products/options.php?product_id=<?= (int) $productId ?>"
                       class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
