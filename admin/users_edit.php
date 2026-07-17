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

$validRoles = ['admin','owner','office','sales','agent','fitter','readonly'];
// 'factory' role only on the Beverley factory account (see admin/users.php).
$isFactoryAccount = function_exists('factory_client_id') && (int) $clientId === factory_client_id();
if ($isFactoryAccount) {
    $validRoles[] = 'factory';
}

// Benches, for a station login (an account that IS a bench rather than a
// person). Null = don't offer the field at all: not the factory account, or the
// routing/station migrations haven't run.
$factoryStations = null;
if ($isFactoryAccount) {
    try {
        db()->query('SELECT factory_station_id FROM client_users LIMIT 0');
        $s = db()->prepare('SELECT id, name FROM factory_stations WHERE client_id = ? AND active = 1 ORDER BY sort_order, id');
        $s->execute([$clientId]);
        $factoryStations = $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $factoryStations = null; }
}

// Priority for picking the "primary" role to write into client_users.role
// when the user has more than one selected. Highest-privilege wins, so
// existing requireAdmin() / role === 'admin' checks behave intuitively
// for someone who's, say, admin AND fitter.
$rolePriority = array_flip($validRoles);   // 'admin' => 0, 'readonly' => 6
$pickPrimary = static function (array $roles) use ($rolePriority): string {
    if (!$roles) return 'sales';
    usort($roles, static fn ($a, $b)
        => ($rolePriority[$a] ?? 99) <=> ($rolePriority[$b] ?? 99));
    return $roles[0];
};

// Load the target user's currently-assigned roles from the junction.
// Falls back to the single legacy role if the junction doesn't have any
// rows yet (e.g. immediately after the migration before any save).
$existingRoles = [];
try {
    $rs = db()->prepare('SELECT role FROM client_user_roles WHERE user_id = ?');
    $rs->execute([$id]);
    $existingRoles = $rs->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    // junction missing → silent fallback
}
if (!$existingRoles && !empty($target['role'])) {
    $existingRoles = [(string) $target['role']];
}

