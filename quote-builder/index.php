<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$user      = current_user();
$firstName = trim((string) (explode(' ', $user['full_name'])[0] ?? ''));
$isAdmin   = $user['role'] === 'admin';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quote Builder &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
</head>
<body>
<div class="app-shell">
    <aside class="app-sidebar" aria-label="Primary navigation">
        <div class="app-sidebar-brand">
            <a href="/quote-builder/index.php" class="app-brand-mark">Your<span class="accent">Blinds</span></a>
            <span class="app-brand-tag">Trade Portal</span>
        </div>

        <div class="app-sidebar-user">
            <div class="app-sidebar-user-name"><?= e($user['full_name']) ?></div>
            <div class="app-sidebar-user-meta">
                <?= e($user['company_name']) ?> &middot; <?= e($user['role']) ?>
            </div>
        </div>

        <nav class="app-sidebar-nav">
            <a href="/quote-builder/index.php" class="active">Dashboard</a>
            <a href="/quote-builder/new.php">New Quote</a>
            <a href="/quote-history/index.php">Quote History</a>
            <a href="/customer-manager/index.php">Customers</a>
            <?php if ($isAdmin): ?>
                <a href="/admin/index.php">Admin Console</a>
            <?php endif; ?>
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
                    Signed in to <?= e($user['company_name']) ?>.
                </p>
            </div>
            <a href="/quote-builder/new.php" class="btn btn-primary">+ New Quote</a>
        </div>

        <div class="placeholder">
            <p class="placeholder-title">Quote Builder</p>
            <p class="placeholder-body">
                Start a new quote, view past quotes, or manage customer records using the navigation on the left.
            </p>
        </div>
    </main>
</div>
</body>
</html>
