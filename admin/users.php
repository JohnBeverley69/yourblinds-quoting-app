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
$rolePriority = array_flip($validRoles);
$pickPrimary = static function (array $roles) use ($rolePriority): string {
    if (!$roles) return 'sales';
    usort($roles, static fn ($a, $b)
        => ($rolePriority[$a] ?? 99) <=> ($rolePriority[$b] ?? 99));
    return $roles[0];
};

$form = [
    'first_name' => '',
    'last_name'  => '',
    'username'   => '',
    'email'      => '',
    'roles'      => ['sales'],
    'can_create_quotes'          => 1,
    'can_create_orders'          => 0,
    'can_view_all_customer_jobs' => 0,
    'can_view_costs'             => 0,
];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_action'] ?? '') === 'create') {
    csrf_check();

    foreach (['first_name','last_name','username','email'] as $f) {
        $form[$f] = trim((string) ($_POST[$f] ?? ''));
    }
    foreach (['can_create_quotes','can_create_orders','can_view_all_customer_jobs','can_view_costs'] as $f) {
        $form[$f] = !empty($_POST[$f]) ? 1 : 0;
    }
    $password = (string) ($_POST['password'] ?? '');

    // Multi-role checkbox group. Filter to known roles, dedupe.
    $rolesIn = is_array($_POST['roles'] ?? null) ? $_POST['roles'] : [];
    $rolesIn = array_values(array_unique(array_intersect(
        array_map('strval', $rolesIn), $validRoles
    )));
    $form['roles'] = $rolesIn ?: ['sales'];
    $primaryRole   = $pickPrimary($rolesIn);

    $fullName = trim($form['first_name'] . ' ' . $form['last_name']);
    if ($fullName === '' || $form['email'] === '') {
        $error = 'Name and email are required.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!$rolesIn) {
        $error = 'Pick at least one role.';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
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
                $primaryRole,
                $form['can_create_quotes'],
                $form['can_create_orders'],
                $form['can_view_all_customer_jobs'],
                $form['can_view_costs'],
            ]);
            $newUserId = (int) $pdo->lastInsertId();
            $insRole = $pdo->prepare(
                'INSERT INTO client_user_roles (user_id, role) VALUES (?, ?)'
            );
            foreach ($rolesIn as $r) {
                $insRole->execute([$newUserId, $r]);
            }
            $pdo->commit();
            $_SESSION['flash_success'] = 'User added.';
            header('Location: /admin/users.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
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

// Pull each user's full role set from the junction in one query and
// fold into a [user_id => [role, ...]] map for the list rendering.
// Falls back gracefully if the table isn't there yet.
$rolesByUser = [];
if ($users) {
    try {
        $ids = array_map(static fn ($u) => (int) $u['id'], $users);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $rs  = db()->prepare(
            "SELECT user_id, role FROM client_user_roles WHERE user_id IN ($ph)"
        );
        $rs->execute($ids);
        foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rolesByUser[(int) $r['user_id']][] = (string) $r['role'];
        }
    } catch (Throwable $e) {
        // table missing → leave $rolesByUser empty; we'll fall back
        // to displaying the legacy single role per user in the loop.
    }
}
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
                        <label>Roles</label>
                        <div style="display:flex; flex-wrap:wrap; gap:0.5rem 1rem;
                                    padding:0.5rem 0.625rem; border:1px solid var(--border-strong);
                                    border-radius:8px; background:var(--bg-input); color:var(--text-body);
                                    font-size:0.9375rem;">
                            <?php foreach ($validRoles as $r): ?>
                                <label style="display:inline-flex; align-items:center;
                                              gap:0.4rem; font-weight:400;">
                                    <input type="checkbox" name="roles[]" value="<?= e($r) ?>"
                                           <?= in_array($r, $form['roles'], true) ? 'checked' : '' ?>>
                                    <?= e(ucfirst($r)) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
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
                                <th>Roles</th>
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
                                    <td style="text-transform:capitalize;">
                                        <?php
                                            // Show every role from the
                                            // junction. Falls back to the
                                            // legacy single role column if
                                            // the junction is empty (e.g.
                                            // before the migration).
                                            $rs = $rolesByUser[(int) $u['id']]
                                                ?? [(string) $u['role']];
                                            echo e(implode(', ', $rs));
                                        ?>
                                    </td>
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
