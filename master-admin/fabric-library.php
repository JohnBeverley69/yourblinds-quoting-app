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

$pdo = db();

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
        } elseif ($action === 'update_fabric_band') {
            $id   = (int) ($_POST['id'] ?? 0);
            $band = trim((string) ($_POST['suggested_band'] ?? ''));
            if ($id > 0) {
                $pdo->prepare('UPDATE library_fabrics SET suggested_band = ? WHERE id = ?')
                    ->execute([$band !== '' ? strtoupper($band) : null, $id]);
                $_SESSION['flash_success'] = 'Band updated.';
            }
        } elseif ($action === 'set_band_bulk') {
            // Re-band a whole list at once (e.g. "all of these are Band D for us").
            $band = trim((string) ($_POST['suggested_band'] ?? ''));
            $ids  = array_values(array_filter(array_map('intval', (array) ($_POST['fab_ids'] ?? [])), fn ($n) => $n > 0));
            if ($ids) {
                $place = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("UPDATE library_fabrics SET suggested_band = ? WHERE id IN ($place)")
                    ->execute(array_merge([$band !== '' ? strtoupper($band) : null], $ids));
                $_SESSION['flash_success'] = count($ids) . ' fabric' . (count($ids) === 1 ? '' : 's') . ' set to band ' . ($band !== '' ? strtoupper($band) : '(none)') . '.';
            } else {
                $_SESSION['flash_error'] = 'Tick the fabrics you want to re-band first.';
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
if ($ready) {
    $suppliers = $pdo->query(
        'SELECT s.id, s.name, s.active,
                (SELECT COUNT(*) FROM library_fabrics f WHERE f.fabric_supplier_id = s.id) AS fabric_count
           FROM fabric_suppliers s
          ORDER BY s.sort_order, s.name'
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pdo->query(
        'SELECT id, fabric_supplier_id, name, colour, code, suggested_band, blind_type, active
           FROM library_fabrics ORDER BY blind_type, name, colour'
    )->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $fabricsBySup[(int) $f['fabric_supplier_id']][] = $f;
    }
}

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
                            <!-- Bulk re-band: ticked rows (associated via form=) + a band → Set. -->
                            <form id="bulk-band-<?= $sid ?>" method="post" action="/master-admin/fabric-library.php"
                                  style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;margin:0 0 .5rem">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_action" value="set_band_bulk">
                                <span style="color:var(--text-muted);font-size:.8125rem">Re-band ticked rows to</span>
                                <input type="text" name="suggested_band" maxlength="20" placeholder="band"
                                       style="width:4rem;padding:.2rem .4rem;border:1px solid var(--border-strong);border-radius:5px;font:inherit;background:var(--bg-input);text-transform:uppercase">
                                <button type="submit" class="btn btn-secondary" style="font-size:.8125rem;padding:.25rem .75rem">Set band on ticked</button>
                            </form>
                            <div class="table-wrap">
                                <table class="table">
                                    <thead><tr>
                                        <th style="width:1.5rem;text-align:center"><input type="checkbox" class="fab-all" data-sid="<?= $sid ?>" aria-label="Select all"></th>
                                        <th>Fabric</th><th>Colour</th><th>Code</th><th>Sugg. band</th><th>Type</th><th></th>
                                    </tr></thead>
                                    <tbody>
                                        <?php foreach ($fabrics as $f): ?>
                                            <tr>
                                                <td style="text-align:center"><input type="checkbox" class="fab-cb-<?= $sid ?>" name="fab_ids[]" value="<?= (int) $f['id'] ?>" form="bulk-band-<?= $sid ?>"></td>
                                                <td><strong><?= e((string) $f['name']) ?></strong></td>
                                                <td><?= e((string) ($f['colour'] ?? '')) ?></td>
                                                <td><?= e((string) ($f['code'] ?? '')) ?></td>
                                                <td>
                                                    <form method="post" action="/master-admin/fabric-library.php" style="display:flex;gap:.25rem;align-items:center;margin:0">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="_action" value="update_fabric_band">
                                                        <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                                                        <input type="text" name="suggested_band" maxlength="20" value="<?= e((string) ($f['suggested_band'] ?? '')) ?>"
                                                               style="width:3rem;padding:.15rem .3rem;border:1px solid var(--border-strong);border-radius:5px;font:inherit;background:var(--bg-input);text-transform:uppercase">
                                                        <button type="submit" style="background:none;border:0;color:var(--link);cursor:pointer;font-size:.75rem" title="Save band">Save</button>
                                                    </form>
                                                </td>
                                                <td><?= e((string) ($f['blind_type'] ?? '')) ?></td>
                                                <td style="text-align:right">
                                                    <form method="post" action="/master-admin/fabric-library.php" style="margin:0"
                                                          data-confirm="Remove “<?= e((string) $f['name']) ?>” from the library?">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="_action" value="delete_fabric">
                                                        <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                                                        <button type="submit" style="background:none;border:0;color:#b91c1c;cursor:pointer;font-size:.8125rem;padding:0">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="color:var(--text-faint);font-size:.875rem;margin:0 0 .5rem">No fabrics yet — add one below (or import, once that's built).</p>
                        <?php endif; ?>

                        <!-- Add a fabric -->
                        <form method="post" action="/master-admin/fabric-library.php" class="fab-add">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="add_fabric">
                            <input type="hidden" name="fabric_supplier_id" value="<?= $sid ?>">
                            <div><label>Fabric name</label><input type="text" name="name" maxlength="160" required></div>
                            <div><label>Colour</label><input type="text" name="colour" maxlength="120"></div>
                            <div><label>Code</label><input type="text" name="code" maxlength="80"></div>
                            <div><label>Band</label><input type="text" name="suggested_band" maxlength="20" placeholder="A"></div>
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
<script>
document.querySelectorAll('.fab-all').forEach(function (all) {
    all.addEventListener('change', function () {
        document.querySelectorAll('.fab-cb-' + all.dataset.sid).forEach(function (cb) { cb.checked = all.checked; });
    });
});
</script>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
