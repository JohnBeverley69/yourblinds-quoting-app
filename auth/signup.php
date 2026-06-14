<?php
declare(strict_types=1);

/**
 * Public self sign-up. Creates a brand-new tenant + its first admin user, then
 * emails a confirmation link. The account exists immediately but the user can't
 * sign in until they confirm their email (gate in login.php).
 *
 * Decisions (agreed with the owner):
 *   - Instant access (no manual approval) — gated only by email confirmation.
 *   - Blank slate — NO catalogue is seeded; they build their own.
 *   - Free core — a 30-day trial is granted on the paid add-ons (best-effort).
 *
 * Abuse defences: CSRF, per-IP rate limit (shared login_attempts ledger),
 * a honeypot field, unique-email check, 8-char minimum password.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/middleware.php';
require_once __DIR__ . '/../_partials/verification.php';

const SIGNUP_TRIAL_DAYS = 30;

if (is_logged_in()) {
    redirect_after_login();
}

$error   = null;
$created = false;
$form    = ['company_name' => '', 'full_name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ip = client_ip();

    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        // Honeypot tripped — a bot filled the hidden field. Pretend it worked so
        // they don't learn they were caught; create nothing.
        $created = true;
    } elseif (rate_limited($ip)) {
        $error = 'Too many attempts. Please wait a few minutes and try again.';
    } else {
        $form['company_name'] = trim((string) ($_POST['company_name'] ?? ''));
        $form['full_name']    = trim((string) ($_POST['full_name'] ?? ''));
        $form['email']        = trim((string) ($_POST['email'] ?? ''));
        $password             = (string) ($_POST['password'] ?? '');
        $confirm              = (string) ($_POST['confirm'] ?? '');

        if ($form['company_name'] === '' || strlen($form['company_name']) > 150) {
            $error = 'Please enter your business name.';
        } elseif ($form['full_name'] === '' || strlen($form['full_name']) > 150) {
            $error = 'Please enter your name.';
        } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || strlen($form['email']) > 190) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            // Counts toward the per-IP limit whether or not it succeeds.
            record_login_attempt($ip, 'signup:' . $form['email'], false);

            $chk = db()->prepare('SELECT 1 FROM client_users WHERE email = ? LIMIT 1');
            $chk->execute([$form['email']]);
            if ($chk->fetchColumn()) {
                $error = 'An account with that email already exists. Try signing in, or reset your password.';
            } else {
                $pdo = db();
                $newClientId = null;
                $newUserId   = null;
                try {
                    $pdo->beginTransaction();

                    $pdo->prepare('INSERT INTO clients (company_name, active) VALUES (?, 1)')
                        ->execute([$form['company_name']]);
                    $newClientId = (int) $pdo->lastInsertId();

                    $pdo->prepare('INSERT INTO client_settings (client_id) VALUES (?)')
                        ->execute([$newClientId]);

                    // Admin of their own tenant; NOT a super admin. email_verified_at
                    // NULL → must confirm before the login gate lets them in.
                    $pdo->prepare(
                        'INSERT INTO client_users
                           (client_id, email, full_name, password_hash,
                            role, active, is_super_admin, email_verified_at)
                         VALUES (?, ?, ?, ?, "admin", 1, 0, NULL)'
                    )->execute([
                        $newClientId,
                        $form['email'],
                        $form['full_name'],
                        password_hash($password, PASSWORD_DEFAULT),
                    ]);
                    $newUserId = (int) $pdo->lastInsertId();

                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('[YourBlinds] signup failed: ' . $e->getMessage());
                    $error = 'Sorry — something went wrong creating your account. Please try again.';
                }

                if ($error === null && $newUserId) {
                    // ── Best-effort onboarding extras — never block signup ──
                    // Grant a 30-day trial on the paid add-ons so they can try
                    // them; the core app is free regardless.
                    try {
                        require_once __DIR__ . '/../_partials/billing_helpers.php';
                        if (function_exists('billing_paid_plans')) {
                            $trialExpiry = date('Y-m-d', strtotime('+' . SIGNUP_TRIAL_DAYS . ' days'));
                            foreach (array_keys(billing_paid_plans()) as $planCode) {
                                try {
                                    db()->prepare(
                                        "INSERT INTO client_plan_overrides
                                           (client_id, plan_code, override_type, expires_at, notes, active)
                                         VALUES (?, ?, 'trial', ?, ?, 1)
                                         ON DUPLICATE KEY UPDATE
                                           override_type = 'trial',
                                           expires_at    = VALUES(expires_at),
                                           notes         = VALUES(notes),
                                           active        = 1"
                                    )->execute([
                                        $newClientId, $planCode, $trialExpiry,
                                        sprintf('Auto-granted %d-day trial on self sign-up (%s).',
                                            SIGNUP_TRIAL_DAYS, date('Y-m-d')),
                                    ]);
                                } catch (Throwable $tErr) { /* trials column / plan absent — skip */ }
                            }
                            if (function_exists('billing_sync_feature_flags_force')) {
                                billing_sync_feature_flags_force($newClientId);
                            }
                        }
                    } catch (Throwable $e) {
                        error_log('[YourBlinds] signup trial-grant failed: ' . $e->getMessage());
                    }

                    // ── Confirmation email ──
                    try {
                        $token = verification_create_token(db(), $newUserId);
                        verification_send_email(
                            $form['email'],
                            verification_build_url($token),
                            $form['company_name']
                        );
                    } catch (Throwable $e) {
                        error_log('[YourBlinds] signup verification email failed: ' . $e->getMessage());
                    }

                    $created = true;
                }
            }
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create your account &middot; YourBlinds</title>
    <link rel="stylesheet" href="/auth/auth.css">
