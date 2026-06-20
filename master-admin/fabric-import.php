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

$user = current_user();   // sidebar derives admin/super-admin from this
$pdo  = db();

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
    $ss = $reader->load($path);

    $fabrics = []; $sheets = []; $skipped = 0;

    // Scan EVERY sheet — pick the fabric-list ones (header has a name column +
    // colour or code) and skip price grids / chrome sheets automatically.
    foreach ($ss->getSheetNames() as $sn) {
        $ws = $ss->getSheetByName($sn);
        if ($ws === null) continue;
        $maxRow = min((int) $ws->getHighestDataRow(), 6000);
        $maxCol = min(Coordinate::columnIndexFromString($ws->getHighestDataColumn()), 30);

        $headerRow = 0; $mapped = [];
        for ($r = 1; $r <= min($maxRow, 15); $r++) {
            $cand = [];
            for ($c = 1; $c <= $maxCol; $c++) {
                $field = $matchField((string) $ws->getCell([$c, $r])->getValue());
                if ($field !== null && !isset($cand[$field])) $cand[$field] = $c;
            }
            if (isset($cand['name']) && (isset($cand['colour']) || isset($cand['code']))) {
                $headerRow = $r; $mapped = $cand; break;
            }
        }
        if ($headerRow === 0) continue;   // not a fabric sheet

        // Blind type from the sheet name (e.g. "FB Roller Fabric" → "FB Roller").
        $sheetType = trim((string) preg_replace('/\bfabrics?\b/i', '', $sn));
        if ($sheetType === '') $sheetType = $sn;
        $hasTypeCol = isset($mapped['blind_type']);

        // Decora-style grouped layout: the fabric NAME + BAND appear once on the
        // first row of a fabric; rows below are more COLOURS with name/band
        // blank. Carry name + band down until the next named fabric.
        $lastName = ''; $lastBand = ''; $count = 0;
        for ($r = $headerRow + 1; $r <= $maxRow; $r++) {
            $cell = static function (string $field) use ($ws, $mapped, $r): string {
                return isset($mapped[$field]) ? trim((string) $ws->getCell([$mapped[$field], $r])->getValue()) : '';
            };
            $nm = $cell('name'); $col = $cell('colour'); $cd = $cell('code');
            $bd = $cell('suggested_band'); $ty = $cell('blind_type');

            if ($nm !== '') {
                if (in_array(strtolower($nm), ['name', 'fabric'], true)) { $skipped++; continue; }  // repeated header
                $lastName = $nm; $lastBand = $bd;   // a new fabric resets the carried band
            }
            $name = $lastName;
            $band = $bd !== '' ? $bd : $lastBand;

            if ($name === '') { if ($col !== '' || $cd !== '') $skipped++; continue; }  // colour before any named fabric
            if ($col === '' && $cd === '' && $nm === '') continue;                       // blank row

            $fabrics[] = [
                'name'           => mb_substr($name, 0, 160),
                'colour'         => $col !== '' ? mb_substr($col, 0, 120) : null,
                'code'           => $cd  !== '' ? mb_substr($cd, 0, 80)   : null,
                'suggested_band' => $band !== '' ? strtoupper(mb_substr($band, 0, 20)) : null,
                'blind_type'     => ($hasTypeCol && $ty !== '') ? mb_substr($ty, 0, 60) : mb_substr($sheetType, 0, 60),
            ];
            $count++;
        }
        $sheets[] = [
            'name'   => $sn,
            'count'  => $count,
            'mapped' => array_map(fn ($c) => Coordinate::stringFromColumnIndex($c), $mapped),
        ];
    }
    return ['fabrics' => $fabrics, 'sheets' => $sheets, 'skipped' => $skipped];
};

