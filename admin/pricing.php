<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$flashMsg = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

$stmt = db()->prepare(
    'SELECT pt.id, pt.table_name, pt.band_code, pt.notes, pt.active,
            p.id   AS product_id,
            p.name AS product_name,
            pg.name AS group_name,
            pg.sort_order,
            (SELECT COUNT(*) FROM price_table_rows WHERE price_table_id = pt.id) AS row_count,
            (SELECT MIN(base_price) FROM price_table_rows WHERE price_table_id = pt.id) AS min_price,
            (SELECT MAX(base_price) FROM price_table_rows WHERE price_table_id = pt.id) AS max_price
       FROM price_tables pt
       JOIN products p          ON p.id  = pt.product_id
       JOIN product_groups pg   ON pg.id = p.product_group_id
      WHERE pt.client_id = ?
      ORDER BY pg.sort_order, pg.name, p.name, pt.band_code'
);
$stmt->execute([$clientId]);
$tables = $stmt->fetchAll();

$money = static fn ($n) => '£' . number_format((float) $n, 2);

$grouped = [];
foreach ($tables as $t) {
    $key = $t['group_name'] . ' / ' . $t['product_name'];
    $grouped[$key][] = $t;
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Price Lists &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
</head>
<body>
<div class="app-shell">
    <aside class="app-sidebar" aria-label="Primary navigation">
        <div class="app-sidebar-brand">
            <a href="/admin/index.php" class="app-brand-mark">Your<span class="accent">Blinds</span></a>
            <span class="app-brand-tag">Admin Console</span>
        </div>
        <div class="app-sidebar-user">
            <div class="app-sidebar-user-name"><?= e($user['full_name']) ?></div>
            <div class="app-sidebar-user-meta"><?= e($user['company_name']) ?> &middot; <?= e($user['role']) ?></div>
        </div>
        <nav class="app-sidebar-nav">
            <a href="/admin/index.php">Dashboard</a>
            <a href="/quote-builder/index.php">Quote Builder</a>
            <a href="/quote-history/index.php">Quote History</a>
            <a href="/customer-manager/index.php">Customer Manager</a>
            <a href="/admin/pricing.php" class="active">Price Lists</a>
            <a href="/admin/users.php">Users</a>
            <a href="/admin/settings.php">Settings</a>
        </nav>
        <div class="app-sidebar-foot">
            <a href="/auth/logout.php">Sign out &rarr;</a>
        </div>
    </aside>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Price Lists</h1>
                <p class="page-subtitle">Per-product width &times; drop matrices, grouped by band.</p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>

        <?php if (empty($tables)): ?>
            <div class="placeholder">
                <p class="placeholder-title">No price tables yet</p>
                <p class="placeholder-body">
                    Pricing is per-client &mdash; you'll need product groups, products and price
                    tables before quotes can be built. Import / management UI is coming next.
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($grouped as $title => $rows): ?>
                <section class="section">
                    <div class="section-header">
                        <h2 class="section-title"><?= e((string) $title) ?></h2>
                    </div>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Band</th>
                                    <th>Table name</th>
                                    <th class="num">Cells</th>
                                    <th class="num">Min</th>
                                    <th class="num">Max</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><strong><?= e((string) ($r['band_code'] ?? '—')) ?></strong></td>
                                        <td><?= e((string) $r['table_name']) ?></td>
                                        <td class="num"><?= (int) $r['row_count'] ?></td>
                                        <td class="num"><?= $r['min_price'] !== null ? e($money($r['min_price'])) : '—' ?></td>
                                        <td class="num"><?= $r['max_price'] !== null ? e($money($r['max_price'])) : '—' ?></td>
                                        <td>
                                            <?php if ((int) $r['active'] === 1): ?>
                                                <span class="badge badge-accepted">active</span>
                                            <?php else: ?>
                                                <span class="badge badge-archived">inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><a href="/admin/pricing_view.php?id=<?= (int) $r['id'] ?>">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
