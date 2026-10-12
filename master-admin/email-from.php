<?php
declare(strict_types=1);

/**
 * Change the platform's send-from email address (app setting mail_from, read
 * by mailer.php ahead of MAIL_FROM in .env). POST-only, super-admin only.
 *
 * AuthSMTP rejects any From address that isn't authorised in its control
 * panel, which would stop every email on the site. So the new address is
 * tried first with a test email to the signed-in super-admin, and only saved
 * if that send succeeds.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/app_settings.php';
require_once __DIR__ . '/../mailer.php';

requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /master-admin/index.php');
    exit;
}
csrf_check();

$new = strtolower(trim((string) ($_POST['mail_from'] ?? '')));
if (!filter_var($new, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = 'That isn’t a valid email address.';
    header('Location: /master-admin/index.php');
    exit;
}
if (app_setting_on('email_paused')) {
    $_SESSION['flash_error'] = 'Emails are paused (testing mode), so the new address can’t be tested. Turn the pause off first.';
    header('Location: /master-admin/index.php');
    exit;
}

// Who gets the test: the signed-in super-admin's own email.
$me = '';
try {
    $st = db()->prepare('SELECT email FROM client_users WHERE id = ? LIMIT 1');
    $st->execute([(int) (current_user()['user_id'] ?? 0)]);
    $me = trim((string) $st->fetchColumn());
} catch (Throwable $e) { /* fall through */ }
if (!filter_var($me, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = 'Your login has no email address to send the test to.';
    header('Location: /master-admin/index.php');
    exit;
}

[$ok, $note] = mailer_deliver(
    $me,
    'YourBlinds send-from test',
    "This is a test from YourBlinds.\n\nIf you're reading this, emails can now be sent from {$new}.\n",
    null, null, ['from_email' => $new, 'from_name' => 'YourBlinds']
);

if (!$ok) {
    $_SESSION['flash_error'] = 'Not changed — the test email from ' . $new . ' was refused'
        . ($note ? ' (' . $note . ')' : '') . '. Check it’s added to AuthSMTP’s authorised From addresses.';
    header('Location: /master-admin/index.php');
    exit;
}

if (app_setting_set('mail_from', $new)) {
    $_SESSION['flash_success'] = 'Emails now come from ' . $new . '. A test was sent to ' . $me
        . ' — check it arrived (and not in junk).';
} else {
    $_SESSION['flash_error'] = 'The test worked, but the setting couldn’t be saved. Has migrate_app_settings.php been run?';
}
header('Location: /master-admin/index.php');
exit;
