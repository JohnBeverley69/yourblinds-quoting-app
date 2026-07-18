<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$systemId = (int) ($_GET['system_id'] ?? 0);
if ($systemId <= 0) {
    header('Location: /admin/products/index.php');
    exit;
}

// Tenant-scoped lookup of the system + its parent product.
$sysStmt = db()->prepare(
    'SELECT s.id, s.name AS system_name, s.product_id,
            p.name AS product_name, p.option_label AS product_option_label
       FROM product_systems s
       JOIN products p ON p.id = s.product_id
      WHERE s.id = ? AND s.client_id = ?'
);
$sysStmt->execute([$systemId, $clientId]);
$system = $sysStmt->fetch();

if (!$system) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>System not found</h1>';
    exit;
}
$productId = (int) $system['product_id'];

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Schema-aware: is the sort_order column present?
// (migrate_price_tables_sort_order.php). Used by both POST handlers
// to set sort_order on new bands and by the list query to render in
// drag-order. Cached once at the top so the inserts and the render
// both see the same answer.
$hasPtSortOrder = false;
try {
    $hasPtSortOrder = (bool) db()->query(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'price_tables'
            AND COLUMN_NAME  = 'sort_order'"
    )->fetchColumn();
} catch (Throwable $e) { /* keep false */ }

$f = ['band_code' => '', 'name' => '', 'notes' => ''];
$error = null;

// Bulk-add: paste a list of band codes, each line becomes an empty
// price table on this system. Same pattern as the wizard / fabric
// bulk-add. Skips duplicates against the unique constraint silently
// so one collision doesn't fail the whole batch.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_action'] ?? '') === 'add_bulk') {
    csrf_check();

    $raw   = (string) ($_POST['bulk_bands'] ?? '');
    $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
    $bands = [];
    $tooLong = 0;
    foreach ($lines as $line) {
        // Allow comma-separated on a single line too — saves users
        // having to reformat their paste if they have "A, B, C, D".
        foreach (preg_split('/,/', $line) ?: [] as $piece) {
            $b = trim((string) preg_replace('/^band\s+/i', '', $piece));
            if ($b === '') continue;
            if (strlen($b) > 60) { $tooLong++; continue; }   // report, don't drop silently
            $bands[] = $b;
        }
    }

    if (!$bands) {
        $error = $tooLong > 0
            ? 'Those band codes are too long (max 60 characters) — shorten them and try again.'
            : 'Paste at least one band code (one per line, or comma-separated).';
    } else {
        require_once __DIR__ . '/../../_partials/catalogue_audit.php';

        // Append new bands at the end of the system's existing list
        // when sort_order is available. Without this, INSERT with
        // DEFAULT 0 would make every new band jump to the top of the
        // drag-ordered list.
        $nextSort = 0;
        if ($hasPtSortOrder) {
            $ns = db()->prepare(
                'SELECT COALESCE(MAX(sort_order), 0) + 1
                   FROM price_tables WHERE system_id = ? AND client_id = ?'
            );
            $ns->execute([$systemId, $clientId]);
            $nextSort = (int) $ns->fetchColumn();
        }

        $ins = $hasPtSortOrder
            ? db()->prepare(
                'INSERT INTO price_tables
                   (client_id, product_id, system_id, band_code, name, notes, active, sort_order)
                 VALUES (?, ?, ?, ?, NULL, NULL, 1, ?)'
            )
            : db()->prepare(
                'INSERT INTO price_tables
                   (client_id, product_id, system_id, band_code, name, notes, active)
                 VALUES (?, ?, ?, ?, NULL, NULL, 1)'
            );
        $added   = 0;
        $skipped = 0;
        foreach ($bands as $b) {
            try {
                if ($hasPtSortOrder) {
                    $ins->execute([$clientId, $productId, $systemId, $b, $nextSort++]);
                } else {
                    $ins->execute([$clientId, $productId, $systemId, $b]);
                }
                $newId = (int) db()->lastInsertId();
                catalogue_audit_log(
                    'price_table', $newId, 'create',
                    'Band ' . $b,
                    null,
                    ['system_id' => $systemId, 'band_code' => $b, 'name' => null],
                    $productId
                );
                $added++;
            } catch (Throwable $e) {
                // Most likely a duplicate against the unique
                // constraint — count as a silent skip.
                $skipped++;
            }
        }

        $msg = "Created $added empty price table" . ($added === 1 ? '' : 's') . '.';
        if ($skipped > 0) $msg .= " Skipped $skipped (likely already existed).";
        if ($tooLong > 0) $msg .= " Skipped $tooLong (too long — max 60 chars).";
        $_SESSION['flash_success'] = $msg;
        header('Location: /admin/products/price-tables.php?system_id=' . $systemId);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_action'] ?? '') === 'create') {
    csrf_check();

    foreach (['band_code','name','notes'] as $k) {
        $f[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $f['band_code'] = preg_replace('/^band\s+/i', '', $f['band_code']);

    if ($f['band_code'] === '') {
        $error = 'Band code is required (e.g. A, B, C).';
    } elseif (strlen($f['band_code']) > 60) {
        $error = 'Band code is too long (max 60 chars).';
    } else {
        try {
            $stmt = db()->prepare(
                'INSERT INTO price_tables
                   (client_id, product_id, system_id, band_code, name, notes, active)
                 VALUES (?, ?, ?, ?, ?, ?, 1)'
            );
            // No strtoupper — keep the case the user typed. Matches
            // the bulk-add path (which never uppercased) and the
            // rename / update_meta path, so all three ways of
            // creating/editing a band agree on case.
            // Append after the last existing band when sort_order is
            // available; otherwise fall back to the legacy 7-column
            // INSERT (the prepare above doesn't include sort_order).
            if ($hasPtSortOrder) {
                $ns = db()->prepare(
                    'SELECT COALESCE(MAX(sort_order), 0) + 1
                       FROM price_tables WHERE system_id = ? AND client_id = ?'
                );
                $ns->execute([$systemId, $clientId]);
                $appendPos = (int) $ns->fetchColumn();
                $stmt = db()->prepare(
                    'INSERT INTO price_tables
                       (client_id, product_id, system_id, band_code, name, notes, active, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, 1, ?)'
                );
                $stmt->execute([
                    $clientId,
                    $productId,
                    $systemId,
                    $f['band_code'],
                    $f['name']  !== '' ? $f['name']  : null,
                    $f['notes'] !== '' ? $f['notes'] : null,
                    $appendPos,
                ]);
            } else {
                $stmt->execute([
                    $clientId,
                    $productId,
                    $systemId,
                    $f['band_code'],
                    $f['name']  !== '' ? $f['name']  : null,
                    $f['notes'] !== '' ? $f['notes'] : null,
                ]);
            }
            $newId = (int) db()->lastInsertId();

            // Audit. system_id, band, and name are all useful diff
            // fields if the table is later renamed/moved/deactivated.
            require_once __DIR__ . '/../../_partials/catalogue_audit.php';
            catalogue_audit_log(
                'price_table', $newId, 'create',
                'Band ' . $f['band_code'] . ($f['name'] !== '' ? ' (' . $f['name'] . ')' : ''),
                null,
                [
                    'system_id' => $systemId,
                    'band_code' => $f['band_code'],
                    'name'      => $f['name']  !== '' ? $f['name']  : null,
                ],
                $productId
            );

            $_SESSION['flash_success'] = 'Price table for band "' . $f['band_code'] . '" created.';
            header('Location: /admin/products/price-table.php?id=' . $newId);
            exit;
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'uniq_price_table_product_system_band')) {
                $error = 'A price table for that band already exists in this system.';
            } else {
                $error = 'Could not create: ' . $e->getMessage();
            }
        }
    }
}

