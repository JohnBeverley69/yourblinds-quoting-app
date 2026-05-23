<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Pulls four counts per product so we can both display them AND
// derive a "is this product ready to quote?" status badge. Counts
// match the setup-checklist tiles on the per-product edit page so
// the two views agree.
//
// solo_system_id is the system's id when the product has exactly one
// active system — used to deep-link the Price tables column straight
// into that system's tables. NULL when 0 or 2+ systems, in which
// case we fall back to the systems list page.
$rows = db()->prepare(
    'SELECT p.id, p.name, p.sort_order, p.active, p.updated_at,
            (SELECT COUNT(*) FROM product_options o WHERE o.product_id = p.id AND o.active = 1) AS option_count,
            (SELECT COUNT(*) FROM product_extras  e WHERE e.product_id = p.id AND e.active = 1) AS extra_count,
            (SELECT COUNT(*) FROM product_systems s WHERE s.product_id = p.id AND s.active = 1) AS system_count,
            (SELECT COUNT(*) FROM price_tables   t WHERE t.product_id = p.id AND t.active = 1) AS price_table_count,
            (CASE
                WHEN (SELECT COUNT(*) FROM product_systems s WHERE s.product_id = p.id AND s.active = 1) = 1
                THEN (SELECT s.id FROM product_systems s WHERE s.product_id = p.id AND s.active = 1 LIMIT 1)
                ELSE NULL
             END) AS solo_system_id
       FROM products p
      WHERE p.client_id = ?
   ORDER BY p.sort_order, p.name'
);
$rows->execute([$clientId]);
$products = $rows->fetchAll();

// "Ready to quote" = has at least one fabric AND at least one price
// table. Systems and Options are nice-to-have but not strictly
// required. The same check feeds the status pill on each row.
$isQuoteReady = static function (array $p): bool {
    return (int) $p['option_count'] > 0
        && (int) $p['price_table_count'] > 0
        && (int) $p['active'] === 1;
};

