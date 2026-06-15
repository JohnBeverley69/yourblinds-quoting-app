<?php
declare(strict_types=1);

/**
 * Master Admin: supplier price-list import — PREVIEW (read-only).
 *
 * Upload a supplier spreadsheet and see exactly what the parser would pull out
 * (products, bands, price cells) and which sheets it can't read. Writes NOTHING
 * to the database — this is the safe front half of the price-list library, and
 * the basis of the eventual "verify before publish" step.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/library.php';

requireSuperAdmin();

$user   = current_user();
$result = null;
$error  = null;
$fileLabel = '';
$importSummary = null;

// Library suppliers (active) for the import target picker, + the master tenant
// the import writes into.
$suppliers   = library_suppliers();
$masterId    = library_master_client_id();
$masterName  = '';
if ($masterId > 0) {
    try {
        $st = db()->prepare('SELECT company_name FROM clients WHERE id = ? LIMIT 1');
        $st->execute([$masterId]);
        $masterName = (string) ($st->fetchColumn() ?: '');
    } catch (Throwable $e) { /* leave blank */ }
}

$mode        = (string) ($_POST['mode'] ?? 'preview');          // preview | import
$supplierKey = (string) ($_POST['supplier_key'] ?? '');
$supplier    = $suppliers[$supplierKey] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        $error = 'Please choose a file.';
    } elseif ($mode === 'import' && $supplier === null) {
        $error = 'Choose which supplier to import into before importing.';
    } elseif ($mode === 'import' && $masterId <= 0) {
        $error = 'No master tenant could be resolved to import into.';
    } else {
        $fileLabel = (string) ($_FILES['file']['name'] ?? '');
        $ext = strtolower((string) pathinfo($fileLabel, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xlsm', 'xls', 'csv', 'ods'], true)) {
            $error = 'Please upload a spreadsheet (.xlsx, .xlsm, .xls, .csv or .ods).';
        } elseif ((int) ($_FILES['file']['size'] ?? 0) > 12 * 1024 * 1024) {
            $error = 'That file is too large (max 12 MB).';
        } else {
            require_once __DIR__ . '/../_partials/supplier_catalogue_reader.php';
            @set_time_limit(180);
            @ini_set('memory_limit', '1024M');

            // Move to a temp path WITH the extension so PhpSpreadsheet picks the
            // right loader; clean it up afterwards.
            $dest = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                  . 'yb_supplier_' . bin2hex(random_bytes(6)) . '.' . $ext;
            try {
                if (!@move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
                    $dest = $_FILES['file']['tmp_name'];   // fallback (no extension)
                }
                $result = supplier_read_catalogue($dest);

                // Import: write the parsed grids into the master catalogue under
                // the chosen supplier's prefix.
                if ($mode === 'import' && $result !== null && $supplier !== null) {
                    require_once __DIR__ . '/../_partials/supplier_catalogue_writer.php';
                    $importSummary = supplier_import_to_catalogue(
                        db(), $masterId, (string) $supplier['prefix'], $result
                    );
                }
            } catch (Throwable $e) {
                error_log('[YourBlinds] supplier-import failed: ' . $e->getMessage());
                $error = 'Could not read that file. Make sure it is a valid spreadsheet.';
            } finally {
                if (isset($dest) && is_file($dest) && strpos($dest, 'yb_supplier_') !== false) {
                    @unlink($dest);
                }
            }
        }
    }
}

$totProducts = $result ? count($result['products']) : 0;
$totSkipped  = $result ? count($result['skipped'])  : 0;
$totBands    = 0;
$totCells    = 0;
if ($result) {
    foreach ($result['products'] as $p) { $totBands += (int) $p['band_count']; $totCells += (int) $p['cell_count']; }
}

