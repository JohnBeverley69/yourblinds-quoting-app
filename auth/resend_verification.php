<?php
declare(strict_types=1);

/**
 * Resend an email-confirmation link. Uniform response whether or not the email
 * matches an unconfirmed account — no account enumeration. Rate-limited per IP
 * via the shared login_attempts ledger.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/middleware.php';
require_once __DIR__ . '/../_partials/verification.php';

$email   = trim((string) ($_GET['email'] ?? $_POST['email'] ?? ''));
$message = null;
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ip = client_ip();

    if (rate_limited($ip)) {
        $error = 'Too many requests. Please wait a few minutes and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        record_login_attempt($ip, 'resend-verify:' . $email, false);
        try {
            // Only unconfirmed, active accounts get a fresh link. (If the
            // email_verified_at column is missing the query throws → caught →
            // uniform message, no email sent.)
            $st = db()->prepare(
                'SELECT id, client_id FROM client_users
                  WHERE email = ? AND active = 1 AND email_verified_at IS NULL
                  LIMIT 1'
            );
            $st->execute([$email]);
            $u = $st->fetch();
            if ($u) {
                $cs = db()->prepare('SELECT company_name FROM clients WHERE id = ? LIMIT 1');
                $cs->execute([(int) $u['client_id']]);
                $company = (string) ($cs->fetchColumn() ?: '');

                $token = verification_create_token(db(), (int) $u['id']);
                verification_send_email($email, verification_build_url($token), $company);
            }
        } catch (Throwable $e) {
            error_log('[YourBlinds] resend verification failed: ' . $e->getMessage());
        }
        $message = 'If that email is registered and not yet confirmed, a new confirmation link is on its way.';
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resend confirmation &middot; YourBlinds</title>
    <link rel="stylesheet" href="/auth/auth.css">
</head>
<body>
    <main class="auth-card" role="main">
        <div class="auth-brand">
            <span class="auth-brand-mark">Your<span class="accent">Blinds</span></span>
            <span class="auth-brand-tag">Trade Quoting Portal</span>
        </div>

        <h1>Resend confirmation email</h1>

        <?php if ($message !== null): ?>
            <div class="alert alert-success" role="status"><?= e($message) ?></div>
            <p class="auth-footer">
                <a href="/auth/login.php">&larr; Back to sign in</a>
            </p>
        <?php else: ?>
            <p class="auth-subtitle">Enter your email and we'll send a fresh confirmation link.</p>

            <?php if ($error !== null): ?>
                <div class="alert alert-error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/auth/resend_verification.php" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" maxlength="190"
                           autocomplete="email" autofocus required value="<?= e($email) ?>">
                </div>
                <button type="submit">Resend link</button>
            </form>

            <p class="auth-footer">
                <a href="/auth/login.php">&larr; Back to sign in</a>
            </p>
        <?php endif; ?>

        <p class="auth-meta">
            &copy; <?= date('Y') ?> YourBlinds. All rights reserved.
        </p>
    </main>
</body>
</html>
