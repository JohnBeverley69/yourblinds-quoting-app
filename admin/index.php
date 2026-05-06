<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireAdmin();

$user      = current_user();
$firstName = trim((string) (explode(' ', $user['full_name'])[0] ?? ''));
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin &middot; YourBlinds</title>
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
            <div class="app-sidebar-user-meta">
                <?= e($user['company_name']) ?> &middot; <?= e($user['role']) ?>
            </div>
        </div>

        <nav class="app-sidebar-nav">
            <a href="/calendar/index.php">Calendar</a>
            <a href="/admin/index.php" class="active">Dashboard</a>
            <a href="/quote-builder/index.php">Quote Builder</a>
            <a href="/quote-history/index.php">Quote History</a>
            <a href="/customer-manager/index.php">Customer Manager</a>
            <a href="/admin/pricing.php">Price Lists</a>
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
                <h1 class="page-title">
                    Welcome<?= $firstName !== '' ? ', ' . e($firstName) : '' ?>.
                </h1>
                <p class="page-subtitle">
                    You are managing <?= e($user['company_name']) ?>.
                </p>
            </div>
        </div>

        <div class="placeholder">
            <p class="placeholder-title">Admin dashboard</p>
            <p class="placeholder-body">
                Use the navigation on the left to manage quotes, customers, pricing and settings.
            </p>
        </div>
    </main>
</div>
</body>
</html>
