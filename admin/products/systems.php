<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$productId = (int) ($_GET['product_id'] ?? 0);
if ($productId <= 0) {
    header('Location: /admin/products/index.php');
    exit;
}

$pStmt = db()->prepare(
    'SELECT id, name FROM products WHERE id = ? AND client_id = ?'
);
$pStmt->execute([$productId, $clientId]);
$product = $pStmt->fetch();

if (!$product) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Product not found</h1>';
    exit;
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$f     = ['name' => ''];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_action'] ?? '') === 'create') {
    csrf_check();

    $f['name'] = trim((string) ($_POST['name'] ?? ''));

    if ($f['name'] === '') {
        $error = 'System name is required.';
    } elseif (strlen($f['name']) > 150) {
        $error = 'System name is too long (max 150 chars).';
    } else {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            // sort_order = MAX+1 so new systems append to the end of the
            // list (drag-and-drop is the only re-orderer after that).
            $sortStmt = $pdo->prepare(
                'SELECT COALESCE(MAX(sort_order), -1) + 1
                   FROM product_systems
                  WHERE product_id = ? AND client_id = ?'
            );
            $sortStmt->execute([$productId, $clientId]);
            $nextSort = (int) $sortStmt->fetchColumn();

            $countStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM product_systems
                  WHERE product_id = ? AND client_id = ?'
            );
            $countStmt->execute([$productId, $clientId]);
            $existingSystems = (int) $countStmt->fetchColumn();

            $stmt = $pdo->prepare(
                'INSERT INTO product_systems (client_id, product_id, name, sort_order, active)
                 VALUES (?, ?, ?, ?, 1)'
            );
            $stmt->execute([$clientId, $productId, $f['name'], $nextSort]);
            $newSystemId = (int) $pdo->lastInsertId();

            // If this is the FIRST system on the product, promote any
            // existing NULL-system markup/discount row into the new
            // system so the engine doesn't quietly drop to 0%. Without
            // this, a product that previously priced fine (with a NULL-
            // system markup row) would suddenly lose its margin the
            // moment someone added a system.
            if ($existingSystems === 0) {
                foreach (
                    [
                        ['table' => 'client_markups',   'col' => 'markup_percent'],
                        ['table' => 'client_discounts', 'col' => 'discount_percent'],
                    ] as $t
                ) {
                    $promote = $pdo->prepare(
                        "INSERT INTO {$t['table']} (client_id, product_id, system_id, {$t['col']})
                         SELECT client_id, product_id, ?, {$t['col']}
                           FROM {$t['table']}
                          WHERE client_id = ? AND product_id = ? AND system_id IS NULL"
                    );
                    $promote->execute([$newSystemId, $clientId, $productId]);

                    $pdo->prepare(
                        "DELETE FROM {$t['table']}
                          WHERE client_id = ? AND product_id = ? AND system_id IS NULL"
                    )->execute([$clientId, $productId]);
                }
            }

            $pdo->commit();

            // Audit
            require_once __DIR__ . '/../../_partials/catalogue_audit.php';
            catalogue_audit_log(
                'system', $newSystemId, 'create',
                $f['name'],
                null,
                ['name' => $f['name']],
                $productId
            );

            $_SESSION['flash_success'] = 'System "' . $f['name'] . '" added.';
            // Honour an explicit return_to so the inline quick-add on
            // the product edit page can bounce straight back to itself
            // rather than dumping the user on the systems list. Only
            // accept SAME-ORIGIN paths (starts with /, no protocol or //)
            // — defends against open-redirect via crafted POSTs.
            $returnTo = trim((string) ($_POST['return_to'] ?? ''));
            if ($returnTo !== ''
                && $returnTo[0] === '/'
                && !str_starts_with($returnTo, '//')
                && !preg_match('#^/?\w+://#', $returnTo)) {
                header('Location: ' . $returnTo);
            } else {
                header('Location: /admin/products/systems.php?product_id=' . $productId);
            }
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (str_contains($e->getMessage(), 'uniq_system_per_product')) {
                $error = 'A system with that name already exists for this product.';
            } else {
                $error = 'Could not add: ' . $e->getMessage();
            }
        }
    }
}

