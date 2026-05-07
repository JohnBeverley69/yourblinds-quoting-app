<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$user     = current_user();
$clientId = $user['client_id'];
$isAdmin  = $user['role'] === 'admin';

$validStatuses = ['draft', 'sent', 'accepted', 'rejected', 'ordered', 'expired', 'archived'];
$status = (string) ($_GET['status'] ?? '');
if (!in_array($status, $validStatuses, true)) {
    $status = '';
}
$q = trim((string) ($_GET['q'] ?? ''));

// Status counts (for the tabs)
$cntStmt = db()->prepare(
    'SELECT status, COUNT(*) AS n FROM quotes WHERE client_id = ? GROUP BY status'
);
$cntStmt->execute([$clientId]);
$byStatus = ['' => 0];
foreach ($cntStmt->fetchAll() as $row) {
    $byStatus[$row['status']] = (int) $row['n'];
    $byStatus[''] += (int) $row['n'];
}

// Build the list query dynamically
$sql = 'SELECT q.id, q.quote_number, q.end_customer_name, q.status,
               q.subtotal, q.vat, q.total, q.created_at, q.updated_at,
               (SELECT COUNT(*) FROM quote_items qi WHERE qi.quote_id = q.id) AS item_count
          FROM quotes q
         WHERE q.client_id = ?';
$params = [$clientId];

if ($status !== '') {
    $sql .= ' AND q.status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $sql .= ' AND (q.quote_number LIKE ? OR q.end_customer_name LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
$sql .= ' ORDER BY q.updated_at DESC LIMIT 100';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$quotes = $stmt->fetchAll();

$money = static fn ($n) => '£' . number_format((float) $n, 2);

$dashHref = $isAdmin ? '/admin/index.php' : '/quote-builder/index.php';
$dashTag  = $isAdmin ? 'Admin Console'    : 'Trade Portal';

$tab = static function (string $key, string $label) use ($status, $byStatus, $q): string {
    $href = '/quote-history/index.php';
    $args = [];
    if ($key !== '') {
        $args['status'] = $key;
    }
    if ($q !== '') {
        $args['q'] = $q;
    }
    if ($args) {
        $href .= '?' . http_build_query($args);
    }
    $active = $status === $key ? ' active' : '';
    $count  = $byStatus[$key] ?? 0;
    return sprintf(
        '<a class="tab%s" href="%s">%s<span class="tab-count">(%d)</span></a>',
        $active,
        htmlspecialchars($href, ENT_QUOTES),
        htmlspecialchars($label, ENT_QUOTES),
        $count
    );
};
$activeNav = 'quote-history';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quote History &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Quote History</h1>
                <p class="page-subtitle">
                    All quotes for <?= e($user['company_name']) ?>.
                </p>
            </div>
            <a href="/quote-builder/new.php" class="btn btn-primary">+ New Quote</a>
        </div>

        <section class="section">
            <form method="get" action="/quote-history/index.php" class="search-form" role="search">
                <input
                    type="search"
                    name="q"
                    value="<?= e($q) ?>"
                    placeholder="Search by quote number or customer name...">
                <?php if ($status !== ''): ?>
                    <input type="hidden" name="status" value="<?= e($status) ?>">
                <?php endif; ?>
                <button type="submit" class="btn btn-secondary">Search</button>
                <?php if ($q !== '' || $status !== ''): ?>
                    <a href="/quote-history/index.php" class="btn btn-sm btn-secondary">Clear</a>
                <?php endif; ?>
            </form>

            <nav class="tabs" aria-label="Filter by status">
                <?= $tab('',         'All') ?>
                <?= $tab('draft',    'Draft') ?>
                <?= $tab('sent',     'Sent') ?>
                <?= $tab('accepted', 'Accepted') ?>
                <?= $tab('ordered',  'Ordered') ?>
                <?= $tab('rejected', 'Rejected') ?>
                <?= $tab('expired',  'Expired') ?>
                <?= $tab('archived', 'Archived') ?>
            </nav>

            <?php if (empty($quotes)): ?>
                <div class="table-empty">
                    <?php if ($q !== '' || $status !== ''): ?>
                        No quotes match the current filter.
                        <a href="/quote-history/index.php">View all &rarr;</a>
                    <?php else: ?>
                        No quotes yet.
                        <a href="/quote-builder/new.php">Create your first quote &rarr;</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Quote #</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th class="num">Items</th>
                                <th class="num">Total</th>
                                <th>Updated</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quotes as $row): ?>
                                <tr>
                                    <td>
                                        <a href="/quote-history/view.php?id=<?= (int) $row['id'] ?>">
                                            <strong><?= e((string) $row['quote_number']) ?></strong>
                                        </a>
                                    </td>
                                    <td><?= e((string) $row['end_customer_name']) ?></td>
                                    <td><span class="badge badge-<?= e((string) $row['status']) ?>"><?= e((string) $row['status']) ?></span></td>
                                    <td class="num"><?= (int) $row['item_count'] ?></td>
                                    <td class="num"><?= e($money($row['total'])) ?></td>
                                    <td><?= e(date('j M Y', strtotime((string) ($row['updated_at'] ?? $row['created_at'])))) ?></td>
                                    <td>
                                        <a href="/quote-history/view.php?id=<?= (int) $row['id'] ?>">View</a>
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
</body>
</html>
