<?php
declare(strict_types=1);

/**
 * Bulk fabric/option import — upload ONE multi-sheet workbook and distribute
 * each sheet's fabrics into the matching product in a single pass.
 *
 * Why: options-import.php is per-product and reads only the active sheet, so a
 * supplier workbook with one sheet per product (e.g. the Decora/Arena fabric
 * files) had to be split and uploaded 27 times. This parses every sheet,
 * auto-matches each to a product by name (the admin can override per sheet or
 * Skip), then inserts into product_options for each chosen product.
 *
 * Flow: upload → map (suggested product per sheet, editable) → import.
 * The parsed rows round-trip through the form as JSON, so there's no re-upload.
 *
 * Admin-only, tenant-scoped, CSRF-checked. Re-uses the same column detection
 * and duplicate-skip behaviour as options-import.php.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = (int) $user['client_id'];
$pdo      = db();

// Tenant's products for the target dropdowns.
$products = $pdo->prepare('SELECT id, name FROM products WHERE client_id = ? ORDER BY name');
$products->execute([$clientId]);
$products = $products->fetchAll();

// ── Helpers ───────────────────────────────────────────────────────────────
// Tokenise a name for matching: lowercase, alnum words >= 3 chars, minus noise.
$STOP = ['decora','arena','fabric','fabrics','the','box','wholesale','blind','blinds','system'];
$toks = function (string $s) use ($STOP): array {
    $s = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $s));
    $out = [];
    foreach (preg_split('/\s+/', trim($s)) as $w) {
        if (strlen($w) >= 3 && !in_array($w, $STOP, true)) $out[] = $w;
    }
    return array_values(array_unique($out));
};
// Best product id for a sheet name (0 = no confident match → Skip).
$matchProduct = function (string $sheetName) use ($products, $toks): int {
    $st = $toks($sheetName);
    if (!$st) return 0;
    $bestId = 0; $bestScore = 0.0;
    foreach ($products as $p) {
        $pt = $toks((string) $p['name']);
        $inter = count(array_intersect($st, $pt));
        if ($inter === 0) continue;
        // Reward overlap, lightly penalise tokens unique to each side so a tight
        // match (FB Roller) beats a looser one (Contract Roller) for "Roller".
        $score = $inter * 2 - (count($pt) - $inter) - (count($st) - $inter);
        if ($score > $bestScore) { $bestScore = $score; $bestId = (int) $p['id']; }
    }
    return $bestId;
};

// Parse one PhpSpreadsheet worksheet into [['band','name','colour'], ...].
$parseSheet = function ($rows): array {
    $headerRow = $rows[1] ?? [];
    $colMap = [];
    foreach ($headerRow as $col => $raw) {
        $h = strtolower(trim(rtrim((string) $raw, '*')));
        if ($h === '') continue;
        if (in_array($h, ['band','band code','bandcode'], true))      $colMap['band']   = $col;
        elseif (in_array($h, ['colour','color'], true))               $colMap['colour'] = $col;
        elseif (str_contains($h, 'name') || $h === 'fabric'
             || $h === 'slat' || $h === 'slat type')                  $colMap['name']   = $col;
    }
    $hasHeaders = isset($colMap['band']) && isset($colMap['name']);
    if (!$hasHeaders) $colMap = ['band' => 'A', 'name' => 'B', 'colour' => 'C'];

    $out = [];
    foreach ($rows as $rowNum => $row) {
        if ($hasHeaders && $rowNum === 1) continue;
        $band = trim((string) ($row[$colMap['band']] ?? ''));
        $name = trim((string) ($row[$colMap['name']] ?? ''));
        if ($band === '' || $name === '') continue;
        $band = preg_replace('/^band\s+/i', '', $band);
        if ($band === '') continue;
        if (in_array(strtoupper($band), ['BAND','BAND CODE'], true)) continue;
        $colour = isset($colMap['colour']) ? trim((string) ($row[$colMap['colour']] ?? '')) : '';
        $out[] = [strtoupper($band), $name, $colour];
    }
    return $out;
};

$error   = null;
$stage   = 'upload';   // upload | map | done
$sheets  = null;       // [['name'=>, 'rows'=>[...], 'match'=>productId], ...]
$summary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? 'upload');
    require __DIR__ . '/../../vendor/autoload.php';

    if ($action === 'import') {
        // Second step — insert from the JSON payload using the per-sheet
        // product the admin chose. All tenant-scoped.
        $payload = json_decode((string) ($_POST['payload'] ?? ''), true);
        $map     = $_POST['map'] ?? [];   // sheet idx => product id (0 = skip)
        if (!is_array($payload)) {
            $error = 'That upload expired — please upload the file again.';
        } else {
            // Valid product ids for this tenant.
            $valid = [];
            foreach ($products as $p) $valid[(int) $p['id']] = (string) $p['name'];
            $insert = $pdo->prepare(
                'INSERT INTO product_options
                   (client_id, product_id, band_code, supplier_name, name, colour, code, sort_order, active)
                 VALUES (?, ?, ?, NULL, ?, ?, NULL, 0, 1)'
            );
            $results = [];
            foreach ($payload as $idx => $sheet) {
                $pid = (int) ($map[$idx] ?? 0);
                if ($pid <= 0 || !isset($valid[$pid])) continue;
                $ins = 0; $dup = 0;
                foreach (($sheet['rows'] ?? []) as $r) {
                    [$band, $name, $colour] = [$r[0] ?? '', $r[1] ?? '', $r[2] ?? ''];
                    if ($band === '' || $name === '') continue;
                    try {
                        $insert->execute([$clientId, $pid, $band, $name, $colour !== '' ? $colour : null]);
                        $ins++;
                    } catch (PDOException $e) {
                        if (str_contains($e->getMessage(), 'uniq_option_per_product')) $dup++;
                        // else: swallow — one bad row shouldn't abort the batch.
                    }
                }
                $results[] = [
                    'sheet'   => (string) ($sheet['name'] ?? ''),
                    'product' => $valid[$pid],
                    'ins'     => $ins,
                    'dup'     => $dup,
                ];
            }
            $summary = $results;
            $stage   = 'done';
        }
    } elseif (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Please choose a file to upload.';
    } elseif (filesize($_FILES['file']['tmp_name']) > 10 * 1024 * 1024) {
        $error = 'File too large (10 MB max).';
    } else {
        try {
            $ss = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['file']['tmp_name']);
            $parsed = [];
            foreach ($ss->getAllSheets() as $sheet) {
                $rows = $parseSheet($sheet->toArray(null, true, true, true));
                if ($rows) {
                    $parsed[] = [
                        'name'  => $sheet->getTitle(),
                        'rows'  => $rows,
                        'match' => $matchProduct($sheet->getTitle()),
                    ];
                }
            }
            if (!$parsed) {
                $error = 'No fabric rows found. Each sheet needs Name + Band columns (Colour optional).';
            } else {
                $sheets = $parsed;
                $stage  = 'map';
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
    <title>Bulk import fabrics &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .tip-box { background: var(--bg-subtle); border: 1px solid var(--border); border-radius: 8px;
            padding: 0.75rem 1rem; font-size: 0.9375rem; color: var(--text-muted); margin-bottom: 1rem; }
        .map-table { width: 100%; border-collapse: collapse; }
        .map-table th, .map-table td { text-align: left; padding: 0.4rem 0.6rem; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
        .map-table select { padding: 0.3rem 0.4rem; font: inherit; font-size: 0.85rem;
            border: 1px solid var(--border-strong); border-radius: 6px; background: var(--bg-input); color: var(--text-body); max-width: 18rem; }
        .pill { display:inline-block; padding: 0.0625rem 0.45rem; font-size: 0.75rem; font-weight: 600;
            background: var(--bg-subtle-2); border-radius: 999px; color: var(--text-faint); }
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
                <h1 class="page-title">Bulk import fabrics</h1>
                <p class="page-subtitle">
                    <a href="/admin/products/index.php">&larr; Back to products</a>
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($stage === 'done'): ?>
            <div class="alert alert-success" role="status">
                Imported into <strong><?= count($summary) ?></strong> product<?= count($summary) === 1 ? '' : 's' ?>.
            </div>
            <section class="section">
                <ul class="summary-list">
                    <?php foreach ($summary as $r): ?>
                        <li>
                            <strong><?= e($r['product']) ?></strong> &mdash; <?= (int) $r['ins'] ?> added
                            <?php if ($r['dup'] > 0): ?><span class="pill"><?= (int) $r['dup'] ?> dup skipped</span><?php endif; ?>
                            <span style="color:var(--text-faint)">(from “<?= e($r['sheet']) ?>”)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p style="margin-top:1rem">
                    <a href="/admin/products/index.php" class="btn btn-primary">Back to products</a>
                    <a href="/admin/products/options-bulk-import.php" class="btn btn-secondary">Import another file</a>
                </p>
            </section>

        <?php elseif ($stage === 'map' && $sheets): ?>
            <section class="section">
                <div class="section-header"><h2 class="section-title">Check the matches, then import</h2></div>
                <div class="tip-box">
                    Each worksheet is matched to a product by name. Change any that are wrong, or set a sheet to
                    <strong>Skip</strong>. A fabric range shared by several products (e.g. all your roller fabrics) can
                    be imported here for one, then add it to the others from their own Fabrics page.
                    Duplicate (band + name + colour) rows are skipped automatically.
                </div>
                <form method="post" action="/admin/products/options-bulk-import.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="import">
                    <input type="hidden" name="payload"
                           value="<?= e((string) json_encode(
                               array_map(static fn ($s) => ['name' => $s['name'], 'rows' => $s['rows']], $sheets),
                               JSON_UNESCAPED_UNICODE
                           )) ?>">
                    <table class="map-table">
                        <thead>
                            <tr><th>Worksheet</th><th>Fabrics</th><th>Import into product</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sheets as $i => $s): ?>
                                <tr>
                                    <td><strong><?= e((string) $s['name']) ?></strong></td>
                                    <td><span class="pill"><?= count($s['rows']) ?></span></td>
                                    <td>
                                        <select name="map[<?= (int) $i ?>]">
                                            <option value="0"<?= $s['match'] === 0 ? ' selected' : '' ?>>— Skip —</option>
                                            <?php foreach ($products as $p): ?>
                                                <option value="<?= (int) $p['id'] ?>"<?= (int) $p['id'] === $s['match'] ? ' selected' : '' ?>>
                                                    <?= e((string) $p['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="form-actions" style="margin-top:1rem">
                        <button type="submit" class="btn btn-primary">Import all matched &rarr;</button>
                        <a href="/admin/products/options-bulk-import.php" class="btn btn-secondary">Start over</a>
                    </div>
                </form>
            </section>

        <?php else: ?>
            <section class="section">
                <div class="tip-box">
                    Upload a workbook with <strong>one sheet per product</strong> (columns
                    <code>Name</code>, <code>Colour</code>, <code>Band</code>). Every sheet is matched to a product
                    and you confirm the mapping before anything is written. Use this for the supplier fabric files;
                    for a single product, the per-product <em>Import</em> on its Fabrics page is simpler.
                </div>
                <form method="post" action="/admin/products/options-bulk-import.php" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload">
                    <div class="form-row full">
                        <div class="form-group">
                            <label for="file">Multi-sheet fabric file (.xlsx)</label>
                            <input id="file" name="file" type="file" accept=".xlsx,.xlsm,.xls,.csv,.ods" required>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Upload &amp; match &rarr;</button>
                        <a href="/admin/products/index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