// Friendly relative-time formatter — shared partial used by every
// admin/products grid that has an Updated column.
require_once __DIR__ . '/../../_partials/time_ago.php';

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Products &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .meta-cell { font-size: 0.8125rem; color: #6b7280; white-space: nowrap; }
        .row-actions { white-space: nowrap; }
        .row-actions a { font-size: 0.875rem; margin-left: 0.5rem; }
        .row-actions form { display: inline; margin: 0; }
        .row-actions button {
            font-size: 0.875rem; color: #b91c1c; background: transparent;
            border: 0; cursor: pointer; padding: 0; margin-left: 0.5rem;
        }
        .row-actions button:hover { text-decoration: underline; }
        .product-name { font-weight: 600; color: #111827; }
        a.product-name:hover { color: #1f3b5b; text-decoration: underline; }
        .inactive-pill {
            display: inline-block; padding: 0.0625rem 0.5rem;
            font-size: 0.6875rem; font-weight: 600; color: #6b7280;
            background: #f3f4f6; border-radius: 999px; margin-left: 0.5rem;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Products</h1>
                <p class="page-subtitle">
                    The product types you sell. Each product has its own options
                    (fabrics/slats), extras (control side, lining, etc.) and
                    price tables.
                </p>
            </div>
            <!-- Two ways to add a product: the wizard (guided, recommended
                 for the first few until the user gets a feel for the setup
                 surface) and the bare "+ New product" form (faster once
                 they know what they're doing). Wizard is the secondary
                 button so it doesn't compete with the primary action for
                 users with existing products. -->
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
                <a href="/admin/products/wizard.php" class="btn btn-secondary"
                   style="display:inline-flex;align-items:center;gap:0.4375rem">
                    <span aria-hidden="true">✨</span>
                    Setup wizard
                </a>
                <a href="/admin/products/new.php" class="btn btn-primary">+ New product</a>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <section class="section">
            <?php if (!$products): ?>
                <!--
                    Rich empty state for brand-new tenants. The first
                    impression of the app for someone setting up — has
                    to read as guidance, not a void. Three numbered
                    steps explain the whole onboarding flow in one
                    glance + a single big CTA to start.
                -->
                <div style="text-align:center;padding:2rem 1rem;
                            background:#f8fafc;border:1px solid #e5e7eb;
                            border-radius:12px">
                    <div style="font-size:2.5rem;margin-bottom:0.5rem">🪟</div>
                    <h2 style="margin:0 0 0.375rem;color:#1f3b5b;font-size:1.25rem">
                        Set up your first product
                    </h2>
                    <p style="margin:0 0 1.25rem;color:#4b5563;font-size:0.9375rem;max-width:34rem;margin-left:auto;margin-right:auto;line-height:1.55">
                        Products are the types of blind you sell &mdash; e.g. Roller,
                        Vertical, Roman, Metal Venetian. Each product gets its own
                        fabrics, systems, options and price tables.
                    </p>
                    <!--
                        Primary CTA = guided wizard (4 steps: Name → Systems
                        → Fabrics → done). Steers brand-new tenants away from
                        the "click around and figure out the rest" experience
                        that came before. The plain "skip wizard" link below
                        keeps the old new.php flow accessible for users who'd
                        rather wing it.
                    -->
                    <div style="display:flex;flex-direction:column;align-items:center;gap:0.625rem">
                        <a href="/admin/products/wizard.php" class="btn btn-primary"
                           style="padding:0.625rem 1.5rem;font-size:1rem;display:inline-flex;align-items:center;gap:0.5rem">
                            <span aria-hidden="true">✨</span>
                            Start the setup wizard
                        </a>
                        <a href="/admin/products/new.php"
                           style="color:#6b7280;font-size:0.8125rem;text-decoration:underline">
                            Skip the wizard &mdash; just add a product
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <p style="color:#6b7280;font-size:0.9375rem;margin:0 0 0.5rem">
                    Drag the <strong>⋮⋮</strong> handle on the left of any row to reorder.
                    The <strong>Status</strong> column tells you if a product is ready to quote yet.
                    <span class="reorder-status" id="reorder-status">Saving…</span>
                </p>
                <div class="table-wrap">
                    <table class="table sortable-list" data-reorder-type="products">
                        <thead>
                            <tr>
                                <th class="drag-col"></th>
                                <th>Name</th>
                                <th>Status</th>
                                <th class="num">Fabrics</th>
                                <th class="num">Systems</th>
                                <th class="num">Options</th>
                                <th class="num">Price tables</th>
                                <th>Updated</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p):
                                $ready = $isQuoteReady($p);
                                if ((int) $p['active'] !== 1) {
                                    $statusBg    = '#e5e7eb'; $statusFg = '#374151';
                                    $statusLabel = 'Inactive';
                                } elseif ($ready) {
                                    $statusBg    = '#d1fae5'; $statusFg = '#065f46';
                                    $statusLabel = '✓ Ready';
                                } else {
                                    $statusBg    = '#fef3c7'; $statusFg = '#78350f';
                                    // Spell out what's missing so they don't have
                                    // to click in to find out.
                                    $missing = [];
                                    if ((int) $p['option_count']      === 0) $missing[] = 'fabric';
                                    if ((int) $p['price_table_count'] === 0) $missing[] = 'price table';
                                    $statusLabel = 'Needs ' . implode(' + ', $missing);
                                }
                            ?>
                                <tr data-id="<?= (int) $p['id'] ?>">
                                    <td class="drag-col" title="Drag to reorder">⋮⋮</td>
                                    <td>
                                        <a href="/admin/products/edit.php?id=<?= (int) $p['id'] ?>"
                                           class="product-name"
                                           style="text-decoration:none">
                                            <?= e((string) $p['name']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span style="display:inline-block;padding:0.1875rem 0.625rem;
                                                     background:<?= $statusBg ?>;color:<?= $statusFg ?>;
                                                     border-radius:999px;font-size:0.75rem;font-weight:600;
                                                     white-space:nowrap">
                                            <?= e($statusLabel) ?>
                                        </span>
                                    </td>
                                    <td class="num">
                                        <a href="/admin/products/options.php?product_id=<?= (int) $p['id'] ?>">
                                            <?= (int) $p['option_count'] ?>
                                        </a>
                                    </td>
                                    <td class="num">
                                        <a href="/admin/products/systems.php?product_id=<?= (int) $p['id'] ?>">
                                            <?= (int) $p['system_count'] ?>
                                        </a>
                                    </td>
                                    <td class="num">
                                        <a href="/admin/products/extras.php?product_id=<?= (int) $p['id'] ?>">
                                            <?= (int) $p['extra_count']  ?>
                                        </a>
                                    </td>
                                    <td class="num">
                                        <?php
                                            // price-tables.php is system-scoped — go direct
                                            // if there's exactly one system, else route
                                            // through the systems list so the user can pick.
                                            $ptHref = !empty($p['solo_system_id'])
                                                ? '/admin/products/price-tables.php?system_id=' . (int) $p['solo_system_id']
                                                : '/admin/products/systems.php?product_id='     . (int) $p['id'];
                                        ?>
                                        <a href="<?= e($ptHref) ?>">
                                            <?= (int) $p['price_table_count'] ?>
                                        </a>
                                    </td>
                                    <td class="meta-cell"
                                        title="<?= e((string) $p['updated_at']) ?>">
                                        <?= e(time_ago((string) $p['updated_at'])) ?>
                                    </td>
                                    <td class="row-actions">
                                        <!--
                                            Duplicate spawns a deep-copy of every
                                            row tied to this product (systems,
                                            fabrics, options, choices, price
                                            tables, markups, discounts) and
                                            drops the user on the new product's
                                            edit page. Most useful when adding
                                            a variant of an existing product —
                                            e.g. "Premium" from "Standard".
                                        -->
                                        <form method="post"
                                              action="/admin/products/duplicate.php"
                                              style="display:inline"
                                              data-confirm="Duplicate <?= e((string) $p['name']) ?>? Creates a full copy (systems, fabrics, options, choices, price tables) with '(copy)' appended to the name.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <button type="submit" style="color:#1f3b5b">Duplicate</button>
                                        </form>
                                        <form method="post"
                                              action="/admin/products/delete.php"
                                              style="display:inline"
                                              data-confirm="Delete <?= e((string) $p['name']) ?>? This removes all options, extras, and price tables linked to it. Cannot be undone.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <button type="submit">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php if ($products): require __DIR__ . '/../../_partials/sortable_init.php'; endif; ?>
<?php require __DIR__ . '/../../_partials/confirm_modal.php'; ?>
</body>
</html>
