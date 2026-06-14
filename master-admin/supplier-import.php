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

requireSuperAdmin();

$user   = current_user();
$result = null;
$error  = null;
$fileLabel = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        $error = 'Please choose a file to preview.';
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
            } catch (Throwable $e) {
                error_log('[YourBlinds] supplier-import preview failed: ' . $e->getMessage());
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
                <h1 class="page-title">Supplier import &mdash; preview</h1>
                <p class="page-subtitle">
                    Upload a supplier price list to see what the parser reads out of it.
                    <strong>This is preview only &mdash; nothing is imported or changed.</strong>
                </p>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <a href="/master-admin/index.php" class="btn btn-secondary">&larr; Master Admin</a>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section">
            <form method="post" action="/master-admin/supplier-import.php" enctype="multipart/form-data"
                  style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center">
                <?= csrf_field() ?>
                <input type="file" name="file" accept=".xlsx,.xlsm,.xls,.csv,.ods" required
                       class="form-control" style="max-width:24rem">
                <button type="submit" class="btn btn-primary">Preview file</button>
            </form>
            <p style="color:var(--text-faint);font-size:0.8125rem;margin:0.75rem 0 0;max-width:46rem">
                Reads each worksheet as a product and pulls its width&nbsp;&times;&nbsp;drop band tables.
                Sheets it can&rsquo;t read (contacts pages, oddly-shaped layouts, etc.) are listed
                separately so nothing slips through silently.
            </p>
        </section>

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

                <p style="color:var(--text-faint);font-size:0.8125rem;margin:1.25rem 0 0">
                    Preview only &mdash; nothing has been imported or changed. (The next stage will turn a
                    confirmed preview into a supplier&rsquo;s catalogue in the library.)
                </p>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
