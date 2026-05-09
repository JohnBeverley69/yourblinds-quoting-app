<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/middleware.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

$message = null;
$error   = null;
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Rate limit BEFORE we touch the DB or send any email — defends against
    // password-reset email bombing and timing-based user enumeration. Reuses
    // the same login_attempts ledger as login.php so failed logins + reset
    // requests count toward a single per-IP cap.
    $ip = client_ip();
    if (rate_limited($ip)) {
        $error = 'Too many requests. Please wait a few minutes and try again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Always record the attempt as "unsuccessful" — it's a request,
            // not a credential check, but we want it to count for rate-limiting.
            // The identifier prefix flags the row's purpose for the ops log.
            record_login_attempt($ip, 'reset:' . $email, false);

            $stmt = db()->prepare(
                'SELECT id FROM client_users WHERE email = ? AND active = 1 LIMIT 1'
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token     = bin2hex(random_bytes(32));               // 64 hex chars
                $tokenHash = hash('sha256', $token);
                $expiresAt = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');

                db()->prepare(
                    'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
                )->execute([(int) $user['id'], $tokenHash, $expiresAt]);

                // Build the reset URL from a configured base, NEVER from the
                // request's Host header (host-header injection lets an attacker
                // force the reset email to point at their own domain). If
                // APP_URL isn't configured we still send the email but log
                // a warning — the link will be relative which most clients
                // surface as plain text the user can copy-paste.
                $resetUrl = build_reset_url($token);

                // In dev, also log the URL so testing works without a real SMTP relay.
                $appEnv = strtolower(env('APP_ENV', 'production') ?? 'production');
                if (in_array($appEnv, ['development', 'dev', 'local'], true)) {
                    error_log("[YourBlinds] Password reset URL for {$email}: {$resetUrl}");
                }

                send_password_reset_email($email, $resetUrl);
            }

            // Same response whether or not the email matched — prevents enumeration.
            $message = 'If that email is registered with us, a password reset link is on its way.';
        }
    }
}

/**
 * Build the password-reset URL from APP_URL (.env). Refuses to fall back to
 * the request's Host header — that's the host-header injection vector we're
 * closing. If APP_URL isn't set, we log a warning and use a relative path,
 * which is at worst inconvenient (the user gets a copy-paste-able token URL
 * that lacks the scheme/host) but never spoofable.
 */
function build_reset_url(string $token): string
{
    $base = trim((string) (env('APP_URL', '') ?? ''));
    if ($base === '') {
        error_log('[YourBlinds] APP_URL not set in .env — password reset URL emitted as relative path. '
                . 'Set APP_URL to e.g. https://yourblinds.uk so reset emails carry an absolute link.');
        return '/auth/reset_password.php?token=' . $token;
    }
    return rtrim($base, '/') . '/auth/reset_password.php?token=' . $token;
}

/**
 * Send the reset link via SMTP (PHPMailer). Failures are logged, never surfaced
 * to the visitor — the public response stays uniform.
 */
function send_password_reset_email(string $to, string $resetUrl): void
{
    if (!class_exists(PHPMailer::class)) {
        error_log('[YourBlinds] PHPMailer not installed — run "composer install" to enable email sending.');
        return;
    }

    $host     = (string) (env('MAIL_HOST',      'mail.authsmtp.com') ?? 'mail.authsmtp.com');
    $port     = (int)    (env('MAIL_PORT',      '2525')               ?? 2525);
    $username = (string) (env('MAIL_USERNAME',  '')                   ?? '');
    $password = (string) (env('MAIL_PASS',      '')                   ?? '');
    $from     = (string) (env('MAIL_FROM',      'noreply@yourblinds.uk') ?? 'noreply@yourblinds.uk');
    $fromName = (string) (env('MAIL_FROM_NAME', 'YourBlinds')         ?? 'YourBlinds');

    if ($host === '' || $username === '' || $password === '') {
        error_log('[YourBlinds] SMTP not configured — set MAIL_HOST / MAIL_USERNAME / MAIL_PASS in .env');
        return;
    }

    $body = "Hello,\n\n"
          . "We received a request to reset the password for your YourBlinds account.\n\n"
          . "Use the link below to choose a new password (valid for 1 hour):\n"
          . $resetUrl . "\n\n"
          . "If you didn't ask to reset your password, you can safely ignore this email.\n\n"
          . "— YourBlinds";

    try {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host       = $host;
        $mailer->Port       = $port;
        $mailer->SMTPAuth   = true;
        $mailer->Username   = $username;
        $mailer->Password   = $password;
        // STARTTLS works on the AuthSMTP submission ports (2525/587).
        // For port 465 use PHPMailer::ENCRYPTION_SMTPS.
        $mailer->SMTPSecure = $port === 465
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->CharSet    = 'UTF-8';
        $mailer->Timeout    = 10;

        $mailer->setFrom($from, $fromName);
        $mailer->addAddress($to);
        $mailer->addReplyTo($from, $fromName);

        $mailer->Subject = 'Reset your YourBlinds password';
        $mailer->Body    = $body;

        $mailer->send();
    } catch (PHPMailerException $e) {
        error_log('[YourBlinds] PHPMailer error: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log('[YourBlinds] Email send failed: ' . $e->getMessage());
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot password &middot; YourBlinds</title>
    <link rel="stylesheet" href="/auth/auth.css">
</head>
<body>
    <main class="auth-card" role="main">
        <div class="auth-brand">
            <span class="auth-brand-mark">Your<span class="accent">Blinds</span></span>
            <span class="auth-brand-tag">Trade Quoting Portal</span>
        </div>

        <h1>Forgot your password?</h1>
        <p class="auth-subtitle">
            Enter your account email and we will send you a link to set a new password.
        </p>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($message !== null): ?>
            <div class="alert alert-success" role="status"><?= e($message) ?></div>
        <?php endif; ?>

        <?php if ($message === null): ?>
            <form method="post" action="/auth/forgot_password.php" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="email">Email address</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        autocomplete="email"
                        autofocus
                        required
                        value="<?= e($email) ?>">
                </div>
                <button type="submit">Send reset link</button>
            </form>
        <?php endif; ?>

        <p class="auth-footer">
            <a href="/auth/login.php">&larr; Back to sign in</a>
        </p>
    </main>
</body>
</html>
