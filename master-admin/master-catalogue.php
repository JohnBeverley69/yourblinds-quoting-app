<?php
declare(strict_types=1);

/**
 * Master Admin: Master Catalogue overview (read-only).
 *
 * The single place to SEE everything held on the master/source tenant — the
 * curated supplier price lists that the Price-List Library copies into
 * subscribing clients. Products are grouped by the supplier whose name-prefix
 * they carry (e.g. "Bev …" → Beverley Blinds Trade). For each supplier you get
 * the product list with a count of systems, fabrics, options and price cells,
 * plus an edit link into the normal Products editor.
 *
 * Anything on the master tenant that doesn't match a known supplier prefix is
 * shown under "Unassigned" so stray catalogue items are easy to spot.
 *
 * Changes nothing — it's a window onto the catalogue, not an editor.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/library.php';

requireSuperAdmin();

$user       = current_user();
$myClientId = (int) $user['client_id'];
$masterId   = library_master_client_id();
$onMaster   = ($masterId > 0 && $myClientId === $masterId);
$suppliers  = library_suppliers();

// Name of the master tenant (so the page can tell you WHERE the catalogue
// lives and which account to log in as to edit it).
$masterName = '';
if ($masterId > 0) {
    try {
        $st = db()->prepare('SELECT company_name FROM clients WHERE id = ? LIMIT 1');
        $st->execute([$masterId]);
        $masterName = (string) ($st->fetchColumn() ?: '');
    } catch (Throwable $e) { /* leave blank */ }
}

// Record a price-change history entry (best-effort — never breaks the bump).
$logPriceChange = function (string $scope, string $target, float $pct, int $cells) use ($masterId, $user): void {
    try {
        db()->prepare(
            'INSERT INTO price_change_log (client_id, scope, target, pct, cells_changed, changed_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $masterId, $scope, mb_substr($target, 0, 160), $pct, $cells,
            mb_substr((string) ($user['full_name'] ?? ''), 0, 120),
        ]);
    } catch (Throwable $e) { /* table absent / log failure — ignore */ }
};

