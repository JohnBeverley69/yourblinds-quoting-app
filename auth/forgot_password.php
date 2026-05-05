<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/middleware.php';

$message = null;
$error   = null;
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email = trim((string) ($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Look up an active user, but do NOT reveal whether the address is registered.
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

            $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                     || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
            $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $resetUrl = "{$scheme}://{$host}/auth/reset_password.php?token={$token}";

            $appEnv = strtolower(env('APP_ENV', 'production') ?? 'production');
            if (in_array($appEnv, ['development', 'dev', 'local'], true)) {
                error_log("[YourBlinds] Password reset URL for {$email}: {$resetUrl}");
            }

            $subject = 'Reset your YourBlinds password';
            $body    = "Hello,\n\n"
                     . "We received a request to reset the password for your YourBlinds account.\n\n"
                     . "Use the link below to choose a new password (valid for 1 hour):\n"
                     . $resetUrl . "\n\n"
                     . "If you didn't ask to reset your password, you can safely ignore this email.\n\n"
                     . "— YourBlinds";
            $headers = "From: noreply@yourblinds.uk\r\n"
                     . "Reply-To: noreply@yourblinds.uk\r\n"
                     . "Content-Type: text/plain; charset=utf-8\r\n";

            // TODO: replace mail() with PHPMailer/SMTP once email config is wired up.
            @mail($email, $subject, $body, $headers);
        }

        // Same response whether or not the email matched — prevents enumeration.
        $message = 'If that email is registered with us, a password reset link is on its way.';
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
