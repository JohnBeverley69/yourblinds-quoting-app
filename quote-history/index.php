<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$user     = current_user();
$clientId = (int) $user['client_id'];

$status = trim((string) ($_GET['status'] ?? ''));
$q      = trim((string) ($_GET['q'] ?? ''));

$where  = ['client_id = ?'];
$params = [$clientId];

if ($status !== '' && in_array($status, ['draft','sent','accepted','declined','ordered','invoiced','paid'], true)) {
    $where[]  = 'status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[]  = '(quote_number LIKE ? OR end_customer_name LIKE ? OR end_customer_postcode LIKE ?)';
    $like     = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$sql = 'SELECT id, quote_number, end_customer_name, end_customer_postcode,
               status, total, created_at, updated_at
          FROM quotes
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY created_at DESC
         LIMIT 200';
$st = db()->prepare($sql);
$st->execute($params);
$quotes = $st->fetchAll();

// Status counts for the filter chips.
$countSt = db()->prepare(
    'SELECT status, COUNT(*) AS n FROM quotes WHERE client_id = ? GROUP BY status'
);
$countSt->execute([$clientId]);
$counts = [];
$total  = 0;
foreach ($countSt->fetchAll() as $r) {
    $counts[$r['status']] = (int) $r['n'];
    $total += (int) $r['n'];
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$money = static fn ($n) => '£' . number_format((float) $n, 2);

$activeNav = 'quote-history';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quote history &middot; YourBlinds</title>
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
        .status-draft     { background: #e5e7eb; color: #374151; }
        .status-sent      { background: #dbeafe; color: #1e40af; }
        .status-accepted  { background: #d1fae5; color: #065f46; }
        .status-declined  { background: #fee2e2; color: #991b1b; }
        .status-ordered   { background: #ede9fe; color: #5b21b6; }
        .status-invoiced  { background: #fef3c7; color: #92400e; }
        .status-paid      { background: #14532d; color: #fff; }
        a.q-link { font-weight: 600; color: #111827; text-decoration: none; }
        a.q-link:hover { color: #1f3b5b; text-decoration: underline; }
        .search-form { display: flex; gap: 0.5rem; margin-bottom: 0.75rem; }
        .search-form input { flex: 1; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font: inherit; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Quote history</h1>
                <p class="page-subtitle">All quotes for <?= e((string) $user['company_name']) ?>.</p>
            </div>
            <a href="/quote-builder/new.php" class="btn btn-primary">+ New quote</a>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <section class="section">
            <div class="filter-chips">
                <a href="/quote-history/index.php" class="<?= $status === '' ? 'active' : '' ?>">
                    All (<?= $total ?>)
                </a>
                <?php foreach (['draft','sent','accepted','declined','ordered','invoiced','paid'] as $s): ?>
                    <?php if (empty($counts[$s])) continue; ?>
                    <a href="/quote-history/index.php?status=<?= e($s) ?>"
                       class="<?= $status === $s ? 'active' : '' ?>">
                        <?= ucfirst($s) ?> (<?= (int) $counts[$s] ?>)
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="get" action="/quote-history/index.php" class="search-form">
                <?php if ($status !== ''): ?>
                    <input type="hidden" name="status" value="<?= e($status) ?>">
                <?php endif; ?>
                <input type="search" name="q" value="<?= e($q) ?>"
                       placeholder="Search by quote #, customer name, or postcode...">
                <button type="submit" class="btn btn-secondary">Search</button>
                <?php if ($q !== ''): ?>
                    <a href="/quote-history/index.php<?= $status !== '' ? '?status=' . e($status) : '' ?>"
                       class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </form>

            <?php if (!$quotes): ?>
                <div class="placeholder">
                    <p class="placeholder-title">No quotes <?= $status !== '' || $q !== '' ? 'match' : 'yet' ?></p>
                    <p class="placeholder-body">
                        <a href="/quote-builder/new.php">Start a new quote &rarr;</a>
                    </p>
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
                                <th class="num">Total</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quotes as $r): ?>
                                <tr>
                                    <td>
                                        <a href="/quote-builder/edit.php?id=<?= (int) $r['id'] ?>" class="q-link">
                                            <?= e((string) $r['quote_number']) ?>
                                        </a>
                                    </td>
                                    <td><?= e((string) $r['end_customer_name']) ?></td>
                                    <td><?= e((string) ($r['end_customer_postcode'] ?? '')) ?></td>
                                    <td>
                                        <span class="status-pill status-<?= e((string) $r['status']) ?>">
                                            <?= e((string) $r['status']) ?>
                                        </span>
                                    </td>
                                    <td class="num"><?= e($money($r['total'])) ?></td>
                                    <td style="font-size:0.8125rem;color:#6b7280;white-space:nowrap">
                                        <?= e(date('j M Y', strtotime((string) $r['created_at']))) ?>
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