// ── Deletes (only when logged in AS the master tenant). The DB cascades a
//    product's systems / fabrics / extras / price tables; quotes survive
//    because they snapshot their own data. ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$onMaster) {
        $_SESSION['flash_error'] = 'Log in as the master account to delete catalogue products.';
        header('Location: /master-admin/master-catalogue.php');
        exit;
    }
    $act = (string) ($_POST['_action'] ?? '');
    try {
        if ($act === 'delete_product') {
            $pid = (int) ($_POST['product_id'] ?? 0);
            if ($pid > 0) {
                $st = db()->prepare('DELETE FROM products WHERE id = ? AND client_id = ?');
                $st->execute([$pid, $masterId]);
                $_SESSION['flash_success'] = $st->rowCount() > 0 ? 'Product deleted.' : 'Nothing to delete.';
            }
        } elseif ($act === 'delete_supplier') {
            $key = (string) ($_POST['supplier_key'] ?? '');
            $sup = $suppliers[$key] ?? null;
            $prefix = $sup ? (string) ($sup['prefix'] ?? '') : '';
            if ($prefix !== '') {
                // Escape LIKE wildcards in the prefix so it matches literally.
                $likePrefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix) . '%';
                $st = db()->prepare('DELETE FROM products WHERE client_id = ? AND name LIKE ?');
                $st->execute([$masterId, $likePrefix]);
                $n = $st->rowCount();
                $_SESSION['flash_success'] = 'Deleted ' . $n . ' product' . ($n === 1 ? '' : 's')
                    . ' under ' . ((string) ($sup['name'] ?? $key)) . '.';
            } else {
                $_SESSION['flash_error'] = 'Unknown supplier — nothing deleted.';
            }
        } elseif ($act === 'bump_product') {
            // Apply a % price change to one product's price grids (nearest penny).
            $pid = (int) ($_POST['product_id'] ?? 0);
            $pct = (float) ($_POST['pct'] ?? 0);
            if ($pid > 0 && $pct !== 0.0 && $pct > -100) {
                $factor = 1 + ($pct / 100);
                $st = db()->prepare(
                    'UPDATE price_table_rows r
                       JOIN price_tables t ON t.id = r.price_table_id
                        SET r.price = ROUND(r.price * ?, 2)
                      WHERE t.product_id = ? AND t.client_id = ?'
                );
                $st->execute([$factor, $pid, $masterId]);
                $cells = $st->rowCount();
                $pn = db()->prepare('SELECT name FROM products WHERE id = ? AND client_id = ? LIMIT 1');
                $pn->execute([$pid, $masterId]);
                $logPriceChange('product', (string) ($pn->fetchColumn() ?: ('product #' . $pid)), $pct, $cells);
                $_SESSION['flash_success'] = 'Adjusted ' . number_format($cells)
                    . ' prices by ' . ($pct > 0 ? '+' : '') . rtrim(rtrim((string) $pct, '0'), '.') . '%.';
            } else {
                $_SESSION['flash_error'] = 'Enter a percentage (e.g. 4 or -2).';
            }
        } elseif ($act === 'bump_supplier') {
            // Apply one % across every product under a supplier's prefix.
            $key = (string) ($_POST['supplier_key'] ?? '');
            $pct = (float) ($_POST['pct'] ?? 0);
            $sup = $suppliers[$key] ?? null;
            $prefix = $sup ? (string) ($sup['prefix'] ?? '') : '';
            if ($prefix !== '' && $pct !== 0.0 && $pct > -100) {
                $likePrefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix) . '%';
                $factor = 1 + ($pct / 100);
                $st = db()->prepare(
                    'UPDATE price_table_rows r
                       JOIN price_tables t ON t.id = r.price_table_id
                       JOIN products     p ON p.id = t.product_id
                        SET r.price = ROUND(r.price * ?, 2)
                      WHERE p.client_id = ? AND p.name LIKE ?'
                );
                $st->execute([$factor, $masterId, $likePrefix]);
                $cells = $st->rowCount();
                $logPriceChange('supplier', (string) ($sup['name'] ?? $key), $pct, $cells);
                $_SESSION['flash_success'] = 'Adjusted ' . number_format($cells)
                    . ' prices across ' . ((string) ($sup['name'] ?? $key))
                    . ' by ' . ($pct > 0 ? '+' : '') . rtrim(rtrim((string) $pct, '0'), '.') . '%.';
            } else {
                $_SESSION['flash_error'] = 'Pick a supplier and enter a percentage.';
            }
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not update: ' . $e->getMessage();
    }
    header('Location: /master-admin/master-catalogue.php');
    exit;
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Price-change history (most recent first). Absent table = empty.
$priceHistory = [];
if ($masterId > 0) {
    try {
        $h = db()->prepare(
            'SELECT scope, target, pct, cells_changed, changed_by, created_at
               FROM price_change_log WHERE client_id = ? ORDER BY id DESC LIMIT 60'
        );
        $h->execute([$masterId]);
        $priceHistory = $h->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* table not migrated yet */ }
}

// Pull every product on the master tenant with its child counts. Scalar
// subqueries keep it to one round-trip; the tables are all core so this is
// safe on any live schema.
$products = [];
$loadError = null;
if ($masterId > 0) {
    try {
        $st = db()->prepare(
            'SELECT p.id, p.name, p.active,
                    (SELECT COUNT(*) FROM product_systems s WHERE s.product_id = p.id) AS systems,
                    (SELECT COUNT(*) FROM product_options o WHERE o.product_id = p.id) AS fabrics,
                    (SELECT COUNT(*) FROM product_extras  e WHERE e.product_id = p.id) AS extras,
                    (SELECT COUNT(*) FROM price_table_rows r
                        JOIN price_tables t ON t.id = r.price_table_id
                       WHERE t.product_id = p.id) AS cells
               FROM products p
              WHERE p.client_id = ?
              ORDER BY p.name'
        );
        $st->execute([$masterId]);
        $products = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $loadError = $e->getMessage();
    }
}

