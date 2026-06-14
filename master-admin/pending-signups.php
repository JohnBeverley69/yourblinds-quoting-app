<?php
declare(strict_types=1);

/**
 * Master Admin: self sign-ups awaiting email confirmation.
 *
 * Lists every account that signed up but hasn't clicked its confirmation link
 * yet, with a manual "Confirm" button. Lets the owner activate (or test) a
 * sign-up even while outgoing email is paused — the normal path is the emailed
 * link (auth/verify.php), this is the back-office override.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

$user = current_user();
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($action === 'confirm' && $userId > 0) {
        try {
            $st = $pdo->prepare(
                'UPDATE client_users SET email_verified_at = NOW()
                  WHERE id = ? AND email_verified_at IS NULL'
            );
            $st->execute([$userId]);
            $_SESSION['flash_success'] = $st->rowCount() > 0
                ? 'Account confirmed — they can sign in now.'
                : 'That account was already confirmed, or no longer exists.';
        } catch (Throwable $e) {
            error_log('[YourBlinds] pending-signup confirm failed: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Could not confirm. Has migrate_email_verification.php been run?';
        }
    }

    header('Location: /master-admin/pending-signups.php');
    exit;
}

// Unconfirmed, active sign-ups. Defensive: the column may be missing if the
// migration hasn't run yet — show a clear note rather than 500ing.
$pending    = [];
$colMissing = false;
try {
    $st = $pdo->query(
        "SELECT u.id, u.email, u.full_name, u.created_at,
                c.id AS client_id, c.company_name
           FROM client_users u
           JOIN clients c ON c.id = u.client_id
          WHERE u.email_verified_at IS NULL AND u.active = 1
       ORDER BY u.created_at DESC"
    );
    $pending = $st->fetchAll();
} catch (Throwable $e) {
    $colMissing = true;
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$activeNav = 'pending-signups';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pending sign-ups &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Pending sign-ups</h1>
                <p class="page-subtitle">
                    Self sign-ups waiting to confirm their email. Normally they click the
                    link in their email; use <strong>Confirm</strong> here to activate one
                    by hand (handy while outgoing email is paused).
                </p>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <a href="/master-admin/index.php" class="btn btn-secondary">&larr; Master Admin</a>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <section class="section">
            <?php if ($colMissing): ?>
                <p style="color:var(--text-secondary)">
                    Email verification isn't set up yet — run
                    <strong>/migrate_email_verification.php</strong> first.
                </p>
            <?php elseif (!$pending): ?>
                <p style="color:var(--text-secondary)">No sign-ups are waiting to confirm. 🎉</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Business</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Signed up</th>
                                <th style="text-align:right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending as $p): ?>
                                <tr>
                                    <td><?= e((string) $p['company_name']) ?></td>
                                    <td><?= e((string) $p['full_name']) ?></td>
                                    <td><?= e((string) $p['email']) ?></td>
                                    <td><?= e($p['created_at'] ? date('j M Y, g:ia', strtotime((string) $p['created_at'])) : '') ?></td>
                                    <td style="text-align:right">
                                        <form method="post" action="/master-admin/pending-signups.php"
                                              style="margin:0;display:inline"
                                              data-confirm="Confirm <?= e((string) $p['email']) ?> and let them sign in?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="_action" value="confirm">
                                            <input type="hidden" name="user_id" value="<?= (int) $p['id'] ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">Confirm</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p style="color:var(--text-faint);font-size:0.8125rem;margin:0.875rem 0 0;max-width:46rem">
                    To reject a spam sign-up instead, delete its tenant from
                    <a href="/master-admin/index.php" style="color:var(--brand)">Master Admin</a>
                    (each row has a delete option).
                </p>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