$activeNav = 'supplier-import';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supplier import (preview) &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Supplier import</h1>
                <p class="page-subtitle">
                    Upload a supplier price list, preview what the parser reads, then import the
                    price grids into the master catalogue under a supplier's prefix.
                </p>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <a href="/master-admin/master-catalogue.php" class="btn btn-secondary">Master Catalogue</a>
                <a href="/master-admin/library-suppliers.php" class="btn btn-secondary">Library suppliers</a>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section">
            <form method="post" action="/master-admin/supplier-import.php" enctype="multipart/form-data" class="form">
                <?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Supplier (import target)</label>
                        <select name="supplier_key" class="form-control" style="max-width:24rem">
                            <option value="">— choose to import —</option>
                            <?php foreach ($suppliers as $key => $sup): ?>
                                <option value="<?= e($key) ?>" <?= $key === $supplierKey ? 'selected' : '' ?>>
                                    <?= e((string) $sup['name']) ?> (prefix “<?= e((string) ($sup['prefix'] ?? '')) ?>”)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p style="margin:.3rem 0 0;color:var(--text-faint);font-size:.75rem">
                            Imported products are named “&lt;prefix&gt; &lt;sheet name&gt;”.
                            Not needed just to preview.
                        </p>
                    </div>
                    <div class="form-group">
                        <label>Price-list file</label>
                        <input type="file" name="file" accept=".xlsx,.xlsm,.xls,.csv,.ods" required
                               class="form-control" style="max-width:24rem">
                    </div>
                </div>
                <div class="form-actions" style="display:flex;gap:0.75rem;flex-wrap:wrap">
                    <button type="submit" name="mode" value="preview" class="btn btn-secondary">Preview only</button>
                    <button type="submit" name="mode" value="import" class="btn btn-primary">Import into catalogue</button>
                </div>
            </form>
            <p style="color:var(--text-faint);font-size:0.8125rem;margin:0.75rem 0 0;max-width:48rem;line-height:1.5">
                Reads each worksheet as a product and pulls its width&nbsp;&times;&nbsp;drop band grids.
                <strong>Preview only</strong> reads and shows what it found, changing nothing.
                <strong>Import</strong> writes those grids into
                <?php if ($masterId > 0): ?>
                    <strong><?= e($masterName !== '' ? $masterName : ('client #' . $masterId)) ?></strong>
                <?php else: ?>the master account<?php endif; ?>
                as products + price tables (each on an auto-created “Standard” system). It imports the
                <em>price grids only</em> — add <strong>fabrics</strong> to each product in
                <a href="/admin/products/index.php">Products</a> afterwards (and rename/split the system if needed).
                Re-importing an updated file refreshes prices without creating duplicates.
            </p>
        </section>

        <?php if ($importSummary !== null): ?>
            <?php
                $added   = (int) $importSummary['products_added'];
                $updated = (int) $importSummary['products_updated'];
                $skipped = (int) ($importSummary['products_skipped'] ?? 0);
                $tbls    = (int) $importSummary['price_tables_added'];
                $cells   = (int) $importSummary['cells_written'];
                $errs    = (array) $importSummary['errors'];
            ?>
            <section class="section">
                <div class="alert <?= $errs ? 'alert-error' : 'alert-success' ?>" role="status">
                    Imported into <strong><?= e($masterName !== '' ? $masterName : ('client #' . $masterId)) ?></strong>
                    under <strong><?= e((string) ($supplier['name'] ?? '')) ?></strong>:
                    <strong><?= $added ?></strong> new product<?= $added === 1 ? '' : 's' ?>,
                    <strong><?= $updated ?></strong> updated,
                    <strong><?= $tbls ?></strong> price table<?= $tbls === 1 ? '' : 's' ?> added,
                    <strong><?= number_format($cells) ?></strong> price cells written.
                    <?php if ($skipped): ?> &middot; <strong><?= $skipped ?></strong> skipped (already has systems).<?php endif; ?>
                    <?php if ($errs): ?> &middot; <strong><?= count($errs) ?></strong> sheet<?= count($errs) === 1 ? '' : 's' ?> errored.<?php endif; ?>
                </div>

                <?php if (!empty($importSummary['per_product'])): ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Product</th><th></th><th style="text-align:right">Bands</th><th style="text-align:right">Cells</th></tr></thead>
                            <tbody>
                                <?php foreach ($importSummary['per_product'] as $pp): ?>
                                    <tr<?= !empty($pp['skipped']) ? ' style="opacity:.6"' : '' ?>>
                                        <td><strong><?= e((string) $pp['product']) ?></strong></td>
                                        <td><?php if (!empty($pp['skipped'])): ?>
                                                <span style="color:#92400e;font-size:.75rem;font-weight:600" title="<?= e((string) ($pp['reason'] ?? '')) ?>">SKIPPED</span>
                                            <?php elseif (!empty($pp['new'])): ?>
                                                <span style="color:var(--alert-success-text);font-size:.75rem;font-weight:600">NEW</span>
                                            <?php else: ?>
                                                <span style="color:var(--text-faint);font-size:.75rem">updated</span>
                                            <?php endif; ?></td>
                                        <td style="text-align:right"><?= !empty($pp['skipped']) ? '—' : (int) $pp['bands'] ?></td>
                                        <td style="text-align:right"><?= !empty($pp['skipped']) ? '—' : number_format((int) $pp['cells']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($errs): ?>
                    <details style="margin-top:1rem">
                        <summary style="cursor:pointer;font-weight:600;color:var(--text-secondary)">
                            <?= count($errs) ?> sheet<?= count($errs) === 1 ? '' : 's' ?> errored — details
                        </summary>
                        <div class="table-wrap" style="margin-top:.625rem">
                            <table class="table">
                                <thead><tr><th>Product</th><th>Error</th></tr></thead>
                                <tbody>
                                    <?php foreach ($errs as $er): ?>
                                        <tr><td><?= e((string) ($er['product'] ?? '')) ?></td><td style="color:var(--text-muted)"><?= e((string) ($er['message'] ?? '')) ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                <?php endif; ?>

                <p style="margin:1rem 0 0">
                    <a href="/master-admin/master-catalogue.php" class="btn btn-secondary">View in Master Catalogue</a>
                </p>
            </section>
        <?php endif; ?>

        <?php if ($result !== null): ?>
            <section class="section">
                <div class="alert alert-success" role="status" style="margin-bottom:1rem">
                    Read <strong><?= (int) $totProducts ?></strong> product<?= $totProducts === 1 ? '' : 's' ?>
                    &middot; <strong><?= (int) $totBands ?></strong> band<?= $totBands === 1 ? '' : 's' ?>
                    &middot; <strong><?= number_format($totCells) ?></strong> price cells
                    <?php if ($totSkipped): ?>&middot; <strong><?= (int) $totSkipped ?></strong> sheet<?= $totSkipped === 1 ? '' : 's' ?> skipped<?php endif; ?>
                    <?php if ($fileLabel !== ''): ?> &middot; <span style="color:var(--text-faint)"><?= e($fileLabel) ?></span><?php endif; ?>
                </div>

                <?php if ($result['products']): ?>
                    <h2 class="section-title">Products it read</h2>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Worksheet (product)</th><th>Bands</th><th style="text-align:right">Price cells</th></tr></thead>
                            <tbody>
                                <?php foreach ($result['products'] as $p): ?>
                                    <tr>
                                        <td><strong><?= e((string) $p['name']) ?></strong></td>
                                        <td><?= (int) $p['band_count'] ?>
                                            <span style="color:var(--text-faint)">[<?php
                                                echo e(implode(', ', array_slice(array_map(static fn ($b) => (string) ($b['code'] ?? '?'), $p['bands']), 0, 8)));
                                            ?>]</span>
                                        </td>
                                        <td style="text-align:right"><?= number_format((int) $p['cell_count']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color:var(--text-secondary)">No readable products found in that file.</p>
                <?php endif; ?>

                <?php if ($result['skipped']): ?>
                    <details style="margin-top:1.25rem">
                        <summary style="cursor:pointer;font-weight:600;color:var(--text-secondary)">
                            <?= (int) $totSkipped ?> sheet<?= $totSkipped === 1 ? '' : 's' ?> skipped &mdash; why?
                        </summary>
                        <div class="table-wrap" style="margin-top:0.625rem">
                            <table class="table">
                                <thead><tr><th>Worksheet</th><th>Reason</th></tr></thead>
                                <tbody>
                                    <?php foreach ($result['skipped'] as $s): ?>
                                        <tr><td><?= e((string) $s['sheet']) ?></td><td style="color:var(--text-muted)"><?= e((string) $s['reason']) ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                <?php endif; ?>

                <?php if ($importSummary === null): ?>
                    <p style="color:var(--text-faint);font-size:0.8125rem;margin:1.25rem 0 0">
                        This was a preview &mdash; nothing has been changed. Happy with it? Choose a supplier
                        above and click <strong>Import into catalogue</strong> to write these grids in.
                    </p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