/** Write parsed fabrics into the library for a manufacturer, skipping dups by name+colour. */
$doImport = static function (int $supId, array $fabrics) use ($pdo): array {
    // A big range (thousands of rows) must be fast AND all-or-nothing on a
    // 30s execution cap: per-row autocommit fsyncs on every INSERT (slow),
    // and a timeout mid-loop would leave a half-finished import that muddies
    // the dup count on a retry. One transaction fixes both — far faster and
    // atomic. Raising the limits here covers BOTH call sites (direct import
    // and the post-preview "Import these N" button, which had no raise).
    @set_time_limit(300);
    @ini_set('memory_limit', '1024M');

    $existing = [];
    $ex = $pdo->prepare('SELECT LOWER(name) AS n, LOWER(COALESCE(colour, "")) AS c FROM library_fabrics WHERE fabric_supplier_id = ?');
    $ex->execute([$supId]);
    foreach ($ex->fetchAll(PDO::FETCH_ASSOC) as $row) $existing[$row['n'] . '|' . $row['c']] = true;

    $sortStart = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM library_fabrics WHERE fabric_supplier_id = ' . (int) $supId)->fetchColumn();
    $ins = $pdo->prepare(
        'INSERT INTO library_fabrics
            (fabric_supplier_id, name, colour, code, suggested_band, blind_type, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $added = 0; $dups = 0;
    $pdo->beginTransaction();
    try {
        foreach ($fabrics as $f) {
            $key = strtolower((string) $f['name']) . '|' . strtolower((string) ($f['colour'] ?? ''));
            if (isset($existing[$key])) { $dups++; continue; }
            $existing[$key] = true;
            $ins->execute([$supId, $f['name'], $f['colour'] ?? null, $f['code'] ?? null, $f['suggested_band'] ?? null, $f['blind_type'] ?? null, $sortStart++]);
            $added++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;   // surfaced to the caller, which keeps the preview for retry
    }
    return ['added' => $added, 'dups' => $dups];
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

    if ($mode === 'import_session') {
        // Import the JUST-PREVIEWED data — no need to re-select the file (the
        // browser can't keep it in the upload box across a reload).
        $pv = $_SESSION['fab_preview'] ?? null;
        if (!$pv || (int) ($pv['sid'] ?? 0) !== $supId || $supId <= 0) {
            $error = 'That preview has expired — please choose the file and preview again.';
        } else {
            $result    = $pv['data'];
            $supName   = (string) $pv['sname'];
            $fileLabel = (string) $pv['file'];
            if (!empty($result['fabrics'])) {
                try {
                    $importSummary = $doImport($supId, $result['fabrics']);
                    unset($_SESSION['fab_preview']);   // clear only once it's safely in
                } catch (Throwable $e) {
                    error_log('[YourBlinds] fabric-import (session) failed: ' . $e->getMessage());
                    // Atomic rollback means nothing was saved. Keep the preview
                    // so the user can just click Import again — no re-upload.
                    $error = 'The import did not finish, so nothing was saved. Please click Import again.';
                }
            } else {
                unset($_SESSION['fab_preview']);
            }
        }
    } elseif (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
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

                if ($mode === 'import' && !empty($result['fabrics'])) {
                    $importSummary = $doImport($supId, $result['fabrics']);
                    unset($_SESSION['fab_preview']);
                } elseif ($mode === 'preview') {
                    // Stash so the user can import without re-uploading.
                    $_SESSION['fab_preview'] = ['sid' => $supId, 'sname' => $supName, 'file' => $fileLabel, 'data' => $result];
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
                        Couldn't find a fabric list in that file. A fabric sheet needs a header row
                        with a <strong>name</strong> column plus a <strong>colour</strong> or
                        <strong>code</strong> column. (Price-grid sheets are skipped automatically.)
                    </div>
                <?php else: ?>
                    <div class="alert alert-success" style="margin-bottom:1rem">
                        Read <strong><?= count($result['fabrics']) ?></strong> fabric<?= count($result['fabrics']) === 1 ? '' : 's' ?>
                        from <strong><?= count($result['sheets']) ?></strong> sheet<?= count($result['sheets']) === 1 ? '' : 's' ?>
                        <?php if ((int) $result['skipped'] > 0): ?>&middot; <?= (int) $result['skipped'] ?> rows skipped<?php endif; ?>
                        <?php if ($fileLabel !== ''): ?> &middot; <span style="color:var(--text-faint)"><?= e($fileLabel) ?></span><?php endif; ?>
                        <div style="margin-top:.4rem;font-size:.8125rem;color:var(--text-muted)">
                            <?php foreach ($result['sheets'] as $sh): ?>
                                <span style="display:inline-block;margin-right:1.25rem">
                                    <strong><?= e((string) $sh['name']) ?></strong>: <?= (int) $sh['count'] ?>
                                    <span style="color:var(--text-faint)">[<?= e(implode(', ', array_map(fn ($f, $c) => "$f→$c", array_keys($sh['mapped']), array_values($sh['mapped'])))) ?>]</span>
                                </span>
                            <?php endforeach; ?>
                        </div>
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
                        <form method="post" action="/master-admin/fabric-import.php" style="margin:1rem 0 0;display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap">
                            <?= csrf_field() ?>
                            <input type="hidden" name="mode" value="import_session">
                            <input type="hidden" name="fabric_supplier_id" value="<?= $supId ?>">
                            <button type="submit" class="btn btn-primary">
                                Import these <?= count($result['fabrics']) ?> fabrics into <?= e($supName) ?>
                            </button>
                            <span style="color:var(--text-faint);font-size:0.8125rem">No need to choose the file again.</span>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php endif; /* ready + suppliers */ ?>
    </main>
</div>
</body>
</html>
