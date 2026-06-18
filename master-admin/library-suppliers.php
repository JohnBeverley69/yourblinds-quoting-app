<?php
declare(strict_types=1);

/**
 * Master Admin: Library suppliers registry.
 *
 * Add / edit / retire the suppliers offered by the Price-List Library. Each
 * supplier has a display name, the product-name prefix the push engine copies
 * (e.g. "Bev"), whether it's free or gated behind the add-on, and a short blurb
 * shown on the client subscribe page. The supplier_key (a slug derived from the
 * name) is the stable join key to client_library_suppliers and never changes
 * once created.
 *
 * Backed by the library_suppliers table (migrate_library_registry.php).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/library.php';

requireSuperAdmin();

$user = current_user();   // sidebar derives admin/super-admin from this
$pdo  = db();

/** Slugify a name into a supplier_key (lowercase, dashes). */
$slugify = static function (string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
};

/** Make a key unique against existing rows by suffixing -2, -3, … */
$uniqueKey = static function (string $base) use ($pdo): string {
    if ($base === '') $base = 'supplier';
    $key = $base;
    $n = 1;
    $chk = $pdo->prepare('SELECT 1 FROM library_suppliers WHERE supplier_key = ? LIMIT 1');
    while (true) {
        $chk->execute([$key]);
        if ($chk->fetchColumn() === false) return $key;
        $n++;
        $key = $base . '-' . $n;
    }
};