// Schema-aware ORDER BY: prefer the explicit sort_order from
// migrate_price_tables_sort_order.php (with band_code as a tie-break),
// fall back to the historical alphabetical-with-AAA-bias ordering on
// installs where the column isn't yet present. $hasPtSortOrder is
// resolved once at the top of this file (above the POST handlers).
if ($hasPtSortOrder) {
    $rows = db()->prepare(
        "SELECT t.id, t.band_code, t.name, t.notes, t.active, t.updated_at,
                t.sort_order,
                (SELECT COUNT(*) FROM price_table_rows r WHERE r.price_table_id = t.id) AS row_count
           FROM price_tables t
          WHERE t.system_id = ? AND t.client_id = ?
       ORDER BY t.sort_order, t.band_code"
    );
} else {
    $rows = db()->prepare(
        "SELECT t.id, t.band_code, t.name, t.notes, t.active, t.updated_at,
                (SELECT COUNT(*) FROM price_table_rows r WHERE r.price_table_id = t.id) AS row_count
           FROM price_tables t
          WHERE t.system_id = ? AND t.client_id = ?
       ORDER BY
            CASE
                WHEN t.band_code = 'AAA' THEN 1
                WHEN t.band_code = 'AA'  THEN 2
                WHEN t.band_code = 'A'   THEN 3
                ELSE 100
            END,
            t.band_code"
    );
}
$rows->execute([$systemId, $clientId]);
$tables = $rows->fetchAll();

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) $system['product_name']) ?> &middot; <?= e((string) $system['system_name']) ?> &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .form-row.cols-3-narrow { grid-template-columns: 1fr 2fr 3fr; }
        @media (max-width: 800px) { .form-row.cols-3-narrow { grid-template-columns: 1fr; } }
        .band-pill {
            display: inline-block; text-align: center;
            padding: 0.125rem 0.625rem; font-weight: 700; font-size: 0.8125rem;
            color: #fff; background: #1f3b5b; border-radius: 6px; white-space: nowrap;
        }
        .row-actions { white-space: nowrap; }
        .row-actions a { font-size: 0.875rem; margin-left: 0.5rem; }
        .row-actions form { display: inline; margin: 0; }
        .row-actions button {
            font-size: 0.875rem; color: #b91c1c; background: transparent;
            border: 0; cursor: pointer; padding: 0; margin-left: 0.5rem;
        }
        .row-actions button:hover { text-decoration: underline; }
        .empty-cells { color: #b45309; }
    </style>
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
                        ['Products',                              '/admin/products/index.php'],
                        [(string) $system['product_name'],        '/admin/products/edit.php?id=' . (int) $productId],
                        ['Systems',                               '/admin/products/systems.php?product_id=' . (int) $productId],
                        [(string) $system['system_name'],         null],
                        ['Price tables',                          null],
                    ]);
                ?>
                <h1 class="page-title">
                    <?= e((string) $system['product_name']) ?>
                    &mdash; <?= e((string) $system['system_name']) ?>
                </h1>
                <p class="page-subtitle">
                    <?php
                        $optLabel = trim((string) ($system['product_option_label'] ?? ''));
                        if ($optLabel === '') $optLabel = 'Fabric';
                    ?>
                    <a href="/admin/products/options.php?product_id=<?= (int) $productId ?>"><?= e($optLabel) ?>s</a>
                </p>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <a href="/admin/products/wizard.php?id=<?= (int) $productId ?>"
                   class="btn btn-secondary">&larr; Back to setup wizard</a>
                <a href="/admin/products/price-tables-single-import.php?system_id=<?= (int) $systemId ?>"
                   class="btn btn-secondary">Single-band import</a>
                <a href="/admin/products/price-tables-bulk-import.php?system_id=<?= (int) $systemId ?>"
                   class="btn btn-secondary">Bulk import (multiple bands)</a>
                <?php if (!empty($isSuperAdmin) || (bool) (current_user()['is_super_admin'] ?? false)): ?>
                <a href="/admin/products/price-cost-import.php?system_id=<?= (int) $systemId ?>"
                   class="btn btn-secondary" title="Overlay your cost grid onto these prices to get profit margins (super-admin only)">Import costs</a>
                <?php endif; ?>
                <a href="/admin/products/extras.php?product_id=<?= (int) $productId ?>"
                   class="btn btn-primary">Next: options &rarr;</a>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>
        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:0.875rem 1.125rem;margin-bottom:1rem">
            <p style="margin:0 0 0.625rem;color:#0c4a6e;font-size:0.9375rem;line-height:1.5">
                <strong>What's a price table?</strong>
                A grid of prices indexed by <em>width</em> &times; <em>drop</em> &mdash; the
                lookup the pricing engine uses when quoting a blind at a specific size.
                One table per fabric <strong>band</strong> in this system (so all Band A
                fabrics use the same grid, Band B fabrics use another, etc.).
            </p>
            <p style="margin:0;color:#0c4a6e;font-size:0.875rem;line-height:1.5">
                <strong>Quickest way to get started:</strong> click <em>Bulk import</em>
                (top right) and upload one Excel file with a sheet per band &mdash; we'll create
                every table in one go. Or add bands manually below and fill the grids one
                at a time via the <em>Open</em> link on each row.
            </p>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Add price tables</h2>
            </div>
            <p style="margin:0 0 0.875rem;color:var(--text-faint);font-size:0.8125rem">
                Paste a list of band codes — one per line, or
                comma-separated. Empty tables get created on this
                system; fill in the actual prices via the
                <em>Open</em> link on each row afterwards. Names
                and notes can be set later via the table's
                <em>Edit band / name / notes</em> link.
            </p>
            <form method="post"
                  action="/admin/products/price-tables.php?system_id=<?= (int) $systemId ?>"
                  novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="add_bulk">

                <div style="display:flex;gap:0.625rem;align-items:start;flex-wrap:wrap">
                    <div style="flex:1 1 18rem;min-width:14rem">
                        <textarea name="bulk_bands" rows="5" required autofocus
                                  placeholder="A&#10;B&#10;C&#10;D"
                                  style="width:100%;padding:0.5rem 0.625rem;border:1px solid var(--border-strong);border-radius:6px;background:var(--bg-input);color:var(--text-body);font:inherit;font-family:ui-monospace,Menlo,Consolas,monospace"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">+ Add</button>
                </div>
            </form>

            <details style="margin-top:0.875rem">
                <summary style="cursor:pointer;color:var(--text-faint);font-size:0.8125rem">
                    Need a name or notes on the new table? Use the single-add form instead.
                </summary>
                <form method="post"
                      action="/admin/products/price-tables.php?system_id=<?= (int) $systemId ?>"
                      class="form" novalidate style="margin-top:0.625rem">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="create">

                    <div class="form-row cols-3-narrow">
                        <div class="form-group">
                            <label for="band_code">Band <span class="required">*</span></label>
                            <input id="band_code" name="band_code" type="text"
                                   required maxlength="60"
                                   value="<?= e((string) $f['band_code']) ?>" placeholder="A">
                        </div>
                        <div class="form-group">
                            <label for="name">Name</label>
                            <input id="name" name="name" type="text" maxlength="150"
                                   value="<?= e((string) $f['name']) ?>"
                                   placeholder="e.g. 2026 Slim Line Band A">
                        </div>
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <input id="notes" name="notes" type="text" maxlength="255"
                                   value="<?= e((string) $f['notes']) ?>"
                                   placeholder="Anything to remember about this sheet">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-secondary">Create one</button>
                    </div>
                </form>
            </details>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Price tables (<?= count($tables) ?>)</h2>
            </div>

            <?php if (!$tables): ?>
                <div class="placeholder">
                    <p class="placeholder-title">No price tables in this system yet</p>
                    <p class="placeholder-body">
                        Add one per fabric band. Or use <strong>Bulk import</strong> at the
                        top right to load every band from one Excel file in a single shot.
                    </p>
                </div>
            <?php else: ?>
                <?php if ($hasPtSortOrder): ?>
                    <p style="font-size:0.8125rem;color:var(--text-faint);margin:0 0 0.5rem">
                        Drag the <strong>⋮⋮</strong> handle to reorder bands.
                        <span class="reorder-status" data-for="price_tables"></span>
                    </p>
                <?php endif; ?>
                <div class="table-wrap">
                    <table class="table <?= $hasPtSortOrder ? 'sortable-list' : '' ?>"
                           <?= $hasPtSortOrder ? 'data-reorder-type="price_tables"' : '' ?>>
                        <thead>
                            <tr>
                                <?php if ($hasPtSortOrder): ?><th class="col-drag"></th><?php endif; ?>
                                <th>Band</th>
                                <th>Name</th>
                                <th>Notes</th>
                                <th class="num">Cells</th>
                                <th>Updated</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tables as $t): ?>
                                <tr data-id="<?= (int) $t['id'] ?>">
                                    <?php if ($hasPtSortOrder): ?>
                                        <td class="drag-col" title="Drag to reorder">⋮⋮</td>
                                    <?php endif; ?>
                                    <td><span class="band-pill">Band <?= e((string) $t['band_code']) ?></span></td>
                                    <td><?= e((string) ($t['name'] ?? '')) ?></td>
                                    <td><?= e((string) ($t['notes'] ?? '')) ?></td>
                                    <td class="num<?= ((int) $t['row_count']) === 0 ? ' empty-cells' : '' ?>">
                                        <?= (int) $t['row_count'] ?>
                                    </td>
                                    <?php require_once __DIR__ . '/../../_partials/time_ago.php'; ?>
                                    <td style="font-size:0.8125rem;color:var(--text-faint);white-space:nowrap"
                                        title="<?= e((string) $t['updated_at']) ?>">
                                        <?= e(time_ago((string) $t['updated_at'])) ?>
                                    </td>
                                    <td class="row-actions">
                                        <a href="/admin/products/price-table.php?id=<?= (int) $t['id'] ?>">Open</a>
                                        <form method="post" action="/admin/products/price-table-delete.php"
                                              data-confirm="Delete the Band <?= e((string) $t['band_code']) ?> price table? This wipes its <?= (int) $t['row_count'] ?> cells too.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                            <input type="hidden" name="system_id" value="<?= (int) $systemId ?>">
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

        <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;
                    margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--border)">
            <span style="flex:1;min-width:14rem;color:var(--text-faint);font-size:0.875rem">
                Prices in? Next, add any options &mdash; operation, controls, extras.
            </span>
            <a href="/admin/products/edit.php?id=<?= (int) $productId ?>"
               class="btn btn-secondary">Back to product</a>
            <a href="/admin/products/extras.php?product_id=<?= (int) $productId ?>"
               class="btn btn-primary">Next: options &rarr;</a>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../../_partials/confirm_modal.php'; ?>
<?php if ($hasPtSortOrder): ?>
    <?php require __DIR__ . '/../../_partials/sortable_init.php'; ?>
<?php endif; ?>
</body>
</html>
