<?php
declare(strict_types=1);

/**
 * Master Admin: Trade Account — single-account detail + edit.
 *
 * The per-client control page that didn't exist before: a super-admin can view
 * and edit an account's business/contact profile, toggle it active/inactive, and
 * see its logins. Login control (reset password / change login) is added in 1B;
 * per-account discounts in 1C — this page is the host for both.
 *
 * Super-admin only. All mutation is POST + CSRF + flash-then-redirect.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../_partials/verification.php';

requireSuperAdmin();

$user     = current_user();
$pdo      = db();
$myClient = (int) $user['client_id'];

$clientId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($clientId <= 0) {
    header('Location: /master-admin/trade-accounts.php');
    exit;
}

$colExists = static function (string $table, string $col) use ($pdo): bool {
    try {
        $st = $pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $col]);
        return $st->fetchColumn() !== false;
    } catch (Throwable $e) { return false; }
};
$hasCreated = $colExists('clients', 'created_at');

// ── POST handlers ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');

    // Confirm the target exists before mutating.
    $chk = $pdo->prepare('SELECT id FROM clients WHERE id = ? LIMIT 1');
    $chk->execute([$clientId]);
    if ($chk->fetchColumn() === false) {
        $_SESSION['flash_error'] = 'Account not found.';
        header('Location: /master-admin/trade-accounts.php');
        exit;
    }

    if ($action === 'profile') {
        // Same field set as the tenant-side business profile (admin/settings.php),
        // but a super-admin can edit any account's here.
        $company = trim((string) ($_POST['company_name'] ?? ''));
        if ($company === '') {
            $_SESSION['flash_error'] = 'Company name is required.';
        } else {
            try {
                $pdo->prepare(
                    'UPDATE clients
                        SET company_name = ?, contact_name = ?, email = ?, phone = ?,
                            vat_number = ?, address1 = ?, address2 = ?, town = ?,
                            county = ?, postcode = ?
                      WHERE id = ?'
                )->execute([
                    mb_substr($company, 0, 150),
                    trim((string) ($_POST['contact_name'] ?? '')) ?: null,
                    trim((string) ($_POST['email']        ?? '')) ?: null,
                    trim((string) ($_POST['phone']        ?? '')) ?: null,
                    trim((string) ($_POST['vat_number']   ?? '')) ?: null,
                    trim((string) ($_POST['address1']     ?? '')) ?: null,
                    trim((string) ($_POST['address2']     ?? '')) ?: null,
                    trim((string) ($_POST['town']         ?? '')) ?: null,
                    trim((string) ($_POST['county']       ?? '')) ?: null,
                    trim((string) ($_POST['postcode']     ?? '')) ?: null,
                    $clientId,
                ]);
                // If we just renamed our OWN client, refresh the session copy.
                if ($clientId === $myClient) $_SESSION['company_name'] = $company;
                $_SESSION['flash_success'] = 'Account details saved.';
            } catch (Throwable $e) {
                $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage();
            }
        }
        header('Location: /master-admin/trade-account.php?id=' . $clientId);
        exit;
    }

    if ($action === 'active') {
        $to = (int) ($_POST['to'] ?? 0) === 1 ? 1 : 0;
        // Never let a super-admin deactivate their OWN account (self-lockout).
        if ($clientId === $myClient && $to === 0) {
            $_SESSION['flash_error'] = 'You can\'t deactivate your own account.';
        } else {
            try {
                $pdo->prepare('UPDATE clients SET active = ? WHERE id = ?')->execute([$to, $clientId]);
                $_SESSION['flash_success'] = $to === 1 ? 'Account activated.' : 'Account deactivated.';
            } catch (Throwable $e) {
                $_SESSION['flash_error'] = 'Could not update: ' . $e->getMessage();
            }
        }
        header('Location: /master-admin/trade-account.php?id=' . $clientId);
        exit;
    }

    // ── Login (client_users) actions ────────────────────────────────────────
    // Every one is scoped to THIS account (WHERE id=? AND client_id=?) and
    // refuses to touch a super-admin user — parity with delete-client.php.
    $userActions = ['user_reset_link','user_set_password','user_change_login',
                    'user_toggle_active','user_resend_verification','user_mark_verified','user_add'];
    if (in_array($action, $userActions, true)) {
        $companyName = (string) ($pdo->query('SELECT company_name FROM clients WHERE id = ' . (int) $clientId)->fetchColumn() ?: '');
        $redirect = '/master-admin/trade-account.php?id=' . $clientId . '#logins';

        // Create a brand-new login on this account.
        if ($action === 'user_add') {
            $name  = trim((string) ($_POST['full_name'] ?? ''));
            $mail  = trim((string) ($_POST['email'] ?? ''));
            $pw    = (string) ($_POST['new_password'] ?? '');
            if ($name === '') {
                $_SESSION['flash_error'] = 'Full name is required.';
            } elseif (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['flash_error'] = 'A valid email is required.';
            } elseif (strlen($pw) < 8) {
                $_SESSION['flash_error'] = 'Password must be at least 8 characters.';
            } else {
                $dup = $pdo->prepare('SELECT 1 FROM client_users WHERE email = ? LIMIT 1');
                $dup->execute([$mail]);
                if ($dup->fetchColumn()) {
                    $_SESSION['flash_error'] = 'That email is already used by another login.';
                } else {
                    try {
                        // Admin-created → verified now (admin vouches), so they aren't
                        // blocked by the confirm-email gate. They can set their own
                        // password via "Send reset link" afterwards.
                        $pdo->prepare(
                            'INSERT INTO client_users
                               (client_id, email, full_name, password_hash, role, active, is_super_admin, email_verified_at)
                             VALUES (?, ?, ?, ?, "admin", 1, 0, NOW())'
                        )->execute([$clientId, $mail, $name, password_hash($pw, PASSWORD_DEFAULT)]);
                        $_SESSION['flash_success'] = 'Login added for ' . $mail . '. Use "Send reset link" if you want them to set their own password.';
                    } catch (Throwable $e) {
                        $_SESSION['flash_error'] = 'Could not add login: ' . $e->getMessage();
                    }
                }
            }
            header('Location: ' . $redirect);
            exit;
        }

        // All remaining actions target an existing user on this account.
        $uid = (int) ($_POST['user_id'] ?? 0);
        $tu  = $pdo->prepare(
            'SELECT id, client_id, email, username, full_name, active, is_super_admin, email_verified_at
               FROM client_users WHERE id = ? AND client_id = ? LIMIT 1'
        );
        $tu->execute([$uid, $clientId]);
        $target = $tu->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            $_SESSION['flash_error'] = 'Login not found on this account.';
            header('Location: ' . $redirect); exit;
        }
        if ((int) $target['is_super_admin'] === 1) {
            $_SESSION['flash_error'] = 'That is a super-admin login — manage it from the owner\'s own account settings, not here.';
            header('Location: ' . $redirect); exit;
        }

        try {
            if ($action === 'user_reset_link') {
                if (($target['email'] ?? '') === '') {
                    $_SESSION['flash_error'] = 'This login has no email — set one, or use "Set a temporary password".';
                } else {
                    $token = bin2hex(random_bytes(32));
                    $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)')
                        ->execute([$uid, hash('sha256', $token), (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s')]);
                    $base = trim((string) (env('APP_URL', '') ?? ''));
                    $url  = ($base !== '' ? rtrim($base, '/') : '') . '/auth/reset_password.php?token=' . $token;
                    $body = "Hello,\n\n"
                          . "The administrator has set up a password reset for your YourBlinds account.\n\n"
                          . "Use the link below to choose a new password (valid for 24 hours):\n"
                          . $url . "\n\n"
                          . "If you weren't expecting this, please contact us.\n\n— YourBlinds";
                    $ok = mailer_send($target['email'], 'Set your YourBlinds password', $body);
                    $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok
                        ? ('Reset link sent to ' . $target['email'] . '.')
                        : ('Could not send the email — check mail settings (or Testing mode is on).');
                }
            } elseif ($action === 'user_set_password') {
                $pw = (string) ($_POST['new_password'] ?? '');
                if (strlen($pw) < 8) {
                    $_SESSION['flash_error'] = 'Password must be at least 8 characters.';
                } else {
                    $pdo->prepare('UPDATE client_users SET password_hash = ? WHERE id = ? AND client_id = ?')
                        ->execute([password_hash($pw, PASSWORD_DEFAULT), $uid, $clientId]);
                    // Invalidate any outstanding reset tokens for this user.
                    try { $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$uid]); }
                    catch (Throwable $e) { /* table/shape tolerant */ }
                    $_SESSION['flash_success'] = 'Temporary password set for ' . ($target['full_name'] ?: $target['email']) . '. Share it securely; they can change it in Settings.';
                }
            } elseif ($action === 'user_change_login') {
                $newEmail = trim((string) ($_POST['email'] ?? ''));
                $newUser  = trim((string) ($_POST['username'] ?? ''));
                if ($newEmail === '' && $newUser === '') {
                    $_SESSION['flash_error'] = 'Enter an email or a username.';
                } elseif ($newEmail !== '' && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                    $_SESSION['flash_error'] = 'That email address isn\'t valid.';
                } else {
                    // Uniqueness against OTHER users.
                    $clash = false;
                    if ($newEmail !== '') {
                        $c = $pdo->prepare('SELECT 1 FROM client_users WHERE email = ? AND id <> ? LIMIT 1');
                        $c->execute([$newEmail, $uid]);
                        if ($c->fetchColumn()) { $clash = true; $_SESSION['flash_error'] = 'That email is already used by another login.'; }
                    }
                    if (!$clash && $newUser !== '') {
                        $c = $pdo->prepare('SELECT 1 FROM client_users WHERE username = ? AND id <> ? LIMIT 1');
                        $c->execute([$newUser, $uid]);
                        if ($c->fetchColumn()) { $clash = true; $_SESSION['flash_error'] = 'That username is already taken.'; }
                    }
                    if (!$clash) {
                        $pdo->prepare('UPDATE client_users SET email = ?, username = ? WHERE id = ? AND client_id = ?')
                            ->execute([$newEmail !== '' ? $newEmail : null, $newUser !== '' ? $newUser : null, $uid, $clientId]);
                        $_SESSION['flash_success'] = 'Login details updated.';
                    }
                }
            } elseif ($action === 'user_toggle_active') {
                $to = (int) ($_POST['to'] ?? 0) === 1 ? 1 : 0;
                if ($uid === (int) ($user['user_id'] ?? 0) && $to === 0) {
                    $_SESSION['flash_error'] = 'You can\'t deactivate your own login.';
                } else {
                    $pdo->prepare('UPDATE client_users SET active = ? WHERE id = ? AND client_id = ?')->execute([$to, $uid, $clientId]);
                    $_SESSION['flash_success'] = $to === 1 ? 'Login activated.' : 'Login deactivated.';
                }
            } elseif ($action === 'user_resend_verification') {
                if (($target['email'] ?? '') === '') {
                    $_SESSION['flash_error'] = 'This login has no email to verify.';
                } else {
                    $token = verification_create_token($pdo, $uid);
                    $ok = verification_send_email($target['email'], verification_build_url($token), $companyName);
                    $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok
                        ? ('Confirmation email sent to ' . $target['email'] . '.')
                        : ('Could not send the email — check mail settings (or Testing mode is on).');
                }
            } elseif ($action === 'user_mark_verified') {
                try {
                    $pdo->prepare('UPDATE client_users SET email_verified_at = NOW() WHERE id = ? AND client_id = ?')->execute([$uid, $clientId]);
                    $_SESSION['flash_success'] = 'Marked as verified — they can sign in now.';
                } catch (Throwable $e) {
                    $_SESSION['flash_error'] = 'Could not update (email_verified_at column missing?).';
                }
            }
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Action failed: ' . $e->getMessage();
        }
        header('Location: ' . $redirect);
        exit;
    }

    header('Location: /master-admin/trade-account.php?id=' . $clientId);
    exit;
}