// Mark a system as default — only one per product.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_action'] ?? '') === 'set_default') {
    csrf_check();
    $targetId = (int) ($_POST['id'] ?? 0);
    if ($targetId > 0) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Clear default on all systems of this product, then set on target.
            $clear = $pdo->prepare(
                'UPDATE product_systems SET is_default = 0
                  WHERE product_id = ? AND client_id = ?'
            );
            $clear->execute([$productId, $clientId]);
            $set = $pdo->prepare(
                'UPDATE product_systems SET is_default = 1
                  WHERE id = ? AND product_id = ? AND client_id = ?'
            );
            $set->execute([$targetId, $productId, $clientId]);
            $pdo->commit();
            $_SESSION['flash_success'] = 'Default system updated.';
            header('Location: /admin/products/systems.php?product_id=' . $productId);
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not set default: ' . $e->getMessage();
            header('Location: /admin/products/systems.php?product_id=' . $productId);
            exit;
        }
    }
}

// List systems for this product, with price-table counts.
// Sort by sort_order alone — drag-and-drop controls position. The 'default'
// flag is shown as a pill but doesn't dictate order anymore.
$rows = db()->prepare(
    'SELECT s.id, s.name, s.sort_order, s.active, s.is_default, s.updated_at,
            (SELECT COUNT(*) FROM price_tables t WHERE t.system_id = s.id) AS table_count
       FROM product_systems s
      WHERE s.product_id = ? AND s.client_id = ?
   ORDER BY s.sort_order, s.name'
);
$rows->execute([$productId, $clientId]);
$systems = $rows->fetchAll();

