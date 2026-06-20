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

$summary    = null;
$error      = null;
$stage      = 'upload';   // 'upload' | 'pick'
$pickSheets = null;       // [['name'=>, 'bands'=>[...], 'cells'=>int], ...] for the pick step

/**
 * Write a set of parsed bands into this product/system. Replaces each band's
 * existing rows. All-or-nothing (one transaction). Returns the per-band summary.
 */
$bpi_import = function (array $bands) use ($clientId, $productId, $systemId): array {
    $pdo = db();
    $insertedBands = [];
    $pdo->beginTransaction();
    try {
        foreach ($bands as $band) {
            $code  = strtoupper((string) ($band['code'] ?? ''));
            $cells = is_array($band['cells'] ?? null) ? $band['cells'] : [];
            if ($code === '') continue;

            $find = $pdo->prepare(
                'SELECT id FROM price_tables
                  WHERE client_id = ? AND product_id = ? AND system_id = ? AND band_code = ?'
            );
            $find->execute([$clientId, $productId, $systemId, $code]);
            $tableId = (int) ($find->fetchColumn() ?: 0);
            if ($tableId === 0) {
                $pdo->prepare(
                    'INSERT INTO price_tables
                       (client_id, product_id, system_id, band_code, name, active)
                     VALUES (?, ?, ?, ?, ?, 1)'
                )->execute([$clientId, $productId, $systemId, $code, 'Imported ' . date('Y-m-d')]);
                $tableId = (int) $pdo->lastInsertId();
            }
            $pdo->prepare('DELETE FROM price_table_rows WHERE price_table_id = ?')->execute([$tableId]);
            $cellIns = $pdo->prepare(
                'INSERT INTO price_table_rows (price_table_id, width_mm, drop_mm, price) VALUES (?, ?, ?, ?)'
            );
            foreach ($cells as $cell) {
                [$w, $d, $p] = array_values($cell);
                $cellIns->execute([$tableId, (int) $w, (int) $d, (float) $p]);
            }
            $insertedBands[] = ['code' => $code, 'cells' => count($cells)];
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return $insertedBands;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? 'upload');
    require __DIR__ . '/../../vendor/autoload.php';
    require __DIR__ . '/../../_partials/price_table_parser.php';

    if ($action === 'import') {
        // Second step: import the worksheet the user picked. The parsed bands
        // round-trip through the form as JSON, so there's no re-upload.
        $all = json_decode((string) ($_POST['payloads'] ?? ''), true);
        $idx = (int) ($_POST['sheet_idx'] ?? -1);
        $bands = (is_array($all) && isset($all[$idx]['bands']) && is_array($all[$idx]['bands']))
            ? $all[$idx]['bands'] : null;
        if (!$bands) {
            $error = 'That selection expired — please upload the file again.';
        } else {
            try { $summary = ['bands' => $bpi_import($bands)]; }
            catch (Throwable $e) { $error = 'Database error: ' . $e->getMessage(); }
        }
    } elseif (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Please choose a file to upload.';
    } elseif (filesize($_FILES['file']['tmp_name']) > 10 * 1024 * 1024) {
        $error = 'File too large (10 MB max).';
    } else {
        try {
            $ss = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['file']['tmp_name']);

            // Parse each sheet separately so a multi-sheet workbook (e.g. one
            // sheet per slat size) can be imported one sheet at a time into the
            // right system, instead of merging every sheet's bands together.
            $sheetsWithBands = [];
            foreach ($ss->getAllSheets() as $sheet) {
                $b = ptp_parse_band_blocks($sheet->toArray(null, true, true, true));
                if ($b) {
                    $sheetsWithBands[] = [
                        'name'  => $sheet->getTitle(),
                        'bands' => $b,
                        'cells' => array_sum(array_map(static fn ($x) => count($x['cells']), $b)),
                    ];
                }
            }

            if (!$sheetsWithBands) {
                $error = 'No band sections detected. Each band block should start with a row containing "Band X" in column A.';
            } elseif (count($sheetsWithBands) === 1) {
                // Single usable sheet — import straight away (unchanged behaviour).
                try { $summary = ['bands' => $bpi_import($sheetsWithBands[0]['bands'])]; }
                catch (Throwable $e) { $error = 'Database error: ' . $e->getMessage(); }
            } else {
                // Several sheets have bands — let the user pick which one goes
                // into THIS system.
                $stage      = 'pick';
                $pickSheets = $sheetsWithBands;
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
                Multiple band blocks can be stacked vertically in one sheet. If the file has
                <strong>several worksheets</strong> with bands (e.g. one per slat size), you'll
                pick which worksheet goes into this system.
                <strong>Re-importing replaces</strong> any existing rows for each band <em>within this system</em>.
            </div>
        </section>

        <?php if ($stage === 'pick' && $pickSheets): ?>
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Which worksheet?</h2>
            </div>
            <p style="color:var(--text-muted);font-size:0.9375rem;margin:0 0 0.75rem">
                This file has more than one worksheet with bands — common when one
                file holds several slat sizes. Pick the one to import into
                <strong><?= e((string) $system['system_name']) ?></strong>.
            </p>
            <form method="post" action="/admin/products/price-tables-bulk-import.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="import">
                <input type="hidden" name="system_id" value="<?= (int) $systemId ?>">
                <input type="hidden" name="payloads"
                       value="<?= e((string) json_encode(
                           array_map(static fn ($s) => ['bands' => $s['bands']], $pickSheets),
                           JSON_UNESCAPED_UNICODE
                       )) ?>">
                <div style="display:flex;flex-direction:column;gap:0.5rem;margin:0 0 1rem">
                    <?php foreach ($pickSheets as $i => $sh): ?>
                        <label style="display:flex;align-items:center;gap:0.6rem;padding:0.5rem 0.75rem;
                                      border:1px solid var(--border-strong);border-radius:8px;cursor:pointer">
                            <input type="radio" name="sheet_idx" value="<?= (int) $i ?>" <?= $i === 0 ? 'checked' : '' ?>>
                            <span>
                                <strong><?= e((string) $sh['name']) ?></strong>
                                <span style="color:var(--text-faint);font-size:0.8125rem">
                                    &mdash; <?= count($sh['bands']) ?> band<?= count($sh['bands']) === 1 ? '' : 's' ?>,
                                    <?= (int) $sh['cells'] ?> cells
                                </span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        Import selected sheet into <?= e((string) $system['system_name']) ?> &rarr;
                    </button>
                    <a href="/admin/products/price-tables-bulk-import.php?system_id=<?= (int) $systemId ?>"
                       class="btn btn-secondary">Start over</a>
                </div>
            </form>
        </section>
        <?php else: ?>
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Upload</h2>
            </div>
            <form method="post"
                  action="/admin/products/price-tables-bulk-import.php"
                  enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload">
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
                    <button type="submit" class="btn btn-primary">Upload &amp; import &rarr;</button>
                    <a href="/admin/products/price-tables.php?system_id=<?= (int) $systemId ?>"
                       class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
