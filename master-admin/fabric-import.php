<?php
declare(strict_types=1);

/**
 * Master Admin: Fabric import — load a manufacturer's fabric range from a
 * spreadsheet into the Fabric Library (library_fabrics).
 *
 * Unlike the price-list import (a width×drop GRID), a fabric list is a flat
 * table: one row per fabric with columns for name / colour / code / band /
 * type. We auto-detect the header row and map columns by their wording, then
 * Preview (reads, changes nothing) or Import (writes, skipping exact dups).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

$pdo = db();

$ready = true;
try { $pdo->query('SELECT 1 FROM library_fabrics LIMIT 0'); }
catch (Throwable $e) { $ready = false; }

$suppliers = $ready
    ? $pdo->query('SELECT id, name FROM fabric_suppliers ORDER BY sort_order, name')->fetchAll(PDO::FETCH_ASSOC)
    : [];

/** Map a header cell's wording to a library_fabrics field (or null). */
$matchField = static function (string $h): ?string {
    $h = strtolower(trim($h));
    if ($h === '') return null;
    if (preg_match('/colou?r|shade/', $h))                         return 'colour';
    if (preg_match('/\bcode\b|\bref\b|reference|sku|item\s*(no|code|#)?/', $h)) return 'code';
    if (preg_match('/price\s*band|price\s*range|\bband\b|\bgroup\b/', $h))      return 'suggested_band';
    if (preg_match('/\btype\b|blind|category/', $h))               return 'blind_type';
    if (preg_match('/fabric|name|description|range|collection/', $h)) return 'name';
    return null;
};

/**
 * Read a fabric list from a spreadsheet. Finds the header row in the first ~12
 * rows (needs a "name" column + at least one other), maps columns, reads data
 * rows below it. Returns ['fabrics'=>[...], 'mapped'=>[field=>colLetter], 'skipped'=>int].
 */
$readFabrics = static function (string $path) use ($matchField): array {
    $reader = IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(true);
    $ws = $reader->load($path)->getActiveSheet();

    $maxRow = min((int) $ws->getHighestDataRow(), 5000);
    $maxCol = Coordinate::columnIndexFromString($ws->getHighestDataColumn());
    $maxCol = min($maxCol, 30);

    // Find the header row: first of the top rows that maps a 'name' column plus
    // at least one more recognised field.
    $headerRow = 0; $mapped = [];
    for ($r = 1; $r <= min($maxRow, 12); $r++) {
        $cand = [];
        for ($c = 1; $c <= $maxCol; $c++) {
            $field = $matchField((string) $ws->getCell([$c, $r])->getValue());
            if ($field !== null && !isset($cand[$field])) {
                $cand[$field] = Coordinate::stringFromColumnIndex($c);
            }
        }
        if (isset($cand['name']) && count($cand) >= 2) { $headerRow = $r; $mapped = $cand; break; }
    }

    $fabrics = []; $skipped = 0;
    if ($headerRow > 0) {
        $colIdx = [];
        foreach ($mapped as $field => $letter) $colIdx[$field] = Coordinate::columnIndexFromString($letter);
        for ($r = $headerRow + 1; $r <= $maxRow; $r++) {
            $get = static function (string $field) use ($ws, $colIdx, $r): string {
                if (!isset($colIdx[$field])) return '';
                return trim((string) $ws->getCell([$colIdx[$field], $r])->getValue());
            };
            $name = $get('name');
            if ($name === '') { continue; }                 // blank row
            // Skip a repeated header / section divider.
            if (strtolower($name) === 'name' || strtolower($name) === 'fabric') { $skipped++; continue; }
            $band = $get('suggested_band');
            $fabrics[] = [
                'name'           => mb_substr($name, 0, 160),
                'colour'         => mb_substr($get('colour'), 0, 120) ?: null,
                'code'           => mb_substr($get('code'), 0, 80) ?: null,
                'suggested_band' => $band !== '' ? strtoupper(mb_substr($band, 0, 20)) : null,
                'blind_type'     => mb_substr($get('blind_type'), 0, 60) ?: null,
            ];
        }
    }
    return ['fabrics' => $fabrics, 'mapped' => $mapped, 'skipped' => $skipped, 'header_row' => $headerRow];
};