$activeNav = 'products';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) $product['name']) ?> &middot; Systems &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .form-row.cols-2-narrow { grid-template-columns: 4fr 1fr; }
        @media (max-width: 700px) { .form-row.cols-2-narrow { grid-template-columns: 1fr; } }
        .row-actions { white-space: nowrap; }
        .row-actions a { font-size: 0.875rem; margin-left: 0.5rem; }
        .row-actions form { display: inline; margin: 0; }
        .row-actions button {
            font-size: 0.875rem; color: #b91c1c; background: transparent;
            border: 0; cursor: pointer; padding: 0; margin-left: 0.5rem;
        }
        .row-actions button:hover { text-decoration: underline; }
        .system-name { font-weight: 600; color: #111827; }
        a.system-name { text-decoration: none; }
        a.system-name:hover { color: #1f3b5b; text-decoration: underline; }
        .inactive-pill {
            display: inline-block; padding: 0.0625rem 0.5rem; font-size: 0.6875rem;
            font-weight: 600; color: #6b7280; background: #f3f4f6;
            border-radius: 999px; margin-left: 0.5rem;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .default-pill {
            display: inline-block; padding: 0.0625rem 0.5rem; font-size: 0.6875rem;
            font-weight: 700; color: #fff; background: #16a34a;
            border-radius: 999px; margin-left: 0.5rem;
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
                <?php
                    require_once __DIR__ . '/../../_partials/breadcrumb.php';
                    echo render_breadcrumb([
                        ['Products',                  '/admin/products/index.php'],
                        [(string) $product['name'],   '/admin/products/edit.php?id=' . (int) $productId],
                        ['Systems',                   null],
                    ]);
                ?>
                <h1 class="page-title">
                    <?= e((string) $product['name']) ?> &mdash; Systems
                </h1>
                <p class="page-subtitle">
                    <a href="/admin/products/edit.php?id=<?= (int) $productId ?>">Edit product</a>
                    &middot;
                    <a href="/admin/products/options.php?product_id=<?= (int) $productId ?>">Fabrics</a>
                </p>
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
            <p style="margin:0;color:#0c4a6e;font-size:0.9375rem;line-height:1.5">
                <strong>What's a system?</strong>
                A variant of the blind that has its <em>own price grid</em> &mdash;
                e.g. <em>Standard</em> vs <em>Motorised</em>, or <em>25mm slat</em>
                vs <em>50mm slat</em> on wood venetians. Each system gets its own
                width × drop price table per fabric band. Add one if this product
                has variants that cost different amounts; skip if not.
            </p>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Add system</h2>
            </div>
            <form method="post" action="/admin/products/systems.php?product_id=<?= (int) $productId ?>"
                  class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="create">

                <div class="form-row full">
                    <div class="form-group">
                        <label for="name">System name <span class="required">*</span></label>
                        <input id="name" name="name" type="text"
                               required maxlength="150" autofocus
                               value="<?= e((string) $f['name']) ?>"
                               placeholder="e.g. Slim Line">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add system</button>
                </div>
            </form>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Systems (<?= count($systems) ?>)</h2>
            </div>

            <?php if (!$systems): ?>
                <div class="placeholder">
                    <p class="placeholder-title">No systems yet</p>
                    <p class="placeholder-body">
                        Add at least one system (e.g. "Slim Line") before importing price tables.
                        Each system gets its own grid of prices.
                    </p>
                </div>
            <?php else: ?>
                <p style="color:#6b7280;font-size:0.9375rem;margin:0 0 0.5rem">
                    Drag the <strong>⋮⋮</strong> handle to reorder.
                    <span class="reorder-status">Saving…</span>
                </p>
                <div class="table-wrap">
                    <table class="table sortable-list" data-reorder-type="systems">
                        <thead>
                            <tr>
                                <th class="drag-col"></th>
                                <th>Name</th>
                                <th class="num">Price tables</th>
                                <th>Updated</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($systems as $s): ?>
                                <tr data-id="<?= (int) $s['id'] ?>">
                                    <td class="drag-col" title="Drag to reorder">⋮⋮</td>
                                    <td>
                                        <a href="/admin/products/price-tables.php?system_id=<?= (int) $s['id'] ?>"
                                           class="system-name">
                                            <?= e((string) $s['name']) ?>
                                        </a>
                                        <?php if ((int) $s['is_default'] === 1): ?>
                                            <span class="default-pill">Default</span>
                                        <?php endif; ?>
                                        <?php if ((int) $s['active'] !== 1): ?>
                                            <span class="inactive-pill">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="num">
                                        <a href="/admin/products/price-tables.php?system_id=<?= (int) $s['id'] ?>">
                                            <?= (int) $s['table_count'] ?>
                                        </a>
                                    </td>
                                    <?php require_once __DIR__ . '/../../_partials/time_ago.php'; ?>
                                    <td style="font-size:0.8125rem;color:#6b7280;white-space:nowrap"
                                        title="<?= e((string) $s['updated_at']) ?>">
                                        <?= e(time_ago((string) $s['updated_at'])) ?>
                                    </td>
                                    <td class="row-actions">
                                        <?php if ((int) $s['is_default'] !== 1): ?>
                                            <form method="post"
                                                  action="/admin/products/systems.php?product_id=<?= (int) $productId ?>"
                                                  style="display:inline;margin:0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_action" value="set_default">
                                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                                <button type="submit"
                                                        style="font-size:0.875rem;color:#1f3b5b;background:transparent;border:0;cursor:pointer;padding:0;margin-left:0.5rem;">
                                                    Set default
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="/admin/products/system-delete.php"
                                              data-confirm="Delete system <?= e((string) $s['name']) ?>? This wipes its <?= (int) $s['table_count'] ?> price tables (and all their cells) too.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                            <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
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
<?php if ($systems): require __DIR__ . '/../../_partials/sortable_init.php'; endif; ?>
<?php require __DIR__ . '/../../_partials/confirm_modal.php'; ?>
</body>
</html>
