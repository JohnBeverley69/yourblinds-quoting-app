<?php
declare(strict_types=1);

/**
 * Scheduled customer messages: runs every ~15 minutes.
 *
 * Today it sends the day-before appointment reminders (10am–8pm UK time, see
 * sms_run_day_before() in _partials/sms.php). Further timed messages (e.g. a
 * quote follow-up) belong here too.
 *
 * RUN IT — one of:
 *   CLI (the Cloudways cron; no auth needed, nothing served):
 *       php /path/to/public_html/cron_messages.php
 *   Web, super-admin only, for a manual run or to check it works:
 *       /cron_messages.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_partials/sms.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
}

@set_time_limit(300);

// One run at a time: a slow provider must never let two overlapping runs
// both send the same reminder.
$lock = @fopen(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yb_cron_messages.lock', 'c');
if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "Another run is still going; skipped.\n";
    exit;
}

$now = new DateTimeImmutable('now');
echo 'cron_messages ' . $now->format('Y-m-d H:i T') . "\n";

if (!sms_ready()) {
    echo "Texting tables not migrated yet; nothing to do.\n";
    exit;
}

$r = sms_run_day_before($now);
echo "Day-before reminders: {$r['sent']} sent, {$r['blocked']} not sent (see Master Admin → Texts), {$r['skipped']} skipped (no mobile / switched off).\n";
if ((int) $now->format('G') < 10 || (int) $now->format('G') >= 20) {
    echo "(Reminders only go out between 10am and 8pm.)\n";
}
