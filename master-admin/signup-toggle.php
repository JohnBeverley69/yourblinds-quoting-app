<?php
declare(strict_types=1);

/**
 * Flip the global "public sign-up open" switch. POST-only, super-admin only.
 * Body: csrf_token + signups_paused (present = closed, absent = open).
 *
 * When ON (paused), auth/signup.php refuses new self sign-ups and the "Create an
 * account" link is hidden on the login page. Existing tenants are unaffected —
 * this only gates the public registration form. Mirrors email-toggle.php.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/app_settings.php';

requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Location: /master-admin/index.php');
    exit;
}

csrf_check();

$paused = !empty($_POST['signups_paused']);
$ok = app_setting_set('signups_paused', $paused ? '1' : '0');

if ($ok) {
    $_SESSION['flash_success'] = $paused
        ? 'Public sign-up is now CLOSED — new self sign-ups are turned off. Existing accounts are unaffected.'
        : 'Public sign-up is now OPEN — anyone can create an account again.';
} else {
    $_SESSION['flash_error'] = 'Could not change the sign-up setting. Has migrate_app_settings.php been run?';
}

header('Location: /master-admin/index.php');
exit;
