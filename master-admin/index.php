<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

$user = current_user();

// One row per client with its feature flags.
$rows = db()->query(
    'SELECT c.id, c.company_name, c.active,
            COALESCE(s.feature_maps, 0) AS feature_maps
       FROM clients c
       LEFT JOIN client_settings s ON s.client_id = c.id
   ORDER BY c.company_name'
)->fetchAll();

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
                                <th class="toggle" title="Maps & directions add-on">Maps</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rows): ?>
                                <tr><td colspan="2" class="table-empty">No clients yet.</td></tr>
                            <?php else: foreach ($rows as $r): ?>
                                <tr class="<?= ((int) $r['active']) === 1 ? '' : 'is-inactive' ?>">
                                    <td>
                                        <?= e((string) $r['company_name']) ?>
                                        <?php if ((int) $r['active'] !== 1): ?>
                                            <span class="inactive-tag">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="toggle">
                                        <input type="checkbox"
                                               name="maps[<?= (int) $r['id'] ?>]"
                                               value="1"
                                               <?= ((int) $r['feature_maps']) === 1 ? 'checked' : '' ?>
                                               aria-label="Maps add-on for <?= e((string) $r['company_name']) ?>">
                                    </td>
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
