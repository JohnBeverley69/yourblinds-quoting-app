<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/customer_access.php';

requireLogin();

$user     = current_user();
$clientId = $user['client_id'];
$isAdmin  = $user['role'] === 'admin';

// Permission gate: non-admin users without can_view_all_customer_jobs
// only see customers linked to jobs they're personally assigned to.
// Admin (and users with the permission) see everything. Same rule as the
// edit/delete pages — see _partials/customer_access.php.
$restrictToMine = !cm_can_view_all_customers($user);
$myUserId = (int) $user['user_id'];

$q = trim((string) ($_GET['q'] ?? ''));

$flashMsg = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

// The `quotes` table is dropped during the Phase 2 schema rebuild and won't
// come back until Phase 3. Until then, gate the JOIN + count column so the
// page survives. When Phase 3 lands, the quote count just starts populating
// automatically — no further code change needed here.
$hasQuotes = (bool) db()->query("SHOW TABLES LIKE 'quotes'")->fetchColumn();

if ($hasQuotes) {
    $selectExtra = ', COUNT(quotes.id) AS quote_count';
    $joinExtra   = 'LEFT JOIN quotes ON quotes.customer_id = c.id';
    $groupBy     = 'GROUP BY c.id';
} else {
    $selectExtra = ', 0 AS quote_count';
    $joinExtra   = '';
    $groupBy     = '';
}

// Permission filter: only customers one of the user's appointments is for
// (directly or via a quote), plus any they added this session.
$permClause = '';
$permParams = [];
if ($restrictToMine && $hasQuotes) {
    $created    = array_map('intval', (array) ($_SESSION['cm_created'] ?? []));
    $createdSql = $created ? ' OR c.id IN (' . implode(',', $created) . ')' : '';
    $permClause = ' AND (' . cm_mine_sql() . $createdSql . ')';
    $permParams = [$myUserId, $myUserId];
}

if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = db()->prepare(
        "SELECT c.id, c.name, c.email, c.phone, c.town, c.postcode, c.updated_at
                $selectExtra
           FROM customers c
           $joinExtra
          WHERE c.client_id = ?
            AND (c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?
                 OR c.postcode LIKE ? OR c.town LIKE ?)
            $permClause
          $groupBy
          ORDER BY c.name
          LIMIT 100"
    );
    $stmt->execute(array_merge(
        [$clientId, $like, $like, $like, $like, $like],
        $permParams
    ));
} else {
    $stmt = db()->prepare(
        "SELECT c.id, c.name, c.email, c.phone, c.town, c.postcode, c.updated_at
                $selectExtra
           FROM customers c
           $joinExtra
          WHERE c.client_id = ?
            $permClause
          $groupBy
          ORDER BY c.name
          LIMIT 100"
    );
    $stmt->execute(array_merge([$clientId], $permParams));
}
$customers = $stmt->fetchAll();

$dashTag = $isAdmin ? 'Admin Console' : 'Trade Portal';
$activeNav = 'customers';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customers &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Customers</h1>
                <p class="page-subtitle">
                    End-customers belonging to <?= e($user['company_name']) ?>.
                </p>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <?php if ($isAdmin): ?>
                    <a href="/customer-manager/dedupe.php" class="btn btn-secondary"
                       title="Find and merge customers with the same name">
                        Find duplicates
                    </a>
                <?php endif; ?>
                <a href="/customer-manager/new.php" class="btn btn-primary">+ Add customer</a>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>

        <section class="section">
            <form method="get" action="/customer-manager/index.php" class="search-form" role="search">
                <input
                    type="search"
                    name="q"
                    value="<?= e($q) ?>"
                    placeholder="Search by name, email, phone, town or postcode...">
                <button type="submit" class="btn btn-secondary">Search</button>
                <?php if ($q !== ''): ?>
                    <a href="/customer-manager/index.php" class="btn btn-sm btn-secondary">Clear</a>
                <?php endif; ?>
            </form>

            <?php if (empty($customers)): ?>
                <div class="table-empty">
                    <?php if ($q !== ''): ?>
                        No customers match &ldquo;<?= e($q) ?>&rdquo;.
                        <a href="/customer-manager/index.php">View all &rarr;</a>
                    <?php else: ?>
                        No customers yet.
                        <a href="/customer-manager/new.php">Add your first customer &rarr;</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Town</th>
                                <th>Postcode</th>
                                <?php if ($hasQuotes): ?>
                                    <th class="num">Quotes</th>
                                <?php endif; ?>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $c): ?>
                                <tr>
                                    <td><strong><?= e($c['name']) ?></strong></td>
                                    <td><?= e((string) ($c['email'] ?? '')) ?></td>
                                    <td><?= e((string) ($c['phone'] ?? '')) ?></td>
                                    <td><?= e((string) ($c['town'] ?? '')) ?></td>
                                    <td><?= e((string) ($c['postcode'] ?? '')) ?></td>
                                    <?php if ($hasQuotes): ?>
                                        <td class="num"><?= (int) $c['quote_count'] ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <a href="/customer-manager/edit.php?id=<?= (int) $c['id'] ?>">Edit</a>
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
