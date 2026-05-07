<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];
$myUserId = $user['user_id'];

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('User not found.');
}

$stmt = db()->prepare(
    'SELECT * FROM client_users WHERE id = ? AND client_id = ? LIMIT 1'
);
$stmt->execute([$id, $clientId]);
$target = $stmt->fetch();
if (!$target) {
    http_response_code(404);
    exit('User not found.');
}

$validRoles = ['admin','owner','office','sales','agent','readonly'];
$error      = null;
$flashMsg   = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_action'] ?? '') === 'update') {
    csrf_check();

    $first = trim((string) ($_POST['first_name'] ?? ''));
    $last  = trim((string) ($_POST['last_name']  ?? ''));
    $email = trim((string) ($_POST['email']      ?? ''));
    $uname = trim((string) ($_POST['username']   ?? ''));
    $role  = (string) ($_POST['role'] ?? 'sales');
    $active = !empty($_POST['active']) ? 1 : 0;
    $newPassword = (string) ($_POST['password'] ?? '');
    $perms = [
        'can_create_quotes'          => !empty($_POST['can_create_quotes']) ? 1 : 0,
        'can_create_orders'          => !empty($_POST['can_create_orders']) ? 1 : 0,
        'can_view_all_customer_jobs' => !empty($_POST['can_view_all_customer_jobs']) ? 1 : 0,
        'can_view_costs'             => !empty($_POST['can_view_costs']) ? 1 : 0,
    ];

    $fullName = trim($first . ' ' . $last);

    if ($fullName === '' || $email === '') {
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!in_array($role, $validRoles, true)) {
        $error = 'Invalid role.';
    } elseif ($newPassword !== '' && strlen($newPassword) < 8) {
        $error = 'New password must be at least 8 characters (or leave blank to keep the current one).';
    } elseif ($id === $myUserId && $active === 0) {
        $error = 'You cannot deactivate your own account.';
    } elseif ($id === $myUserId && $role !== 'admin') {
        $error = 'You cannot remove admin role from your own account.';
    } else {
        try {
            $sql = 'UPDATE client_users
                       SET first_name                 = ?,
                           last_name                  = ?,
                           full_name                  = ?,
                           email                      = ?,
                           username                   = ?,
                           role                       = ?,
                           active                     = ?,
                           can_create_quotes          = ?,
                           can_create_orders          = ?,
                           can_view_all_customer_jobs = ?,
                           can_view_costs             = ?';
            $params = [
                $first !== '' ? $first : null,
                $last  !== '' ? $last  : null,
                $fullName,
                $email,
                $uname !== '' ? $uname : null,
                $role,
                $active,
                $perms['can_create_quotes'],
                $perms['can_create_orders'],
                $perms['can_view_all_customer_jobs'],
                $perms['can_view_costs'],
            ];
            if ($newPassword !== '') {
                $sql .= ', password_hash = ?';
                $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
            }
            $sql .= ' WHERE id = ? AND client_id = ?';
            $params[] = $id;
            $params[] = $clientId;

            db()->prepare($sql)->execute($params);
            $_SESSION['flash_success'] = 'User updated.';
            header('Location: /admin/users_edit.php?id=' . $id);
            exit;
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'uniq_client_user_email')) {
                $error = 'That email address is already in use.';
            } elseif (str_contains($e->getMessage(), 'uniq_client_user_username')) {
                $error = 'That username is already in use.';
            } else {
                $error = 'Could not update user: ' . $e->getMessage();
            }
        }
    }

    // Re-render with the typed values
    $target = array_merge((array) $target, [
        'first_name' => $first,
        'last_name'  => $last,
        'full_name'  => $fullName,
        'email'      => $email,
        'username'   => $uname,
        'role'       => $role,
        'active'     => $active,
    ] + $perms);
}
$activeNav = 'users';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit user &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title"><?= e((string) $target['full_name']) ?></h1>
                <p class="page-subtitle">
                    <a href="/admin/users.php">&larr; Back to users</a>
                </p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section">
            <form method="post" action="/admin/users_edit.php" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $target['id'] ?>">
                <input type="hidden" name="_action" value="update">

                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First name</label>
                        <input id="first_name" name="first_name" type="text" maxlength="80"
                               value="<?= e((string) ($target['first_name'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last name</label>
                        <input id="last_name" name="last_name" type="text" maxlength="80"
                               value="<?= e((string) ($target['last_name'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <input id="email" name="email" type="email" required maxlength="150"
                               value="<?= e((string) $target['email']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" maxlength="60"
                               value="<?= e((string) ($target['username'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">New password</label>
                        <input id="password" name="password" type="password" minlength="8"
                               autocomplete="new-password" placeholder="Leave blank to keep current">
                    </div>
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" <?= (int) $target['id'] === $myUserId ? 'disabled' : '' ?>>
                            <?php foreach ($validRoles as $r): ?>
                                <option value="<?= e($r) ?>" <?= ((string) $target['role']) === $r ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ((int) $target['id'] === $myUserId): ?>
                            <input type="hidden" name="role" value="admin">
                            <p style="font-size:.8125rem; color:#6b7280; margin:.4rem 0 0;">You cannot change your own role.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label>Permissions</label>
                        <div style="display:flex; flex-wrap:wrap; gap:1rem; font-size:0.9375rem;">
                            <label style="display:inline-flex; align-items:center; gap:.4rem; font-weight:400;">
                                <input type="checkbox" name="can_create_quotes" value="1" <?= !empty($target['can_create_quotes']) ? 'checked' : '' ?>>
                                Create quotes
                            </label>
                            <label style="display:inline-flex; align-items:center; gap:.4rem; font-weight:400;">
                                <input type="checkbox" name="can_create_orders" value="1" <?= !empty($target['can_create_orders']) ? 'checked' : '' ?>>
                                Create orders
                            </label>
                            <label style="display:inline-flex; align-items:center; gap:.4rem; font-weight:400;">
                                <input type="checkbox" name="can_view_all_customer_jobs" value="1" <?= !empty($target['can_view_all_customer_jobs']) ? 'checked' : '' ?>>
                                View all customer jobs
                            </label>
                            <label style="display:inline-flex; align-items:center; gap:.4rem; font-weight:400;">
                                <input type="checkbox" name="can_view_costs" value="1" <?= !empty($target['can_view_costs']) ? 'checked' : '' ?>>
                                View costs
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label style="display:inline-flex; align-items:center; gap:.5rem; font-weight:400;">
                            <input type="checkbox" name="active" value="1" <?= !empty($target['active']) ? 'checked' : '' ?> <?= (int) $target['id'] === $myUserId ? 'disabled' : '' ?>>
                            Active (can sign in)
                        </label>
                        <?php if ((int) $target['id'] === $myUserId): ?>
                            <input type="hidden" name="active" value="1">
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="/admin/users.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>

        <?php if ((int) $target['id'] !== $myUserId): ?>
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title" style="color:#b91c1c;">Danger zone</h2>
                </div>
                <p style="color:#6b7280; margin: 0 0 1rem;">
                    Deleting this user is permanent. Their existing quotes will be kept (link cleared).
                </p>
                <form method="post" action="/admin/users_delete.php" style="margin:0;"
                      onsubmit="return confirm('Delete <?= e(addslashes((string) $target['full_name'])) ?>? This cannot be undone.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $target['id'] ?>">
                    <button type="submit" class="btn btn-danger">Delete user</button>
                </form>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
