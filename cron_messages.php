<?php
declare(strict_types=1);

/**
 * Scheduled customer messages: runs every ~15 minutes.
 *
 * Sends, 10am–8pm UK time:
 *   - day-before appointment reminders (sms_run_day_before(), _partials/sms.php)
 *   - quote follow-ups (qfu_run(), _partials/quote_follow_up.php)
 * and deletes Messages-log entries over 12 months old (sms_purge_old()).
 *
 * RUN IT — one of:
 *   CLI (the Cloudways cron; no auth needed, nothing served):
 *       php /path/to/public_html/cron_messages.php
 *   Web, super-admin only, for a manual run or to check it works:
 *       /cron_messages.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_partials/sms.php';
require_once __DIR__ . '/_partials/quote_follow_up.php';
// Also needed from the CLI: the email cap (send_quota.php) uses is_factory_client().
require_once __DIR__ . '/auth/middleware.php';

if (PHP_SAPI !== 'cli') {
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

// QuickBooks: send paid invoices + payments (and retry any that failed). Does
// nothing unless switched on in Master Admin → QuickBooks.
try {
    require_once __DIR__ . '/_partials/qbo_push.php';
    $qf = factory_client_id();
    if ($qf > 0 && qbo_push_settings($qf)['on']) {
        $q = qbo_push_run($qf);
        echo 'QuickBooks: ' . $q['message'] . "\n";
    }
} catch (Throwable $e) {
    echo 'QuickBooks: ' . $e->getMessage() . "\n";
}

if (!sms_ready()) {
    echo "Texting tables not migrated yet; nothing to do.\n";
    exit;
}

$r = sms_run_day_before($now);
echo "Day-before reminders: {$r['sent']} sent, {$r['blocked']} not sent (see Master Admin → Texts), {$r['skipped']} skipped (no mobile / switched off).\n";
$f = qfu_run($now);
echo "Quote follow-ups: {$f['texts']} texts and {$f['emails']} emails sent, {$f['not_sent']} not sent (see Messages).\n";
if ((int) $now->format('G') < 10 || (int) $now->format('G') >= 20) {
    echo "(Reminders and follow-ups only go out between 10am and 8pm.)\n";
}

// Messages log retention: 12 months (privacy policy).
$purged = sms_purge_old();
if ($purged > 0) echo "Messages log: {$purged} entries over 12 months old deleted.\n";
