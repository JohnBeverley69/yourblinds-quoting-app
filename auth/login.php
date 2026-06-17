<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/middleware.php';

// Already logged in? Bounce straight to the dashboard.
if (is_logged_in()) {
    redirect_after_login();
}

$error      = null;
$identifier = '';
$next       = (string) ($_GET['next'] ?? '');
// Set when the password was right but the email isn't confirmed yet (self
// sign-up). Drives a "resend confirmation" link in the form below.
$needsVerification = false;
$unverifiedEmail   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password   = (string) ($_POST['password'] ?? '');
    $next       = (string) ($_POST['next'] ?? '');
    $ip         = client_ip();

    // Rate limit BEFORE we touch the DB — defends against enumeration timing.
    // Keyed on (ip, identifier) so a fumbled password on one account doesn't
    // lock out every account on the same connection.
    if (rate_limited($ip, $identifier)) {
        $error = 'Too many failed attempts. Please wait a few minutes and try again.';
    } elseif ($identifier === '' || $password === '') {
        $error = 'Please enter your username or email and password.';
    } else {
        // Two positional placeholders — MySQL native prepares (EMULATE_PREPARES=false)
        // require one slot per occurrence, so the same value is bound twice.
        $stmt = db()->prepare(
            'SELECT u.id, u.client_id, u.full_name, u.password_hash,
                    u.role, u.active, u.is_super_admin, u.email,
                    c.company_name
               FROM client_users u
               JOIN clients c ON c.id = u.client_id
              WHERE u.email = ? OR u.username = ?
              LIMIT 1'
        );
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        $valid = $user
              && (int) $user['active'] === 1
              && password_verify($password, (string) $user['password_hash']);

        record_login_attempt($ip, $identifier, $valid);

        // Email-verification gate (self sign-up). Only checked when the password
        // is correct. Defensive: if the column doesn't exist yet (migration not
        // run) the probe throws and we treat the user as verified, so no
        // existing account is ever locked out.
        $verified = true;
        if ($valid) {
            try {
                $vs = db()->prepare('SELECT email_verified_at FROM client_users WHERE id = ? LIMIT 1');
                $vs->execute([(int) $user['id']]);
                $vAt = $vs->fetchColumn();
                $verified = ($vAt === false) ? true : ($vAt !== null);
            } catch (Throwable $e) {
                $verified = true;
            }
        }

        if ($valid && !$verified) {
            $needsVerification = true;
            $unverifiedEmail   = (string) ($user['email'] ?? '');
            $error = 'Please confirm your email address before signing in — check your inbox for the link we sent.';
        } elseif ($valid) {
            // Session fixation defence: regenerate ID at the privilege boundary.
            session_regenerate_id(true);

            $_SESSION['user_id']        = (int) $user['id'];
            $_SESSION['client_id']      = (int) $user['client_id'];
            $_SESSION['role']           = (string) $user['role'];   // primary
            $_SESSION['company_name']   = (string) $user['company_name'];
            $_SESSION['full_name']      = (string) $user['full_name'];
            $_SESSION['is_super_admin'] = (int) ($user['is_super_admin'] ?? 0) === 1;

            // Load the user's full role set into the session. Falls back
            // gracefully if migrate_user_multi_roles.php hasn't been run
            // (table missing) — in that case we keep the legacy
            // single-role behaviour.
            $rolesArr = [(string) $user['role']];
            try {
                $rs = db()->prepare(
                    'SELECT role FROM client_user_roles WHERE user_id = ?'
                );
                $rs->execute([(int) $user['id']]);
                $fetched = $rs->fetchAll(PDO::FETCH_COLUMN);
                if ($fetched) {
                    // Primary first, then the rest. Deduped.
                    $rolesArr = array_values(array_unique(array_merge(
                        [(string) $user['role']],
                        array_map('strval', $fetched)
                    )));
                }
            } catch (Throwable $e) {
                // junction table missing → stick with single-role.
            }
            $_SESSION['roles'] = $rolesArr;

            // Best-effort last-login update; don't fail login if this errors.
            try {
                db()->prepare('UPDATE client_users SET last_login_at = NOW() WHERE id = ?')
                    ->execute([$user['id']]);
            } catch (Throwable $e) {
                error_log('last_login_at update failed: ' . $e->getMessage());
            }

            redirect_after_login();
        } else {
            // Generic message — do not reveal which of identifier / password was wrong.
            $error = 'Invalid username/email or password.';
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in &middot; YourBlinds</title>
    <link rel="stylesheet" href="/auth/auth.css">
</head>
<body>
    <main class="auth-card" role="main">
        <div class="auth-brand">
            <span class="auth-brand-mark">Your<span class="accent">Blinds</span></span>
            <span class="auth-brand-tag">Trade Quoting Portal</span>
        </div>

        <h1>Sign in to your account</h1>
        <p class="auth-subtitle">Enter your credentials to continue.</p>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($needsVerification): ?>
            <p class="auth-footer">
                <a href="/auth/resend_verification.php?email=<?= e(urlencode($unverifiedEmail)) ?>">Resend confirmation email &rarr;</a>
            </p>
        <?php endif; ?>

        <form method="post" action="/auth/login.php" novalidate autocomplete="on">
            <?= csrf_field() ?>
            <?php if ($next !== ''): ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">
            <?php endif; ?>

            <div class="field">
                <label for="identifier">Username or email</label>
                <input
                    id="identifier"
                    name="identifier"
                    type="text"
                    autocomplete="username"
                    autofocus
                    required
                    value="<?= e($identifier) ?>">
            </div>

            <div class="field">
                <div class="field-row">
                    <label for="password">Password</label>
                    <a href="/auth/forgot_password.php">Forgot?</a>
                </div>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required>
            </div>

            <button type="submit">Sign in</button>
        </form>

        <p class="auth-footer">
            New to YourBlinds? <a href="/auth/signup.php">Create an account</a>
        </p>

        <p class="auth-meta">
            &copy; <?= date('Y') ?> YourBlinds. All rights reserved.
        </p>
    </main>
</body>
</html>
