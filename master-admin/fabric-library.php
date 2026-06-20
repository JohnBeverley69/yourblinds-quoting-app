<?php
declare(strict_types=1);

/**
 * Master Admin: Fabric Library — a curated catalogue of fabrics grouped by
 * their MANUFACTURER (fabric supplier), separate from the price-list catalogue.
 *
 * Add manufacturers, then add (Phase 1) or import (next) their fabrics. Each
 * fabric carries name / colour / code / a suggested band (overridable when
 * pulled into a product) / an optional blind-type tag. Phase 2 pulls a
 * manufacturer's fabrics into a client's product.
 *
 * Backed by fabric_suppliers + library_fabrics (migrate_fabric_library.php).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

$user = current_user();   // sidebar derives admin/super-admin from this
$pdo  = db();

$ready = true;
try { $pdo->query('SELECT 1 FROM library_fabrics LIMIT 0'); }
catch (Throwable $e) { $ready = false; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');
    try {
        if ($action === 'add_supplier') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                $_SESSION['flash_error'] = 'Give the manufacturer a name.';
            } else {
                $dup = $pdo->prepare('SELECT id FROM fabric_suppliers WHERE LOWER(name) = LOWER(?) LIMIT 1');
                $dup->execute([$name]);
                if ($dup->fetchColumn() !== false) {
                    $_SESSION['flash_error'] = 'There is already a manufacturer called “' . $name . '”.';
                } else {
                    $next = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM fabric_suppliers')->fetchColumn();
                    $pdo->prepare('INSERT INTO fabric_suppliers (name, sort_order) VALUES (?, ?)')->execute([$name, $next]);
                    $_SESSION['flash_success'] = 'Added manufacturer “' . $name . '”.';
                }
            }
        } elseif ($action === 'update_supplier') {
            $id   = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $act  = !empty($_POST['active']) ? 1 : 0;
            if ($id > 0 && $name !== '') {
                $pdo->prepare('UPDATE fabric_suppliers SET name = ?, active = ? WHERE id = ?')->execute([$name, $act, $id]);
                $_SESSION['flash_success'] = 'Saved “' . $name . '”.';
            } else {
                $_SESSION['flash_error'] = 'A manufacturer needs a name.';
            }
        } elseif ($action === 'delete_supplier') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare('DELETE FROM library_fabrics WHERE fabric_supplier_id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM fabric_suppliers WHERE id = ?')->execute([$id]);
                $_SESSION['flash_success'] = 'Manufacturer and its fabrics removed.';
            }
        } elseif ($action === 'add_fabric') {
            $sid    = (int) ($_POST['fabric_supplier_id'] ?? 0);
            $name   = trim((string) ($_POST['name'] ?? ''));
            $colour = trim((string) ($_POST['colour'] ?? '')) ?: null;
            $code   = trim((string) ($_POST['code'] ?? '')) ?: null;
            $band   = trim((string) ($_POST['suggested_band'] ?? '')) ?: null;
            $type   = trim((string) ($_POST['blind_type'] ?? '')) ?: null;
            if ($sid <= 0 || $name === '') {
                $_SESSION['flash_error'] = 'A fabric needs a manufacturer and a name.';
            } else {
                $next = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM library_fabrics WHERE fabric_supplier_id = ' . $sid)->fetchColumn();
                $pdo->prepare(
                    'INSERT INTO library_fabrics
                        (fabric_supplier_id, name, colour, code, suggested_band, blind_type, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([$sid, $name, $colour, $code, $band ? strtoupper($band) : null, $type, $next]);
                $_SESSION['flash_success'] = 'Added fabric “' . $name . '”.';
            }
        } elseif ($action === 'delete_fabric') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare('DELETE FROM library_fabrics WHERE id = ?')->execute([$id]);
                $_SESSION['flash_success'] = 'Fabric removed.';
            }
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage();
    }
    header('Location: /master-admin/fabric-library.php');
    exit;
}

$suppliers     = [];
$fabricsBySup  = [];
$catsBySup     = [];
$hasFabricCats = false;
if ($ready) {
    // Grouping is live once BOTH the categories table and the
    // library_fabrics.category_id column exist (migrate_fabric_library_categories.php).
    try {
        $pdo->query('SELECT 1 FROM library_fabric_categories LIMIT 0');
        $pdo->query('SELECT category_id FROM library_fabrics LIMIT 0');
        $hasFabricCats = true;
    } catch (Throwable $e) { $hasFabricCats = false; }

    $suppliers = $pdo->query(
        'SELECT s.id, s.name, s.active,
                (SELECT COUNT(*) FROM library_fabrics f WHERE f.fabric_supplier_id = s.id) AS fabric_count
           FROM fabric_suppliers s
          ORDER BY s.sort_order, s.name'
    )->fetchAll(PDO::FETCH_ASSOC);

    if ($hasFabricCats) {
        foreach ($pdo->query(
            'SELECT id, fabric_supplier_id, name FROM library_fabric_categories
              ORDER BY fabric_supplier_id, sort_order, name'
        )->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $catsBySup[(int) $c['fabric_supplier_id']][] = $c;
        }
    }

    $fabCols = 'id, fabric_supplier_id, name, colour, code, suggested_band, blind_type, active'
             . ($hasFabricCats ? ', category_id' : '');
    foreach ($pdo->query(
        "SELECT $fabCols FROM library_fabrics ORDER BY blind_type, name, colour"
    )->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $fabricsBySup[(int) $f['fabric_supplier_id']][] = $f;
    }
}

/** Render one fabric row (drag handle + Group dropdown only when grouping is on). */
$renderFabricRow = function (array $f, array $cats, bool $hasFabricCats): void {
    $fid    = (int) $f['id'];
    $curCat = (int) ($f['category_id'] ?? 0);
    ?>
    <tr data-id="<?= $fid ?>" data-sid="<?= (int) $f['fabric_supplier_id'] ?>"<?= $hasFabricCats ? ' draggable="true"' : '' ?>>
        <?php if ($hasFabricCats): ?><td class="drag-col" title="Drag onto a group">⋮⋮</td><?php endif; ?>
        <td><strong><?= e((string) $f['name']) ?></strong></td>
        <td><?= e((string) ($f['colour'] ?? '')) ?></td>
        <td><?= e((string) ($f['code'] ?? '')) ?></td>
        <td><?= e((string) ($f['suggested_band'] ?? '')) ?></td>
        <td><?= e((string) ($f['blind_type'] ?? '')) ?></td>
        <?php if ($hasFabricCats): ?>
            <td>
                <form method="post" action="/master-admin/fabric-category.php" style="margin:0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="assign">
                    <input type="hidden" name="fabric_id" value="<?= $fid ?>">
                    <select name="category_id" onchange="this.form.submit()" class="group-select">
                        <option value="0"<?= $curCat === 0 ? ' selected' : '' ?>>— Ungrouped —</option>
                        <?php foreach ($cats as $c): $cid = (int) $c['id']; ?>
                            <option value="<?= $cid ?>"<?= $curCat === $cid ? ' selected' : '' ?>><?= e((string) $c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </td>
        <?php endif; ?>
        <td style="text-align:right">
            <form method="post" action="/master-admin/fabric-library.php" style="margin:0"
                  data-confirm="Remove “<?= e((string) $f['name']) ?>” from the library?">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="delete_fabric">
                <input type="hidden" name="id" value="<?= $fid ?>">
                <button type="submit" style="background:none;border:0;color:#b91c1c;cursor:pointer;font-size:.8125rem;padding:0">Delete</button>
            </form>
        </td>
    </tr>
    <?php
};

/** Render a fabrics table for a list of rows. */
$renderFabricTable = function (array $rows, array $cats, bool $hasFabricCats) use ($renderFabricRow): void {
    ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr>
                <?php if ($hasFabricCats): ?><th class="drag-col"></th><?php endif; ?>
                <th>Fabric</th><th>Colour</th><th>Code</th><th>Sugg. band</th><th>Type</th>
                <?php if ($hasFabricCats): ?><th>Group</th><?php endif; ?>
                <th></th>
            </tr></thead>
            <tbody>
                <?php foreach ($rows as $f) $renderFabricRow($f, $cats, $hasFabricCats); ?>
            </tbody>
        </table>
    </div>
    <?php
};

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$activeNav = 'fabric-library';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fabric Library &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        details.fab-sup > summary {
            list-style: none; cursor: pointer;
            display: flex; justify-content: space-between; align-items: baseline;
            gap: 1rem; flex-wrap: wrap; padding: 0.25rem 0;
        }
        details.fab-sup > summary::-webkit-details-marker { display: none; }
        details.fab-sup .tw { display: inline-block; color: var(--text-faint); font-size: 0.75rem; transition: transform 120ms; }
        details.fab-sup[open] > summary .tw { transform: rotate(90deg); }
        details.fab-sup .s-name { font-weight: 700; font-size: 1.05rem; color: var(--text-primary); }
        details.fab-sup .s-count { color: var(--text-muted); font-size: 0.8125rem; }
        .fab-add { display: grid; grid-template-columns: 1.4fr 1fr 0.8fr 4rem 1fr auto; gap: 0.4rem; align-items: end; margin: 0.75rem 0 0; }
        .fab-add label { font-size: 0.625rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-faint); font-weight: 600; }
        .fab-add input { padding: 0.35rem 0.45rem; border: 1px solid var(--border-strong); border-radius: 6px; font: inherit; background: var(--bg-input); width: 100%; }
        @media (max-width: 900px) { .fab-add { grid-template-columns: 1fr 1fr; } .fab-add > button { grid-column: 1 / -1; } }
        .retired { opacity: 0.6; }

        /* Grouping (mirrors the Products page) */
        .drag-col { cursor: grab; color: var(--text-faint); width: 1.5rem; text-align: center; user-select: none; }
        .drag-col:active { cursor: grabbing; }
        tr.dragging { opacity: 0.45; }
        .drop-zone { border-radius: 10px; padding: 0.15rem 0.5rem; margin: 0 -0.5rem 0.4rem; transition: background 80ms; }
        .drop-zone.drop-hover { background: rgba(37, 99, 235, 0.07); outline: 2px dashed #2563eb; outline-offset: -2px; }
        .fcat-heading { display: flex; align-items: center; gap: 0.4rem; font-size: 0.95rem; font-weight: 700; color: var(--text-primary); margin: 0.4rem 0; }
        .fcat-heading.dragging { opacity: 0.5; }
        .fcat-draggable { cursor: grab; }
        .fcat-draggable:active { cursor: grabbing; }
        .fcat-grip { color: var(--text-faint); cursor: grab; }
        .fcat-toggle { background: none; border: 0; cursor: pointer; color: var(--text-faint); font-size: 0.75rem; padding: 0; transition: transform 120ms; }
        .fcat-toggle.expanded { transform: rotate(90deg); }
        .fcat-count { background: var(--bg-subtle-2); color: var(--text-muted); font-size: 0.6875rem; font-weight: 600; border-radius: 999px; padding: 0.05rem 0.45rem; }
        .fcat-del { background: none; border: 0; color: #b91c1c; cursor: pointer; font-size: 0.75rem; text-decoration: underline; padding: 0; }
        .fcat-body.collapsed { display: none; }
        .drop-empty { color: var(--text-faint); font-size: 0.8125rem; padding: 0.5rem 0.25rem; margin: 0; }
        .group-select { padding: 0.2rem 0.3rem; border: 1px solid var(--border-strong); border-radius: 6px; font: inherit; font-size: 0.8125rem; background: var(--bg-input); max-width: 11rem; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Fabric Library</h1>
                <p class="page-subtitle">
                    Fabrics grouped by manufacturer — the second library that feeds products
                    (the Master Catalogue holds price tables; this holds the cloth).
                </p>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <a href="/master-admin/fabric-import.php" class="btn btn-primary">Import fabrics</a>
                <a href="/master-admin/master-catalogue.php" class="btn btn-secondary">Master Catalogue</a>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <?php if (!$ready): ?>
            <section class="section">
                <div class="alert alert-error" role="alert">
                    The Fabric Library tables aren't on this database yet — run
                    <a href="/migrate_fabric_library.php"><code>/migrate_fabric_library.php</code></a>
                    (super-admin) first, then reload.
                </div>
            </section>
        <?php else: ?>

        <!-- Add a manufacturer -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.75rem">Add a fabric manufacturer</h2>
            <form method="post" action="/master-admin/fabric-library.php"
                  style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="add_supplier">
                <input type="text" name="name" class="form-control" maxlength="120"
                       placeholder="Manufacturer name (e.g. Louvolite)" style="max-width:20rem">
                <button type="submit" class="btn btn-primary">+ Add manufacturer</button>
                <span style="color:var(--text-faint);font-size:0.8125rem">Then expand it to add fabrics (bulk import is the next feature).</span>
            </form>
        </section>

        <!-- Manufacturers (collapsible) -->
        <?php if (!$suppliers): ?>
            <section class="section"><p style="color:var(--text-faint);margin:0">No manufacturers yet — add one above.</p></section>
        <?php else: foreach ($suppliers as $s):
            $sid     = (int) $s['id'];
            $fabrics = $fabricsBySup[$sid] ?? [];
        ?>
            <section class="section <?= ((int) $s['active']) === 1 ? '' : 'retired' ?>">
                <details class="fab-sup">
                    <summary>
                        <span style="display:flex;align-items:baseline;gap:.4rem">
                            <span class="tw">&#9654;</span>
                            <span class="s-name"><?= e((string) $s['name']) ?><?= ((int) $s['active']) !== 1 ? ' <span style="font-size:.6875rem;color:var(--text-faint);text-transform:uppercase">retired</span>' : '' ?></span>
                        </span>
                        <span class="s-count"><?= (int) $s['fabric_count'] ?> fabric<?= (int) $s['fabric_count'] === 1 ? '' : 's' ?></span>
                    </summary>
                    <div style="margin-top:0.75rem">
                        <!-- Manufacturer settings -->
                        <form method="post" action="/master-admin/fabric-library.php"
                              style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;margin:0 0 0.75rem">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="update_supplier">
                            <input type="hidden" name="id" value="<?= $sid ?>">
                            <input type="text" name="name" class="form-control" maxlength="120"
                                   value="<?= e((string) $s['name']) ?>" style="max-width:16rem">
                            <label style="display:inline-flex;align-items:center;gap:.4rem;font-size:.8125rem">
                                <input type="checkbox" name="active" value="1" <?= ((int) $s['active']) === 1 ? 'checked' : '' ?>> Active
                            </label>
                            <button type="submit" class="btn btn-secondary" style="font-size:.8125rem;padding:.25rem .75rem">Save</button>
                            <span style="flex:1"></span>
                            <button type="submit" class="btn btn-secondary" form="del-sup-<?= $sid ?>"
                                    style="color:#b91c1c;font-size:.8125rem;padding:.25rem .625rem">Delete manufacturer</button>
                        </form>
                        <form id="del-sup-<?= $sid ?>" method="post" action="/master-admin/fabric-library.php" style="display:none"
                              data-confirm="Delete “<?= e((string) $s['name']) ?>” and ALL <?= (int) $s['fabric_count'] ?> of its library fabrics? Products that already pulled fabrics in keep them. No undo.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="delete_supplier">
                            <input type="hidden" name="id" value="<?= $sid ?>">
                        </form>

                        <!-- Fabric list -->
                        <?php if ($fabrics): ?>
                            <p style="color:var(--text-faint);font-size:.8125rem;margin:0 0 .5rem">
                                “Sugg. band” is the supplier's suggested band (from the import). Each client sets
                                their own band when they add a fabric to a product — on the product's Fabrics page.
                            </p>
                        <?php endif; ?>

                        <?php if (!$hasFabricCats): ?>
                            <!-- Grouping not migrated yet: flat list (+ a hint). -->
                            <?php if ($fabrics): $renderFabricTable($fabrics, [], false); else: ?>
                                <p style="color:var(--text-faint);font-size:.875rem;margin:0 0 .5rem">No fabrics yet — add one below, or import them.</p>
                            <?php endif; ?>
                            <p style="color:var(--text-faint);font-size:.8125rem;margin:.5rem 0 0">
                                Want to file these under headings? Run
                                <a href="/migrate_fabric_library_categories.php"><code>/migrate_fabric_library_categories.php</code></a>
                                (super-admin) to switch on groups.
                            </p>
                        <?php else: ?>
                            <?php
                                $cats = $catsBySup[$sid] ?? [];
                                // Bucket this manufacturer's fabrics by group (0 = ungrouped).
                                $byCat = [];
                                foreach ($fabrics as $f) { $byCat[(int) ($f['category_id'] ?? 0)][] = $f; }
                            ?>
                            <!-- Add a group for this manufacturer -->
                            <form method="post" action="/master-admin/fabric-category.php"
                                  style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;margin:0 0 .75rem">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_action" value="create">
                                <input type="hidden" name="fabric_supplier_id" value="<?= $sid ?>">
                                <input type="text" name="name" class="form-control" maxlength="120"
                                       placeholder="New group (e.g. Blackout)" style="max-width:16rem">
                                <button type="submit" class="btn btn-secondary" style="font-size:.8125rem;padding:.3rem .75rem">+ Add group</button>
                                <span style="color:var(--text-faint);font-size:.8125rem">Drag the ⋮⋮ handle onto a group, or use the Group dropdown.</span>
                            </form>

                            <?php foreach ($cats as $c): $cidd = (int) $c['id']; $gRows = $byCat[$cidd] ?? []; $bodyId = 'fgb-' . $sid . '-' . $cidd; ?>
                                <div class="fdz drop-zone" data-sid="<?= $sid ?>" data-cat="<?= $cidd ?>">
                                    <h3 class="fcat-heading fcat-draggable" draggable="true" data-sid="<?= $sid ?>" data-cat="<?= $cidd ?>" title="Drag to reorder groups">
                                        <button type="button" class="fcat-toggle" data-target="<?= $bodyId ?>" draggable="false" aria-label="Show or hide fabrics">&#9654;</button>
                                        <span class="fcat-grip" aria-hidden="true">⠿</span>
                                        <?= e((string) $c['name']) ?>
                                        <span class="fcat-count"><?= count($gRows) ?></span>
                                        <form method="post" action="/master-admin/fabric-category.php" style="display:inline;margin-left:.5rem"
                                              data-confirm="Delete the group &quot;<?= e((string) $c['name']) ?>&quot;? Its fabrics are NOT deleted — they just become ungrouped.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="_action" value="delete">
                                            <input type="hidden" name="category_id" value="<?= $cidd ?>">
                                            <button type="submit" class="fcat-del">remove group</button>
                                        </form>
                                    </h3>
                                    <div class="fcat-body collapsed" id="<?= $bodyId ?>">
                                        <?php if ($gRows): $renderFabricTable($gRows, $cats, true); else: ?>
                                            <p class="drop-empty">Empty — drag a fabric's ⋮⋮ handle here, or pick this group from its Group dropdown.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php $ung = $byCat[0] ?? []; ?>
                            <div class="fdz drop-zone" data-sid="<?= $sid ?>" data-cat="0">
                                <h3 class="fcat-heading">
                                    <button type="button" class="fcat-toggle expanded" data-target="fgb-<?= $sid ?>-0" draggable="false" aria-label="Show or hide fabrics">&#9654;</button>
                                    Ungrouped <span class="fcat-count"><?= count($ung) ?></span>
                                </h3>
                                <div class="fcat-body" id="fgb-<?= $sid ?>-0">
                                    <?php if ($ung): $renderFabricTable($ung, $cats, true); else: ?>
                                        <p class="drop-empty">Nothing ungrouped — drag a fabric's ⋮⋮ handle here to remove it from its group.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Add a fabric -->
                        <form method="post" action="/master-admin/fabric-library.php" class="fab-add">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="add_fabric">
                            <input type="hidden" name="fabric_supplier_id" value="<?= $sid ?>">
                            <div><label>Fabric name</label><input type="text" name="name" maxlength="160" required></div>
                            <div><label>Colour</label><input type="text" name="colour" maxlength="120"></div>
                            <div><label>Code</label><input type="text" name="code" maxlength="80"></div>
                            <div><label>Band</label><input type="text" name="suggested_band" maxlength="60" placeholder="A"></div>
                            <div><label>Blind type</label><input type="text" name="blind_type" maxlength="60" placeholder="Roller"></div>
                            <button type="submit" class="btn btn-secondary" style="font-size:.8125rem;padding:.4rem .75rem">+ Add</button>
                        </form>
                    </div>
                </details>
            </section>
        <?php endforeach; endif; ?>

        <?php endif; /* ready */ ?>
    </main>
</div>

<?php if ($ready && $hasFabricCats): ?>
<!-- Hidden forms the drag handlers submit. -->
<form id="fab-assign-form" method="post" action="/master-admin/fabric-category.php" style="display:none">
    <?= csrf_field() ?>
    <input type="hidden" name="_action" value="assign">
    <input type="hidden" name="fabric_id" value="">
    <input type="hidden" name="category_id" value="">
</form>
<form id="fab-order-form" method="post" action="/master-admin/fabric-category.php" style="display:none">
    <?= csrf_field() ?>
    <input type="hidden" name="_action" value="reorder_groups">
    <input type="hidden" name="fabric_supplier_id" value="">
</form>
<script>
(function () {
    var assignForm = document.getElementById('fab-assign-form');
    var orderForm  = document.getElementById('fab-order-form');
    var zones = Array.prototype.slice.call(document.querySelectorAll('.drop-zone'));
    var dragType = null, dragId = null, dragSid = null;

    function clearHover() { zones.forEach(function (z) { z.classList.remove('drop-hover'); }); }

    // Drag a FABRIC row → file it into a group (within its own manufacturer).
    document.querySelectorAll('tr[draggable="true"]').forEach(function (tr) {
        tr.addEventListener('dragstart', function (e) {
            dragType = 'fabric'; dragId = tr.getAttribute('data-id'); dragSid = tr.getAttribute('data-sid');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', dragId); } catch (err) {}
            tr.classList.add('dragging');
        });
        tr.addEventListener('dragend', function () { tr.classList.remove('dragging'); clearHover(); });
    });

    // Drag a GROUP heading → reorder groups within that manufacturer.
    document.querySelectorAll('.fcat-draggable').forEach(function (h) {
        h.addEventListener('dragstart', function (e) {
            dragType = 'group'; dragId = h.getAttribute('data-cat'); dragSid = h.getAttribute('data-sid');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', 'g' + dragId); } catch (err) {}
            h.classList.add('dragging');
        });
        h.addEventListener('dragend', function () { h.classList.remove('dragging'); clearHover(); });
    });

    zones.forEach(function (z) {
        z.addEventListener('dragover', function (e) {
            if (dragSid !== null && z.getAttribute('data-sid') !== dragSid) return;  // other manufacturer
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
            var sid = z.getAttribute('data-sid');
            var cat = z.getAttribute('data-cat');
            if (dragSid !== sid) return;   // can't cross manufacturers

            if (dragType === 'group') {
                if (!dragId || dragId === cat) return;
                var order = zones
                    .filter(function (zz) { return zz.getAttribute('data-sid') === sid; })
                    .map(function (zz) { return zz.getAttribute('data-cat'); })
                    .filter(function (c) { return c !== '0' && c !== dragId; });
                if (cat === '0') { order.push(dragId); }
                else { var ti = order.indexOf(cat); if (ti < 0) order.push(dragId); else order.splice(ti, 0, dragId); }
                orderForm.querySelectorAll('input[name="order[]"]').forEach(function (n) { n.remove(); });
                orderForm.querySelector('[name=fabric_supplier_id]').value = sid;
                order.forEach(function (id) {
                    var inp = document.createElement('input');
                    inp.type = 'hidden'; inp.name = 'order[]'; inp.value = id;
                    orderForm.appendChild(inp);
                });
                orderForm.submit();
                return;
            }

            // Fabric assign (default).
            if (!dragId) return;
            var row = document.querySelector('tr[data-id="' + dragId + '"]');
            if (row && row.closest('.drop-zone') === z) return;   // already in this group
            assignForm.querySelector('[name=fabric_id]').value = dragId;
            assignForm.querySelector('[name=category_id]').value = cat;
            assignForm.submit();
        });
    });

    // Collapse / expand a group's fabrics.
    document.querySelectorAll('.fcat-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var body = document.getElementById(btn.getAttribute('data-target'));
            if (!body) return;
            body.classList.toggle('collapsed');
            btn.classList.toggle('expanded', !body.classList.contains('collapsed'));
        });
    });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
