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
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not delete: ' . $e->getMessage();
    }
    header('Location: /master-admin/master-catalogue.php');
    exit;
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

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
            <!-- One section per supplier -->
            <?php foreach ($groups as $key => $g):
                $sup  = $g['supplier'];
                $rows = $g['rows'];
            ?>
                <section class="section">
                    <div class="section-header" style="display:flex;justify-content:space-between;align-items:baseline;gap:1rem;flex-wrap:wrap">
                        <h2 class="section-title" style="margin:0">
                            <?= e((string) $sup['name']) ?>
                            <span style="font-size:.75rem;color:var(--text-faint);font-weight:500">
                                prefix “<?= e((string) ($sup['prefix'] ?? '')) ?>”
                                · <?= !empty($sup['free']) ? 'free' : 'paid' ?>
                            </span>
                        </h2>
                        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
                            <span style="color:var(--text-muted);font-size:.8125rem">
                                <?= count($rows) ?> product<?= count($rows) === 1 ? '' : 's' ?>
                                · <?= number_format($g['fabrics']) ?> fabrics
                                · <?= number_format($g['cells']) ?> price cells
                            </span>
                            <?php if ($onMaster && $rows): ?>
                                <form method="post" action="/master-admin/master-catalogue.php" style="margin:0"
                                      data-confirm="Delete ALL <?= count($rows) ?> product<?= count($rows) === 1 ? '' : 's' ?> under &quot;<?= e((string) $sup['name']) ?>&quot; (prefix &quot;<?= e((string) ($sup['prefix'] ?? '')) ?>&quot;)? This removes their systems, fabrics and price tables. Quotes already raised keep working. No undo.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_action" value="delete_supplier">
                                    <input type="hidden" name="supplier_key" value="<?= e($key) ?>">
                                    <button type="submit" class="btn btn-secondary" style="color:#b91c1c;font-size:.8125rem;padding:.25rem .625rem">Delete all</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!$rows): ?>
                        <p style="color:var(--text-faint);margin:.5rem 0 0">
                            No products carry the “<?= e((string) ($sup['prefix'] ?? '')) ?>” prefix yet.
                        </p>
                    <?php else: ?>
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
                </section>
            <?php endforeach; ?>

            <!-- Unassigned products (no supplier prefix) -->
            <?php if ($unassigned['rows']): ?>
                <section class="section">
                    <div class="section-header" style="display:flex;justify-content:space-between;align-items:baseline;gap:1rem;flex-wrap:wrap">
                        <h2 class="section-title" style="margin:0">
                            Unassigned
                            <span style="font-size:.75rem;color:var(--text-faint);font-weight:500">no supplier prefix</span>
                        </h2>
                        <div style="color:var(--text-muted);font-size:.8125rem">
                            <?= count($unassigned['rows']) ?> product<?= count($unassigned['rows']) === 1 ? '' : 's' ?>
                            · <?= number_format($unassigned['cells']) ?> price cells
                        </div>
                    </div>
                    <p style="color:var(--text-faint);font-size:.8125rem;margin:.25rem 0 .75rem;line-height:1.5">
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
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