$tableReady = true;
try {
    $pdo->query('SELECT 1 FROM library_suppliers LIMIT 0');
} catch (Throwable $e) {
    $tableReady = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableReady) {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');

    try {
        if ($action === 'add') {
            $name   = trim((string) ($_POST['name']   ?? ''));
            $prefix = trim((string) ($_POST['prefix'] ?? ''));
            $blurb  = trim((string) ($_POST['blurb']  ?? ''));
            $free   = !empty($_POST['is_free']) ? 1 : 0;

            if ($name === '' || $prefix === '') {
                $_SESSION['flash_error'] = 'A supplier needs both a name and a prefix.';
            } else {
                $key = $uniqueKey($slugify($name));
                $sortStmt = $pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM library_suppliers');
                $nextSort = (int) $sortStmt->fetchColumn();
                $pdo->prepare(
                    'INSERT INTO library_suppliers
                        (supplier_key, name, prefix, is_free, blurb, active, sort_order)
                     VALUES (?, ?, ?, ?, ?, 1, ?)'
                )->execute([$key, $name, $prefix, $free, ($blurb !== '' ? $blurb : null), $nextSort]);
                $_SESSION['flash_success'] = 'Added “' . $name . '”.';
            }
        } elseif ($action === 'update') {
            $id     = (int) ($_POST['id'] ?? 0);
            $name   = trim((string) ($_POST['name']   ?? ''));
            $prefix = trim((string) ($_POST['prefix'] ?? ''));
            $blurb  = trim((string) ($_POST['blurb']  ?? ''));
            $free   = !empty($_POST['is_free']) ? 1 : 0;
            $active = !empty($_POST['active'])  ? 1 : 0;

            if ($id <= 0 || $name === '' || $prefix === '') {
                $_SESSION['flash_error'] = 'A supplier needs both a name and a prefix.';
            } else {
                $pdo->prepare(
                    'UPDATE library_suppliers
                        SET name = ?, prefix = ?, is_free = ?, blurb = ?, active = ?
                      WHERE id = ?'
                )->execute([$name, $prefix, $free, ($blurb !== '' ? $blurb : null), $active, $id]);
                $_SESSION['flash_success'] = 'Saved “' . $name . '”.';
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $row = $pdo->prepare('SELECT name FROM library_suppliers WHERE id = ?');
                $row->execute([$id]);
                $nm = (string) ($row->fetchColumn() ?: 'supplier');
                $pdo->prepare('DELETE FROM library_suppliers WHERE id = ?')->execute([$id]);
                $_SESSION['flash_success'] = 'Removed “' . $nm . '”. '
                    . 'Clients keep any products already copied to them.';
            }
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage();
    }

    header('Location: /master-admin/library-suppliers.php');
    exit;
}

// Load every supplier (active + retired) plus a subscriber count per key.
// client_library_suppliers shares supplier_key's collation (both new tables)
// so this join is safe.
$suppliers = [];
if ($tableReady) {
    $suppliers = $pdo->query(
        'SELECT s.id, s.supplier_key, s.name, s.prefix, s.is_free, s.blurb, s.active, s.sort_order,
                (SELECT COUNT(*) FROM client_library_suppliers c WHERE c.supplier_key = s.supplier_key) AS subscribers
           FROM library_suppliers s
          ORDER BY s.sort_order, s.name'
    )->fetchAll(PDO::FETCH_ASSOC);
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$activeNav = 'library-suppliers';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Library suppliers &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Library suppliers</h1>
                <p class="page-subtitle">
                    The suppliers offered by the Price-List Library. Each one's products live
                    on the master account under its prefix.
                </p>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <a href="/master-admin/master-catalogue.php" class="btn btn-secondary">Master Catalogue</a>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <?php if (!$tableReady): ?>
            <section class="section">
                <div class="alert alert-error" role="alert">
                    The registry table isn't on this database yet — run
                    <a href="/migrate_library_registry.php"><code>/migrate_library_registry.php</code></a>
                    (super-admin) first, then reload this page.
                </div>
            </section>
        <?php else: ?>

        <!-- Add a supplier -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 1rem">Add a supplier</h2>
            <form method="post" action="/master-admin/library-suppliers.php" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="add">
                <div class="form-row">
                    <div class="form-group">
                        <label>Supplier name</label>
                        <input type="text" name="name" class="form-control" maxlength="120"
                               placeholder="e.g. Decora" required>
                    </div>
                    <div class="form-group">
                        <label>Product prefix</label>
                        <input type="text" name="prefix" class="form-control" maxlength="40"
                               placeholder="e.g. Decora" required>
                        <p style="margin:.3rem 0 0;color:var(--text-faint);font-size:.75rem">
                            Products named with this prefix on the master account belong to this supplier.
                        </p>
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label>Blurb <span style="color:var(--text-faint);font-weight:400">(optional)</span></label>
                        <textarea name="blurb" class="form-control" rows="2" maxlength="500"
                                  placeholder="Short description shown to clients on the subscribe page."></textarea>
                    </div>
                </div>
                <div class="form-row full">
                    <label style="display:inline-flex;align-items:center;gap:.5rem;font-weight:600">
                        <input type="checkbox" name="is_free" value="1">
                        Free to every account (otherwise gated behind the price-library add-on)
                    </label>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add supplier</button>
                </div>
            </form>
        </section>

        <!-- Existing suppliers -->
        <?php if (!$suppliers): ?>
            <section class="section">
                <p style="color:var(--text-faint);margin:0">No suppliers yet — add one above.</p>
            </section>
        <?php else: foreach ($suppliers as $s):
            $subs = (int) $s['subscribers'];
        ?>
            <section class="section" style="<?= ((int) $s['active']) === 1 ? '' : 'opacity:.7' ?>">
                <div class="section-header" style="display:flex;justify-content:space-between;align-items:baseline;gap:1rem;flex-wrap:wrap">
                    <h2 class="section-title" style="margin:0">
                        <?= e((string) $s['name']) ?>
                        <?php if ((int) $s['active'] !== 1): ?>
                            <span style="font-size:.6875rem;color:var(--text-faint);font-weight:600;text-transform:uppercase;margin-left:.4rem">Retired</span>
                        <?php endif; ?>
                    </h2>
                    <div style="color:var(--text-faint);font-size:.8125rem">
                        key <code><?= e((string) $s['supplier_key']) ?></code>
                        · <?= $subs ?> subscriber<?= $subs === 1 ? '' : 's' ?>
                    </div>
                </div>

                <form method="post" action="/master-admin/library-suppliers.php" class="form" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="update">
                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Supplier name</label>
                            <input type="text" name="name" class="form-control" maxlength="120"
                                   value="<?= e((string) $s['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Product prefix</label>
                            <input type="text" name="prefix" class="form-control" maxlength="40"
                                   value="<?= e((string) $s['prefix']) ?>" required>
                        </div>
                    </div>
                    <div class="form-row full">
                        <div class="form-group">
                            <label>Blurb</label>
                            <textarea name="blurb" class="form-control" rows="2" maxlength="500"><?= e((string) ($s['blurb'] ?? '')) ?></textarea>
                        </div>
                    </div>
                    <div class="form-row full" style="display:flex;gap:1.5rem;flex-wrap:wrap">
                        <label style="display:inline-flex;align-items:center;gap:.5rem;font-weight:600">
                            <input type="checkbox" name="is_free" value="1" <?= ((int) $s['is_free']) === 1 ? 'checked' : '' ?>>
                            Free to every account
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:.5rem;font-weight:600">
                            <input type="checkbox" name="active" value="1" <?= ((int) $s['active']) === 1 ? 'checked' : '' ?>>
                            Active (shown in the library)
                        </label>
                    </div>
                    <div class="form-actions" style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap">
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>

                <form method="post" action="/master-admin/library-suppliers.php" style="margin:.5rem 0 0"
                      data-confirm="Remove &quot;<?= e((string) $s['name']) ?>&quot; from the library? Clients that already imported it keep their products; they just can't subscribe or re-import. To simply hide it, untick Active instead.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                    <button type="submit" class="btn btn-link" style="color:#b91c1c;background:none;border:0;cursor:pointer;padding:0;font-size:.875rem">
                        Delete supplier
                    </button>
                </form>
            </section>
        <?php endforeach; endif; ?>

        <?php endif; /* tableReady */ ?>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
