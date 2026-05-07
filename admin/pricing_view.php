<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('Price table not found.');
}

$stmt = db()->prepare(
    'SELECT pt.*, p.name AS product_name, pg.name AS group_name
       FROM price_tables pt
       JOIN products p        ON p.id = pt.product_id
       JOIN product_groups pg ON pg.id = p.product_group_id
      WHERE pt.id = ? AND pt.client_id = ?
      LIMIT 1'
);
$stmt->execute([$id, $clientId]);
$table = $stmt->fetch();
if (!$table) {
    http_response_code(404);
    exit('Price table not found.');
}

$rowsStmt = db()->prepare(
    'SELECT width_value, drop_value_exact, base_price
       FROM price_table_rows
      WHERE price_table_id = ?
      ORDER BY width_value, drop_value_exact'
);
$rowsStmt->execute([$id]);
$rows = $rowsStmt->fetchAll();

// Build width × drop pivot
$widths = [];
$drops  = [];
$cell   = [];
foreach ($rows as $r) {
    $w = (string) $r['width_value'];
    $d = (string) $r['drop_value_exact'];
    $widths[$w] = (float) $w;
    $drops[$d]  = (float) $d;
    $cell[$w][$d] = (float) $r['base_price'];
}
ksort($widths, SORT_NUMERIC);
ksort($drops,  SORT_NUMERIC);

$money    = static fn ($n) => '£' . number_format((float) $n, 2);
$fmtSize  = static fn (float $v) => number_format($v, 1, '.', '');
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) $table['table_name']) ?> &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .matrix { width: auto; min-width: 100%; }
        .matrix th, .matrix td { text-align: right; font-variant-numeric: tabular-nums; padding: 0.5rem 0.75rem; }
        .matrix thead th { background: #1f3b5b; color: #fff; }
        .matrix tbody th { background: #f9fafb; color: #1f3b5b; font-weight: 600; }
        .matrix tbody td { background: #fff; }
        .matrix .corner { background: transparent; border: 0; }
    </style>
</head>
<body>
<div class="app-shell">
    <input type="checkbox" id="navToggle" class="nav-toggle-input">
    <label class="nav-fab" for="navToggle" aria-label="Open menu" tabindex="0">
        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
    </label>
    <label class="nav-close" for="navToggle" aria-label="Close menu" tabindex="0">
        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </label>
    <label class="nav-backdrop" for="navToggle" aria-hidden="true"></label>
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
                <h1 class="page-title"><?= e((string) $table['table_name']) ?></h1>
                <p class="page-subtitle">
                    <a href="/admin/pricing.php">&larr; Back to price lists</a>
                </p>
            </div>
        </div>

        <section class="section">
            <div class="detail-cols">
                <div>
                    <h3>Product</h3>
                    <p style="margin:0; font-weight:600; color:#111827;">
                        <?= e((string) $table['product_name']) ?>
                    </p>
                    <p style="margin:.25rem 0 0; color:#6b7280; font-size:.875rem;">
                        <?= e((string) $table['group_name']) ?>
                        <?php if (!empty($table['band_code'])): ?>
                            &middot; Band <?= e((string) $table['band_code']) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <h3>Status</h3>
                    <dl>
                        <dt>Active</dt>
                        <dd><?= (int) $table['active'] === 1 ? 'Yes' : 'No' ?></dd>
                        <dt>Cells</dt>
                        <dd><?= count($rows) ?></dd>
                        <?php if (!empty($table['notes'])): ?>
                            <dt>Notes</dt>
                            <dd><?= e((string) $table['notes']) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Width &times; drop matrix (metres)</h2>
            </div>

            <?php if (empty($rows)): ?>
                <div class="table-empty">No matrix rows yet for this price table.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="matrix table">
                        <thead>
                            <tr>
                                <th class="corner"><span style="font-size:.75rem; color:#6b7280;">Width &darr; / Drop &rarr;</span></th>
                                <?php foreach ($drops as $d): ?>
                                    <th><?= e($fmtSize($d)) ?> m</th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($widths as $wKey => $w): ?>
                                <tr>
                                    <th scope="row"><?= e($fmtSize($w)) ?> m</th>
                                    <?php foreach ($drops as $dKey => $d): ?>
                                        <td>
                                            <?php if (isset($cell[$wKey][$dKey])): ?>
                                                <?= e($money($cell[$wKey][$dKey])) ?>
                                            <?php else: ?>
                                                <span style="color:#d1d5db;">—</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
