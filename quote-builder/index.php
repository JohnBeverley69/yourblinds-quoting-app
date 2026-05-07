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
            <a href="/calendar/index.php">Calendar</a>
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
