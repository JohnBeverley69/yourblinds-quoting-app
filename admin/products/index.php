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
// Category grouping is optional (migrate_product_categories.php). Probe so the
// page works before and after the migration.
$hasCategories = false;
try {
    db()->query('SELECT 1 FROM product_categories LIMIT 0');
    $colChk = db()->query(
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
            AND COLUMN_NAME = 'category_id' LIMIT 1"
    );
    $hasCategories = $colChk->fetchColumn() !== false;
} catch (Throwable $e) {
    $hasCategories = false;
}

$catSelect = $hasCategories ? 'p.category_id,' : 'NULL AS category_id,';
$rows = db()->prepare(
    'SELECT p.id, p.name, p.sort_order, p.active, p.updated_at, p.requires_option,
            ' . $catSelect . '
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

// Categories for this tenant, and products grouped by them (0 = ungrouped).
$categories  = [];
$catNameById = [];
if ($hasCategories) {
    $cs = db()->prepare('SELECT id, name FROM product_categories WHERE client_id = ? ORDER BY sort_order, name');
    $cs->execute([$clientId]);
    $categories = $cs->fetchAll();
    foreach ($categories as $c) $catNameById[(int) $c['id']] = (string) $c['name'];
}
$grouped = [];
foreach ($products as $p) {
    $cid = $hasCategories ? (int) ($p['category_id'] ?? 0) : 0;
    if ($cid > 0 && !isset($catNameById[$cid])) $cid = 0;   // stale id → ungrouped
    $grouped[$cid][] = $p;
}

// "Ready to quote" = has at least one fabric AND at least one price
// table. Systems and Options are nice-to-have but not strictly
// required. The same check feeds the status pill on each row.
// A no-fabric product (requires_option = 0, e.g. a headrail) doesn't need
// an option/fabric to be quote-ready — just an active price table.
$isQuoteReady = static function (array $p): bool {
    $needsFabric = !isset($p['requires_option']) || (int) $p['requires_option'] === 1;
    return (!$needsFabric || (int) $p['option_count'] > 0)
        && (int) $p['price_table_count'] > 0
        && (int) $p['active'] === 1;
};

// Friendly relative-time formatter — shared partial used by every
// admin/products grid that has an Updated column.
require_once __DIR__ . '/../../_partials/time_ago.php';

// Render one product row. Shared across category groups so the markup stays
// in one place. Captures the quote-ready check + the category list.
$renderRow = function (array $p) use ($isQuoteReady, $categories, $hasCategories): void {
    $ready  = $isQuoteReady($p);
    $ptHref = !empty($p['solo_system_id'])
        ? '/admin/products/price-tables.php?system_id=' . (int) $p['solo_system_id']
        : '/admin/products/systems.php?product_id='     . (int) $p['id'];
    $fixHref = null;   // when not ready, where clicking the status takes you
    if ((int) $p['active'] !== 1) {
        $statusBg = 'var(--border)'; $statusFg = 'var(--text-secondary)'; $statusLabel = 'Inactive';
    } elseif ($ready) {
        $statusBg = '#d1fae5'; $statusFg = '#065f46'; $statusLabel = '✓ Ready';
    } else {
        $statusBg = '#fef3c7'; $statusFg = '#78350f';
        $needsFabric = !isset($p['requires_option']) || (int) $p['requires_option'] === 1;
        $missing = [];
        if ($needsFabric && (int) $p['option_count'] === 0) $missing[] = 'fabric';
        if ((int) $p['price_table_count'] === 0) $missing[] = 'price table';
        $statusLabel = 'Needs ' . implode(' + ', $missing);
        // Click the pill to jump straight to what's missing: fabrics first, else price tables.
        $fixHref = ($needsFabric && (int) $p['option_count'] === 0)
            ? '/admin/products/options.php?product_id=' . (int) $p['id']
            : $ptHref;
    }
    $cid = $hasCategories ? (int) ($p['category_id'] ?? 0) : 0;
    ?>
    <tr data-id="<?= (int) $p['id'] ?>" draggable="true">
        <td class="check-col"><input type="checkbox" class="bulk-row" value="<?= (int) $p['id'] ?>" aria-label="Select <?= e((string) $p['name']) ?>"></td>
        <td class="drag-col" title="Drag to reorder — or onto a group to file it">⋮⋮</td>
        <td>
            <a href="/admin/products/edit.php?id=<?= (int) $p['id'] ?>" class="product-name" style="text-decoration:none">
                <?= e((string) $p['name']) ?>
            </a>
        </td>
        <td>
            <?php if ($fixHref !== null): ?>
                <a href="<?= e($fixHref) ?>" title="Click to add what's missing"
                   style="display:inline-block;padding:0.1875rem 0.625rem;background:<?= $statusBg ?>;color:<?= $statusFg ?>;border-radius:999px;font-size:0.75rem;font-weight:600;white-space:nowrap;text-decoration:none">
                    <?= e($statusLabel) ?> &rarr;
                </a>
            <?php else: ?>
                <span style="display:inline-block;padding:0.1875rem 0.625rem;background:<?= $statusBg ?>;color:<?= $statusFg ?>;border-radius:999px;font-size:0.75rem;font-weight:600;white-space:nowrap">
                    <?= e($statusLabel) ?>
                </span>
            <?php endif; ?>
        </td>
        <td class="num"><a href="/admin/products/options.php?product_id=<?= (int) $p['id'] ?>"><?= (int) $p['option_count'] ?></a></td>
        <td class="num"><a href="/admin/products/systems.php?product_id=<?= (int) $p['id'] ?>"><?= (int) $p['system_count'] ?></a></td>
        <td class="num"><a href="/admin/products/extras.php?product_id=<?= (int) $p['id'] ?>"><?= (int) $p['extra_count'] ?></a></td>
        <td class="num"><a href="<?= e($ptHref) ?>"><?= (int) $p['price_table_count'] ?></a></td>
        <?php if ($hasCategories): ?>
            <td>
                <form method="post" action="/admin/products/set-category.php" style="margin:0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="assign">
                    <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                    <select name="category_id" onchange="this.form.submit()" class="group-select">
                        <option value="0"<?= $cid === 0 ? ' selected' : '' ?>>— Ungrouped —</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"<?= $cid === (int) $c['id'] ? ' selected' : '' ?>>
                                <?= e((string) $c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </td>
        <?php endif; ?>
        <td class="meta-cell" title="<?= e((string) $p['updated_at']) ?>"><?= e(time_ago((string) $p['updated_at'])) ?></td>
        <td class="row-actions">
            <?php $isActive = (int) $p['active'] === 1; ?>
            <form method="post" action="/admin/products/set-active.php" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="active" value="<?= $isActive ? 0 : 1 ?>">
                <button type="submit" style="color:<?= $isActive ? '#92400e' : '#15803d' ?>">
                    <?= $isActive ? 'Deactivate' : 'Activate' ?>
                </button>
            </form>
            <form method="post" action="/admin/products/duplicate.php" style="display:inline"
                  data-confirm="Duplicate <?= e((string) $p['name']) ?>? Creates a full copy (systems, fabrics, options, choices, price tables) with '(copy)' appended to the name.">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button type="submit" style="color:#1f3b5b">Duplicate</button>
            </form>
            <form method="post" action="/admin/products/delete.php" style="display:inline"
                  data-confirm="Delete <?= e((string) $p['name']) ?>? This removes all options, extras, and price tables linked to it. Cannot be undone.">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button type="submit">Delete</button>
            </form>
        </td>
    </tr>
    <?php
};

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Products &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .meta-cell { font-size: 0.8125rem; color: var(--text-faint); white-space: nowrap; }
        .row-actions { white-space: nowrap; }
        .row-actions a { font-size: 0.875rem; margin-left: 0.5rem; }
        .row-actions form { display: inline; margin: 0; }
        .row-actions button {
            font-size: 0.875rem; color: #b91c1c; background: transparent;
            border: 0; cursor: pointer; padding: 0; margin-left: 0.5rem;
        }
        .row-actions button:hover { text-decoration: underline; }
        .product-name { font-weight: 600; color: var(--text-primary); }
        a.product-name:hover { color: #1f3b5b; text-decoration: underline; }
        .inactive-pill {
            display: inline-block; padding: 0.0625rem 0.5rem;
            font-size: 0.6875rem; font-weight: 600; color: var(--text-faint);
            background: var(--bg-subtle-2); border-radius: 999px; margin-left: 0.5rem;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .cat-heading {
            font-size: 1rem; color: var(--text-primary); margin: 1.25rem 0 0.5rem;
            display: flex; align-items: center; gap: 0.4rem;
        }
        .cat-heading:first-of-type { margin-top: 0.25rem; }
        .cat-count {
            font-size: 0.75rem; font-weight: 600; color: var(--text-faint);
            background: var(--bg-subtle-2); border-radius: 999px; padding: 0.0625rem 0.5rem;
        }
        .cat-del {
            background: transparent; border: 0; color: var(--text-faint);
            cursor: pointer; font-size: 0.75rem; text-decoration: underline; padding: 0;
        }
        .cat-del:hover { color: #b91c1c; }
        .cat-bulk { background: transparent; border: 0; cursor: pointer; font-size: 0.75rem; text-decoration: underline; padding: 0; }
        .cat-bulk:hover { opacity: 0.8; }
        .cat-toggle {
            background: transparent; border: 0; cursor: pointer; color: var(--text-faint);
            font-size: 0.7rem; padding: 0; margin-right: 0.1rem; transition: transform 120ms; transform-origin: center;
        }
        .cat-toggle.expanded { transform: rotate(90deg); }
        .group-body.collapsed { display: none; }
        .grp-tools { display: flex; gap: 0.5rem; margin: 0 0 0.5rem; }
        .grp-tools button {
            background: transparent; border: 0; color: var(--link); cursor: pointer;
            font-size: 0.8125rem; text-decoration: underline; padding: 0;
        }
        .cat-draggable { cursor: grab; }
        .cat-draggable:active { cursor: grabbing; }
        .cat-heading.dragging { opacity: 0.5; }
        .cat-grip { color: var(--text-faint); font-size: 0.9rem; margin-right: 0.1rem; }
        .group-select {
            padding: 0.25rem 0.4rem; font: inherit; font-size: 0.8125rem;
            border: 1px solid var(--border-strong); border-radius: 6px;
            background: var(--bg-input); color: var(--text-body); max-width: 11rem;
        }
        .check-col { width: 1.5rem; text-align: center; }
        .check-col input { cursor: pointer; margin: 0; }
        .drag-col { cursor: grab; color: var(--text-faint); width: 1.5rem; text-align: center; user-select: none; }
        .drag-col:active { cursor: grabbing; }
        .bulk-bar { display: flex; gap: 0.625rem; align-items: center; flex-wrap: wrap; margin: 0 0 0.75rem; }
        tr.dragging { opacity: 0.45; }
        .drop-zone { border-radius: 10px; padding: 0.25rem 0.5rem; margin: 0 -0.5rem; transition: background 80ms; }
        .drop-zone.drop-hover { background: rgba(37, 99, 235, 0.07); outline: 2px dashed #2563eb; outline-offset: -2px; }
        .drop-empty {
            color: var(--text-faint); font-size: 0.875rem; margin: 0 0 1rem;
            padding: 0.75rem; border: 1px dashed var(--border); border-radius: 8px;
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
                            background:#f8fafc;border:1px solid var(--border);
                            border-radius:12px">
                    <div style="font-size:2.5rem;margin-bottom:0.5rem">🪟</div>
                    <h2 style="margin:0 0 0.375rem;color:#1f3b5b;font-size:1.25rem">
                        Set up your first product
                    </h2>
                    <p style="margin:0 0 1.25rem;color:var(--text-muted);font-size:0.9375rem;max-width:34rem;margin-left:auto;margin-right:auto;line-height:1.55">
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
                           style="color:var(--text-faint);font-size:0.8125rem;text-decoration:underline">
                            Skip the wizard &mdash; just add a product
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php if (!$hasCategories): ?>
                    <div class="alert alert-error" role="alert">
                        Product grouping isn't enabled yet — run
                        <a href="/migrate_product_categories.php"><code>/migrate_product_categories.php</code></a>
                        (super-admin) to file products under headings like "Woods".
                    </div>
                <?php else: ?>
                    <form method="post" action="/admin/products/set-category.php"
                          style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;margin:0 0 1rem">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="create">
                        <input type="text" name="name" class="form-control" maxlength="120"
                               placeholder="New group name (e.g. Woods)" style="max-width:18rem">
                        <button type="submit" class="btn btn-secondary">+ Add group</button>
                        <span style="color:var(--text-faint);font-size:0.8125rem">
                            Then use the <strong>Group</strong> dropdown on each row to file it. Products keep everything they have.
                        </span>
                    </form>
                <?php endif; ?>

                <?php
                    // Shared table renderer for a set of product rows.
                    $renderTable = function (array $rowsToShow) use ($renderRow, $hasCategories): void {
                        ?>
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th class="check-col"><input type="checkbox" class="bulk-all" aria-label="Select all in this table"></th>
                                        <th class="drag-col"></th>
                                        <th>Name</th>
                                        <th>Status</th>
                                        <th class="num">Fabrics</th>
                                        <th class="num">Systems</th>
                                        <th class="num">Options</th>
                                        <th class="num">Price tables</th>
                                        <?php if ($hasCategories): ?><th>Group</th><?php endif; ?>
                                        <th>Updated</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rowsToShow as $p) $renderRow($p); ?>
                                </tbody>
                            </table>
                        </div>
                        <?php
                    };
                ?>

                <!-- Bulk actions. The row checkboxes live inside the product
                     tables (which already contain per-row action forms, so we
                     can't wrap them in a form); JS keeps each bulk form's
                     hidden ids in sync with the ticked boxes. -->
                <div class="bulk-bar">
                    <span id="bulk-count" style="color:var(--text-faint);font-size:0.8125rem">(none selected)</span>
                    <?php if ($hasCategories && $categories): ?>
                        <form method="post" action="/admin/products/set-category.php" id="bulk-move-form"
                              style="display:inline-flex;gap:0.4rem;align-items:center;margin:0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="assign">
                            <select name="category_id" id="bulk-move-select" class="group-select" disabled
                                    aria-label="Move selected products to a group">
                                <option value="">Move selected to&hellip;</option>
                                <option value="0">&mdash; Ungrouped &mdash;</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>"><?= e((string) $c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="/admin/products/delete.php" id="bulk-delete-form"
                          data-confirm="Delete the selected products?" style="display:inline;margin:0">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger" id="bulk-delete-btn"
                                style="padding:0.3125rem 0.875rem;font-size:0.875rem" disabled>Delete selected</button>
                    </form>
                    <button type="button" id="bulk-clear"
                            style="background:transparent;border:0;color:var(--link);cursor:pointer;font-size:0.8125rem;text-decoration:underline;padding:0;display:none">Clear</button>
                </div>

                <?php if ($hasCategories && $categories): ?>
                    <div class="grp-tools">
                        <button type="button" id="grp-expand-all">Expand all</button>
                        <span style="color:var(--text-faint)">·</span>
                        <button type="button" id="grp-collapse-all">Collapse all</button>
                    </div>
                    <p style="color:var(--text-faint);font-size:.8125rem;margin:0 0 .75rem">
                        Click a group's <strong>&#9654;</strong> to show its products. Drag the <strong>⋮⋮</strong> handle onto a group to file a product (or use the <strong>Group</strong> dropdown).
                    </p>
                    <?php foreach ($categories as $c): $cidd = (int) $c['id']; $gRows = $grouped[$cidd] ?? []; ?>
                        <div class="drop-zone" data-cat="<?= $cidd ?>">
                            <h2 class="cat-heading cat-draggable" draggable="true" data-cat="<?= $cidd ?>" title="Drag to reorder groups">
                                <button type="button" class="cat-toggle" data-target="gb-<?= $cidd ?>" draggable="false" aria-label="Show or hide products">&#9654;</button>
                                <span class="cat-grip" aria-hidden="true">⠿</span>
                                <?= e((string) $c['name']) ?>
                                <span class="cat-count"><?= count($gRows) ?></span>
                                <?php if ($gRows): ?>
                                    <form method="post" action="/admin/products/set-category.php" style="display:inline;margin-left:.5rem"
                                          data-confirm="Deactivate all <?= count($gRows) ?> products in &quot;<?= e((string) $c['name']) ?>&quot;? They'll be hidden from new quotes (reversible).">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_action" value="set_group_active">
                                        <input type="hidden" name="category_id" value="<?= $cidd ?>">
                                        <input type="hidden" name="active" value="0">
                                        <button type="submit" class="cat-bulk" style="color:#92400e">Deactivate all</button>
                                    </form>
                                    <form method="post" action="/admin/products/set-category.php" style="display:inline;margin-left:.35rem">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_action" value="set_group_active">
                                        <input type="hidden" name="category_id" value="<?= $cidd ?>">
                                        <input type="hidden" name="active" value="1">
                                        <button type="submit" class="cat-bulk" style="color:#15803d">Activate all</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="/admin/products/set-category.php" style="display:inline;margin-left:.5rem"
                                      data-confirm="Delete the group &quot;<?= e((string) $c['name']) ?>&quot;? Its products are NOT deleted — they just become ungrouped.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_action" value="delete">
                                    <input type="hidden" name="category_id" value="<?= $cidd ?>">
                                    <button type="submit" class="cat-del">remove group</button>
                                </form>
                            </h2>
                            <div class="group-body collapsed" id="gb-<?= $cidd ?>">
                                <?php if ($gRows): $renderTable($gRows); else: ?>
                                    <p class="drop-empty">Empty — drag a product's <strong>⋮⋮</strong> handle here, or pick this group from its <strong>Group</strong> dropdown.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php $ung = $grouped[0] ?? []; ?>
                    <div class="drop-zone" data-cat="0">
                        <h2 class="cat-heading">
                            <button type="button" class="cat-toggle expanded" data-target="gb-0" draggable="false" aria-label="Show or hide products">&#9654;</button>
                            Ungrouped <span class="cat-count"><?= count($ung) ?></span>
                        </h2>
                        <div class="group-body" id="gb-0">
                            <?php if ($ung): $renderTable($ung); else: ?>
                                <p class="drop-empty">Nothing ungrouped — drag a product's <strong>⋮⋮</strong> handle here to remove it from its group.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php $renderTable($products); ?>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php if ($products && $hasCategories): ?>
    <!-- Hidden forms the drop handlers submit: file a product, or reorder groups. -->
    <form id="dnd-form" method="post" action="/admin/products/set-category.php" style="display:none">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="assign">
        <input type="hidden" name="product_id" value="">
        <input type="hidden" name="category_id" value="">
    </form>
    <form id="group-order-form" method="post" action="/admin/products/set-category.php" style="display:none">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="reorder_groups">
    </form>
    <script>
    (function () {
        var dndForm   = document.getElementById('dnd-form');
        var orderForm = document.getElementById('group-order-form');
        var zones = Array.prototype.slice.call(document.querySelectorAll('.drop-zone'));
        var dragType = null, dragId = null;

        function clearHover() { zones.forEach(function (z) { z.classList.remove('drop-hover'); }); }

        // Drag a PRODUCT row → file it into a group.
        document.querySelectorAll('tr[draggable="true"]').forEach(function (tr) {
            tr.addEventListener('dragstart', function (e) {
                dragType = 'product'; dragId = tr.getAttribute('data-id');
                e.dataTransfer.effectAllowed = 'move';
                try { e.dataTransfer.setData('text/plain', dragId); } catch (err) {}
                tr.classList.add('dragging');
            });
            tr.addEventListener('dragend', function () { tr.classList.remove('dragging'); clearHover(); });
        });

        // Drag a GROUP heading → reorder the groups.
        document.querySelectorAll('.cat-draggable').forEach(function (h) {
            h.addEventListener('dragstart', function (e) {
                dragType = 'group'; dragId = h.getAttribute('data-cat');
                e.dataTransfer.effectAllowed = 'move';
                try { e.dataTransfer.setData('text/plain', 'g' + dragId); } catch (err) {}
                h.classList.add('dragging');
            });
            h.addEventListener('dragend', function () { h.classList.remove('dragging'); clearHover(); });
        });

        zones.forEach(function (z) {
            z.addEventListener('dragover', function (e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                z.classList.add('drop-hover');
            });
            z.addEventListener('dragleave', function (e) {
                if (!z.contains(e.relatedTarget)) z.classList.remove('drop-hover');
            });
            z.addEventListener('drop', function (e) {
                e.preventDefault();
                clearHover();
                var cat = z.getAttribute('data-cat');

                if (dragType === 'group') {
                    if (!dragId || dragId === cat) return;
                    // Build the new order of category groups (skip the '0' Ungrouped zone).
                    var order = zones.map(function (zz) { return zz.getAttribute('data-cat'); })
                                     .filter(function (c) { return c !== '0'; })
                                     .filter(function (c) { return c !== dragId; });
                    if (cat === '0') {
                        order.push(dragId);                       // dropped on Ungrouped → bottom
                    } else {
                        var ti = order.indexOf(cat);
                        if (ti < 0) order.push(dragId); else order.splice(ti, 0, dragId);
                    }
                    orderForm.querySelectorAll('input[name="order[]"]').forEach(function (n) { n.remove(); });
                    order.forEach(function (id) {
                        var inp = document.createElement('input');
                        inp.type = 'hidden'; inp.name = 'order[]'; inp.value = id;
                        orderForm.appendChild(inp);
                    });
                    orderForm.submit();
                    return;
                }

                // Product assign (default).
                if (!dragId) return;
                var row = document.querySelector('tr[data-id="' + dragId + '"]');
                if (row && row.closest('.drop-zone') === z) return;   // same group → no-op
                dndForm.querySelector('[name=product_id]').value = dragId;
                dndForm.querySelector('[name=category_id]').value = cat;
                dndForm.submit();
            });
        });
    })();
    </script>
<?php endif; ?>
<?php if ($hasCategories): ?>
    <script>
    (function () {
        function setState(btn, body, open) {
            body.classList.toggle('collapsed', !open);
            btn.classList.toggle('expanded', open);
        }
        document.querySelectorAll('.cat-toggle').forEach(function (btn) {
            var body = document.getElementById(btn.dataset.target);
            if (!body) return;
            var key = 'pg-' + btn.dataset.target;
            var stored = localStorage.getItem(key);
            if (stored === 'open')   setState(btn, body, true);
            if (stored === 'closed') setState(btn, body, false);
            // Don't let interacting with the caret start a group drag.
            btn.addEventListener('mousedown', function (e) { e.stopPropagation(); });
            btn.addEventListener('click', function (e) {
                e.stopPropagation(); e.preventDefault();
                var opening = body.classList.contains('collapsed');
                setState(btn, body, opening);
                localStorage.setItem(key, opening ? 'open' : 'closed');
            });
        });
        function applyAll(open) {
            document.querySelectorAll('.cat-toggle').forEach(function (btn) {
                var body = document.getElementById(btn.dataset.target);
                if (!body) return;
                setState(btn, body, open);
                localStorage.setItem('pg-' + btn.dataset.target, open ? 'open' : 'closed');
            });
        }
        var ex = document.getElementById('grp-expand-all');
        var col = document.getElementById('grp-collapse-all');
        if (ex)  ex.addEventListener('click',  function () { applyAll(true); });
        if (col) col.addEventListener('click', function () { applyAll(false); });
    })();
    </script>
<?php endif; ?>
<?php if ($products): ?>
<script>
(function () {
    // ---------- Bulk select + delete ----------
    var form  = document.getElementById('bulk-delete-form');
    var btn   = document.getElementById('bulk-delete-btn');
    var count = document.getElementById('bulk-count');
    var clear = document.getElementById('bulk-clear');
    var moveForm = document.getElementById('bulk-move-form');
    var moveSel  = document.getElementById('bulk-move-select');
    function allRows() { return Array.prototype.slice.call(document.querySelectorAll('input.bulk-row')); }
    function syncIds(f, fieldName, sel) {
        f.querySelectorAll('input[name="' + fieldName + '"]').forEach(function (n) { n.remove(); });
        sel.forEach(function (c) {
            var i = document.createElement('input');
            i.type = 'hidden'; i.name = fieldName; i.value = c.value;
            f.appendChild(i);
        });
    }

    function refresh() {
        var sel = allRows().filter(function (c) { return c.checked; });
        syncIds(form, 'ids[]', sel);                 // delete form
        if (moveForm) {                              // move-to-group form
            syncIds(moveForm, 'product_ids[]', sel);
            moveSel.disabled = sel.length === 0;
            if (sel.length === 0) moveSel.value = '';
        }
        btn.disabled = sel.length === 0;
        count.textContent = sel.length ? (sel.length + ' selected') : '(none selected)';
        clear.style.display = sel.length ? '' : 'none';
        form.dataset.confirm = 'Delete ' + sel.length + ' selected product' + (sel.length === 1 ? '' : 's')
            + '? This removes all options, extras and price tables linked to them. Cannot be undone.';
        // Reflect each table's select-all state (checked / indeterminate).
        document.querySelectorAll('input.bulk-all').forEach(function (a) {
            var tbl = a.closest('table'); if (!tbl) return;
            var cs = tbl.querySelectorAll('input.bulk-row');
            var ck = tbl.querySelectorAll('input.bulk-row:checked');
            a.checked = cs.length > 0 && cs.length === ck.length;
            a.indeterminate = ck.length > 0 && ck.length < cs.length;
        });
    }
    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('bulk-row')) refresh();
        else if (e.target.classList.contains('bulk-all')) {
            var tbl = e.target.closest('table');
            if (tbl) tbl.querySelectorAll('input.bulk-row').forEach(function (c) { c.checked = e.target.checked; });
            refresh();
        }
    });

    // Shift-click range select: tick one, hold Shift and click another, and
    // every row between takes the second click's state — so filing a long run
    // is two clicks, not 72. Scoped to the clicked row's own table.
    var lastChecked = null;
    document.addEventListener('click', function (e) {
        var cb = e.target;
        if (!cb.classList || !cb.classList.contains('bulk-row')) return;
        if (e.shiftKey && lastChecked && lastChecked !== cb) {
            var tbl = cb.closest('table');
            var boxes = tbl ? Array.prototype.slice.call(tbl.querySelectorAll('input.bulk-row')) : allRows();
            var start = boxes.indexOf(lastChecked), end = boxes.indexOf(cb);
            if (start !== -1 && end !== -1) {
                if (start > end) { var t = start; start = end; end = t; }
                for (var i = start; i <= end; i++) boxes[i].checked = cb.checked;
            }
        }
        lastChecked = cb;
        refresh();
    });
    clear.addEventListener('click', function () { allRows().forEach(function (c) { c.checked = false; }); refresh(); });
    // Pick a group → move every ticked product into it (non-destructive, no confirm).
    if (moveSel) moveSel.addEventListener('change', function () {
        if (moveSel.value !== '' && allRows().some(function (c) { return c.checked; })) moveForm.submit();
    });

    // ---------- Drag-to-reorder products within a table ----------
    var CSRF = (document.querySelector('input[name="_csrf"]') || {}).value || '';
    var dragRow = null;
    document.querySelectorAll('table.table tbody').forEach(function (tbody) {
        tbody.querySelectorAll('tr[data-id]').forEach(function (tr) {
            tr.addEventListener('dragstart', function () { dragRow = tr; tr.classList.add('dragging'); });
            tr.addEventListener('dragend', function () {
                tr.classList.remove('dragging');
                if (dragRow && dragRow.parentNode === tbody) persist(tbody);
                dragRow = null;
            });
            tr.addEventListener('dragover', function (e) {
                if (!dragRow || dragRow === tr || dragRow.parentNode !== tbody) return;  // cross-table → leave to group filing
                e.preventDefault();
                e.stopPropagation();   // same-table reorder: don't let the group drop-zone highlight
                var r = tr.getBoundingClientRect();
                tbody.insertBefore(dragRow, (e.clientY - r.top) > r.height / 2 ? tr.nextSibling : tr);
            });
        });
    });
    function persist(tbody) {
        var ids = Array.prototype.map.call(tbody.querySelectorAll('tr[data-id]'),
            function (tr) { return tr.getAttribute('data-id'); });
        if (!ids.length) return;
        var fd = new FormData();
        fd.append('_csrf', CSRF); fd.append('type', 'products');
        ids.forEach(function (id) { fd.append('ids[]', id); });
        fetch('/admin/products/reorder.php?type=products', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); }).catch(function () {});
    }
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../../_partials/confirm_modal.php'; ?>
</body>
</html>
