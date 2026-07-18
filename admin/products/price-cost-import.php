<?php
declare(strict_types=1);

/**
 * Import a COST grid — the sibling of the sell-price bulk import.
 *
 * Same file, same banded width×drop shape, same parser. The only difference:
 * this OVERLAYS cost onto the price cells that already exist (UPDATE cost),
 * rather than replacing the price grid. So margin = price − cost, exact at every
 * size. Import the sell prices first; this attaches your cost to them.
 *
 * The cost sheet's cells are live formulas (structured references), so this
 * reads the STORED values — never recalculates, which would crash on them.
 *
 * ?system_id=<id> — the system whose price grid to cost.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = (int) $user['client_id'];

$systemId = (int) ($_GET['system_id'] ?? $_POST['system_id'] ?? 0);
if ($systemId <= 0) { header('Location: /admin/products/index.php'); exit; }

$sysStmt = db()->prepare(
    'SELECT s.id, s.name AS system_name, s.product_id, p.name AS product_name
       FROM product_systems s JOIN products p ON p.id = s.product_id
      WHERE s.id = ? AND s.client_id = ?'
);
$sysStmt->execute([$systemId, $clientId]);
$system = $sysStmt->fetch(PDO::FETCH_ASSOC);
if (!$system) { http_response_code(404); echo '<h1>System not found</h1>'; exit; }
$productId = (int) $system['product_id'];

$error = null; $summary = null; $stage = 'upload'; $pickSheets = [];

/** A sheet's rows as ptp_parse_band_blocks wants them — STORED values, no recalc. */
$sheetRowsCached = static function ($sheet): array {
    $rows = [];
    $hi = $sheet->getHighestRow();
    $hc = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
    for ($r = 1; $r <= $hi; $r++) {
        $row = [];
        for ($c = 1; $c <= $hc; $c++) {
            $addr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . $r;
            $cell = $sheet->getCell($addr);
            $row[\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c)]
                = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
        }
        $rows[$r] = $row;
    }
    return $rows;
};

/**
 * Overlay parsed cost bands onto this system's existing price rows. Only UPDATEs
 * where a price cell already exists (same band, width, drop). Returns a summary.
 */
