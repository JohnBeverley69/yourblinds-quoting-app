<?php
declare(strict_types=1);

/**
 * Wipe products — super-admin tool for one-off catalogue cleanups.
 *
 * Use case: a master-tenant catalogue gets renamed / restructured
 * during the testing phase, and you need to clear stale copies of
 * specific products from every target tenant in one shot before
 * re-pushing. Doing it by hand on each tenant is tedious; this gives
 * one button.
 *
 * Flow:
 *   GET → enter a name filter (matched as LIKE %X% on products.name,
 *         case-insensitive via MySQL default collation). Page shows
 *         every tenant that has matching products, with the matched
 *         names listed and a tick box per tenant. Plus a "what would
 *         be cascade-deleted" preview (systems, fabrics, price
 *         tables, quote_items referencing the products).
 *
 *   POST → user has ticked tenants + typed the confirmation phrase
 *          "WIPE" → DELETE FROM products WHERE name LIKE ? AND
 *          client_id IN (...). FK cascades wipe systems, fabrics,
 *          extras, choices, price tables, price_table_rows.
 *          Quotes themselves stay intact (they snapshot product
 *          data at quote time, so PDFs and order history don't
 *          break — just the catalogue rows disappear).
 *
 * Belt-and-braces gating:
 *   - requireSuperAdmin() — no normal admin can hit this
 *   - CSRF on the POST
 *   - Typed "WIPE" confirmation (avoids accidental form submits)
 *   - Empty filter rejected (no "wipe all products" footgun)
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

$user = current_user();
$pdo  = db();

// ── GET: read filter, build preview ──────────────────────────────────
$filter = trim((string) ($_GET['filter'] ?? $_POST['filter'] ?? ''));

$preview = [];   // tenants matching → [{client_id, company_name, products: [{id, name}]}]
$summary = [
    'tenants'  => 0,
    'products' => 0,
];
if ($filter !== '') {
    $like = '%' . $filter . '%';
    $stmt = $pdo->prepare(
        "SELECT p.id, p.name, p.client_id,
                c.name      AS company_name
           FROM products p
           JOIN clients c ON c.id = p.client_id
          WHERE p.name LIKE ?
       ORDER BY c.name, p.name"
    );
    $stmt->execute([$like]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cid = (int) $r['client_id'];
        if (!isset($preview[$cid])) {
            $preview[$cid] = [
                'client_id'    => $cid,
                'company_name' => (string) $r['company_name'],
                'products'     => [],
            ];
            $summary['tenants']++;
        }
        $preview[$cid]['products'][] = [
            'id'   => (int) $r['id'],
            'name' => (string) $r['name'],
        ];
        $summary['products']++;
    }
}

// ── POST: actually wipe ──────────────────────────────────────────────
$flash = null;
$flashErr = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $confirm   = trim((string) ($_POST['confirm']    ?? ''));
    $tenantIds = $_POST['tenants'] ?? [];
    if (!is_array($tenantIds)) $tenantIds = [];
    $tenantIds = array_values(array_unique(array_filter(
        array_map('intval', $tenantIds),
        static fn ($n) => $n > 0
    )));

    if ($filter === '') {
        $flashErr = 'Name filter required — refuse to wipe every product.';
    } elseif (strtoupper($confirm) !== 'WIPE') {
        $flashErr = 'Type the word WIPE in the confirmation field.';
    } elseif (!$tenantIds) {
        $flashErr = 'No tenants selected.';
    } else {
        $like = '%' . $filter . '%';
        $ph   = implode(',', array_fill(0, count($tenantIds), '?'));
        $del  = $pdo->prepare(
            "DELETE FROM products WHERE name LIKE ? AND client_id IN ($ph)"
        );
        $del->execute(array_merge([$like], $tenantIds));
        $deleted = $del->rowCount();

        $flash = "Wiped {$deleted} product"
               . ($deleted === 1 ? '' : 's')
               . " across " . count($tenantIds) . " tenant"
               . (count($tenantIds) === 1 ? '' : 's')
               . ". Cascade dropped systems / fabrics / price tables / extras for each.";

        // Reset the preview so the page re-renders with the wipe done.
        $preview = [];
        $summary = ['tenants' => 0, 'products' => 0];
    }
}

$activeNav = 'master-admin';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wipe products &middot; Master admin</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .danger-box {
            background: #fef2f2; border: 1px solid #fca5a5;
            border-radius: 10px; padding: 1rem 1.25rem;
            color: #7f1d1d; margin-bottom: 1.25rem;
        }
        [data-theme="dark"] .danger-box {
            background: rgba(127, 29, 29, 0.25);
            border-color: rgba(248, 113, 113, 0.4);
            color: #fca5a5;
        }
        .tenant-row {
            display: grid; grid-template-columns: 2.25rem 1fr;
            gap: 0.625rem;
            padding: 0.625rem 0.75rem;
            border-bottom: 1px solid var(--border);
        }
        .tenant-row label { cursor: pointer; }
        .tenant-row .company { font-weight: 600; color: var(--text-primary); }
        .tenant-row .products { color: var(--text-muted); font-size: 0.8125rem; margin-top: 0.25rem; line-height: 1.5; }
        .filter-form input[type="search"] {
            padding: 0.5rem 0.625rem; font: inherit;
            border: 1px solid var(--border-strong); border-radius: 6px;
            background: var(--bg-input); color: var(--text-body);
            min-width: 16rem;
        }
        .confirm-input {
            padding: 0.5rem 0.625rem; font: inherit; font-weight: 700;
            border: 1px solid var(--border-strong); border-radius: 6px;
            background: var(--bg-input); color: var(--text-body);
            width: 10rem;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Wipe products</h1>
                <p class="page-subtitle">
                    Bulk-delete matching products across tenants.
                    Cascade drops every system, fabric, option and
                    price table tied to them. Quotes raised before
                    the wipe keep working (they snapshot their own
                    data).
                </p>
            </div>
        </div>

        <?php if ($flash !== null): ?>
            <div class="alert alert-success" role="status">
                &check; <?= e($flash) ?>
            </div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert">
                <?= e($flashErr) ?>
            </div>
        <?php endif; ?>

        <div class="danger-box">
            <strong>Danger zone.</strong>
            This deletes from the live database. Test-phase only — once
            real quotes start landing, prefer per-tenant deletes via the
            normal admin UI so each tenant admin sees what's happening.
        </div>

        <section class="section">
            <h2 class="section-title" style="margin-bottom:0.75rem">1. Filter</h2>
            <p style="margin:0 0 0.625rem;color:var(--text-faint);font-size:0.875rem">
                Name match is <code>LIKE %X%</code> — e.g. typing
                <code>Beverley</code> matches any product whose name
                contains "Beverley". Case-insensitive.
            </p>
            <form method="get" action="/master-admin/wipe-products.php"
                  class="filter-form" style="display:flex;gap:0.5rem;align-items:center">
                <input type="search" name="filter" autofocus
                       value="<?= e($filter) ?>"
                       placeholder="e.g. Beverley">
                <button type="submit" class="btn btn-secondary">Preview</button>
            </form>
        </section>

        <?php if ($filter !== '' && !$preview && $flash === null): ?>
            <section class="section">
                <p style="color:var(--text-faint);font-size:0.9375rem;margin:0">
                    No products match <code><?= e($filter) ?></code> on any tenant.
                </p>
            </section>
        <?php endif; ?>

        <?php if ($preview): ?>
            <form method="post" action="/master-admin/wipe-products.php">
                <?= csrf_field() ?>
                <input type="hidden" name="filter" value="<?= e($filter) ?>">

                <section class="section">
                    <h2 class="section-title" style="margin-bottom:0.5rem">
                        2. Tenants with matches
                        <span style="color:var(--text-faint);font-weight:400;font-size:0.875rem">
                            (<?= (int) $summary['tenants'] ?> tenants,
                            <?= (int) $summary['products'] ?> products)
                        </span>
                    </h2>
                    <p style="margin:0 0 0.75rem;color:var(--text-faint);font-size:0.875rem">
                        Untick any tenant you want to leave alone.
                        <button type="button" id="toggle-all"
                                style="background:transparent;border:0;color:var(--link);cursor:pointer;font:inherit;text-decoration:underline">
                            toggle all
                        </button>
                    </p>
                    <div style="border:1px solid var(--border);border-radius:8px;overflow:hidden;background:var(--bg-card)">
                        <?php foreach ($preview as $p): ?>
                            <div class="tenant-row">
                                <div>
                                    <input type="checkbox" name="tenants[]"
                                           class="tenant-cb"
                                           value="<?= (int) $p['client_id'] ?>"
                                           id="t<?= (int) $p['client_id'] ?>" checked
                                           style="width:18px;height:18px;cursor:pointer">
                                </div>
                                <div>
                                    <label for="t<?= (int) $p['client_id'] ?>">
                                        <div class="company">
                                            <?= e((string) $p['company_name']) ?>
                                            <span style="color:var(--text-faint);font-weight:400;font-size:0.8125rem">
                                                (client #<?= (int) $p['client_id'] ?>)
                                            </span>
                                        </div>
                                        <div class="products">
                                            <?php
                                                $names = array_map(static fn ($x) => $x['name'], $p['products']);
                                                echo e(implode(', ', $names));
                                            ?>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="section">
                    <h2 class="section-title" style="margin-bottom:0.5rem">3. Confirm</h2>
                    <p style="margin:0 0 0.625rem;color:var(--text-faint);font-size:0.875rem">
                        Type <strong>WIPE</strong> in the box and click Wipe.
                        Cascade-delete is immediate and unrecoverable
                        without a backup restore.
                    </p>
                    <div style="display:flex;gap:0.625rem;align-items:center;flex-wrap:wrap">
                        <input type="text" name="confirm" class="confirm-input"
                               autocomplete="off" placeholder="Type WIPE">
                        <button type="submit" class="btn btn-danger"
                                style="padding:0.5rem 1.125rem">
                            Wipe selected
                        </button>
                    </div>
                </section>
            </form>

            <script>
            (function () {
                var toggle = document.getElementById('toggle-all');
                var cbs    = document.querySelectorAll('.tenant-cb');
                if (toggle) {
                    toggle.addEventListener('click', function () {
                        var allChecked = Array.prototype.every.call(cbs, function (c) {
                            return c.checked;
                        });
                        cbs.forEach(function (c) { c.checked = !allChecked; });
                    });
                }
            })();
            </script>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
