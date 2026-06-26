<?php
declare(strict_types=1);

/**
 * Pull fabrics from the master Fabric Library into THIS product's fabrics
 * (product_options). Phase 2 of the Fabric Library.
 *
 *   ?product_id=N                  → pick a manufacturer
 *   ?product_id=N&supplier_id=M    → tick fabrics, set the band (suggested,
 *                                    overridable) + which system they apply to,
 *                                    then Add
 *
 * Each pulled fabric becomes a product_option: band_code = the (overridable)
 * band, supplier_name = the manufacturer, name/colour/code from the library.
 * Duplicates (same band + supplier + name + colour) are skipped.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = (int) $user['client_id'];
$pdo      = db();

$productId  = (int) ($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
$supplierId = (int) ($_GET['supplier_id'] ?? $_POST['supplier_id'] ?? 0);
// When launched from the setup wizard (ret=wizard), bounce back there after
// adding so the flow continues straight to price tables instead of dead-ending
// on the Fabrics page.
$ret        = (($_GET['ret'] ?? $_POST['ret'] ?? '') === 'wizard');
$redirect   = $ret
    ? '/admin/products/wizard.php?id=' . $productId . '&step=3'
    : '/admin/products/options.php?product_id=' . $productId;
$error      = null;

// Validate the product belongs to this tenant.
$pStmt = $pdo->prepare('SELECT id, name FROM products WHERE id = ? AND client_id = ? LIMIT 1');
$pStmt->execute([$productId, $clientId]);
$product = $pStmt->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    $_SESSION['flash_error'] = 'Product not found.';
    header('Location: /admin/products/index.php');
    exit;
}

// Fabric Library available?
$libReady = true;
try { $pdo->query('SELECT 1 FROM library_fabrics LIMIT 0'); }
catch (Throwable $e) { $libReady = false; }

// product_options column probes (system_id absent on older schemas).
$hasCol = static function (string $col) use ($pdo): bool {
    try {
        $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_options' AND COLUMN_NAME = ? LIMIT 1");
        $st->execute([$col]);
        return $st->fetchColumn() !== false;
    } catch (Throwable $e) { return false; }
};
$hasSystemId    = $hasCol('system_id');
$hasCode        = $hasCol('code');
$hasFabricGroup = $hasCol('fabric_group');

// Does the library carry groups? (migrate_fabric_library_categories.php).
// When it does, a pulled fabric's group name rides along onto the product.
$libHasCats = false;
try {
    $pdo->query('SELECT 1 FROM library_fabric_categories LIMIT 0');
    $pdo->query('SELECT category_id FROM library_fabrics LIMIT 0');
    $libHasCats = true;
} catch (Throwable $e) { $libHasCats = false; }

// This product's systems (for the scope picker).
$sysStmt = $pdo->prepare('SELECT id, name FROM product_systems WHERE product_id = ? AND client_id = ? AND active = 1 ORDER BY sort_order, name');
$sysStmt->execute([$productId, $clientId]);
$systems = $sysStmt->fetchAll(PDO::FETCH_ASSOC);

// Manufacturers + their fabric counts.
$suppliers = $libReady
    ? $pdo->query('SELECT s.id, s.name, (SELECT COUNT(*) FROM library_fabrics f WHERE f.fabric_supplier_id = s.id) AS n
                     FROM fabric_suppliers s WHERE s.active = 1 ORDER BY s.sort_order, s.name')->fetchAll(PDO::FETCH_ASSOC)
    : [];
$supplier  = null;
foreach ($suppliers as $s) { if ((int) $s['id'] === $supplierId) $supplier = $s; }

// ── POST: pull selected fabrics ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pull' && $libReady) {
    csrf_check();

    $wantIds = array_values(array_filter(array_map('intval', (array) ($_POST['fab_ids'] ?? [])), fn ($n) => $n > 0));
    $bandsIn = (array) ($_POST['bands'] ?? []);             // fabric id => band override
    $sysId   = (int) ($_POST['system_id'] ?? 0);           // 0 = all systems (NULL)

    if ($supplier === null) {
        $error = 'Choose a manufacturer.';
    } elseif (!$wantIds) {
        $error = 'Tick at least one fabric to add.';
    } else {
        // Validate the chosen system belongs to this product.
        $sysOk = true;
        if ($sysId > 0) {
            $sysOk = false;
            foreach ($systems as $s) { if ((int) $s['id'] === $sysId) $sysOk = true; }
        }
        if (!$sysOk) { $sysId = 0; }

        $supplierName = (string) $supplier['name'];

        // Existing keys (band|supplier|name|colour) to skip dups — matches the
        // product_options unique key.
        $dupKey = static fn ($b, $sup, $n, $c): string =>
            mb_strtolower(trim((string) $b)) . '|' . mb_strtolower(trim((string) $sup)) . '|'
            . mb_strtolower(trim((string) $n)) . '|' . mb_strtolower(trim((string) $c));
        $exStmt = $pdo->prepare('SELECT band_code, supplier_name, name, colour FROM product_options WHERE product_id = ? AND client_id = ?');
        $exStmt->execute([$productId, $clientId]);
        $existing = [];
        foreach ($exStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $existing[$dupKey($r['band_code'], $r['supplier_name'], $r['name'], $r['colour'])] = true;
        }

        // Load the chosen fabrics from the library, with their group name when
        // the library has grouping (LEFT JOIN so ungrouped fabrics still load).
        $idPh = implode(',', array_fill(0, count($wantIds), '?'));
        if ($libHasCats) {
            $fStmt = $pdo->prepare(
                "SELECT f.id, f.name, f.colour, f.code, f.suggested_band, c.name AS group_name
                   FROM library_fabrics f
                   LEFT JOIN library_fabric_categories c ON c.id = f.category_id
                  WHERE f.fabric_supplier_id = ? AND f.id IN ($idPh)"
            );
        } else {
            $fStmt = $pdo->prepare(
                "SELECT id, name, colour, code, suggested_band, NULL AS group_name
                   FROM library_fabrics WHERE fabric_supplier_id = ? AND id IN ($idPh)"
            );
        }
        $fStmt->execute(array_merge([$supplierId], $wantIds));
        $fabRows = $fStmt->fetchAll(PDO::FETCH_ASSOC);

        // Insert columns (system_id / code / fabric_group only if present).
        $cols = ['client_id', 'product_id', 'band_code', 'supplier_name', 'name', 'colour', 'sort_order', 'active'];
        if ($hasCode)        $cols[] = 'code';
        if ($hasSystemId)    $cols[] = 'system_id';
        if ($hasFabricGroup) $cols[] = 'fabric_group';

        $added = 0; $skipped = 0; $noBand = 0;
        $pdo->beginTransaction();
        try {
            $nextSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_options WHERE product_id = ' . (int) $productId . ' AND client_id = ' . (int) $clientId)->fetchColumn();

            $batch = [];
            foreach ($fabRows as $f) {
                $fid    = (int) $f['id'];
                $band   = trim((string) ($bandsIn[$fid] ?? $f['suggested_band'] ?? ''));
                $band   = $band !== '' ? strtoupper($band) : '';
                if ($band === '') $noBand++;
                $colour = (string) ($f['colour'] ?? '');
                $key    = $dupKey($band, $supplierName, (string) $f['name'], $colour);
                if (isset($existing[$key])) { $skipped++; continue; }
                $existing[$key] = true;

                $vals = [
                    $clientId, $productId, $band, $supplierName,
                    (string) $f['name'], ($colour !== '' ? $colour : null),
                    $nextSort++, 1,
                ];
                if ($hasCode)     $vals[] = ($f['code'] !== null && $f['code'] !== '') ? (string) $f['code'] : null;
                if ($hasSystemId) $vals[] = ($sysId > 0 ? $sysId : null);
                if ($hasFabricGroup) {
                    $grp     = trim((string) ($f['group_name'] ?? ''));
                    $vals[]  = $grp !== '' ? $grp : null;
                }
                $batch[] = $vals;
                $added++;
            }

            if ($batch) {
                $colSql = '`' . implode('`,`', $cols) . '`';
                $rowPh  = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
                for ($i = 0; $i < count($batch); $i += 100) {
                    $chunk  = array_slice($batch, $i, 100);
                    $params = [];
                    foreach ($chunk as $v) foreach ($v as $x) $params[] = $x;
                    $pdo->prepare("INSERT INTO product_options ($colSql) VALUES " . implode(',', array_fill(0, count($chunk), $rowPh)))->execute($params);
                }
            }
            $pdo->commit();

            $msg = "Added $added fabric" . ($added === 1 ? '' : 's') . ' from "' . $supplierName . '".';
            if ($skipped > 0) $msg .= " Skipped $skipped already on this product.";
            if ($noBand  > 0) $msg .= " $noBand had no band — set their band so they price correctly.";
            $_SESSION['flash_success'] = $msg;
            header('Location: ' . $redirect);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('options-from-library failed (client ' . $clientId . '): ' . $e->getMessage());
            $error = 'Could not add the fabrics — please try again.';
        }
    }
}

// Fabrics for the chosen manufacturer (step 2).
$fabrics = [];
if ($libReady && $supplier !== null) {
    $fl = $pdo->prepare('SELECT id, name, colour, code, suggested_band, blind_type FROM library_fabrics WHERE fabric_supplier_id = ? ORDER BY blind_type, name, colour');
    $fl->execute([$supplierId]);
    $fabrics = $fl->fetchAll(PDO::FETCH_ASSOC);
}

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add fabrics from library &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title"><?= e((string) $product['name']) ?> &mdash; add fabrics from library</h1>
                <p class="page-subtitle"><a href="<?= e($redirect) ?>">&larr; Back to fabrics</a></p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (!$libReady): ?>
            <section class="section"><p style="margin:0">The Fabric Library isn't set up yet.</p></section>

        <?php elseif ($supplier === null): ?>
            <!-- Step 1: pick a manufacturer -->
            <section class="section">
                <h2 class="section-title" style="margin:0 0 0.75rem">Pick a fabric manufacturer</h2>
                <?php if (!$suppliers): ?>
                    <p style="color:var(--text-faint);margin:0">No manufacturers in the library yet.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Manufacturer</th><th style="text-align:right">Fabrics</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($suppliers as $s): ?>
                                    <tr>
                                        <td><strong><?= e((string) $s['name']) ?></strong></td>
                                        <td style="text-align:right"><?= (int) $s['n'] ?></td>
                                        <td style="text-align:right">
                                            <a class="btn btn-secondary" style="font-size:.8125rem;padding:.25rem .75rem"
                                               href="/admin/products/options-from-library.php?product_id=<?= $productId ?>&supplier_id=<?= (int) $s['id'] ?><?= $ret ? '&ret=wizard' : '' ?>">Choose &rarr;</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

        <?php else: ?>
            <!-- Step 2: tick fabrics + band + system -->
            <form method="post" action="/admin/products/options-from-library.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="pull">
                <?php if ($ret): ?><input type="hidden" name="ret" value="wizard"><?php endif; ?>
                <input type="hidden" name="product_id" value="<?= $productId ?>">
                <input type="hidden" name="supplier_id" value="<?= $supplierId ?>">

                <section class="section" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
                    <div>
                        <div style="font-weight:700;font-size:1.05rem"><?= e((string) $supplier['name']) ?></div>
                        <div style="color:var(--text-faint);font-size:.8125rem"><?= count($fabrics) ?> fabrics in the library</div>
                    </div>
                    <div style="margin-left:auto">
                        <label style="display:block;font-size:.6875rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-faint);font-weight:600">Apply to system</label>
                        <select name="system_id" class="form-control" style="min-width:14rem">
                            <option value="0">All systems</option>
                            <?php foreach ($systems as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"><?= e((string) $s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Add ticked fabrics</button>
                </section>

                <section class="section">
                    <?php if (!$fabrics): ?>
                        <p style="color:var(--text-faint);margin:0">This manufacturer has no fabrics yet.</p>
                    <?php else: ?>
                        <p style="color:var(--text-faint);font-size:.8125rem;margin:0 0 .5rem">
                            All ticked by default. The <strong>band</strong> is pre-filled from the library's suggested band — edit any to suit your pricing before adding.
                        </p>
                        <div class="table-wrap">
                            <table class="table">
                                <thead><tr>
                                    <th style="width:1.5rem;text-align:center"><input type="checkbox" id="all" checked aria-label="Select all"></th>
                                    <th>Fabric</th><th>Colour</th><th>Code</th><th>Band</th><th>Type</th>
                                </tr></thead>
                                <tbody>
                                    <?php foreach ($fabrics as $f): ?>
                                        <tr>
                                            <td style="text-align:center"><input type="checkbox" class="fcb" name="fab_ids[]" value="<?= (int) $f['id'] ?>" checked></td>
                                            <td><strong><?= e((string) $f['name']) ?></strong></td>
                                            <td><?= e((string) ($f['colour'] ?? '')) ?></td>
                                            <td><?= e((string) ($f['code'] ?? '')) ?></td>
                                            <td>
                                                <input type="text" name="bands[<?= (int) $f['id'] ?>]" maxlength="60"
                                                       value="<?= e((string) ($f['suggested_band'] ?? '')) ?>"
                                                       style="width:3rem;padding:.15rem .3rem;border:1px solid var(--border-strong);border-radius:5px;font:inherit;background:var(--bg-input);text-transform:uppercase">
                                            </td>
                                            <td><?= e((string) ($f['blind_type'] ?? '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </form>
            <script>
            (function () {
                var all = document.getElementById('all');
                if (all) all.addEventListener('change', function () {
                    document.querySelectorAll('.fcb').forEach(function (cb) { cb.checked = all.checked; });
                });
            })();
            </script>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
