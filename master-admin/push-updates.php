<?php
declare(strict_types=1);

/**
 * Master Admin: push catalogue updates from the master tenant to
 * selected client tenants.
 *
 * Only products whose name starts with the configured prefix
 * (default "Beverley") are pushed. The rest of every tenant's
 * catalogue is left alone. Markups/discounts are never touched.
 * See _partials/catalogue_push.php for the full rules.
 *
 * Page flow:
 *   GET  → lists Beverley-prefixed products in the master + a list
 *          of other tenants with tickboxes. Big red "Push to ticked"
 *          button at the bottom.
 *   POST → runs push_catalogue_to_client() per ticked tenant, shows
 *          a per-tenant results summary on the redirected GET.
 *
 * Super-admin only.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/catalogue_push.php';

requireSuperAdmin();

$user           = current_user();
$masterClientId = (int) $user['client_id'];
$prefix         = 'Beverley';   // currently fixed; could be a setting later

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $targetIds = is_array($_POST['target_ids'] ?? null) ? $_POST['target_ids'] : [];
    $targetIds = array_values(array_unique(array_filter(
        array_map('intval', $targetIds),
        static fn ($v) => $v > 0 && $v !== $masterClientId
    )));

    if (!$targetIds) {
        $_SESSION['flash_error'] = 'Pick at least one tenant to push to.';
        header('Location: /master-admin/push-updates.php');
        exit;
    }

    $pdo = db();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Resolve tenant names upfront so the summary reads nicely.
    $names = [];
    $st = $pdo->prepare('SELECT id, company_name FROM clients WHERE id = ? LIMIT 1');
    foreach ($targetIds as $tid) {
        $st->execute([$tid]);
        $row = $st->fetch();
        if ($row) $names[$tid] = (string) $row['company_name'];
    }

    $results = [];
    foreach ($targetIds as $tid) {
        try {
            $summary = push_catalogue_to_client($pdo, $masterClientId, $tid, $prefix);
            $results[$tid] = ['name' => $names[$tid] ?? ('client #' . $tid), 'summary' => $summary, 'failed' => false];
        } catch (Throwable $e) {
            $results[$tid] = [
                'name'    => $names[$tid] ?? ('client #' . $tid),
                'summary' => null,
                'failed'  => true,
                'error'   => $e->getMessage(),
            ];
        }
    }

    $_SESSION['push_results'] = $results;
    $_SESSION['flash_success'] = 'Push complete — see the summary below.';
    header('Location: /master-admin/push-updates.php');
    exit;
}

// GET — list master's prefixed products + other tenants.
$pdo = db();

$srcProductsSt = $pdo->prepare(
    'SELECT id, name, active,
            (SELECT COUNT(*) FROM product_options o WHERE o.product_id = p.id AND o.active = 1) AS fab_count,
            (SELECT COUNT(*) FROM product_systems s WHERE s.product_id = p.id AND s.active = 1) AS sys_count,
            (SELECT COUNT(*) FROM product_extras  e WHERE e.product_id = p.id AND e.active = 1) AS ext_count,
            (SELECT COUNT(*) FROM price_tables   t WHERE t.product_id = p.id AND t.active = 1) AS pt_count
       FROM products p
      WHERE p.client_id = ?
        AND p.name LIKE ?
   ORDER BY p.name'
);
$srcProductsSt->execute([$masterClientId, $prefix . '%']);
$srcProducts = $srcProductsSt->fetchAll();

// Other tenants. Exclude the master.
$tenantsSt = $pdo->prepare(
    'SELECT id, company_name, active
       FROM clients
      WHERE id != ?
   ORDER BY company_name'
);
$tenantsSt->execute([$masterClientId]);
$tenants = $tenantsSt->fetchAll();

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
$results  = $_SESSION['push_results']  ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['push_results']);

$activeNav = 'push-updates';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Push catalogue updates &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .info-panel {
            background:#f0f9ff; border:1px solid #bae6fd; border-radius:10px;
            padding:0.875rem 1.125rem; margin-bottom:1rem;
            color:#0c4a6e; font-size:0.9375rem; line-height:1.5;
        }
        .info-panel strong { color:#0c4a6e; }
        .info-panel code {
            background:#fff; padding:0.0625rem 0.375rem; border-radius:4px;
            border:1px solid #bae6fd; font-size:0.8125rem;
        }
        .src-grid {
            display:grid; gap:0.5rem;
            grid-template-columns: 1fr;
        }
        .src-row {
            background:#fff; border:1px solid #e5e7eb; border-radius:10px;
            padding:0.625rem 0.875rem;
            display:flex; align-items:center; gap:0.875rem; flex-wrap:wrap;
        }
        .src-row .src-name { font-weight:600; color:#111827; flex:0 0 auto; min-width:14rem; }
        .src-row .src-bits { color:#6b7280; font-size:0.875rem; }
        .tenant-row {
            background:#fff; border:1px solid #e5e7eb; border-radius:10px;
            padding:0.5rem 0.875rem; margin-bottom:0.375rem;
            display:flex; align-items:center; gap:0.625rem;
        }
        .tenant-row label {
            display:inline-flex; align-items:center; gap:0.5rem;
            cursor:pointer; flex:1 1 auto; font-weight:500;
        }
        .tenant-row input[type="checkbox"] { width:18px; height:18px; }
        .tenant-row .inactive {
            font-size:0.6875rem; color:#6b7280; background:#f3f4f6;
            padding:0.0625rem 0.4375rem; border-radius:999px;
            text-transform:uppercase; letter-spacing:0.05em;
        }
        .summary-block {
            background:#fff; border:1px solid #e5e7eb; border-radius:10px;
            padding:0.75rem 1rem; margin-bottom:0.625rem;
        }
        .summary-block .sb-name { font-weight:700; color:#1f3b5b; font-size:1.0625rem; }
        .summary-block .sb-stats {
            display:flex; flex-wrap:wrap; gap:0.5rem 1rem;
            margin-top:0.5rem; font-size:0.875rem; color:#374151;
        }
        .summary-block .sb-stats span strong { color:#065f46; }
        .summary-block.failed { border-color:#fecaca; background:#fef2f2; }
        .summary-block.failed .sb-name { color:#991b1b; }
        .summary-block .sb-errors {
            margin-top:0.5rem; font-size:0.8125rem; color:#991b1b;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Push catalogue updates</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/index.php">&larr; Master Admin</a>
                </p>
            </div>
        </div>

        <?php if ($flashMsg): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <section class="info-panel">
            <p style="margin:0 0 0.5rem">
                Pushes <strong>your prefixed products</strong> (any product whose name starts
                with <code><?= e($prefix) ?></code>) into the selected tenants. Each tenant's
                own non-prefixed products are <em>not touched</em>.
            </p>
            <p style="margin:0">
                <strong>What gets synced:</strong> products, systems, fabrics, options (extras),
                choices, and price tables.
                <strong>What does NOT get changed:</strong> any tenant's markup or discount %,
                or any non-prefixed product they've set up themselves.
                <br>
                <strong>How updates work:</strong> matched items (by name) are updated in place.
                Missing items are added. Items the tenant has added themselves are kept.
                Prices in matched grids are overwritten cell-by-cell; cells the tenant has at
                sizes you don't cover are kept.
            </p>
        </section>

        <?php if ($results): ?>
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title">Latest push results</h2>
                </div>
                <?php foreach ($results as $tid => $res):
                    $s = $res['summary'];
                ?>
                    <div class="summary-block <?= $res['failed'] ? 'failed' : '' ?>">
                        <div class="sb-name">
                            <?= e((string) $res['name']) ?>
                            <?php if ($res['failed']): ?>
                                &mdash; <span style="color:#991b1b">failed</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($res['failed']): ?>
                            <div class="sb-errors">
                                <?= e((string) ($res['error'] ?? 'Unknown error')) ?>
                            </div>
                        <?php else: ?>
                            <div class="sb-stats">
                                <span><strong><?= (int) $s['products_added']     ?></strong> products added</span>
                                <span><strong><?= (int) $s['products_updated']   ?></strong> products refreshed</span>
                                <span><strong><?= (int) $s['systems_added']      ?></strong> systems added</span>
                                <span><strong><?= (int) $s['fabrics_added']      ?></strong> fabrics added</span>
                                <span><strong><?= (int) $s['fabrics_updated']    ?></strong> fabrics updated</span>
                                <span><strong><?= (int) $s['extras_added']       ?></strong> options added</span>
                                <span><strong><?= (int) $s['extras_updated']     ?></strong> options updated</span>
                                <span><strong><?= (int) $s['choices_added']      ?></strong> choices added</span>
                                <span><strong><?= (int) $s['choices_updated']    ?></strong> choices updated</span>
                                <span><strong><?= (int) $s['price_tables_added'] ?></strong> price tables added</span>
                                <span><strong><?= (int) $s['price_table_cells']  ?></strong> price cells synced</span>
                                <span><strong><?= (int) $s['width_table_cells']  ?></strong> width-table cells synced</span>
                            </div>
                            <?php if (!empty($s['errors'])): ?>
                                <div class="sb-errors">
                                    <strong><?= count($s['errors']) ?> product(s) failed:</strong>
                                    <?php foreach ($s['errors'] as $err): ?>
                                        <div>&middot; <?= e((string) ($err['product'] ?? '?')) ?>: <?= e((string) ($err['message'] ?? '')) ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">
                    Your "<?= e($prefix) ?>"-prefixed products (<?= count($srcProducts) ?>)
                </h2>
            </div>
            <?php if (!$srcProducts): ?>
                <p style="color:#9ca3af;font-style:italic">
                    None yet. Create a product with a name starting with <code><?= e($prefix) ?></code>
                    in your own catalogue, then come back here to push it to your tenants.
                </p>
            <?php else: ?>
                <p style="color:#6b7280;font-size:0.875rem;margin:0 0 0.625rem">
                    These are what will be pushed. Tenants either get them added or get matched
                    items updated.
                </p>
                <div class="src-grid">
                    <?php foreach ($srcProducts as $p): ?>
                        <div class="src-row">
                            <span class="src-name"><?= e((string) $p['name']) ?></span>
                            <span class="src-bits">
                                <?= (int) $p['sys_count'] ?> system<?= (int) $p['sys_count'] === 1 ? '' : 's' ?>,
                                <?= (int) $p['fab_count'] ?> fabric<?= (int) $p['fab_count'] === 1 ? '' : 's' ?>,
                                <?= (int) $p['ext_count'] ?> option<?= (int) $p['ext_count'] === 1 ? '' : 's' ?>,
                                <?= (int) $p['pt_count']  ?> price table<?= (int) $p['pt_count']  === 1 ? '' : 's' ?>
                            </span>
                            <?php if ((int) $p['active'] !== 1): ?>
                                <span style="font-size:0.6875rem;color:#6b7280;background:#f3f4f6;padding:0.0625rem 0.4375rem;border-radius:999px;text-transform:uppercase;letter-spacing:0.05em">Inactive</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($srcProducts && $tenants): ?>
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title">Push to which tenants?</h2>
                </div>
                <form method="post" action="/master-admin/push-updates.php"
                      data-confirm="Push <?= count($srcProducts) ?> &quot;<?= e($prefix) ?>&quot;-prefixed products to the selected tenants? Matched items will be updated, missing ones added. Tenant markups, discounts, and non-prefixed products are not touched.">
                    <?= csrf_field() ?>
                    <p style="color:#6b7280;font-size:0.875rem;margin:0 0 0.625rem">
                        Tick the tenants who should receive these products.
                    </p>
                    <div style="margin-bottom:0.625rem">
                        <label style="display:inline-flex;align-items:center;gap:0.5rem;cursor:pointer;color:#1f3b5b;font-weight:600">
                            <input type="checkbox" id="select-all-tenants" style="width:18px;height:18px">
                            Select all
                        </label>
                    </div>
                    <?php foreach ($tenants as $t): ?>
                        <div class="tenant-row">
                            <label>
                                <input type="checkbox" name="target_ids[]" value="<?= (int) $t['id'] ?>"
                                       class="tenant-cb">
                                <?= e((string) $t['company_name']) ?>
                            </label>
                            <?php if ((int) $t['active'] !== 1): ?>
                                <span class="inactive">Inactive tenant</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="form-actions" style="margin-top:1rem">
                        <button type="submit" class="btn btn-primary"
                                style="background:#b91c1c;border-color:#b91c1c">
                            Push to selected tenants &raquo;
                        </button>
                    </div>
                </form>
                <script>
                (function () {
                    var all = document.getElementById('select-all-tenants');
                    if (!all) return;
                    all.addEventListener('change', function () {
                        document.querySelectorAll('.tenant-cb').forEach(function (cb) {
                            cb.checked = all.checked;
                        });
                    });
                })();
                </script>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