$error      = null;
$flashMsg   = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_action'] ?? '') === 'update') {
    csrf_check();

    $first = trim((string) ($_POST['first_name'] ?? ''));
    $last  = trim((string) ($_POST['last_name']  ?? ''));
    $email = trim((string) ($_POST['email']      ?? ''));
    $uname = trim((string) ($_POST['username']   ?? ''));
    // Multi-role: roles[] is the checkbox group. Accept a legacy
    // scalar `role` too so the form still works if anyone POSTs the
    // old shape (e.g. for self-edits where the hidden role=admin
    // input is still there).
    $rolesRaw = $_POST['roles'] ?? null;
    if (is_array($rolesRaw)) {
        $rolesIn = array_values(array_unique(array_filter(
            array_map('strval', $rolesRaw),
            static fn ($r) => $r !== ''
        )));
    } else {
        $rolesIn = isset($_POST['role']) && (string) $_POST['role'] !== ''
            ? [(string) $_POST['role']]
            : [];
    }
    // Validate every picked role is in the allowed set.
    $rolesIn = array_values(array_intersect($rolesIn, $validRoles));
    $role  = $pickPrimary($rolesIn);   // highest-privilege one
    $active = !empty($_POST['active']) ? 1 : 0;
    $newPassword = (string) ($_POST['password'] ?? '');
    $perms = [
        'can_create_quotes'          => !empty($_POST['can_create_quotes']) ? 1 : 0,
        'can_create_orders'          => !empty($_POST['can_create_orders']) ? 1 : 0,
        'can_view_all_customer_jobs' => !empty($_POST['can_view_all_customer_jobs']) ? 1 : 0,
        'can_view_costs'             => !empty($_POST['can_view_costs']) ? 1 : 0,
        'can_view_fittings_only'     => !empty($_POST['can_view_fittings_only']) ? 1 : 0,
        // Dashboard panel flags. Tenant admins ignore these (always
        // see the full dashboard); they only affect non-admin users.
        'dash_view_revenue'          => !empty($_POST['dash_view_revenue'])  ? 1 : 0,
        'dash_view_team'             => !empty($_POST['dash_view_team'])     ? 1 : 0,
        'dash_view_products'         => !empty($_POST['dash_view_products']) ? 1 : 0,
        'dash_view_profit'           => !empty($_POST['dash_view_profit'])   ? 1 : 0,
        'dash_view_recent'           => !empty($_POST['dash_view_recent'])   ? 1 : 0,
    ];
    $home = [
        'home_address1' => trim((string) ($_POST['home_address1'] ?? '')),
        'home_address2' => trim((string) ($_POST['home_address2'] ?? '')),
        'home_town'     => trim((string) ($_POST['home_town']     ?? '')),
        'home_county'   => trim((string) ($_POST['home_county']   ?? '')),
        'home_postcode' => trim((string) ($_POST['home_postcode'] ?? '')),
    ];

    $fullName = trim($first . ' ' . $last);

    if ($fullName === '') {
        $error = 'A name is required.';
    } elseif ($email === '' && $uname === '') {
        $error = 'Enter an email address or a username.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!$rolesIn) {
        $error = 'Pick at least one role for this user.';
    } elseif ($newPassword !== '' && strlen($newPassword) < 8) {
        $error = 'New password must be at least 8 characters (or leave blank to keep the current one).';
    } elseif ($id === $myUserId && $active === 0) {
        $error = 'You cannot deactivate your own account.';
    } elseif ($id === $myUserId && !in_array('admin', $rolesIn, true)) {
        $error = 'You cannot remove admin from your own account.';
    } else {
        // Whether the dashboard-permission columns exist (migration may
        // not have run yet). Decided once before building SQL so we
        // don't slip into "wrote half the columns" territory on failure.
        try {
            db()->query("SELECT dash_view_revenue FROM client_users LIMIT 0");
            $hasDashCols = true;
        } catch (Throwable $e) {
            $hasDashCols = false;
        }
        try {
            db()->query("SELECT can_view_fittings_only FROM client_users LIMIT 0");
            $hasFittingsCol = true;
        } catch (Throwable $e) {
            $hasFittingsCol = false;
        }

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
                           can_view_costs             = ?,';
            if ($hasFittingsCol) {
                $sql .= '   can_view_fittings_only     = ?,';
            }
            if ($hasDashCols) {
                $sql .= '   dash_view_revenue          = ?,
                           dash_view_team             = ?,
                           dash_view_products         = ?,
                           dash_view_profit           = ?,
                           dash_view_recent           = ?,';
            }
            $sql .= '       home_address1              = ?,
                           home_address2              = ?,
                           home_town                  = ?,
                           home_county                = ?,
                           home_postcode              = ?';
            $params = [
                $first !== '' ? $first : null,
                $last  !== '' ? $last  : null,
                $fullName,
                $email !== '' ? $email : null,
                $uname !== '' ? $uname : null,
                $role,
                $active,
                $perms['can_create_quotes'],
                $perms['can_create_orders'],
                $perms['can_view_all_customer_jobs'],
                $perms['can_view_costs'],
            ];
            if ($hasFittingsCol) {
                $params[] = $perms['can_view_fittings_only'];
            }
            if ($hasDashCols) {
                $params[] = $perms['dash_view_revenue'];
                $params[] = $perms['dash_view_team'];
                $params[] = $perms['dash_view_products'];
                $params[] = $perms['dash_view_profit'];
                $params[] = $perms['dash_view_recent'];
            }
            array_push($params,
                $home['home_address1'] !== '' ? $home['home_address1'] : null,
                $home['home_address2'] !== '' ? $home['home_address2'] : null,
                $home['home_town']     !== '' ? $home['home_town']     : null,
                $home['home_county']   !== '' ? $home['home_county']   : null,
                $home['home_postcode'] !== '' ? $home['home_postcode'] : null,
            );
            // Bench assignment — only ever touched on the factory account, where
            // the field is actually rendered. Other accounts keep whatever's
            // there (which is NULL) rather than being blanked by a form that
            // never showed the option.
            if ($factoryStations !== null) {
                $sql .= ', factory_station_id = ?';
                $stationPick = (int) ($_POST['factory_station_id'] ?? 0);
                $params[] = $stationPick > 0 ? $stationPick : null;
            }
            if ($newPassword !== '') {
                $sql .= ', password_hash = ?';
                $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
            }
            $sql .= ' WHERE id = ? AND client_id = ?';
            $params[] = $id;
            $params[] = $clientId;

            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare($sql)->execute($params);

            // Sync the junction: wipe + reinsert. Simpler and faster
            // than diffing, and the FK to client_users + CASCADE on
            // delete makes it safe.
            $pdo->prepare('DELETE FROM client_user_roles WHERE user_id = ?')
                ->execute([$id]);
            $insRole = $pdo->prepare(
                'INSERT INTO client_user_roles (user_id, role) VALUES (?, ?)'
            );
            foreach ($rolesIn as $r) {
                $insRole->execute([$id, $r]);
            }
            $pdo->commit();

            // If we just edited our own roles, refresh the live session
            // so the change takes effect without needing a log-out.
            if ($id === $myUserId) {
                $_SESSION['role']  = $role;
                $_SESSION['roles'] = $rolesIn;
            }

            $_SESSION['flash_success'] = 'User updated.';
            header('Location: /admin/users_edit.php?id=' . $id);
            exit;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
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
    ] + $perms + $home);
    // Reflect the user's typed checkbox selection on re-render too.
    $existingRoles = $rolesIn;
}

