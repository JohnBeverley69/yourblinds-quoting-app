<?php
declare(strict_types=1);

/**
 * Supplier price-list library — client page.
 *
 * A tenant admin enables the suppliers they use; enabling copies that
 * supplier's catalogue (products, systems, fabrics, price tables) from the
 * master library into their own account, keeping their own markup and their own
 * products untouched (the existing push engine). The free "Beverley Blinds
 * Trade" supplier is the "blank account -> full catalogue" demo.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireAdmin();

require_once __DIR__ . '/../_partials/library.php';

$user     = current_user();
$clientId = (int) $user['client_id'];
$isAdmin  = true;
$master   = library_master_client_id();
$isMaster = ($master > 0 && $master === $clientId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $key       = (string) ($_POST['supplier_key'] ?? '');
    $suppliers = library_suppliers();

    if (!isset($suppliers[$key])) {
        $_SESSION['flash_error'] = 'Unknown supplier.';
    } else {
        $sup = $suppliers[$key];
        if (empty($sup['free']) && !library_has_addon($clientId)) {
            $_SESSION['flash_error'] = $sup['name'] . ' needs the Price-list library add-on. See Billing to enable it.';
        } elseif ($master <= 0 || $isMaster) {
            $_SESSION['flash_error'] = $isMaster
                ? 'This account is the library source, so it can\'t add suppliers to itself.'
                : 'The library isn\'t set up yet — no master catalogue found.';
        } else {
            require_once __DIR__ . '/../_partials/catalogue_push.php';
            try {
                $summary = push_catalogue_to_client(db(), $master, $clientId, (string) $sup['prefix']);
                db()->prepare(
                    'INSERT INTO client_library_suppliers (client_id, supplier_key, last_imported_at)
                     VALUES (?, ?, NOW())
                     ON DUPLICATE KEY UPDATE last_imported_at = NOW()'
                )->execute([$clientId, $key]);

                $added   = (int) ($summary['products_added']   ?? 0);
                $updated = (int) ($summary['products_updated'] ?? 0);
                $cells   = (int) ($summary['price_table_cells'] ?? 0);
                $_SESSION['flash_success'] = sprintf(
                    '%s added — %d product%s added, %d updated, %s price cells. Your prices use your own markup.',
                    $sup['name'], $added, $added === 1 ? '' : 's', $updated, number_format($cells)
                );
            } catch (Throwable $e) {
                error_log('[YourBlinds] library enable failed: ' . $e->getMessage());
                $_SESSION['flash_error'] = 'Could not add that supplier. Please try again.';
            }
        }
    }
    header('Location: /library/index.php');
    exit;
}

$suppliers = library_suppliers();
$enabled   = library_client_enabled($clientId);
$hasAddon  = library_has_addon($clientId);

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$activeNav = 'price-library';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supplier catalogues &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Supplier catalogues</h1>
                <p class="page-subtitle">
                    Switch on the suppliers you use and their products &mdash; fully priced &mdash; drop
                    straight into your account. We keep the prices up to date; you set your own margin.
                </p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <?php if ($isMaster): ?>
            <section class="section">
                <p style="color:var(--text-secondary)">
                    This account is the <strong>library source</strong>. Manage the master catalogues here;
                    other tenants enable them from their own Supplier catalogues page.
                </p>
            </section>
        <?php else: ?>
            <?php foreach ($suppliers as $key => $sup): ?>
                <?php
                    $isOn   = isset($enabled[$key]);
                    $locked = empty($sup['free']) && !$hasAddon;
                    $last   = $isOn && !empty($enabled[$key]['last_imported_at'])
                            ? date('j M Y', strtotime((string) $enabled[$key]['last_imported_at'])) : '';
                ?>
                <section class="section" style="<?= $isOn ? 'border-left:4px solid var(--brand)' : '' ?>">
                    <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:flex-start">
                        <div style="max-width:42rem">
                            <h2 class="section-title" style="margin:0 0 0.25rem">
                                <?= e((string) $sup['name']) ?>
                                <?php if (!empty($sup['free'])): ?>
                                    <span style="font-size:0.75rem;font-weight:600;color:var(--alert-success-text);margin-left:0.4rem">FREE</span>
                                <?php endif; ?>
                                <?php if ($isOn): ?>
                                    <span style="font-size:0.75rem;font-weight:600;color:var(--brand);margin-left:0.4rem">&check; ENABLED</span>
                                <?php endif; ?>
                            </h2>
                            <p style="color:var(--text-secondary);font-size:0.9375rem;margin:0">
                                <?= e((string) ($sup['blurb'] ?? '')) ?>
                            </p>
                            <?php if ($last !== ''): ?>
                                <p style="color:var(--text-faint);font-size:0.8125rem;margin:0.375rem 0 0">Last updated from the library: <?= e($last) ?></p>
                            <?php endif; ?>
                        </div>
                        <div style="flex-shrink:0">
                            <?php if ($locked): ?>
                                <a href="/billing/index.php" class="btn btn-secondary">Unlock with the add-on</a>
                            <?php else: ?>
                                <form method="post" action="/library/index.php" style="margin:0"
                                      data-confirm="<?= $isOn
                                          ? 'Update your ' . e((string) $sup['name']) . ' products to the latest library prices? Your own markup and your own products are kept.'
                                          : 'Add ' . e((string) $sup['name']) . '\'s full catalogue to your account?' ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="supplier_key" value="<?= e($key) ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <?= $isOn ? 'Update from library' : 'Add to my account' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endforeach; ?>

            <p style="color:var(--text-faint);font-size:0.8125rem;margin:0.5rem 0 0;max-width:46rem">
                More suppliers are added to the library over time. Adding a supplier never touches the
                products you've built yourself, and only ever updates prices &mdash; your markup stays as you set it.
            </p>

            <!-- Request a supplier (demand-driven). Optional price-list upload speeds it up. -->
            <section class="section" style="margin-top:1rem;background:var(--bg-subtle);">
                <h2 class="section-title" style="margin:0 0 0.25rem">Don't see your supplier?</h2>
                <p style="color:var(--text-secondary);font-size:0.9375rem;margin:0 0 0.875rem;max-width:46rem">
                    Request it and we'll look at adding it to the library. If you have their current
                    price list, attach it &mdash; that helps us add it faster.
                </p>
                <form method="post" action="/library/request-supplier.php" enctype="multipart/form-data" class="form" style="max-width:40rem">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Supplier name <span class="required">*</span></label>
                            <input type="text" name="supplier_name" class="form-control" maxlength="160" required
                                   placeholder="e.g. Louvolite">
                        </div>
                        <div class="form-group">
                            <label>Website <span style="color:var(--text-faint);font-weight:400">(optional)</span></label>
                            <input type="text" name="website" class="form-control" maxlength="255"
                                   placeholder="e.g. louvolite.com">
                        </div>
                    </div>
                    <div class="form-row full">
                        <div class="form-group">
                            <label>Notes <span style="color:var(--text-faint);font-weight:400">(optional)</span></label>
                            <input type="text" name="notes" class="form-control" maxlength="500"
                                   placeholder="Which products / ranges you'd like, anything useful">
                        </div>
                    </div>
                    <div class="form-row full">
                        <div class="form-group">
                            <label>Price list <span style="color:var(--text-faint);font-weight:400">(optional &mdash; Excel, CSV or PDF)</span></label>
                            <input type="file" name="price_list" class="form-control" accept=".xlsx,.xlsm,.xls,.csv,.ods,.pdf" style="max-width:24rem">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Request supplier</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
