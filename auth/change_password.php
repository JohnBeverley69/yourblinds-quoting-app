<?php
declare(strict_types=1);

/**
 * Change-password page for logged-in users.
 *
 * Requires:
 *   - The user be logged in (requireLogin).
 *   - The current password be entered correctly (defence against an
 *     attacker who's grabbed a session — they still can't pivot to
 *     a permanent account takeover without the existing password).
 *   - The new password be at least 8 characters and entered twice
 *     identically (same rules as reset_password.php).
 *
 * On success the password_hash column is updated and a flash message
 * sends the user back to /admin/settings.php (or wherever they came
 * from via ?return=...). No session invalidation needed — the active
 * session stays valid since they're the same user.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/middleware.php';

requireLogin();

$user   = current_user();
$userId = (int) $user['user_id'];

$error      = null;
$flashMsg   = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

// Where to send the user on success — sanitised to local paths only.
$return = (string) ($_GET['return'] ?? '/admin/settings.php');
if ($return === '' || $return[0] !== '/' || str_starts_with($return, '//')) {
    $return = '/admin/settings.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $current = (string) ($_POST['current'] ?? '');
    $new     = (string) ($_POST['new']     ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    // Load current hash.
    $st = db()->prepare('SELECT password_hash FROM client_users WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $hash = (string) ($st->fetchColumn() ?: '');

    if ($current === '' || !password_verify($current, $hash)) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } elseif ($new === $current) {
        $error = 'New password must be different from the current one.';
    } else {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        db()->prepare('UPDATE client_users SET password_hash = ? WHERE id = ?')
            ->execute([$newHash, $userId]);

        // Invalidate any outstanding reset tokens for this user so a
        // stale reset link can't be used to set it back.
        try {
            db()->prepare(
                'UPDATE password_resets
                    SET used_at = NOW()
                  WHERE user_id = ? AND used_at IS NULL'
            )->execute([$userId]);
        } catch (Throwable $e) {
            // Reset-token table may not exist on some installs — non-fatal.
        }

        $_SESSION['flash_success'] = 'Password changed.';
        header('Location: ' . $return);
        exit;
    }
}

$activeNav = '';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change password &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Change password</h1>
                <p class="page-subtitle">
                    Logged in as <strong><?= e((string) $user['full_name']) ?></strong>
                    (<?= e((string) $user['role']) ?>).
                </p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section" style="max-width:480px">
            <form method="post" action="/auth/change_password.php<?= $return !== '/admin/settings.php' ? '?return=' . urlencode($return) : '' ?>"
                  class="form" novalidate autocomplete="off">
                <?= csrf_field() ?>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="current">Current password <span class="required">*</span></label>
                        <input id="current" name="current" type="password"
                               required autocomplete="current-password">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="new">New password <span class="required">*</span></label>
                        <input id="new" name="new" type="password"
                               required minlength="8" autocomplete="new-password">
                        <small style="color:#6b7280;font-size:0.8125rem">
                            At least 8 characters. Mix letters, numbers and symbols
                            to make it harder to guess.
                        </small>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="confirm">Confirm new password <span class="required">*</span></label>
                        <input id="confirm" name="confirm" type="password"
                               required minlength="8" autocomplete="new-password">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Change password</button>
                    <a href="<?= e($return) ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