// ── Load the account ────────────────────────────────────────────────────────
$sel = 'SELECT id, company_name, contact_name, email, phone, vat_number,
               address1, address2, town, county, postcode, active, logo_path'
     . ($hasCreated ? ', created_at' : '') . '
          FROM clients WHERE id = ? LIMIT 1';
$st = $pdo->prepare($sel);
$st->execute([$clientId]);
$acc = $st->fetch(PDO::FETCH_ASSOC);

if (!$acc) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Account not found</h1><p><a href="/master-admin/trade-accounts.php">Back to Trade Accounts</a></p>';
    exit;
}

// Logins (read-only here; management arrives in 1B).
$logins = [];
try {
    $lu = $pdo->prepare(
        'SELECT id, full_name, email, username, role, active, last_login_at, email_verified_at, is_super_admin
           FROM client_users WHERE client_id = ? ORDER BY is_super_admin DESC, full_name, email'
    );
    $lu->execute([$clientId]);
    $logins = $lu->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* leave empty */ }

// Quick counts.
$countOf = static function (string $sql, int $id) use ($pdo): int {
    try { $s = $pdo->prepare($sql); $s->execute([$id]); return (int) $s->fetchColumn(); }
    catch (Throwable $e) { return 0; }
};
$productCount  = $countOf('SELECT COUNT(*) FROM products WHERE client_id = ?', $clientId);
$discountCount = $countOf('SELECT COUNT(*) FROM client_discounts WHERE client_id = ?', $clientId);

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$isActive  = (int) $acc['active'] === 1;
$hasPortal = count($logins) > 0;

