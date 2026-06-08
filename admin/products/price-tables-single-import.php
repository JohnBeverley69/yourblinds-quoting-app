<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$systemId = (int) ($_GET['system_id'] ?? $_POST['system_id'] ?? 0);
if ($systemId <= 0) {
    header('Location: /admin/products/index.php');
    exit;
}

$sysStmt = db()->prepare(
    'SELECT s.id, s.name AS system_name, s.product_id,
            p.name AS product_name
       FROM product_systems s
       JOIN products p ON p.id = s.product_id
      WHERE s.id = ? AND s.client_id = ?'
);
$sysStmt->execute([$systemId, $clientId]);
$system = $sysStmt->fetch();

if (!$system) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>System not found</h1>';
    exit;
}
$productId = (int) $system['product_id'];

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
        require __DIR__ . '/../../_partials/price_table_parser.php';
        try {
            $ss     = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['file']['tmp_name']);
            $bands  = [];
            foreach ($ss->getAllSheets() as $sheet) {
                $rows = $sheet->toArray(null, true, true, true);
                $bands = array_merge($bands, ptp_parse_band_blocks($rows));
            }

            if (!$bands) {
                $error = 'No band section detected. The file should contain a "Band X" header row.';
            } else {
                // Single-band semantics: take the first band only.
                $picked       = $bands[0];
                $extrasIgnored = max(0, count($bands) - 1);
                $code         = strtoupper($picked['code']);

                if (!$picked['cells']) {
                    $error = 'Band ' . $code . ' had no price cells.';
                } else {
                    $pdo = db();
                    $pdo->beginTransaction();
                    try {
                        $find = $pdo->prepare(
                            'SELECT id FROM price_tables
                              WHERE client_id = ? AND product_id = ? AND system_id = ? AND band_code = ?'
                        );
                        $find->execute([$clientId, $productId, $systemId, $code]);
                        $tableId = (int) ($find->fetchColumn() ?: 0);
                        $created = false;
                        if ($tableId === 0) {
                            $ins = $pdo->prepare(
                                'INSERT INTO price_tables
                                   (client_id, product_id, system_id, band_code, name, active)
                                 VALUES (?, ?, ?, ?, ?, 1)'
                            );
                            $ins->execute([
                                $clientId, $productId, $systemId, $code,
                                'Imported ' . date('Y-m-d'),
                            ]);
                            $tableId = (int) $pdo->lastInsertId();
                            $created = true;
                        }
                        // Replace cells.
                        $del = $pdo->prepare('DELETE FROM price_table_rows WHERE price_table_id = ?');
                        $del->execute([$tableId]);
                        $cellIns = $pdo->prepare(
                            'INSERT INTO price_table_rows
                               (price_table_id, width_mm, drop_mm, price)
                             VALUES (?, ?, ?, ?)'
                        );
                        foreach ($picked['cells'] as [$w, $d, $p]) {
                            $cellIns->execute([$tableId, $w, $d, $p]);
                        }
                        $pdo->commit();
                        $summary = [
                            'code'           => $code,
                            'cells'          => count($picked['cells']),
                            'created'        => $created,
                            'extras_ignored' => $extrasIgnored,
                            'table_id'       => $tableId,
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

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Single-band import &middot; <?= e((string) $system['system_name']) ?> &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
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
                    Single-band import &mdash;
                    <?= e((string) $system['product_name']) ?>
                    / <?= e((string) $system['system_name']) ?>
                </h1>
                <p class="page-subtitle">
                    <a href="/admin/products/price-tables.php?system_id=<?= (int) $systemId ?>">
                        &larr; Back to <?= e((string) $system['system_name']) ?> price tables
                    </a>
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($summary !== null): ?>
            <div class="alert alert-success" role="status">
                Imported <span class="band-pill">Band <?= e((string) $summary['code']) ?></span>
                &mdash; <strong><?= (int) $summary['cells'] ?></strong>
                cell<?= $summary['cells'] === 1 ? '' : 's' ?>
                <?= $summary['created'] ? '(new price table created)' : '(replaced existing price table)' ?>.
                <?php if ($summary['extras_ignored'] > 0): ?>
                    <em>The file contained <?= (int) $summary['extras_ignored'] ?>
                    other band<?= $summary['extras_ignored'] === 1 ? '' : 's' ?>; only Band
                    <?= e((string) $summary['code']) ?> was used. To bring all of them in at
                    once, use <strong>Bulk import</strong> instead.</em>
                <?php endif; ?>
            </div>
            <div class="form-actions" style="margin-bottom:1rem">
                <a href="/admin/products/price-table.php?id=<?= (int) $summary['table_id'] ?>"
                   class="btn btn-primary">View price table</a>
                <a href="/admin/products/price-tables.php?system_id=<?= (int) $systemId ?>"
                   class="btn btn-secondary">Back to price tables</a>
            </div>
        <?php endif; ?>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">What this expects</h2>
            </div>
            <div class="tip-box">
                A single Excel file with <strong>one band block</strong>:
                <ul style="margin:0.5rem 0 0;padding-left:1.25rem">
                    <li>One row with <code>Band X</code> (or <code>Price Band X</code>) in column A</li>
                    <li>A widths row in <strong>mm</strong> or <strong>metres</strong> (auto-detected)</li>
                    <li>Data rows: drop in column A, prices in £ across the width columns</li>
                </ul>
                The price table for that band is <strong>created if it doesn't exist</strong>, or
                <strong>its cells replaced</strong> if it does.
                If the file actually contains multiple bands, only the first is used — you'll
                get a hint suggesting bulk import instead.
            </div>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Upload</h2>
            </div>
            <form method="post"
                  action="/admin/products/price-tables-single-import.php"
                  enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="system_id" value="<?= (int) $systemId ?>">

                <div class="form-row full">
                    <div class="form-group">
                        <label for="file">Single-band file (.xlsx)</label>
                        <input id="file" name="file" type="file"
                               accept=".xlsx,.xlsm,.xls,.csv,.ods"
                               required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Upload &amp; import</button>
                    <a href="/admin/products/price-tables.php?system_id=<?= (int) $systemId ?>"
                       class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
