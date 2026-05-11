<?php
declare(strict_types=1);

/**
 * Orders view — quotes that have been won.
 *
 * Lifecycle: draft → sent → accepted → ordered → invoiced → paid.
 * Anything in 'accepted' and beyond is, conceptually, an order: the
 * customer's said yes and the job is in motion. This page filters
 * the quotes table down to those four statuses so they stop getting
 * lost in Quote History alongside drafts and declines.
 *
 * Reuses the quote schema rather than promoting orders into their
 * own table — the snapshot fields and totals are exactly the data
 * an order needs anyway. If the schema ever evolves a real "orders"
 * table, this page becomes the natural reading layer for it.
 *
 * Same shape as quote-history/index.php: search box, status chips
 * pre-filtered to the order subset, and a table of records linking
 * back to /quote-builder/edit.php for the underlying quote.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$user     = current_user();
$clientId = (int) $user['client_id'];

$orderStatuses = ['accepted', 'ordered', 'invoiced', 'paid'];

$status = trim((string) ($_GET['status'] ?? ''));
$q      = trim((string) ($_GET['q'] ?? ''));

$where  = ['client_id = ?'];
$params = [$clientId];

if ($status !== '' && in_array($status, $orderStatuses, true)) {
    $where[]  = 'status = ?';
    $params[] = $status;
} else {
    // Default: all order statuses, in one IN-clause.
    $ph        = implode(',', array_fill(0, count($orderStatuses), '?'));
    $where[]   = "status IN ($ph)";
    $params    = array_merge($params, $orderStatuses);
}

if ($q !== '') {
    $where[]  = '(quote_number LIKE ? OR end_customer_name LIKE ? OR end_customer_postcode LIKE ?)';
    $like     = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$sql = 'SELECT id, quote_number, end_customer_name, end_customer_postcode,
               status, total, accepted_at, created_at, updated_at
          FROM quotes
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY COALESCE(accepted_at, created_at) DESC
         LIMIT 200';
$st = db()->prepare($sql);
$st->execute($params);
$orders = $st->fetchAll();

// Per-status counts for the filter chips — scoped to the order
// subset (so "ordered (3)" etc. always refers to the orders pool,
// not the full quotes pool).
$countSt = db()->prepare(
    "SELECT status, COUNT(*) AS n
       FROM quotes
      WHERE client_id = ?
        AND status IN (" . implode(',', array_fill(0, count($orderStatuses), '?')) . ")
   GROUP BY status"
);
$countSt->execute(array_merge([$clientId], $orderStatuses));
$counts = [];
$grandTotal = 0;
foreach ($countSt->fetchAll() as $r) {
    $counts[$r['status']] = (int) $r['n'];
    $grandTotal += (int) $r['n'];
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$money   = static fn ($n) => '£' . number_format((float) $n, 2);
$fmtDate = static function (?string $dt): string {
    if (!$dt) return '—';
    $ts = strtotime((string) $dt);
    return $ts ? date('j M Y', $ts) : '—';
};

$activeNav = 'orders';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orders &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .filter-chips { display: flex; gap: 0.5rem; flex-wrap: wrap; margin: 0 0 0.75rem; }
        .filter-chips a {
            display: inline-block; padding: 0.25rem 0.625rem;
            font-size: 0.8125rem; border-radius: 999px; text-decoration: none;
            background: #f3f4f6; color: #4b5563; border: 1px solid transparent;
        }
        .filter-chips a.active { background: #1f3b5b; color: #fff; }
        .filter-chips a:hover  { border-color: #d1d5db; }
        .status-pill {
            display: inline-block; padding: 0.0625rem 0.5rem; font-size: 0.6875rem;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
            border-radius: 999px;
        }
        .status-accepted  { background: #d1fae5; color: #065f46; }
        .status-ordered   { background: #ede9fe; color: #5b21b6; }
        .status-invoiced  { background: #fef3c7; color: #92400e; }
        .status-paid      { background: #14532d; color: #fff; }
        a.q-link { font-weight: 600; color: #111827; text-decoration: none; }
        a.q-link:hover { color: #1f3b5b; text-decoration: underline; }
        .search-form { display: flex; gap: 0.5rem; margin-bottom: 0.75rem; }
        .search-form input { flex: 1; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font: inherit; }
        .empty-state {
            padding: 2rem 1rem; text-align: center;
            color: #6b7280; font-size: 0.9375rem;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Orders</h1>
                <p class="page-subtitle">
                    Quotes the customer's accepted — accepted, ordered, invoiced and paid.
                </p>
            </div>
            <a href="/quote-history/index.php" class="btn btn-secondary">Quote history &rarr;</a>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <section class="section">
            <div class="filter-chips">
                <a href="/orders/index.php" class="<?= $status === '' ? 'active' : '' ?>">
                    All (<?= $grandTotal ?>)
                </a>
                <?php foreach ($orderStatuses as $s): ?>
                    <?php if (empty($counts[$s])) continue; ?>
                    <a href="/orders/index.php?status=<?= e($s) ?>"
                       class="<?= $status === $s ? 'active' : '' ?>">
                        <?= ucfirst($s) ?> (<?= (int) $counts[$s] ?>)
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="get" action="/orders/index.php" class="search-form">
                <?php if ($status !== ''): ?>
                    <input type="hidden" name="status" value="<?= e($status) ?>">
                <?php endif; ?>
                <input type="search" name="q" value="<?= e($q) ?>"
                       placeholder="Search by quote #, customer name, or postcode...">
                <button type="submit" class="btn btn-secondary">Search</button>
                <?php if ($q !== ''): ?>
                    <a href="/orders/index.php<?= $status !== '' ? '?status=' . e($status) : '' ?>"
                       class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </form>

            <?php if (!$orders): ?>
                <div class="empty-state">
                    <?php if ($q !== '' || $status !== ''): ?>
                        Nothing matches your filter. <a href="/orders/index.php">Clear filters</a>.
                    <?php else: ?>
                        No orders yet. Once a quote is marked accepted it'll appear here.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Quote #</th>
                                <th>Customer</th>
                                <th>Postcode</th>
                                <th>Status</th>
                                <th>Accepted</th>
                                <th class="num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $o): ?>
                                <tr>
                                    <td>
                                        <a class="q-link"
                                           href="/quote-builder/edit.php?id=<?= (int) $o['id'] ?>">
                                            <?= e((string) $o['quote_number']) ?>
                                        </a>
                                    </td>
                                    <td><?= e((string) ($o['end_customer_name'] ?? '')) ?></td>
                                    <td><?= e((string) ($o['end_customer_postcode'] ?? '')) ?></td>
                                    <td>
                                        <span class="status-pill status-<?= e((string) $o['status']) ?>">
                                            <?= e((string) $o['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= e($fmtDate((string) ($o['accepted_at'] ?? null))) ?></td>
                                    <td class="num"><?= e($money($o['total'])) ?></td>
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