// Group products by the FIRST supplier whose prefix the name starts with
// (case-insensitive). No match → "Unassigned". Mirrors the push engine, which
// copies products whose name LIKE 'prefix%'.
$groups = [];                       // supplierKey => ['supplier'=>..., 'rows'=>[], totals...]
foreach ($suppliers as $key => $sup) {
    $groups[$key] = ['supplier' => $sup, 'rows' => [], 'cells' => 0, 'fabrics' => 0];
}
$unassigned = ['rows' => [], 'cells' => 0, 'fabrics' => 0];

foreach ($products as $p) {
    $name = (string) $p['name'];
    $placed = false;
    foreach ($suppliers as $key => $sup) {
        $prefix = (string) ($sup['prefix'] ?? '');
        if ($prefix !== '' && stripos($name, $prefix) === 0) {
            $groups[$key]['rows'][]   = $p;
            $groups[$key]['cells']   += (int) $p['cells'];
            $groups[$key]['fabrics'] += (int) $p['fabrics'];
            $placed = true;
            break;
        }
    }
    if (!$placed) {
        $unassigned['rows'][]   = $p;
        $unassigned['cells']   += (int) $p['cells'];
        $unassigned['fabrics'] += (int) $p['fabrics'];
    }
}

/** Render one product table for a group. */
$renderRows = function (array $rows) use ($onMaster): void {
    foreach ($rows as $p):
        $pid = (int) $p['id'];
        ?>
        <tr<?= ((int) $p['active']) === 1 ? '' : ' style="opacity:.55"' ?>>
            <td>
                <?php if ($onMaster): ?>
                    <a href="/admin/products/edit.php?id=<?= $pid ?>"><?= e((string) $p['name']) ?></a>
                <?php else: ?>
                    <?= e((string) $p['name']) ?>
                <?php endif; ?>
                <?php if ((int) $p['active'] !== 1): ?>
                    <span style="font-size:.6875rem;color:var(--text-faint);font-weight:600;text-transform:uppercase;margin-left:.4rem">Inactive</span>
                <?php endif; ?>
            </td>
            <td style="text-align:right"><?= (int) $p['systems'] ?></td>
            <td style="text-align:right"><?= (int) $p['fabrics'] ?></td>
            <td style="text-align:right"><?= (int) $p['extras'] ?></td>
            <td style="text-align:right"><?= number_format((int) $p['cells']) ?></td>
            <?php if ($onMaster): ?>
                <td style="text-align:right;white-space:nowrap">
                    <form method="post" action="/master-admin/master-catalogue.php" style="margin:0 .5rem 0 0;display:inline-flex;gap:.2rem;align-items:center"
                          data-confirm="Change ALL prices for &quot;<?= e((string) $p['name']) ?>&quot; by the % entered? Rounds to the nearest penny. No undo.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="bump_product">
                        <input type="hidden" name="product_id" value="<?= $pid ?>">
                        <input type="number" name="pct" step="0.01" placeholder="%" title="e.g. 4 or -2"
                               style="width:3.2rem;padding:.1rem .25rem;border:1px solid var(--border-strong);border-radius:5px;font:inherit;font-size:.8125rem;background:var(--bg-input)">
                        <button type="submit" style="background:none;border:0;color:#1f3b5b;cursor:pointer;font-size:.8125rem;padding:0">Apply %</button>
                    </form>
                    <form method="post" action="/master-admin/master-catalogue.php" style="margin:0;display:inline"
                          data-confirm="Delete &quot;<?= e((string) $p['name']) ?>&quot; and all its systems, fabrics and price tables? Quotes already raised keep working. No undo.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="delete_product">
                        <input type="hidden" name="product_id" value="<?= $pid ?>">
                        <button type="submit" style="background:none;border:0;color:#b91c1c;cursor:pointer;font-size:.8125rem;padding:0">Delete</button>
                    </form>
                </td>
            <?php endif; ?>
        </tr>
        <?php
    endforeach;
};