</head>
<body>
    <main class="auth-card" role="main">
        <div class="auth-brand">
            <span class="auth-brand-mark">Your<span class="accent">Blinds</span></span>
            <span class="auth-brand-tag">Trade Quoting Portal</span>
        </div>

        <?php if ($created): ?>
            <h1>Check your email</h1>
            <div class="alert alert-success" role="status">
                Your account is created. We've sent a confirmation link to
                <strong><?= e($form['email'] !== '' ? $form['email'] : 'your email address') ?></strong>
                — click it to activate your account, then sign in.
            </div>
            <p class="auth-subtitle">
                The link is valid for 24 hours. Didn't get it? Check your spam folder,
                or <a href="/auth/resend_verification.php?email=<?= e(urlencode($form['email'])) ?>">resend it</a>.
            </p>
            <p class="auth-footer">
                <a href="/auth/login.php">&larr; Back to sign in</a>
            </p>
        <?php else: ?>
            <h1>Create your account</h1>
            <p class="auth-subtitle">Set up your blinds business in a couple of minutes. The core app is free.</p>

            <?php if ($error !== null): ?>
                <div class="alert alert-error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/auth/signup.php" novalidate autocomplete="on">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="company_name">Business name</label>
                    <input id="company_name" name="company_name" type="text" maxlength="150"
                           autofocus required value="<?= e($form['company_name']) ?>">
                </div>

                <div class="field">
                    <label for="full_name">Your name</label>
                    <input id="full_name" name="full_name" type="text" maxlength="150"
                           autocomplete="name" required value="<?= e($form['full_name']) ?>">
                </div>

                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" maxlength="190"
                           autocomplete="email" required value="<?= e($form['email']) ?>">
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password"
                           autocomplete="new-password" minlength="8" required>
                </div>

                <div class="field">
                    <label for="confirm">Confirm password</label>
                    <input id="confirm" name="confirm" type="password"
                           autocomplete="new-password" minlength="8" required>
                </div>

                <!-- Honeypot: hidden from people, irresistible to bots. -->
                <div style="position:absolute;left:-9999px" aria-hidden="true">
                    <label for="website">Leave this empty</label>
                    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>

                <button type="submit">Create account</button>
            </form>

            <p class="auth-footer">
                Already have an account? <a href="/auth/login.php">Sign in</a>
            </p>
        <?php endif; ?>

        <p class="auth-meta">
            &copy; <?= date('Y') ?> YourBlinds. All rights reserved.
        </p>
    </main>
</body>
</html>