$result    = null;
$error     = null;
$fileLabel = '';
$importSummary = null;
$mode      = (string) ($_POST['mode'] ?? 'preview');
$supId     = (int) ($_POST['fabric_supplier_id'] ?? 0);
$supName   = '';
foreach ($suppliers as $s) { if ((int) $s['id'] === $supId) $supName = (string) $s['name']; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    csrf_check();
    if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        $error = 'Please choose a file.';
    } elseif ($supId <= 0 || $supName === '') {
        $error = 'Choose which manufacturer these fabrics belong to.';
    } else {
        $fileLabel = (string) ($_FILES['file']['name'] ?? '');
        $ext = strtolower((string) pathinfo($fileLabel, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xlsm', 'xls', 'csv', 'ods'], true)) {
            $error = 'Please upload a spreadsheet (.xlsx, .xlsm, .xls, .csv or .ods).';
        } elseif ((int) ($_FILES['file']['size'] ?? 0) > 12 * 1024 * 1024) {
            $error = 'That file is too large (max 12 MB).';
        } else {
            require __DIR__ . '/../vendor/autoload.php';
            @set_time_limit(180);
            @ini_set('memory_limit', '1024M');
            $dest = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yb_fabric_' . bin2hex(random_bytes(6)) . '.' . $ext;
            try {
                if (!@move_uploaded_file($_FILES['file']['tmp_name'], $dest)) $dest = $_FILES['file']['tmp_name'];
                $result = $readFabrics($dest);

                if ($mode === 'import' && $result['fabrics']) {
                    // Skip exact dups already in the library for this manufacturer.
                    $existing = [];
                    $ex = $pdo->prepare('SELECT LOWER(name) AS n, LOWER(COALESCE(colour, "")) AS c FROM library_fabrics WHERE fabric_supplier_id = ?');
                    $ex->execute([$supId]);
                    foreach ($ex->fetchAll(PDO::FETCH_ASSOC) as $row) $existing[$row['n'] . '|' . $row['c']] = true;

                    $sortStart = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM library_fabrics WHERE fabric_supplier_id = ' . $supId)->fetchColumn();
                    $ins = $pdo->prepare(
                        'INSERT INTO library_fabrics
                            (fabric_supplier_id, name, colour, code, suggested_band, blind_type, sort_order)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $added = 0; $dups = 0;
                    foreach ($result['fabrics'] as $f) {
                        $key = strtolower($f['name']) . '|' . strtolower((string) ($f['colour'] ?? ''));
                        if (isset($existing[$key])) { $dups++; continue; }
                        $existing[$key] = true;
                        $ins->execute([$supId, $f['name'], $f['colour'], $f['code'], $f['suggested_band'], $f['blind_type'], $sortStart++]);
                        $added++;
                    }
                    $importSummary = ['added' => $added, 'dups' => $dups];
                }
            } catch (Throwable $e) {
                error_log('[YourBlinds] fabric-import failed: ' . $e->getMessage());
                $error = 'Could not read that file. Make sure it is a valid spreadsheet.';
            } finally {
                if (isset($dest) && is_file($dest) && strpos($dest, 'yb_fabric_') !== false) @unlink($dest);
            }
        }
    }
}

$activeNav = 'fabric-library';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fabric import &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Fabric import</h1>
                <p class="page-subtitle">
                    Load a manufacturer's fabric range from a spreadsheet into the Fabric Library.
                </p>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <a href="/master-admin/fabric-library.php" class="btn btn-secondary">&larr; Fabric Library</a>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (!$ready): ?>
            <section class="section">
                <div class="alert alert-error" role="alert">
                    Run <a href="/migrate_fabric_library.php"><code>/migrate_fabric_library.php</code></a> first.
                </div>
            </section>
        <?php elseif (!$suppliers): ?>
            <section class="section">
                <p style="margin:0">Add a manufacturer first on
                   <a href="/master-admin/fabric-library.php">Fabric Library</a>, then import its fabrics here.</p>
            </section>
        <?php else: ?>

        <section class="section">
            <form method="post" action="/master-admin/fabric-import.php" enctype="multipart/form-data" class="form">
                <?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Manufacturer</label>
                        <select name="fabric_supplier_id" class="form-control" style="max-width:22rem" required>
                            <option value="">— choose —</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $supId ? 'selected' : '' ?>><?= e((string) $s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Fabric list (spreadsheet)</label>
                        <input type="file" name="file" accept=".xlsx,.xlsm,.xls,.csv,.ods" required class="form-control" style="max-width:22rem">
                    </div>
                </div>
                <div class="form-actions" style="display:flex;gap:0.75rem;flex-wrap:wrap">
                    <button type="submit" name="mode" value="preview" class="btn btn-secondary">Preview only</button>
                    <button type="submit" name="mode" value="import" class="btn btn-primary">Import into library</button>
                </div>
            </form>
            <p style="color:var(--text-faint);font-size:0.8125rem;margin:0.75rem 0 0;max-width:48rem;line-height:1.5">
                It auto-detects the header row and maps columns by their wording —
                <strong>name / colour / code / band / type</strong>. <strong>Preview</strong> shows what it read
                (changes nothing); <strong>Import</strong> writes them in, skipping any that already exist for this
                manufacturer (matched on name + colour). Bands can be tweaked later, and are overridable per product.
            </p>
        </section>

        <?php if ($importSummary !== null): ?>
            <section class="section">
                <div class="alert alert-success" role="status">
                    Imported into <strong><?= e($supName) ?></strong>:
                    <strong><?= (int) $importSummary['added'] ?></strong> fabric<?= (int) $importSummary['added'] === 1 ? '' : 's' ?> added<?php
                    if ((int) $importSummary['dups'] > 0): ?>, <strong><?= (int) $importSummary['dups'] ?></strong> skipped (already in the library)<?php endif; ?>.
                </div>
                <a href="/master-admin/fabric-library.php" class="btn btn-secondary">View in Fabric Library</a>
            </section>
        <?php endif; ?>

        <?php if ($result !== null): ?>
            <section class="section">
                <?php if (!$result['fabrics']): ?>
                    <div class="alert alert-error" role="alert">
                        Couldn't find a fabric table in that file. It needs a header row with at least a
                        <strong>name</strong> column plus one of colour / code / band.
                        <?php if (($result['header_row'] ?? 0) === 0): ?>(No header row was recognised.)<?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success" style="margin-bottom:1rem">
                        Read <strong><?= count($result['fabrics']) ?></strong> fabric<?= count($result['fabrics']) === 1 ? '' : 's' ?>
                        <?php if ((int) $result['skipped'] > 0): ?>&middot; <?= (int) $result['skipped'] ?> rows skipped<?php endif; ?>
                        &middot; columns mapped:
                        <code><?= e(implode(', ', array_map(fn ($f, $c) => "$f→$c", array_keys($result['mapped']), array_values($result['mapped'])))) ?></code>
                        <?php if ($fileLabel !== ''): ?> &middot; <span style="color:var(--text-faint)"><?= e($fileLabel) ?></span><?php endif; ?>
                    </div>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Fabric</th><th>Colour</th><th>Code</th><th>Band</th><th>Type</th></tr></thead>
                            <tbody>
                                <?php foreach (array_slice($result['fabrics'], 0, 100) as $f): ?>
                                    <tr>
                                        <td><strong><?= e((string) $f['name']) ?></strong></td>
                                        <td><?= e((string) ($f['colour'] ?? '')) ?></td>
                                        <td><?= e((string) ($f['code'] ?? '')) ?></td>
                                        <td><?= e((string) ($f['suggested_band'] ?? '')) ?></td>
                                        <td><?= e((string) ($f['blind_type'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($result['fabrics']) > 100): ?>
                        <p style="color:var(--text-faint);font-size:0.8125rem;margin:0.5rem 0 0">Showing the first 100 of <?= count($result['fabrics']) ?>.</p>
                    <?php endif; ?>
                    <?php if ($importSummary === null): ?>
                        <p style="color:var(--text-faint);font-size:0.8125rem;margin:1rem 0 0">
                            Preview only — nothing imported. Happy with the mapping? Click <strong>Import into library</strong>.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php endif; /* ready + suppliers */ ?>
    </main>
</div>
</body>
</html>
