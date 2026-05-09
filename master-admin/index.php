<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

$user  = current_user();
$flags = require __DIR__ . '/../_partials/feature_flags.php';

// Build a SELECT that pulls every flag column dynamically. Column names come
// from a server-side allowlist (the $flags array) — never from user input —
// so it's safe to interpolate directly.
$flagCols = implode(",\n            ",
    array_map(static fn ($k) => "COALESCE(s.$k, 0) AS $k", array_keys($flags))
);
$sql = "SELECT c.id, c.company_name, c.active,
            $flagCols
       FROM clients c
       LEFT JOIN client_settings s ON s.client_id = c.id
   ORDER BY c.company_name";
$rows = db()->query($sql)->fetchAll();

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$activeNav = 'master-admin';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Master Admin &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .feature-table th { white-space: nowrap; }
        .feature-table td.toggle { width: 1%; text-align: center; }
        .feature-table input[type="checkbox"] { width: 18px; height: 18px; }
        .feature-table tr.is-inactive td:first-child { color: #6b7280; }
        .feature-table tr.is-inactive .inactive-tag {
            display: inline-block;
            margin-left: 0.5rem;
            padding: 0.0625rem 0.5rem;
            font-size: 0.6875rem;
            font-weight: 600;
            color: #6b7280;
            background: #f3f4f6;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Master Admin</h1>
                <p class="page-subtitle">
                    Per-client feature flags. Tick to enable a paid add-on for that client.
                </p>
            </div>
            <a href="/master-admin/new-client.php" class="btn btn-primary">+ New client</a>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <section class="section">
            <form method="post" action="/master-admin/save.php">
                <?= csrf_field() ?>
                <div class="table-wrap">
                    <table class="table feature-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <?php foreach ($flags as $col => $label): ?>
                                    <th class="toggle"><?= e($label) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rows): ?>
                                <tr><td colspan="<?= count($flags) + 1 ?>" class="table-empty">No clients yet.</td></tr>
                            <?php else: foreach ($rows as $r): ?>
                                <tr class="<?= ((int) $r['active']) === 1 ? '' : 'is-inactive' ?>">
                                    <td>
                                        <?= e((string) $r['company_name']) ?>
                                        <?php if ((int) $r['active'] !== 1): ?>
                                            <span class="inactive-tag">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php foreach ($flags as $col => $label): ?>
                                        <td class="toggle">
                                            <input type="checkbox"
                                                   name="flags[<?= e($col) ?>][<?= (int) $r['id'] ?>]"
                                                   value="1"
                                                   <?= ((int) $r[$col]) === 1 ? 'checked' : '' ?>
                                                   aria-label="<?= e($label) ?> for <?= e((string) $r['company_name']) ?>">
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
