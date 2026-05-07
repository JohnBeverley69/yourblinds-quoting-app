<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$user      = current_user();
$firstName = trim((string) (explode(' ', $user['full_name'])[0] ?? ''));
$isAdmin   = $user['role'] === 'admin';
$activeNav = 'dashboard';
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
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

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
