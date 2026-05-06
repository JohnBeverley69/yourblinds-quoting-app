<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$user     = current_user();
$clientId = $user['client_id'];
$isAdmin  = $user['role'] === 'admin';

$q = trim((string) ($_GET['q'] ?? ''));

$flashMsg = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = db()->prepare(
        'SELECT c.id, c.name, c.email, c.phone, c.town, c.postcode, c.updated_at,
                COUNT(quotes.id) AS quote_count
           FROM customers c
           LEFT JOIN quotes ON quotes.customer_id = c.id
          WHERE c.client_id = ?
            AND (c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?
                 OR c.postcode LIKE ? OR c.town LIKE ?)
          GROUP BY c.id
          ORDER BY c.name
          LIMIT 100'
    );
    $stmt->execute([$clientId, $like, $like, $like, $like, $like]);
} else {
    $stmt = db()->prepare(
        'SELECT c.id, c.name, c.email, c.phone, c.town, c.postcode, c.updated_at,
                COUNT(quotes.id) AS quote_count
           FROM customers c
           LEFT JOIN quotes ON quotes.customer_id = c.id
          WHERE c.client_id = ?
          GROUP BY c.id
          ORDER BY c.name
          LIMIT 100'
    );
    $stmt->execute([$clientId]);
}
$customers = $stmt->fetchAll();

$dashHref = $isAdmin ? '/admin/index.php' : '/quote-builder/index.php';
$dashTag  = $isAdmin ? 'Admin Console'    : 'Trade Portal';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customers &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
</head>
<body>
<div class="app-shell">
    <aside class="app-sidebar" aria-label="Primary navigation">
        <div class="app-sidebar-brand">
            <a href="<?= e($dashHref) ?>" class="app-brand-mark">Your<span class="accent">Blinds</span></a>
            <span class="app-brand-tag"><?= e($dashTag) ?></span>
        </div>
        <div class="app-sidebar-user">
            <div class="app-sidebar-user-name"><?= e($user['full_name']) ?></div>
            <div class="app-sidebar-user-meta">
                <?= e($user['company_name']) ?> &middot; <?= e($user['role']) ?>
            </div>
        </div>
        <nav class="app-sidebar-nav">
            <a href="/calendar/index.php">Calendar</a>
            <a href="<?= e($dashHref) ?>">Dashboard</a>
            <a href="/quote-builder/new.php">New Quote</a>
            <a href="/quote-history/index.php">Quote History</a>
            <a href="/customer-manager/index.php" class="active">Customers</a>
            <?php if ($isAdmin): ?>
                <a href="/admin/pricing.php">Price Lists</a>
                <a href="/admin/settings.php">Settings</a>
            <?php endif; ?>
        </nav>
        <div class="app-sidebar-foot">
            <a href="/auth/logout.php">Sign out &rarr;</a>
        </div>
    </aside>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Customers</h1>
                <p class="page-subtitle">
                    End-customers belonging to <?= e($user['company_name']) ?>.
                </p>
            </div>
            <a href="/customer-manager/new.php" class="btn btn-primary">+ Add customer</a>
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
                                <th class="num">Quotes</th>
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
                                    <td class="num"><?= (int) $c['quote_count'] ?></td>
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