$activeNav = 'trade-accounts';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) $acc['company_name']) ?> &middot; Trade Account</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .ta-badge { display:inline-block; margin-left:0.4rem; padding:0.0625rem 0.5rem; font-size:0.6875rem; font-weight:700; border-radius:999px; text-transform:uppercase; letter-spacing:0.04em; }
        .ta-badge.inactive { background:var(--bg-subtle-2); color:var(--text-faint); }
        .ta-badge.noportal { background:#fef3c7; color:#92400e; }
        .ta-badge.portal { background:#d1fae5; color:#065f46; }
        .ta-badge.you { background:#1f3b5b; color:#fff; }
        .ta-form-grid { display:grid; gap:0.75rem 1rem; grid-template-columns:1fr 1fr; }
        @media (max-width:720px){ .ta-form-grid { grid-template-columns:1fr; } }
        .ta-form-grid .full { grid-column:1 / -1; }
        .ta-form-grid label { display:block; font-size:0.75rem; font-weight:600; color:var(--text-faint); text-transform:uppercase; letter-spacing:0.03em; margin-bottom:0.25rem; }
        .ta-form-grid input { width:100%; box-sizing:border-box; padding:0.5rem 0.7rem; border:1px solid var(--border-strong); border-radius:8px; font:inherit; background:var(--bg-input); }
        .ta-counts { display:flex; gap:1.25rem; flex-wrap:wrap; color:var(--text-muted); font-size:0.9375rem; margin:0 0 0.25rem; }
        .ta-counts b { color:var(--text-primary); }
        .login-card { border:1px solid var(--border); border-radius:10px; background:var(--bg-card); padding:0.75rem 0.9rem; margin:0 0 0.6rem; }
        .login-head { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; flex-wrap:wrap; }
        .login-sub { color:var(--text-faint); font-size:0.8125rem; margin-top:0.15rem; }
        .login-meta { color:var(--text-faint); font-size:0.8125rem; white-space:nowrap; }
        .login-actions { margin-top:0.5rem; }
        .login-actions > summary { cursor:pointer; font-size:0.8125rem; font-weight:600; color:var(--link); }
        .login-actions-body { display:flex; flex-direction:column; gap:0.55rem; margin-top:0.6rem; padding-top:0.6rem; border-top:1px solid var(--border); }
        .action-row { display:flex; gap:0.4rem; align-items:center; flex-wrap:wrap; }
        .action-row input { padding:0.35rem 0.5rem; border:1px solid var(--border-strong); border-radius:6px; font:inherit; font-size:0.85rem; background:var(--bg-input); }
        .action-row .lbl { font-size:0.75rem; color:var(--text-faint); font-weight:600; min-width:6.5rem; }
        .btn-sm { font-size:0.8125rem; padding:0.3rem 0.7rem; }
        .protected { color:var(--text-faint); font-size:0.8125rem; font-style:italic; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <?= e((string) $acc['company_name']) ?>
                    <?php if ($clientId === $myClient): ?><span class="ta-badge you">You</span><?php endif; ?>
                    <?php if (!$isActive): ?><span class="ta-badge inactive">Inactive</span><?php endif; ?>
                    <?php if ($hasPortal): ?><span class="ta-badge portal">Portal</span><?php else: ?><span class="ta-badge noportal">No portal</span><?php endif; ?>
                </h1>
                <p class="page-subtitle">
                    <a href="/master-admin/trade-accounts.php">&larr; Trade Accounts</a>
                    <?php if ($hasCreated && !empty($acc['created_at'])): ?>
                        &middot; account since <?= e(date('j M Y', strtotime((string) $acc['created_at']))) ?>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <form method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>" style="margin:0"
                      <?php if ($isActive): ?>data-confirm="Deactivate <?= e((string) $acc['company_name']) ?>? Their logins won't be able to sign in until you re-activate."<?php endif; ?>>
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="active">
                    <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                    <input type="hidden" name="to" value="<?= $isActive ? 0 : 1 ?>">
                    <?php if ($clientId === $myClient && $isActive): ?>
                        <button type="button" class="btn btn-secondary" disabled title="You can't deactivate your own account">Deactivate</button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-<?= $isActive ? 'secondary' : 'primary' ?>"><?= $isActive ? 'Deactivate' : 'Activate' ?></button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?><div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div><?php endif; ?>
        <?php if ($flashErr !== null): ?><div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div><?php endif; ?>

        <div class="ta-counts">
            <span><b><?= count($logins) ?></b> login<?= count($logins) === 1 ? '' : 's' ?></span>
            <span><b><?= number_format($productCount) ?></b> products</span>
            <span><b><?= number_format($discountCount) ?></b> discount<?= $discountCount === 1 ? '' : 's' ?> set</span>
        </div>

        <!-- Business / contact profile -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.75rem">Account details</h2>
            <form method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="profile">
                <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                <div class="ta-form-grid">
                    <div class="full">
                        <label for="company_name">Company name *</label>
                        <input id="company_name" name="company_name" type="text" required maxlength="150" value="<?= e((string) $acc['company_name']) ?>">
                    </div>
                    <div>
                        <label for="contact_name">Contact name</label>
                        <input id="contact_name" name="contact_name" type="text" maxlength="150" value="<?= e((string) ($acc['contact_name'] ?? '')) ?>">
                    </div>
                    <div>
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" maxlength="190" value="<?= e((string) ($acc['email'] ?? '')) ?>">
                    </div>
                    <div>
                        <label for="phone">Phone</label>
                        <input id="phone" name="phone" type="text" maxlength="60" value="<?= e((string) ($acc['phone'] ?? '')) ?>">
                    </div>
                    <div>
                        <label for="vat_number">VAT number</label>
                        <input id="vat_number" name="vat_number" type="text" maxlength="40" value="<?= e((string) ($acc['vat_number'] ?? '')) ?>">
                    </div>
                    <div class="full">
                        <label for="address1">Address line 1</label>
                        <input id="address1" name="address1" type="text" maxlength="150" value="<?= e((string) ($acc['address1'] ?? '')) ?>">
                    </div>
                    <div class="full">
                        <label for="address2">Address line 2</label>
                        <input id="address2" name="address2" type="text" maxlength="150" value="<?= e((string) ($acc['address2'] ?? '')) ?>">
                    </div>
                    <div>
                        <label for="town">Town / city</label>
                        <input id="town" name="town" type="text" maxlength="100" value="<?= e((string) ($acc['town'] ?? '')) ?>">
                    </div>
                    <div>
                        <label for="county">County</label>
                        <input id="county" name="county" type="text" maxlength="100" value="<?= e((string) ($acc['county'] ?? '')) ?>">
                    </div>
                    <div>
                        <label for="postcode">Postcode</label>
                        <input id="postcode" name="postcode" type="text" maxlength="20" value="<?= e((string) ($acc['postcode'] ?? '')) ?>">
                    </div>
                </div>
                <div class="form-actions" style="margin-top:1rem">
                    <button type="submit" class="btn btn-primary">Save details</button>
                </div>
            </form>
        </section>

        <!-- Logins — view + manage -->
        <section class="section" id="logins">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin:0 0 0.6rem">
                <h2 class="section-title" style="margin:0">Logins</h2>
            </div>

            <?php if (!$logins): ?>
                <p style="color:var(--text-faint);margin:0 0 0.75rem">
                    No portal login yet &mdash; this is a <strong>no-portal</strong> account (a one-off you invoice/ship
                    to). Add a login below to give them portal access.
                </p>
            <?php else: foreach ($logins as $u):
                $isSuper   = (int) $u['is_super_admin'] === 1;
                $unverified = ($u['email'] ?? '') !== '' && empty($u['email_verified_at']);
            ?>
                <div class="login-card">
                    <div class="login-head">
                        <div>
                            <strong><?= e((string) ($u['full_name'] ?? '') ?: ($u['email'] ?: $u['username'] ?? '(no name)')) ?></strong>
                            <?php if ($isSuper): ?><span class="ta-badge you">Super</span><?php endif; ?>
                            <?php if ((int) $u['active'] !== 1): ?><span class="ta-badge inactive">Inactive</span><?php endif; ?>
                            <?php if ($unverified): ?><span class="ta-badge noportal">Unverified</span><?php endif; ?>
                            <div class="login-sub">
                                <?= e((string) ($u['email'] ?: $u['username'] ?? '')) ?>
                                &middot; <span style="text-transform:capitalize"><?= e((string) ($u['role'] ?? '')) ?></span>
                            </div>
                        </div>
                        <div class="login-meta">
                            <?= !empty($u['last_login_at']) ? 'last login ' . e(date('j M Y', strtotime((string) $u['last_login_at']))) : 'never signed in' ?>
                        </div>
                    </div>

                    <?php if ($isSuper): ?>
                        <div style="margin-top:0.4rem"><span class="protected">Protected &mdash; manage from the owner's own account settings.</span></div>
                    <?php else: ?>
                        <details class="login-actions">
                            <summary>Manage this login</summary>
                            <div class="login-actions-body">
                                <!-- Send reset link -->
                                <form class="action-row" method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_action" value="user_reset_link">
                                    <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <span class="lbl">Password</span>
                                    <button type="submit" class="btn btn-secondary btn-sm"
                                            <?= ($u['email'] ?? '') === '' ? 'disabled title="No email on this login"' : '' ?>>Send reset link</button>
                                </form>
                                <!-- Set a temporary password -->
                                <form class="action-row" method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_action" value="user_set_password">
                                    <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <span class="lbl">or set one</span>
                                    <input type="text" name="new_password" minlength="8" placeholder="temporary password (8+)" autocomplete="off" style="min-width:14rem">
                                    <button type="submit" class="btn btn-secondary btn-sm">Set password</button>
                                </form>
                                <!-- Change login (email / username) -->
                                <form class="action-row" method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_action" value="user_change_login">
                                    <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <span class="lbl">Login</span>
                                    <input type="email" name="email" value="<?= e((string) ($u['email'] ?? '')) ?>" placeholder="email" style="min-width:13rem">
                                    <input type="text" name="username" value="<?= e((string) ($u['username'] ?? '')) ?>" placeholder="username (optional)" style="min-width:10rem">
                                    <button type="submit" class="btn btn-secondary btn-sm">Save login</button>
                                </form>
                                <!-- Verification (only when an email is unverified) -->
                                <?php if ($unverified): ?>
                                    <div class="action-row">
                                        <span class="lbl">Verification</span>
                                        <form method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>" style="margin:0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="_action" value="user_resend_verification">
                                            <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm">Resend confirmation</button>
                                        </form>
                                        <form method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>" style="margin:0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="_action" value="user_mark_verified">
                                            <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm">Mark verified</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                                <!-- Activate / deactivate -->
                                <form class="action-row" method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>"
                                      <?php if ((int) $u['active'] === 1): ?>data-confirm="Deactivate this login? They won't be able to sign in until re-activated."<?php endif; ?>>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_action" value="user_toggle_active">
                                    <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <input type="hidden" name="to" value="<?= (int) $u['active'] === 1 ? 0 : 1 ?>">
                                    <span class="lbl">Access</span>
                                    <?php if ((int) $u['id'] === (int) ($user['user_id'] ?? 0) && (int) $u['active'] === 1): ?>
                                        <button type="button" class="btn btn-secondary btn-sm" disabled title="You can't deactivate your own login">Deactivate</button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-secondary btn-sm"><?= (int) $u['active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endforeach; endif; ?>

            <!-- Add a login -->
            <details style="margin-top:0.5rem">
                <summary style="cursor:pointer;font-weight:600;color:var(--link)">+ Add a login</summary>
                <form method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>" style="margin-top:0.6rem">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="user_add">
                    <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                    <div class="action-row">
                        <input type="text" name="full_name" placeholder="Full name" required style="min-width:12rem">
                        <input type="email" name="email" placeholder="Email" required style="min-width:14rem">
                        <input type="text" name="new_password" minlength="8" placeholder="Initial password (8+)" autocomplete="off" required style="min-width:12rem">
                        <button type="submit" class="btn btn-primary btn-sm">Add login</button>
                    </div>
                    <p style="color:var(--text-faint);font-size:0.8125rem;margin:0.4rem 0 0">
                        Created as an <strong>admin</strong> login, already verified. Then use <em>Send reset link</em>
                        so they can set their own password.
                    </p>
                </form>
            </details>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
