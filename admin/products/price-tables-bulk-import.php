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

// Other systems on this product — so the import-success screen can offer
// "now import the next one" (e.g. Special Frame after Standard Frame)
// instead of dead-ending.
$otherSystems = [];
try {
    $osStmt = db()->prepare(
        'SELECT id, name FROM product_systems
          WHERE product_id = ? AND client_id = ? AND id <> ? AND active = 1
       ORDER BY sort_order, name'
    );
    $osStmt->execute([$productId, $clientId, $systemId]);
    $otherSystems = $osStmt->fetchAll();
} catch (Throwable $e) { /* non-fatal — just no "next system" buttons */ }

// Of those other systems, which still have a band WITHOUT a price table?
// (Same gap logic as the setup wizard.) Only those should be offered as
// "import next" — re-nudging a system that's already fully priced is what made
// this success screen look like a dead-end (e.g. offering "Import Free Hanging"
// after it was already done). Banded products only; if the product has no band
// codes the gap query can't see its systems, so we keep the full list as a
// safe default.
$systemsNeedingImport = $otherSystems;
try {
    $bandStmt = db()->prepare(
        "SELECT 1 FROM product_options
          WHERE product_id = ? AND client_id = ? AND active = 1
            AND band_code IS NOT NULL AND band_code <> '' LIMIT 1"
    );
    $bandStmt->execute([$productId, $clientId]);
    if ($bandStmt->fetchColumn()) {
        $gapStmt = db()->prepare(
            "SELECT DISTINCT s.id
               FROM product_systems s
               JOIN product_options po
                 ON po.product_id = s.product_id AND po.client_id = s.client_id
                AND po.active = 1 AND po.band_code IS NOT NULL AND po.band_code <> ''
                AND (po.system_id IS NULL OR po.system_id = s.id)
              WHERE s.product_id = ? AND s.client_id = ? AND s.active = 1
                AND NOT EXISTS (
                  SELECT 1 FROM price_tables t
                   WHERE t.product_id = s.product_id AND t.system_id = s.id
                     AND t.band_code = po.band_code AND t.client_id = ? AND t.active = 1
                )"
        );
        $gapStmt->execute([$productId, $clientId, $clientId]);
        $gapIds = array_map('intval', array_column($gapStmt->fetchAll(), 'id'));
        $systemsNeedingImport = array_values(array_filter(
            $otherSystems,
            static fn ($os) => in_array((int) $os['id'], $gapIds, true)
        ));
    }
} catch (Throwable $e) { /* keep the safe fallback (show all) */ }

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
                $error = 'No band sections detected. Each band block should start with a row containing "Band X" in column A.';
            } else {
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $insertedBands = [];
                    foreach ($bands as $band) {
                        $code = strtoupper($band['code']);
                        // Find or create the price_table for this product+system+band.
                        $find = $pdo->prepare(
                            'SELECT id FROM price_tables
                              WHERE client_id = ? AND product_id = ? AND system_id = ? AND band_code = ?'
                        );
                        $find->execute([$clientId, $productId, $systemId, $code]);
                        $tableId = (int) ($find->fetchColumn() ?: 0);
                        if ($tableId === 0) {
                            $ins = $pdo->prepare(
                                'INSERT INTO price_tables
                                   (client_id, product_id, system_id, band_code, name, active)
                                 VALUES (?, ?, ?, ?, ?, 1)'
                            );
                            $ins->execute([
                                $clientId,
                                $productId,
                                $systemId,
                                $code,
                                'Imported ' . date('Y-m-d'),
                            ]);
                            $tableId = (int) $pdo->lastInsertId();
                        }
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
                        $insertedBands[] = ['code' => $code, 'cells' => count($band['cells'])];
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

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bulk import &middot; <?= e((string) $system['system_name']) ?> &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
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
                    Bulk import &mdash;
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
                Imported <strong><?= count($summary['bands']) ?></strong>
                band<?= count($summary['bands']) === 1 ? '' : 's' ?>
                into <strong><?= e((string) $system['system_name']) ?></strong>:
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
                <?php if ($systemsNeedingImport): ?>
                    <p style="margin:1rem 0 0.25rem;color:var(--text-secondary);font-size:0.9375rem">
                        Got prices for the other system<?= count($systemsNeedingImport) === 1 ? '' : 's' ?> too? Import next:
                    </p>
                <?php else: ?>
                    <p style="margin:1rem 0 0.25rem;color:var(--alert-success-text);font-size:0.9375rem;font-weight:600">
                        ✓ That's every system priced for this product — it's ready to quote.
                    </p>
                <?php endif; ?>
                <div class="form-actions" style="margin-top:0.75rem;display:flex;gap:0.5rem;flex-wrap:wrap">
                    <?php foreach ($systemsNeedingImport as $os): ?>
                        <a href="/admin/products/price-tables-bulk-import.php?system_id=<?= (int) $os['id'] ?>"
                           class="btn btn-primary">Import <?= e((string) $os['name']) ?> &rarr;</a>
                    <?php endforeach; ?>
                    <a href="/admin/products/price-tables.php?system_id=<?= (int) $systemId ?>"
                       class="btn btn-secondary">View <?= e((string) $system['system_name']) ?> prices</a>
                    <a href="/admin/products/wizard.php?id=<?= (int) $productId ?>&amp;step=4"
                       class="btn <?= $systemsNeedingImport ? 'btn-secondary' : 'btn-primary' ?>">Back to setup wizard</a>
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
                    <li>One row with <code>Band X</code> (or <code>Price Band X</code>, or <code>Bnad X</code>) in column A</li>
                    <li>A widths row — values in <strong>mm</strong> (e.g. <code>610mm</code>) or <strong>metres</strong> (e.g. <code>0.800</code>); auto-detected per cell</li>
                    <li>Optional label rows (<code>DROP</code>, <code>WIDTH</code>, <code>Metric</code>, inches reference) get skipped</li>
                    <li>Data rows: drop in column A, prices in £ across the width columns; currency symbols / commas are stripped automatically</li>
                </ul>
                Multiple band blocks can be stacked vertically in one sheet, or spread across multiple sheets — both work.
                <strong>Re-importing replaces</strong> any existing rows for each band <em>within this system</em>.
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
                <input type="hidden" name="system_id" value="<?= (int) $systemId ?>">

                <div class="form-row full">
                    <div class="form-group">
                        <label for="file">Multi-band file (.xlsx)</label>
                        <input id="file" name="file" type="file"
                               accept=".xlsx,.xlsm,.xls,.csv,.ods"
                               required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Upload &amp; import all bands</button>
                    <a href="/admin/products/price-tables.php?system_id=<?= (int) $systemId ?>"
                       class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
