<?php
declare(strict_types=1);

/**
 * Confirm an email address from the link in the sign-up email. Marks
 * client_users.email_verified_at so the login gate lets the user in.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/middleware.php';

$token   = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$error   = null;
$success = false;

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $error = 'Invalid or missing confirmation link.';
} else {
    $tokenHash = hash('sha256', $token);
    $stmt = db()->prepare(
        'SELECT id, user_id, expires_at, used_at
           FROM email_verifications
          WHERE token_hash = ?
          LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();

    if (!$row) {
        $error = 'That confirmation link is not valid.';
    } elseif ($row['used_at'] !== null) {
        // Already confirmed — not an error worth alarming over.
        $success = true;
    } elseif (strtotime((string) $row['expires_at']) < time()) {
        $error = 'That confirmation link has expired. Sign in and we\'ll offer to send a fresh one.';
    } else {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE client_users SET email_verified_at = NOW() WHERE id = ?')
                ->execute([(int) $row['user_id']]);
            $pdo->prepare(
                'UPDATE email_verifications SET used_at = NOW()
                  WHERE user_id = ? AND used_at IS NULL'
            )->execute([(int) $row['user_id']]);
            $pdo->commit();
            $success = true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[YourBlinds] email verify failed: ' . $e->getMessage());
            $error = 'Could not confirm your email. Please try again.';
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirm email &middot; YourBlinds</title>
    <link rel="stylesheet" href="/auth/auth.css">
</head>
<body>
    <main class="auth-card" role="main">
        <div class="auth-brand">
            <span class="auth-brand-mark">Your<span class="accent">Blinds</span></span>
            <span class="auth-brand-tag">Trade Quoting Portal</span>
        </div>

        <h1>Email confirmation</h1>

        <?php if ($success): ?>
            <div class="alert alert-success" role="status">
                Your email is confirmed — your account is active. You can sign in now.
            </div>
            <p class="auth-footer">
                <a href="/auth/login.php">Go to sign in &rarr;</a>
            </p>
        <?php else: ?>
            <div class="alert alert-error" role="alert"><?= e((string) $error) ?></div>
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
