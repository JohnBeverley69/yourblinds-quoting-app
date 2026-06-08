<?php
declare(strict_types=1);

/**
 * Width-only price importer.
 *
 * For products flagged width_only = 1 (headrail / track), the price is a
 * single width → price list per system, not a width × drop grid. This
 * importer reads a spreadsheet laid out as repeated system blocks:
 *
 *   Slim Line
 *   Metric |     | 0.8 | 1.2 | 1.6 | …      ← widths in metres
 *          | Ins | …                         ← imperial row (ignored)
 *          |     | 7.94 | 9.21 | …           ← prices (per width)
 *   (blank)
 *   Nova
 *   …
 *
 * Each block's system name is matched to one of THIS product's systems
 * (case/space-insensitive, so "Slim Line" matches "Slimline"). Matched
 * blocks become a price table (band ''), with one row per width stored at
 * drop_mm 0 — which is exactly what the width-only engine lookup expects.
 *
 * Two stages: upload → preview (shows match status) → confirm import.
 * Re-importing replaces a system's existing rows, so it's safe to redo.
 *
 * Admin-gated, tenant-scoped.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

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
$redirect = '/admin/products/price-tables.php?product_id=' . $productId;

// Product systems → normalised-name lookup.
$sysStmt = $pdo->prepare(
    'SELECT id, name FROM product_systems
      WHERE product_id = ? AND client_id = ? AND active = 1
      ORDER BY sort_order, name'
);
$sysStmt->execute([$productId, $clientId]);
$systems = $sysStmt->fetchAll();

$norm = static fn (string $s): string =>
    (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($s)));
$sysByNorm = [];
foreach ($systems as $s) {
    $sysByNorm[$norm((string) $s['name'])] = $s;
}

// Parse the uploaded sheet into width→price blocks.
//
// Layout (per system block): a system-name row, then a row of widths (in
// metres), an "Ins" row (imperial — ignored), then a row of prices, then a
// blank.
//
// IMPORTANT: this is written to be INDEX-AGNOSTIC. PhpSpreadsheet's
// toArray() column keys can start at 0 or 1 depending on version/host, and a
// sheet may have leading blank columns — so we must never assume a fixed
// "data starts at column N". Instead:
//   - a row's numeric cells are collected keyed by their actual column index;
//   - the imperial row is detected by an "Ins" label cell ANYWHERE in it;
//   - a system-name row is one with NO numeric cells but a text cell that
//     isn't "Metric"/"Ins";
//   - widths and prices are paired by SHARED column index, so they line up
//     no matter where the first data column sits.
$parseSheet = static function (string $path): array {
    require_once __DIR__ . '/../../vendor/autoload.php';
    $ss    = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    $sheet = $ss->getActiveSheet();
    $rows  = array_values($sheet->toArray(null, true, false, false));
    $n     = count($rows);

    // Numeric cells of a row, keyed by their column index.
    $numericCells = static function (array $row): array {
        $out = [];
        foreach ($row as $idx => $val) {
            if (is_numeric($val)) $out[$idx] = (float) $val;
        }
        return $out;
    };
    // Non-empty text cells of a row, lower-cased.
    $textCells = static function (array $row): array {
        $out = [];
        foreach ($row as $val) {
            if (is_string($val)) {
                $t = trim($val);
                if ($t !== '') $out[] = mb_strtolower($t);
            }
        }
        return $out;
    };
    $blocks = [];
    $i = 0;
    while ($i < $n) {
        $nums = $numericCells($rows[$i]);
        $txts = array_values(array_filter(
            $textCells($rows[$i]),
            static fn ($t) => $t !== 'metric' && $t !== 'ins'
        ));
        // A system-name row: no numbers, has a non-label text cell.
        if ($nums || !$txts) { $i++; continue; }

        // Recover the original (non-lowercased) name from the row.
        $systemName = '';
        foreach ($rows[$i] as $val) {
            if (is_string($val)) {
                $t = trim($val);
                $lt = mb_strtolower($t);
                if ($t !== '' && $lt !== 'metric' && $lt !== 'ins') { $systemName = $t; break; }
            }
        }

        // Collect ALL numeric rows in this block (stop at the next name
        // row / EOF). A block is laid out widths → [inches] → prices, and
        // the imperial row often has NO label to skip on — so we don't try
        // to identify it. Instead: WIDTHS = the first number row, PRICES =
        // the LAST number row. Any middle row (inches) is ignored. This is
        // robust whether or not the sheet carries "Metric"/"Ins" labels.
        $dataRows = [];
        $j = $i + 1;
        for (; $j < $n; $j++) {
            $rnums = $numericCells($rows[$j]);
            if (!$rnums) {
                // Blank row → skip; a text-only (name) row → stop the block.
                $rtxt = array_values(array_filter(
                    $textCells($rows[$j]),
                    static fn ($t) => $t !== 'metric' && $t !== 'ins'
                ));
                if ($rtxt) break;
                continue;
            }
            $dataRows[] = $rnums;
        }

        if (count($dataRows) >= 2 && $systemName !== '') {
            $widthsByCol = $dataRows[0];                       // first number row
            $pricesByCol = $dataRows[count($dataRows) - 1];   // last number row
            $pairs = [];
            foreach ($widthsByCol as $idx => $wv) {
                if (!isset($pricesByCol[$idx])) continue;   // pair by shared column
                // Widths are in metres; convert to mm. (A value already
                // >= 100 is treated as mm, just in case.)
                $mm    = $wv < 100 ? (int) round($wv * 1000) : (int) round($wv);
                $price = round($pricesByCol[$idx], 2);
                if ($mm > 0) $pairs[$mm] = $price;   // dedupe by width
            }
            if ($pairs) {
                ksort($pairs);
                $blocks[] = ['system_name' => $systemName, 'pairs' => $pairs];
            }
        }
        $i = $j;   // continue after the rows we consumed
    }
    return $blocks;
};

$error    = null;
$preview  = null;   // parsed blocks for the confirm step
$stage    = 'upload';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? 'upload');

    // ── Stage: parse the uploaded file → preview ──────────────────────
    if ($action === 'upload') {
        if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'Please choose a file to upload.';
        } elseif (filesize($_FILES['file']['tmp_name']) > 10 * 1024 * 1024) {
            $error = 'File too large (10 MB max).';
        } else {
            try {
                $blocks = $parseSheet($_FILES['file']['tmp_name']);
                if (!$blocks) {
                    $error = 'No width price lists detected. Expected system blocks with a '
                           . '"Metric" widths row and a prices row.';
                } else {
                    foreach ($blocks as &$b) {
                        $match = $sysByNorm[$norm($b['system_name'])] ?? null;
                        $b['system_id']         = $match ? (int) $match['id'] : null;
                        $b['matched_name']      = $match ? (string) $match['name'] : null;
                    }
                    unset($b);
                    $preview = $blocks;
                    $stage   = 'preview';
                }
            } catch (Throwable $e) {
                $error = 'Could not read the spreadsheet: ' . $e->getMessage();
            }
        }
    }

    // ── Stage: commit the import ──────────────────────────────────────
    if ($action === 'import') {
        $payload = json_decode((string) ($_POST['payload'] ?? ''), true);
        if (!is_array($payload) || !$payload) {
            $error = 'Nothing to import — please upload the file again.';
        } else {
            $importedSystems = 0;
            $importedRows    = 0;
            $skippedBlocks   = 0;

            $pdo->beginTransaction();
            try {
                foreach ($payload as $b) {
                    $systemId = (int) ($b['system_id'] ?? 0);
                    $pairs    = is_array($b['pairs'] ?? null) ? $b['pairs'] : [];
                    if ($systemId <= 0 || !$pairs) { $skippedBlocks++; continue; }

                    // Re-verify the system belongs to this product + tenant.
                    $chk = $pdo->prepare(
                        'SELECT 1 FROM product_systems
                          WHERE id = ? AND product_id = ? AND client_id = ? AND active = 1'
                    );
                    $chk->execute([$systemId, $productId, $clientId]);
                    if (!$chk->fetchColumn()) { $skippedBlocks++; continue; }

                    // Find or create the (band-less) price table for this system.
                    $find = $pdo->prepare(
                        "SELECT id FROM price_tables
                          WHERE client_id = ? AND product_id = ? AND system_id = ? AND band_code = ''"
                    );
                    $find->execute([$clientId, $productId, $systemId]);
                    $tableId = (int) ($find->fetchColumn() ?: 0);
                    if ($tableId === 0) {
                        $ins = $pdo->prepare(
                            "INSERT INTO price_tables
                               (client_id, product_id, system_id, band_code, name, active)
                             VALUES (?, ?, ?, '', ?, 1)"
                        );
                        $ins->execute([$clientId, $productId, $systemId, 'Width prices ' . date('Y-m-d')]);
                        $tableId = (int) $pdo->lastInsertId();
                    }

                    // Replace rows (width-only: drop_mm 0).
                    $pdo->prepare('DELETE FROM price_table_rows WHERE price_table_id = ?')
                        ->execute([$tableId]);
                    $rowIns = $pdo->prepare(
                        'INSERT INTO price_table_rows (price_table_id, width_mm, drop_mm, price)
                         VALUES (?, ?, 0, ?)'
                    );
                    foreach ($pairs as $mm => $price) {
                        $mm = (int) $mm;
                        if ($mm <= 0) continue;
                        $rowIns->execute([$tableId, $mm, round((float) $price, 2)]);
                        $importedRows++;
                    }
                    $importedSystems++;
                }

                $pdo->commit();

                require_once __DIR__ . '/../../_partials/catalogue_audit.php';
                catalogue_audit_log(
                    'price_table', null, 'import', null, null,
                    ['systems' => $importedSystems, 'rows' => $importedRows],
                    $productId, ['action' => 'width_only_import']
                );

                $msg = "Imported width prices for $importedSystems system"
                     . ($importedSystems === 1 ? '' : 's')
                     . " ($importedRows price" . ($importedRows === 1 ? '' : 's') . ').';
                if ($skippedBlocks > 0) {
                    $msg .= " Skipped $skippedBlocks unmatched block"
                          . ($skippedBlocks === 1 ? '' : 's') . '.';
                }
                $_SESSION['flash_success'] = $msg;
                header('Location: ' . $redirect);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('width-import failed (client ' . $clientId . ', product '
                    . $productId . '): ' . $e->getMessage());
                $error = 'Could not import — please try again.';
            }
        }
    }
}

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Width price import &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
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
                        [(string) $product['name'],  '/admin/products/edit.php?id=' . $productId],
                        ['Price tables',             $redirect],
                        ['Width price import',       null],
                    ]);
                ?>
                <h1 class="page-title">
                    Width price import &mdash; <?= e((string) $product['name']) ?>
                </h1>
                <p class="page-subtitle"><a href="<?= e($redirect) ?>">&larr; Back to price tables</a></p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($stage === 'preview' && $preview): ?>
            <section class="section">
                <div class="section-header"><h2 class="section-title">Preview</h2></div>
                <p style="color:var(--text-muted);margin:0 0 1rem">
                    Each block below was matched to one of this product's systems by name.
                    Confirm to import — matched systems get a width &rarr; price list
                    (existing rows are replaced).
                </p>

                <?php foreach ($preview as $b):
                    $matched = !empty($b['system_id']);
                ?>
                    <div style="border:1px solid <?= $matched ? 'var(--border)' : 'var(--alert-error-border, #fca5a5)' ?>;
                                border-radius:8px;padding:0.75rem 1rem;margin-bottom:0.75rem">
                        <div style="display:flex;justify-content:space-between;align-items:baseline;gap:1rem;flex-wrap:wrap">
                            <strong><?= e((string) $b['system_name']) ?></strong>
                            <?php if ($matched): ?>
                                <span style="color:#16a34a;font-size:0.875rem">
                                    &check; matches system "<?= e((string) $b['matched_name']) ?>"
                                    &middot; <?= count($b['pairs']) ?> widths
                                </span>
                            <?php else: ?>
                                <span style="color:#b91c1c;font-size:0.875rem">
                                    no matching system on this product — will be skipped
                                </span>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top:0.5rem;color:var(--text-secondary);font-size:0.8125rem;line-height:1.6">
                            <?php
                                $bits = [];
                                foreach ($b['pairs'] as $mm => $price) {
                                    $bits[] = $mm . 'mm = £' . number_format((float) $price, 2);
                                }
                                echo e(implode('  ·  ', $bits));
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php
                    // Only carry matched blocks forward.
                    $toImport = array_values(array_filter($preview, static fn ($b) => !empty($b['system_id'])));
                ?>
                <form method="post" style="margin-top:1rem">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="import">
                    <input type="hidden" name="product_id" value="<?= $productId ?>">
                    <input type="hidden" name="payload"
                           value="<?= e(json_encode($toImport, JSON_UNESCAPED_UNICODE)) ?>">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"
                                <?= $toImport ? '' : 'disabled' ?>>
                            Import <?= count($toImport) ?> system<?= count($toImport) === 1 ? '' : 's' ?> &rarr;
                        </button>
                        <a href="?product_id=<?= $productId ?>" class="btn btn-secondary">Start over</a>
                    </div>
                </form>
            </section>
        <?php else: ?>
            <section class="section" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:0.875rem 1.125rem;margin-bottom:1rem">
                <p style="margin:0;color:#0c4a6e;font-size:0.9375rem;line-height:1.5">
                    Upload a spreadsheet with one block per system &mdash; a system name on
                    its own line, a <strong>Metric</strong> row of widths (in metres), and a
                    row of prices beneath. Each block is matched to this product's systems by
                    name (spacing/case ignored, so "Slim Line" matches "Slimline").
                </p>
            </section>

            <?php if ($systems): ?>
                <section class="section">
                    <div class="section-header"><h2 class="section-title">This product's systems</h2></div>
                    <p style="color:var(--text-muted);margin:0">
                        <?php
                            echo e(implode(', ', array_map(static fn ($s) => (string) $s['name'], $systems)));
                        ?>
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
                        <label for="file">Spreadsheet (.xlsx / .xls / .csv)</label>
                        <input id="file" name="file" type="file"
                               accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="form-actions" style="margin-top:1rem">
                        <button type="submit" class="btn btn-primary">Upload &amp; preview &rarr;</button>
                        <a href="<?= e($redirect) ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