$activeNav = 'master-catalogue';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Master Catalogue &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        details.cat-supplier { margin: 0; }
        details.cat-supplier > summary {
            list-style: none; cursor: pointer;
            -webkit-user-select: none; user-select: none;   /* a click toggles, never selects the heading text */
            padding: 0.55rem 0.5rem; border-radius: 6px;     /* bigger, easier hit area */
        }
        details.cat-supplier > summary:hover { background: var(--bg-subtle-2); }
        details.cat-supplier > summary::-webkit-details-marker { display: none; }
        /* Flex lives on an inner wrapper, NOT the <summary> itself — that keeps
           the native click-to-toggle reliable across browsers. */
        details.cat-supplier > summary > .sum-row {
            display: flex; justify-content: space-between; align-items: baseline;
            gap: 1rem; flex-wrap: wrap;
        }
        details.cat-supplier .tw {
            display: inline-block; color: var(--text-faint); font-size: 0.8125rem;
            transition: transform 120ms; transform-origin: center;
        }
        details.cat-supplier[open] > summary .tw { transform: rotate(90deg); }
        details.cat-supplier .sup-name { font-weight: 700; font-size: 1.05rem; color: var(--text-primary); }
        details.cat-supplier .sup-meta { font-size: 0.75rem; color: var(--text-faint); font-weight: 500; }
        details.cat-supplier .sup-counts { color: var(--text-muted); font-size: 0.8125rem; }
        details.cat-supplier .sup-body { margin-top: 0.75rem; }
        .cat-tools { display: flex; gap: 0.5rem; margin: 0 0 0.5rem; }
        .cat-tools button {
            background: transparent; border: 0; color: var(--link); cursor: pointer;
            font-size: 0.8125rem; text-decoration: underline; padding: 0;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Master Catalogue</h1>
                <p class="page-subtitle">
                    Every supplier price list held on the master account — the source the
                    Price-List Library copies into subscribing clients. Review, open or delete.
                </p>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <a href="/master-admin/library-suppliers.php" class="btn btn-secondary">Library suppliers</a>
                <a href="/master-admin/supplier-import.php" class="btn btn-secondary">Supplier import</a>
                <a href="/master-admin/push-updates.php" class="btn btn-secondary">Push updates</a>
                <?php if ($onMaster): ?>
                    <a href="/admin/products/new.php" class="btn btn-primary">+ New product</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <!-- Where the catalogue lives -->
        <section class="section">
            <?php if ($masterId <= 0): ?>
                <div class="alert alert-error" role="alert">
                    No master tenant could be resolved. Make sure a super-admin user exists,
                    or set <code>library_master_client_id</code> in app settings.
                </div>
            <?php else: ?>
                <p style="margin:0;line-height:1.6">
                    The master catalogue lives on
                    <strong><?= e($masterName !== '' ? $masterName : ('client #' . $masterId)) ?></strong>
                    (client #<?= $masterId ?>).
                    <?php if ($onMaster): ?>
                        You're logged in as that account, so product names below link straight
                        into the editor.
                    <?php else: ?>
                        <span style="color:#92400e;font-weight:600">
                            You're not logged in as that account
                        </span>
                        — to edit the catalogue, log in as
                        <strong><?= e($masterName !== '' ? $masterName : ('client #' . $masterId)) ?></strong> first.
                    <?php endif; ?>
                </p>
                <p style="margin:.6rem 0 0;color:var(--text-faint);font-size:.8125rem;line-height:1.5">
                    Products are grouped by the supplier whose name-prefix they carry
                    (e.g. a product named “<strong>Bev</strong> Pleated” belongs to Beverley).
                    Subscribing clients receive exactly the prefixed products for the suppliers
                    they enable. Loading a new supplier = add its prefix in the library config,
                    then name that supplier's products with the prefix
                    (<a href="/master-admin/supplier-import.php">Supplier import</a> previews a spreadsheet first).
                </p>
            <?php endif; ?>
        </section>

        <?php if ($loadError !== null): ?>
            <section class="section">
                <div class="alert alert-error" role="alert">
                    Could not read the catalogue: <?= e($loadError) ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($masterId > 0 && $loadError === null): ?>
            <div class="cat-tools">
                <button type="button" id="cat-expand">Expand all</button>
                <span style="color:var(--text-faint)">·</span>
                <button type="button" id="cat-collapse">Collapse all</button>
            </div>
            <!-- One collapsible section per supplier (collapsed by default) -->
            <?php foreach ($groups as $key => $g):
                $sup  = $g['supplier'];
                $rows = $g['rows'];
            ?>
                <section class="section">
                    <details class="cat-supplier">
                        <summary>
                            <div class="sum-row">
                                <span style="display:flex;align-items:baseline;gap:.4rem">
                                    <span class="tw">&#9654;</span>
                                    <span class="sup-name">
                                        <?= e((string) $sup['name']) ?>
                                        <span class="sup-meta">prefix “<?= e((string) ($sup['prefix'] ?? '')) ?>” · <?= !empty($sup['free']) ? 'free' : 'paid' ?></span>
                                    </span>
                                </span>
                                <span class="sup-counts">
                                    <?= count($rows) ?> product<?= count($rows) === 1 ? '' : 's' ?>
                                    · <?= number_format($g['fabrics']) ?> fabrics
                                    · <?= number_format($g['cells']) ?> price cells
                                </span>
                            </div>
                        </summary>
                        <div class="sup-body">
                            <?php if (!$rows): ?>
                                <p style="color:var(--text-faint);margin:0">
                                    No products carry the “<?= e((string) ($sup['prefix'] ?? '')) ?>” prefix yet.
                                </p>
                            <?php else: ?>
                                <?php if ($onMaster): ?>
                                    <div style="display:flex;gap:.9rem;align-items:center;flex-wrap:wrap;margin:0 0 .625rem">
                                        <form method="post" action="/master-admin/master-catalogue.php" style="display:flex;gap:.35rem;align-items:center;margin:0"
                                              data-confirm="Change ALL prices across &quot;<?= e((string) $sup['name']) ?>&quot; by the % entered? It applies to every product under this supplier, rounded to the nearest penny. No undo.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="_action" value="bump_supplier">
                                            <input type="hidden" name="supplier_key" value="<?= e($key) ?>">
                                            <span style="color:var(--text-muted);font-size:.8125rem">Price change</span>
                                            <input type="number" name="pct" step="0.01" placeholder="%" title="e.g. 4 or -2"
                                                   style="width:4rem;padding:.2rem .4rem;border:1px solid var(--border-strong);border-radius:5px;font:inherit;background:var(--bg-input)">
                                            <button type="submit" class="btn btn-secondary" style="font-size:.8125rem;padding:.25rem .75rem">Apply to all</button>
                                        </form>
                                        <form method="post" action="/master-admin/master-catalogue.php" style="margin:0"
                                              data-confirm="Delete ALL <?= count($rows) ?> product<?= count($rows) === 1 ? '' : 's' ?> under &quot;<?= e((string) $sup['name']) ?>&quot; (prefix &quot;<?= e((string) ($sup['prefix'] ?? '')) ?>&quot;)? This removes their systems, fabrics and price tables. Quotes already raised keep working. No undo.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="_action" value="delete_supplier">
                                            <input type="hidden" name="supplier_key" value="<?= e($key) ?>">
                                            <button type="submit" class="btn btn-secondary" style="color:#b91c1c;font-size:.8125rem;padding:.25rem .625rem">Delete all</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                                <div class="table-wrap">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th style="text-align:right">Systems</th>
                                                <th style="text-align:right">Fabrics</th>
                                                <th style="text-align:right">Options</th>
                                                <th style="text-align:right">Price cells</th><?php if ($onMaster): ?><th></th><?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody><?php $renderRows($rows); ?></tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </details>
                </section>
            <?php endforeach; ?>

            <!-- Unassigned products (no supplier prefix) -->
            <?php if ($unassigned['rows']): ?>
                <section class="section">
                    <details class="cat-supplier">
                        <summary>
                            <div class="sum-row">
                                <span style="display:flex;align-items:baseline;gap:.4rem">
                                    <span class="tw">&#9654;</span>
                                    <span class="sup-name">Unassigned <span class="sup-meta">no supplier prefix</span></span>
                                </span>
                                <span class="sup-counts">
                                    <?= count($unassigned['rows']) ?> product<?= count($unassigned['rows']) === 1 ? '' : 's' ?>
                                    · <?= number_format($unassigned['cells']) ?> price cells
                                </span>
                            </div>
                        </summary>
                        <div class="sup-body">
                            <p style="color:var(--text-faint);font-size:.8125rem;margin:0 0 .75rem;line-height:1.5">
                                These products don't start with any supplier prefix, so the library
                                won't copy them to clients. Rename them with a supplier prefix to
                                include them, or leave them as the master account's own products.
                            </p>
                            <div class="table-wrap">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th style="text-align:right">Systems</th>
                                            <th style="text-align:right">Fabrics</th>
                                            <th style="text-align:right">Options</th>
                                            <th style="text-align:right">Price cells</th><?php if ($onMaster): ?><th></th><?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody><?php $renderRows($unassigned['rows']); ?></tbody>
                                </table>
                            </div>
                        </div>
                    </details>
                </section>
            <?php endif; ?>

            <?php if ($priceHistory): ?>
                <section class="section">
                    <details>
                        <summary style="cursor:pointer;font-weight:700;font-size:1.05rem;color:var(--text-primary)">
                            Price change history
                            <span style="font-weight:500;color:var(--text-faint);font-size:.8125rem">(last <?= count($priceHistory) ?>)</span>
                        </summary>
                        <div class="table-wrap" style="margin-top:.75rem">
                            <table class="table">
                                <thead><tr><th>When</th><th>Who</th><th>What</th><th style="text-align:right">Change</th><th style="text-align:right">Prices</th></tr></thead>
                                <tbody>
                                    <?php foreach ($priceHistory as $h): $pc = (float) $h['pct']; ?>
                                        <tr>
                                            <td style="white-space:nowrap;color:var(--text-muted);font-size:.8125rem"><?= e(date('j M Y, g:ia', strtotime((string) $h['created_at']))) ?></td>
                                            <td><?= e((string) ($h['changed_by'] ?? '')) ?></td>
                                            <td><?= e(ucfirst((string) $h['scope'])) ?>: <strong><?= e((string) $h['target']) ?></strong></td>
                                            <td style="text-align:right;font-weight:600;color:<?= $pc >= 0 ? '#92400e' : '#15803d' ?>"><?= ($pc > 0 ? '+' : '') . rtrim(rtrim(number_format($pc, 2), '0'), '.') ?>%</td>
                                            <td style="text-align:right"><?= number_format((int) $h['cells_changed']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>
<script>
(function () {
    var all = function () { return document.querySelectorAll('details.cat-supplier'); };
    var ex  = document.getElementById('cat-expand');
    var col = document.getElementById('cat-collapse');
    if (ex)  ex.addEventListener('click',  function () { all().forEach(function (d) { d.open = true; }); });
    if (col) col.addEventListener('click', function () { all().forEach(function (d) { d.open = false; }); });
})();
</script>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
