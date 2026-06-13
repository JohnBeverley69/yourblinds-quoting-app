<?php
declare(strict_types=1);

/**
 * Flip the global "pause outgoing emails" switch (testing mode). POST-only,
 * super-admin only. Body: csrf_token + email_paused (present = on, absent = off).
 *
 * When ON, mailer_send() drops every message site-wide (see mailer.php) so a
 * QA tester on the live site can't email a real supplier or customer.
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

$on = !empty($_POST['email_paused']);
$ok = app_setting_set('email_paused', $on ? '1' : '0');

if ($ok) {
    $_SESSION['flash_success'] = $on
        ? 'Testing mode ON — all outgoing emails are paused. Remember to turn this off before going live.'
        : 'Testing mode OFF — outgoing emails are sending normally again.';
} else {
    $_SESSION['flash_error'] = 'Could not change the email setting. Has migrate_app_settings.php been run?';
}

header('Location: /master-admin/index.php');
exit;