// Postcode-lookup feature flag (gates the optional Find-by-postcode widget).
$pcFlag = db()->prepare(
    'SELECT COALESCE(feature_postcode_lookup, 0) FROM client_settings WHERE client_id = ?'
);
$pcFlag->execute([$clientId]);
$postcodeLookupEnabled = (int) $pcFlag->fetchColumn() === 1;

$activeNav = 'users';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit user &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
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
                        <label for="email">Email <span style="color:var(--text-faint);font-weight:400">(optional)</span></label>
                        <input id="email" name="email" type="email" maxlength="150"
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
                        <label>Roles</label>
                        <div style="display:flex; flex-wrap:wrap; gap:0.5rem 1rem;
                                    padding:0.5rem 0.625rem; border:1px solid var(--border-strong);
                                    border-radius:8px; background:var(--bg-input); color:var(--text-body);
                                    font-size:0.9375rem;">
                            <?php $isSelf = (int) $target['id'] === $myUserId; ?>
                            <?php foreach ($validRoles as $r):
                                $checked = in_array($r, $existingRoles, true);
                                // For self-edits, force admin to stay
                                // ticked + disabled so the user can't
                                // lock themselves out.
                                $forcedSelf = $isSelf && $r === 'admin';
                            ?>
                                <label style="display:inline-flex; align-items:center;
                                              gap:0.4rem; font-weight:400;">
                                    <input type="checkbox" name="roles[]"
                                           value="<?= e($r) ?>"
                                           <?= $checked || $forcedSelf ? 'checked' : '' ?>
                                           <?= $forcedSelf ? 'disabled' : '' ?>>
                                    <?= e(ucfirst($r)) ?>
                                </label>
                                <?php if ($forcedSelf): ?>
                                    <input type="hidden" name="roles[]" value="admin">
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <p style="font-size:0.8125rem; color:#6b7280; margin:0.4rem 0 0;">
                            Tick every role this person fills — e.g. someone who fits
                            and also closes sales should have both ticked. The most
                            privileged role drives admin-only access.
                            <?php if ($isSelf): ?>
                                You can't untick your own admin.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <?php if ($factoryStations !== null): ?>
                <div class="form-row full">
                    <div class="form-group">
                        <label for="factory_station_id">Bench</label>
                        <select id="factory_station_id" name="factory_station_id">
                            <option value="">Not a bench login — lands on Incoming Orders</option>
                            <?php foreach ($factoryStations as $s): ?>
                                <option value="<?= (int) $s['id'] ?>" <?= (int) ($target['factory_station_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p style="font-size:0.8125rem; color:#6b7280; margin:0.4rem 0 0;">
                            For a workshop login that <em>is</em> a bench rather than a person —
                            different staff use the same account all day. It logs straight into
                            that bench's queue, and once the scanners are in, a scan knows which
                            bench it came from. Needs the <strong>Factory</strong> role ticked above.
                        </p>
                    </div>
                </div>
                <?php endif; ?>

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
                            <label style="display:inline-flex; align-items:center; gap:.4rem; font-weight:400;"
                                   title="The calendar shows this user only fitting jobs — measure/sales visits are hidden. Ideal for fitters.">
                                <input type="checkbox" name="can_view_fittings_only" value="1" <?= !empty($target['can_view_fittings_only']) ? 'checked' : '' ?>>
                                Fittings only
                            </label>
                        </div>
                        <p style="margin:0.5rem 0 0; font-size:0.8125rem; color:var(--text-faint);">
                            <strong>Fittings only</strong> limits a user's calendar to fitting jobs (hides measures /
                            sales visits) — handy for fitters.
                        </p>
                    </div>
                </div>

                <!--
                    Dashboard permissions — tick which panels of /dashboard/
                    this user is allowed to see. Tenant admins (role 'admin')
                    ignore these flags entirely and see the full Dashboard.
                    A non-admin user with NONE of these ticked has the
                    Dashboard menu entry hidden and is bounced to Calendar if
                    they hit /dashboard/index.php directly.
                -->
                <fieldset style="border:1px solid #e5e7eb;border-radius:10px;padding:1rem 1.125rem;margin:1rem 0">
                    <legend style="padding:0 0.5rem;font-size:0.8125rem;font-weight:600;color:#1f3b5b;text-transform:uppercase;letter-spacing:0.05em">
                        Dashboard
                    </legend>
                    <p style="color:#6b7280;font-size:0.875rem;margin:0 0 0.75rem;line-height:1.45">
                        Which Dashboard panels this user can see. Admins always see
                        everything; these checkboxes only apply to non-admin users.
                        Tick none to hide the Dashboard menu entry entirely for this user.
                        <strong>Gross profit</strong> also requires the
                        <em>View costs</em> permission above.
                    </p>
                    <div style="display:flex; flex-wrap:wrap; gap:1rem; font-size:0.9375rem;">
                        <label style="display:inline-flex; align-items:center; gap:.4rem; font-weight:400;">
                            <input type="checkbox" name="dash_view_revenue" value="1"
                                <?= !empty($target['dash_view_revenue']) ? 'checked' : '' ?>>
                            Revenue &amp; KPIs
                        </label>
                        <label style="display:inline-flex; align-items:center; gap:.4rem; font-weight:400;">
                            <input type="checkbox" name="dash_view_team" value="1"
                                <?= !empty($target['dash_view_team']) ? 'checked' : '' ?>>
                            Sales-team leaderboard
                        </label>
                        <label style="display:inline-flex; align-items:center; gap:.4rem; font-weight:400;">
                            <input type="checkbox" name="dash_view_products" value="1"
                                <?= !empty($target['dash_view_products']) ? 'checked' : '' ?>>
                            Product mix
                        </label>
                        <label style="display:inline-flex; align-items:center; gap:.4rem; font-weight:400;">
                            <input type="checkbox" name="dash_view_profit" value="1"
                                <?= !empty($target['dash_view_profit']) ? 'checked' : '' ?>>
                            Gross profit
                        </label>
                        <label style="display:inline-flex; align-items:center; gap:.4rem; font-weight:400;">
                            <input type="checkbox" name="dash_view_recent" value="1"
                                <?= !empty($target['dash_view_recent']) ? 'checked' : '' ?>>
                            Recent wins
                        </label>
                    </div>
                </fieldset>

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

                <fieldset style="border:1px solid #e5e7eb;border-radius:10px;padding:1rem 1.125rem;margin:1rem 0">
                    <legend style="padding:0 0.5rem;font-size:0.8125rem;font-weight:600;color:#1f3b5b;text-transform:uppercase;letter-spacing:0.05em">
                        Home address
                    </legend>
                    <p style="color:#6b7280;font-size:0.875rem;margin:0 0 0.75rem">
                        Used as the start and end point for the calendar's "Today's run" map. Leave
                        blank if the user works out of the office only.
                    </p>

                    <?php if ($postcodeLookupEnabled): ?>
                        <?php
                            $pcFieldMap = [
                                'line1'    => 'home_address1',
                                'line2'    => 'home_address2',
                                'town'     => 'home_town',
                                'county'   => 'home_county',
                                'postcode' => 'home_postcode',
                            ];
                            require __DIR__ . '/../_partials/postcode_lookup.php';
                        ?>
                    <?php endif; ?>

                    <div class="form-row full">
                        <div class="form-group">
                            <label for="home_address1">Address line 1</label>
                            <input id="home_address1" name="home_address1" type="text" maxlength="150"
                                   value="<?= e((string) ($target['home_address1'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="form-row full">
                        <div class="form-group">
                            <label for="home_address2">Address line 2</label>
                            <input id="home_address2" name="home_address2" type="text" maxlength="150"
                                   value="<?= e((string) ($target['home_address2'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="form-row cols-3">
                        <div class="form-group">
                            <label for="home_town">Town</label>
                            <input id="home_town" name="home_town" type="text" maxlength="100"
                                   value="<?= e((string) ($target['home_town'] ?? '')) ?>">
                        </div>
                        <div class="form-group">
                            <label for="home_county">County</label>
                            <input id="home_county" name="home_county" type="text" maxlength="100"
                                   value="<?= e((string) ($target['home_county'] ?? '')) ?>">
                        </div>
                        <div class="form-group">
                            <label for="home_postcode">Postcode</label>
                            <input id="home_postcode" name="home_postcode" type="text" maxlength="20"
                                   value="<?= e((string) ($target['home_postcode'] ?? '')) ?>">
                        </div>
                    </div>
                </fieldset>

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
                      data-confirm="Delete <?= e((string) $target['full_name']) ?>? This cannot be undone.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $target['id'] ?>">
                    <button type="submit" class="btn btn-danger">Delete user</button>
                </form>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
