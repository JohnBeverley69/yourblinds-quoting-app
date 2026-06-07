<?php
declare(strict_types=1);

/**
 * Per-slat RATE importer (products.price_per_slat = 1).
 *
 * For products like "Vertical Fabric Only", the price list is a drop ->
 * price-per-slat grid per (system, band): rows = drops (metres), columns =
 * bands (Band AAA … Band E), and the cell is the price per slat at that drop.
 * The sheet usually carries TWO sub-tables — "With Chains" and "Chainless" —
 * which map to the product's two systems.
 *
 * This reads such a workbook, matches each sub-table to one of the product's
 * systems (chains/chainless), and writes a drop -> price-per-slat price table
 * per (system, band) — the drop in drop_mm, width_mm 0, the per-slat price in
 * the price column, which is exactly what the price_per_slat engine path
 * (pe_find_matrix_row_by_drop) expects.
 *
 * Workbooks here have several sheets (Summary / Costs / Base / final), so
 * the upload step parses every sheet and lets the user pick which one to
 * import.
 *
 * Admin-gated, tenant-scoped.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';
require __DIR__ . '/../../_partials/units.php';

requireAdmin();

$user     = current_user();
$clientId = (int) $user['client_id'];
$pdo      = db();

$productId = (int) ($_GET['product_id'] ?? $_POST['product_id'] ?? 0);

$pStmt = $pdo->prepare('SELECT id, name FROM products WHERE id = ? AND client_id = ?');
$pStmt->execute([$productId, $clientId]);
$product = $pStmt->fetch();
if (!$product) {
    header('Location: /admin/products/index.php');
    exit;
}
$redirect = '/admin/products/edit.php?id=' . $productId;

// Product systems → for matching sub-tables.
$sysStmt = $pdo->prepare(
    'SELECT id, name FROM product_systems
      WHERE product_id = ? AND client_id = ? AND active = 1 ORDER BY sort_order, name'
);
$sysStmt->execute([$productId, $clientId]);
$systems = $sysStmt->fetchAll();

$norm = static fn (string $s): string =>
    (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($s)));

// Match a "chains" / "chainless" hint to one of the product's systems.
$matchSystem = static function (string $hint) use ($systems, $norm): ?array {
    $wantChainless = (mb_stripos($hint, 'chainless') !== false);
    foreach ($systems as $s) {
        $n = mb_strtolower((string) $s['name']);
        $isChainless = (mb_stripos($n, 'chainless') !== false);
        if ($wantChainless && $isChainless) return $s;
        if (!$wantChainless && !$isChainless && mb_stripos($n, 'chain') !== false) return $s;
    }
    // Fall back to a looser normalised contains.
    $key = $wantChainless ? 'chainless' : 'chain';
    foreach ($systems as $s) {
        if (mb_stripos($norm((string) $s['name']), $key) !== false) return $s;
    }
    return null;
};

// ── Parse one sheet's rows into [ ['system_hint'=>.., 'bands'=>[band=>[mm=>rate]]], … ] ──
$parseSheetRows = static function (array $rows): array {
    $rows = array_values($rows);
    $n = count($rows);
    $rowText = static function (array $row): string {
        $bits = [];
        foreach ($row as $v) { if (is_string($v)) $bits[] = $v; }
        return mb_strtolower(implode(' ', $bits));
    };
    $tables = [];
    $i = 0;
    while ($i < $n) {
        $txt = $rowText($rows[$i]);
        // A sub-table starts at a "…chains…" / "…chainless…" title row.
        if (mb_stripos($txt, 'chain') === false) { $i++; continue; }
        $hint = (mb_stripos($txt, 'chainless') !== false) ? 'Chainless' : 'Chained';

        // Find the band-header row (cells like "Band AAA", "BandC").
        $bandCols = [];
        $j = $i + 1;
        for (; $j < min($n, $i + 6); $j++) {
            $cand = [];
            foreach ($rows[$j] as $idx => $v) {
                if (is_string($v) && preg_match('/^\s*band\s*([a-z]+)\s*$/i', trim($v), $m)) {
                    $cand[$idx] = strtoupper($m[1]);
                }
            }
            if ($cand) { $bandCols = $cand; $j++; break; }
        }
        if (!$bandCols) { $i = $j; continue; }

        // Read width rows (col 0 numeric) until the next title / EOF.
        $bands = [];
        $k = $j;
        for (; $k < $n; $k++) {
            if (mb_stripos($rowText($rows[$k]), 'chain') !== false) break;  // next sub-table
            $w = $rows[$k][0] ?? null;
            if (!is_numeric($w)) continue;            // imperial / label / blank row
            $wv = (float) $w;
            $mm = $wv < 100 ? (int) round($wv * 1000) : (int) round($wv);
            if ($mm <= 0) continue;
            foreach ($bandCols as $idx => $band) {
                $val = $rows[$k][$idx] ?? null;
                if (is_numeric($val) && (float) $val > 0) {
                    $bands[$band][$mm] = round((float) $val, 4);
                }
            }
        }
        if ($bands) $tables[] = ['system_hint' => $hint, 'bands' => $bands];
        $i = $k;
    }
    return $tables;
};

$error    = null;
$sheets   = null;   // [ ['name'=>.., 'tables'=>..], … ] for the pick step
$stage    = 'upload';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = (string) ($_POST['action'] ?? 'upload');

    if ($act === 'upload') {
        if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'Please choose a file to upload.';
        } elseif (filesize($_FILES['file']['tmp_name']) > 10 * 1024 * 1024) {
            $error = 'File too large (10 MB max).';
        } else {
            try {
                require __DIR__ . '/../../vendor/autoload.php';
                $ss = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['file']['tmp_name']);
                $sheets = [];
                foreach ($ss->getAllSheets() as $sheet) {
                    $rows = $sheet->toArray(null, true, false, false);
                    $tables = $parseSheetRows($rows);
                    if ($tables) {
                        // Attach system match per sub-table.
                        foreach ($tables as &$t) {
                            $m = $matchSystem($t['system_hint']);
                            $t['system_id']   = $m ? (int) $m['id'] : null;
                            $t['system_name'] = $m ? (string) $m['name'] : null;
                        }
                        unset($t);
                        $sheets[] = ['name' => $sheet->getTitle(), 'tables' => $tables];
                    }
                }
                if (!$sheets) {
                    $error = 'No rate tables found. Expected sheets with a "…Chains…" title, '
                           . 'a "Band AAA … Band E" header row, and width rows beneath.';
                } else {
                    $stage = 'pick';
                }
            } catch (Throwable $e) {
                $error = 'Could not read the spreadsheet: ' . $e->getMessage();
            }
        }
    }

    if ($act === 'import') {
        $payload = json_decode((string) ($_POST['payload'] ?? ''), true);
        if (!is_array($payload) || !$payload) {
            $error = 'Nothing to import — upload the file again.';
        } else {
            $tablesMade = 0; $rowsMade = 0; $skipped = 0;
            $pdo->beginTransaction();
            try {
                foreach ($payload as $t) {
                    $systemId = (int) ($t['system_id'] ?? 0);
                    $bands    = is_array($t['bands'] ?? null) ? $t['bands'] : [];
                    if ($systemId <= 0 || !$bands) { $skipped++; continue; }
                    // Re-verify system belongs to product/tenant.
                    $chk = $pdo->prepare(
                        'SELECT 1 FROM product_systems
                          WHERE id = ? AND product_id = ? AND client_id = ? AND active = 1'
                    );
                    $chk->execute([$systemId, $productId, $clientId]);
                    if (!$chk->fetchColumn()) { $skipped++; continue; }

                    foreach ($bands as $band => $widthRates) {
                        $band = (string) $band;
                        if (!is_array($widthRates) || !$widthRates) continue;
                        // Find/create the (system, band) price table.
                        $find = $pdo->prepare(
                            'SELECT id FROM price_tables
                              WHERE client_id = ? AND product_id = ? AND system_id = ? AND band_code = ?'
                        );
                        $find->execute([$clientId, $productId, $systemId, $band]);
                        $tableId = (int) ($find->fetchColumn() ?: 0);
                        if ($tableId === 0) {
                            $ins = $pdo->prepare(
                                'INSERT INTO price_tables
                                   (client_id, product_id, system_id, band_code, name, active)
                                 VALUES (?, ?, ?, ?, ?, 1)'
                            );
                            $ins->execute([$clientId, $productId, $systemId, $band, 'Rates ' . date('Y-m-d')]);
                            $tableId = (int) $pdo->lastInsertId();
                        }
                        // Replace rows. Per-slat tables are keyed by DROP, so
                        // the axis value goes in drop_mm (width_mm 0); price is
                        // the per-slat rate. Matches pe_find_matrix_row_by_drop.
                        $pdo->prepare('DELETE FROM price_table_rows WHERE price_table_id = ?')->execute([$tableId]);
                        $rowIns = $pdo->prepare(
                            'INSERT INTO price_table_rows (price_table_id, width_mm, drop_mm, price)
                             VALUES (?, 0, ?, ?)'
                        );
                        foreach ($widthRates as $mm => $rate) {
                            $mm = (int) $mm;
                            if ($mm <= 0) continue;
                            $rowIns->execute([$tableId, $mm, round((float) $rate, 4)]);
                            $rowsMade++;
                        }
                        $tablesMade++;
                    }
                }
                $pdo->commit();

                require_once __DIR__ . '/../../_partials/catalogue_audit.php';
                catalogue_audit_log('price_table', null, 'import', null, null,
                    ['tables' => $tablesMade, 'rows' => $rowsMade],
                    $productId, ['action' => 'rate_import']);

                $msg = "Imported $tablesMade rate table" . ($tablesMade === 1 ? '' : 's')
                     . " ($rowsMade rate" . ($rowsMade === 1 ? '' : 's') . ').';
                if ($skipped > 0) $msg .= " Skipped $skipped unmatched sub-table(s).";
                $_SESSION['flash_success'] = $msg;
                header('Location: ' . $redirect);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('rate-import failed (client ' . $clientId . ', product '
                    . $productId . '): ' . $e->getMessage());
                $error = 'Could not import — please try again.';
            }
        }
    }
}

// Best-match sheet name for default selection (contains both "vertical"/"fabric").
$bestIdx = 0;
if ($sheets) {
    foreach ($sheets as $idx => $sh) {
        $ln = mb_strtolower($sh['name']);
        if (mb_stripos($ln, 'cost') !== false || mb_stripos($ln, 'base') !== false
            || mb_stripos($ln, 'sum') !== false) continue;
        $bestIdx = $idx; break;
    }
}

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rate import &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../../_partials/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <?php
                    require_once __DIR__ . '/../../_partials/breadcrumb.php';
                    echo render_breadcrumb([
                        ['Products',                 '/admin/products/index.php'],
                        [(string) $product['name'],  $redirect],
                        ['Rate import',              null],
                    ]);
                ?>
                <h1 class="page-title">Rate import &mdash; <?= e((string) $product['name']) ?></h1>
                <p class="page-subtitle"><a href="<?= e($redirect) ?>">&larr; Back to product</a></p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($stage === 'pick' && $sheets): ?>
            <section class="section">
                <div class="section-header"><h2 class="section-title">Choose the price sheet to import</h2></div>
                <p style="color:var(--text-muted);margin:0 0 1rem">
                    This workbook has more than one usable sheet (e.g. cost / base /
                    final). Pick the one with your <strong>final trade rates</strong>.
                    Each cell is the price per slat at that drop; the line price will be
                    that rate &times; the number of slats.
                </p>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="import">
                    <input type="hidden" name="product_id" value="<?= $productId ?>">
                    <?php foreach ($sheets as $idx => $sh):
                        // Only import matched sub-tables.
                        $matched = array_values(array_filter($sh['tables'], static fn ($t) => !empty($t['system_id'])));
                    ?>
                        <label style="display:block;border:1px solid var(--border);border-radius:8px;
                                      padding:0.75rem 1rem;margin-bottom:0.625rem;cursor:pointer">
                            <div style="display:flex;align-items:center;gap:0.625rem">
                                <input type="radio" name="sheet_idx" value="<?= (int) $idx ?>"
                                       <?= $idx === $bestIdx ? 'checked' : '' ?>
                                       data-payload='<?= e(json_encode($matched, JSON_UNESCAPED_UNICODE)) ?>'>
                                <strong><?= e($sh['name']) ?></strong>
                            </div>
                            <div style="margin-top:0.5rem;color:var(--text-secondary);font-size:0.8125rem;line-height:1.6">
                                <?php foreach ($sh['tables'] as $t):
                                    $bandList = implode('/', array_keys($t['bands']));
                                    $anyBand  = $t['bands'][array_key_first($t['bands'])] ?? [];
                                    $wCount   = $anyBand ? count($anyBand) : 0;
                                ?>
                                    <div>
                                        <?= e($t['system_hint']) ?>
                                        <?php if (!empty($t['system_id'])): ?>
                                            &rarr; <span style="color:#16a34a">system "<?= e((string) $t['system_name']) ?>"</span>
                                        <?php else: ?>
                                            &rarr; <span style="color:#b91c1c">no matching system — skipped</span>
                                        <?php endif; ?>
                                        &middot; bands <?= e($bandList) ?> &middot; <?= (int) $wCount ?> drops
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </label>
                    <?php endforeach; ?>

                    <input type="hidden" name="payload" id="rate-payload" value="">
                    <div class="form-actions" style="margin-top:1rem">
                        <button type="submit" class="btn btn-primary" id="rate-import-btn">Import selected sheet &rarr;</button>
                        <a href="?product_id=<?= $productId ?>" class="btn btn-secondary">Start over</a>
                    </div>
                </form>
                <script>
                (function () {
                    var form = document.getElementById('rate-payload').form;
                    function sync() {
                        var sel = form.querySelector('input[name="sheet_idx"]:checked');
                        document.getElementById('rate-payload').value = sel ? sel.getAttribute('data-payload') : '';
                    }
                    form.addEventListener('change', sync);
                    form.addEventListener('submit', sync);
                    sync();
                })();
                </script>
            </section>
        <?php else: ?>
            <section class="section" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:0.875rem 1.125rem;margin-bottom:1rem">
                <p style="margin:0;color:#0c4a6e;font-size:0.9375rem;line-height:1.5">
                    Upload a per-slat rate workbook. Each sub-table
                    ("With Chains" / "Chainless") has a <strong>Band AAA … Band E</strong>
                    header and drop rows; the cells are the price per slat at that drop.
                    We'll match each sub-table to this product's systems and write a
                    drop &rarr; price-per-slat table per band.
                </p>
            </section>
            <?php if ($systems): ?>
                <section class="section">
                    <div class="section-header"><h2 class="section-title">This product's systems</h2></div>
                    <p style="color:var(--text-muted);margin:0">
                        <?= e(implode(', ', array_map(static fn ($s) => (string) $s['name'], $systems))) ?>
                    </p>
                </section>
            <?php endif; ?>
            <section class="section">
                <div class="section-header"><h2 class="section-title">Upload</h2></div>
                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="product_id" value="<?= $productId ?>">
                    <div class="form-group">
                        <label for="file">Spreadsheet (.xlsx / .xls)</label>
                        <input id="file" name="file" type="file" accept=".xlsx,.xls" required>
                    </div>
                    <div class="form-actions" style="margin-top:1rem">
                        <button type="submit" class="btn btn-primary">Upload &amp; choose sheet &rarr;</button>
                        <a href="<?= e($redirect) ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
