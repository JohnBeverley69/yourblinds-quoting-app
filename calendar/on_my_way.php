<?php
declare(strict_types=1);

/**
 * "On my way" button (My Schedule + appointment page): texts the customer
 * that the fitter is on the way, signed with the tapping user's first name.
 * Once per appointment per day. See sms_send_on_the_way().
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/sms.php';

requireLogin();

$back = '/calendar/schedule.php';
$ret  = (string) ($_POST['return'] ?? '');
if (preg_match('#^/calendar/[a-z_]+\.php(\?[A-Za-z0-9=&_-]*)?$#', $ret)) $back = $ret;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $back);
    exit;
}
csrf_check();

$user     = current_user();
$clientId = (int) $user['client_id'];
$apptId   = (int) ($_POST['appointment_id'] ?? 0);

$res = sms_send_on_the_way($clientId, $apptId, (int) $user['user_id'], trim((string) ($user['full_name'] ?? '')) ?: null);
$_SESSION[$res['ok'] ? 'flash_success' : 'flash_error'] = $res['message'];
header('Location: ' . $back);
exit;
