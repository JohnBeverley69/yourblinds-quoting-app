<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireAdmin();

$user      = current_user();
$firstName = trim((string) (explode(' ', $user['full_name'])[0] ?? ''));
$activeNav = 'dashboard';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
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
