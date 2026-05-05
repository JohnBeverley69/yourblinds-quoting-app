<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/middleware.php';

$token   = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$error   = null;
$success = false;
$reset   = null;

// ---------------------------------------------------------------------------
// Validate the token before showing or processing the form.
// ---------------------------------------------------------------------------
$tokenValid = false;
if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $error = 'Invalid or missing reset token.';
} else {
    $tokenHash = hash('sha256', $token);
    $stmt = db()->prepare(
        'SELECT id, user_id, expires_at, used_at
           FROM password_resets
          WHERE token_hash = ?
          LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $reset = $stmt->fetch();

    if (!$reset) {
        $error = 'That reset link is not valid.';
    } elseif ($reset['used_at'] !== null) {
        $error = 'That reset link has already been used. Please request a new one.';
    } elseif (strtotime((string) $reset['expires_at']) < time()) {
        $error = 'That reset link has expired. Please request a new one.';
    } else {
        $tokenValid = true;
    }
}

// ---------------------------------------------------------------------------
// Process the new password.
// ---------------------------------------------------------------------------
if ($tokenValid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm']  ?? '');

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $pdo->prepare('UPDATE client_users SET password_hash = ? WHERE id = ?')
                ->execute([$newHash, (int) $reset['user_id']]);

            // Mark this token used AND invalidate any other outstanding tokens for the user.
            $pdo->prepare(
                'UPDATE password_resets
                    SET used_at = NOW()
                  WHERE user_id = ? AND used_at IS NULL'
            )->execute([(int) $reset['user_id']]);

            $pdo->commit();
            $success    = true;
            $tokenValid = false; // hide the form once the password is changed
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Password reset failed: ' . $e->getMessage());
            $error = 'Could not update your password. Please try again.';
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset password &middot; YourBlinds</title>
    <link rel="stylesheet" href="/auth/auth.css">
</head>
<body>
    <main class="auth-card" role="main">
        <div class="auth-brand">
            <span class="auth-brand-mark">Your<span class="accent">Blinds</span></span>
            <span class="auth-brand-tag">Trade Quoting Portal</span>
        </div>

        <h1>Choose a new password</h1>

        <?php if ($success): ?>
            <div class="alert alert-success" role="status">
                Your password has been updated. You can now sign in with your new password.
            </div>
            <p class="auth-footer">
                <a href="/auth/login.php">Go to sign in &rarr;</a>
            </p>
        <?php else: ?>
            <p class="auth-subtitle">Pick something at least 8 characters long.</p>

            <?php if ($error !== null): ?>
                <div class="alert alert-error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($tokenValid): ?>
                <form method="post" action="/auth/reset_password.php" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <div class="field">
                        <label for="password">New password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            minlength="8"
                            autofocus
                            required>
                    </div>

                    <div class="field">
                        <label for="confirm">Confirm new password</label>
                        <input
                            id="confirm"
                            name="confirm"
                            type="password"
                            autocomplete="new-password"
                            minlength="8"
                            required>
                    </div>

                    <button type="submit">Update password</button>
                </form>
            <?php else: ?>
                <p class="auth-footer">
                    <a href="/auth/forgot_password.php">Request a new reset link &rarr;</a>
                    <br><br>
                    <a href="/auth/login.php">&larr; Back to sign in</a>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
