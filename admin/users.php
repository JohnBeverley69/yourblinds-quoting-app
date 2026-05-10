<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];
$myUserId = $user['user_id'];

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$validRoles = ['admin','owner','office','sales','agent','fitter','readonly'];

$form = [
    'first_name' => '',
    'last_name'  => '',
    'username'   => '',
    'email'      => '',
    'role'       => 'sales',
    'can_create_quotes'          => 1,
    'can_create_orders'          => 0,
    'can_view_all_customer_jobs' => 0,
    'can_view_costs'             => 0,
];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_action'] ?? '') === 'create') {
    csrf_check();

    foreach (['first_name','last_name','username','email','role'] as $f) {
        $form[$f] = trim((string) ($_POST[$f] ?? ''));
    }
    foreach (['can_create_quotes','can_create_orders','can_view_all_customer_jobs','can_view_costs'] as $f) {
        $form[$f] = !empty($_POST[$f]) ? 1 : 0;
    }
    $password = (string) ($_POST['password'] ?? '');

    $fullName = trim($form['first_name'] . ' ' . $form['last_name']);
    if ($fullName === '' || $form['email'] === '') {
        $error = 'Name and email are required.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!in_array($form['role'], $validRoles, true)) {
        $error = 'Invalid role.';
    } else {
        try {
            $stmt = db()->prepare(
                'INSERT INTO client_users
                  (client_id, username, full_name, first_name, last_name,
                   email, password_hash, role,
                   can_create_quotes, can_create_orders,
                   can_view_all_customer_jobs, can_view_costs, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
            );
            $stmt->execute([
                $clientId,
                $form['username'] !== '' ? $form['username'] : null,
                $fullName,
                $form['first_name'] !== '' ? $form['first_name'] : null,
                $form['last_name']  !== '' ? $form['last_name']  : null,
                $form['email'],
                password_hash($password, PASSWORD_DEFAULT),
                $form['role'],
                $form['can_create_quotes'],
                $form['can_create_orders'],
                $form['can_view_all_customer_jobs'],
                $form['can_view_costs'],
            ]);
            $_SESSION['flash_success'] = 'User added.';
            header('Location: /admin/users.php');
            exit;
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'uniq_client_user_email')) {
                $error = 'That email address is already in use.';
            } elseif (str_contains($e->getMessage(), 'uniq_client_user_username')) {
                $error = 'That username is already in use.';
            } else {
                $error = 'Could not add user: ' . $e->getMessage();
            }
        }
    }
}

$users = db()->prepare(
    'SELECT id, full_name, email, username, role, active, last_login_at, created_at
       FROM client_users
      WHERE client_id = ?
      ORDER BY full_name'
);
$users->execute([$clientId]);
$users = $users->fetchAll();
$activeNav = 'users';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Users &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Users</h1>
                <p class="page-subtitle">Login accounts for <?= e($user['company_name']) ?>.</p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>
        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Add user</h2>
            </div>
            <form method="post" action="/admin/users.php" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="create">

                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First name <span class="required">*</span></label>
                        <input id="first_name" name="first_name" type="text" required maxlength="80"
                               value="<?= e($form['first_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last name <span class="required">*</span></label>
                        <input id="last_name" name="last_name" type="text" required maxlength="80"
                               value="<?= e($form['last_name']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <input id="email" name="email" type="email" required maxlength="150"
                               value="<?= e($form['email']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="username">Username (optional)</label>
                        <input id="username" name="username" type="text" maxlength="60"
                               value="<?= e($form['username']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <input id="password" name="password" type="password" minlength="8" required
                               autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role">
                            <?php foreach ($validRoles as $r): ?>
                                <option value="<?= e($r) ?>" <?= $form['role'] === $r ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label>Permissions</label>
                        <div style="display:flex; flex-wrap:wrap; gap:1rem; font-size:0.9375rem;">
                            <label style="display:inline-flex; align-items:center; gap:.4rem; font-weight:400;">
                                <input type="checkbox" name="can_create_quotes" value="1" <?= $form['can_create_quotes'] ? 'checked' : '' ?>>
                                Create quotes
                            </label>
                            <label style="display:inline-flex; align-items:center; gap:.4rem; font-weight:400;">
                                <input type="checkbox" name="can_create_orders" value="1" <?= $form['can_create_orders'] ? 'checked' : '' ?>>
                                Create orders
                            </label>
                            <label style="display:inline-flex; align-items:center; gap:.4rem; font-weight:400;">
                                <input type="checkbox" name="can_view_all_customer_jobs" value="1" <?= $form['can_view_all_customer_jobs'] ? 'checked' : '' ?>>
                                View all customer jobs
                            </label>
                            <label style="display:inline-flex; align-items:center; gap:.4rem; font-weight:400;">
                                <input type="checkbox" name="can_view_costs" value="1" <?= $form['can_view_costs'] ? 'checked' : '' ?>>
                                View costs
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add user</button>
                </div>
            </form>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Existing users (<?= count($users) ?>)</h2>
            </div>
            <?php if (empty($users)): ?>
                <div class="table-empty">No users yet.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last login</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td>
                                        <strong><?= e((string) $u['full_name']) ?></strong>
                                        <?php if ((int) $u['id'] === $myUserId): ?>
                                            <span style="color:#6b7280; font-size:0.8125rem;">(you)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e((string) $u['email']) ?></td>
                                    <td style="text-transform:capitalize;"><?= e((string) $u['role']) ?></td>
                                    <td>
                                        <?php if ((int) $u['active'] === 1): ?>
                                            <span class="badge badge-accepted">active</span>
                                        <?php else: ?>
                                            <span class="badge badge-archived">inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($u['last_login_at'])): ?>
                                            <?= e(date('j M Y H:i', (int) strtotime((string) $u['last_login_at']))) ?>
                                        <?php else: ?>
                                            <span style="color:#9ca3af;">never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/admin/users_edit.php?id=<?= (int) $u['id'] ?>">Edit</a>
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
