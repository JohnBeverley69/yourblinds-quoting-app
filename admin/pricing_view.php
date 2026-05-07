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
$activeNav = 'pricing';
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
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

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