$overlayCost = function (array $bands) use ($clientId, $productId, $systemId): array {
    $pdo = db();
    $out = ['bands' => [], 'matched' => 0, 'unmatched' => 0, 'noTable' => []];
    $pdo->beginTransaction();
    try {
        $findTable = $pdo->prepare(
            'SELECT id FROM price_tables WHERE client_id = ? AND product_id = ? AND system_id = ? AND band_code = ?'
        );
        $updCost = $pdo->prepare(
            'UPDATE price_table_rows SET cost = ? WHERE price_table_id = ? AND width_mm = ? AND drop_mm = ?'
        );
        foreach ($bands as $band) {
            $code  = strtoupper((string) ($band['code'] ?? ''));
            $cells = is_array($band['cells'] ?? null) ? $band['cells'] : [];
            if ($code === '') continue;

            $findTable->execute([$clientId, $productId, $systemId, $code]);
            $tableId = (int) ($findTable->fetchColumn() ?: 0);
            if ($tableId === 0) { $out['noTable'][] = $code; continue; }   // no sell grid for this band yet

            $bm = 0; $bu = 0;
            foreach ($cells as $cell) {
                [$w, $d, $c] = array_values($cell);
                $updCost->execute([(float) $c, $tableId, (int) $w, (int) $d]);
                if ($updCost->rowCount() > 0) $bm++; else $bu++;
            }
            $out['bands'][] = ['code' => $code, 'matched' => $bm, 'unmatched' => $bu];
            $out['matched'] += $bm; $out['unmatched'] += $bu;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return $out;
};

/** A quick margin read after import, so the result screen shows it worked. */
$marginStats = function () use ($clientId, $productId, $systemId): array {
    $st = db()->prepare(
        "SELECT COUNT(*) AS n, MIN(r.price - r.cost) AS minm, MAX(r.price - r.cost) AS maxm,
                AVG(CASE WHEN r.price > 0 THEN (r.price - r.cost) / r.price * 100 END) AS avgpct
           FROM price_table_rows r
           JOIN price_tables t ON t.id = r.price_table_id
          WHERE t.client_id = ? AND t.product_id = ? AND t.system_id = ? AND r.cost IS NOT NULL AND r.price IS NOT NULL"
    );
    $st->execute([$clientId, $productId, $systemId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: [];
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    require __DIR__ . '/../../vendor/autoload.php';
    require __DIR__ . '/../../_partials/price_table_parser.php';
    $action = (string) ($_POST['action'] ?? 'upload');

    if ($action === 'import') {
        $all = json_decode((string) ($_POST['payloads'] ?? ''), true);
        $idx = (int) ($_POST['sheet_idx'] ?? -1);
        $bands = (is_array($all) && isset($all[$idx]['bands']) && is_array($all[$idx]['bands'])) ? $all[$idx]['bands'] : null;
        if (!$bands) { $error = 'That selection expired — upload the file again.'; }
        else {
            try { $summary = $overlayCost($bands); $summary['margin'] = $marginStats(); }
            catch (Throwable $e) { $error = 'Database error: ' . $e->getMessage(); }
        }
    } elseif (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Please choose a file to upload.';
    } elseif (filesize($_FILES['file']['tmp_name']) > 10 * 1024 * 1024) {
        $error = 'File too large (10 MB max).';
    } else {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($_FILES['file']['tmp_name']);
            $reader->setReadDataOnly(false);   // keep cached formula values
            $ss = $reader->load($_FILES['file']['tmp_name']);

            $sheetsWithBands = [];
            foreach ($ss->getAllSheets() as $sheet) {
                $b = ptp_parse_band_blocks($sheetRowsCached($sheet));
                if ($b) {
                    $sheetsWithBands[] = [
                        'name'  => $sheet->getTitle(),
                        'bands' => $b,
                        'cells' => array_sum(array_map(static fn ($x) => count($x['cells']), $b)),
                    ];
                }
            }
            if (!$sheetsWithBands) {
                $error = 'No band sections found. Each band should start with "Band X" in column A.';
            } elseif (count($sheetsWithBands) === 1) {
                try { $summary = $overlayCost($sheetsWithBands[0]['bands']); $summary['margin'] = $marginStats(); }
                catch (Throwable $e) { $error = 'Database error: ' . $e->getMessage(); }
            } else {
                $stage = 'pick'; $pickSheets = $sheetsWithBands;   // several grids — pick the cost one
            }
        } catch (Throwable $e) {
            $error = 'Could not read the file: ' . $e->getMessage();
        }
    }
}

$mm = static fn ($v) => $v === null ? '—' : '£' . number_format((float) $v, 2);
$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Import costs &middot; <?= e((string) $system['system_name']) ?> &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../../_partials/sidebar.php'; ?>
    <main class="app-main">
<div style="max-width:760px;margin:0 auto;padding:1rem;font-family:system-ui,sans-serif;">
    <p style="margin:0 0 .3rem"><a href="/admin/products/edit.php?id=<?= $productId ?>">&larr; <?= e((string) $system['product_name']) ?></a></p>
    <h1 style="font-size:1.5rem;margin:0 0 .3rem">Import costs</h1>
    <p style="color:#667;margin:0 0 1.2rem"><?= e((string) $system['product_name']) ?> &middot; <?= e((string) $system['system_name']) ?> &mdash; overlays your cost onto this system's price grid, so <strong>margin = price − cost</strong> at every size.</p>

    <?php if ($error): ?><div style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;padding:.7rem 1rem;border-radius:10px;margin:0 0 1rem"><?= e($error) ?></div><?php endif; ?>

    <?php if ($summary !== null): ?>
        <div style="background:#dcfce7;color:#166534;border:1px solid #86efac;padding:1rem 1.2rem;border-radius:12px;margin:0 0 1rem">
            <div style="font-size:1.15rem;font-weight:700;margin-bottom:.3rem">Costs imported.</div>
            <div><strong><?= (int) $summary['matched'] ?></strong> price cells costed<?= $summary['unmatched'] ? ', ' . (int) $summary['unmatched'] . ' had no matching size (skipped)' : '' ?>.</div>
            <?php if (!empty($summary['noTable'])): ?><div style="margin-top:.3rem;color:#92600a">No sell grid yet for band(s): <?= e(implode(', ', $summary['noTable'])) ?> — import those prices first, then re-run.</div><?php endif; ?>
            <?php $m = $summary['margin'] ?? []; if (($m['n'] ?? 0) > 0): ?>
                <div style="margin-top:.5rem;padding-top:.5rem;border-top:1px solid #86efac">
                    Margin across <?= (int) $m['n'] ?> sizes: <strong><?= number_format((float) ($m['avgpct'] ?? 0), 1) ?>%</strong> average
                    &middot; profit from <?= $mm($m['minm'] ?? null) ?> to <?= $mm($m['maxm'] ?? null) ?> per blind.
                </div>
            <?php endif; ?>
        </div>
        <p><a href="/admin/products/price-table.php?system_id=<?= $systemId ?>" style="font-weight:600">View the priced grid &rarr;</a></p>
    <?php elseif ($stage === 'pick'): ?>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?><input type="hidden" name="action" value="import"><input type="hidden" name="system_id" value="<?= $systemId ?>">
            <input type="hidden" name="payloads" value="<?= e(json_encode($pickSheets)) ?>">
            <p>Several sheets look like cost grids. Which one holds the <strong>costs</strong>?</p>
            <?php foreach ($pickSheets as $i => $sh): ?>
                <label style="display:block;padding:.6rem .8rem;border:1px solid #cbd5e1;border-radius:8px;margin-bottom:.5rem;cursor:pointer">
                    <input type="radio" name="sheet_idx" value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?>>
                    <strong><?= e((string) $sh['name']) ?></strong> — <?= count($sh['bands']) ?> bands, <?= (int) $sh['cells'] ?> cells
                </label>
            <?php endforeach; ?>
            <button type="submit" style="font:inherit;font-weight:600;padding:.5rem 1.1rem;border:none;border-radius:8px;background:#166534;color:#fff;cursor:pointer">Import costs from this sheet</button>
        </form>
    <?php else: ?>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?><input type="hidden" name="system_id" value="<?= $systemId ?>">
            <p>Upload the same workbook as your prices — this reads the <strong>cost</strong> sheet. Import the sell prices first if you haven't; cost attaches to them.</p>
            <p style="margin:1rem 0"><input type="file" name="file" accept=".xlsx,.xlsm,.xls,.csv,.ods" required></p>
            <button type="submit" style="font:inherit;font-weight:600;padding:.5rem 1.1rem;border:none;border-radius:8px;background:#166534;color:#fff;cursor:pointer">Upload &amp; read costs</button>
        </form>
    <?php endif; ?>
</div>
    </main>
</div>
</body>
</html>
