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

// Order checks: once a day from 7am, email the super-admins the orders whose
// records disagree (_partials/order_checks.php) — only when there are any; a
// quiet day sends nothing. The count is kept for the Dashboard's line.
try {
    require_once __DIR__ . '/_partials/app_settings.php';
    $ocFactory = factory_client_id();
    $ocToday   = $now->format('Y-m-d');
    if ($ocFactory > 0 && (int) $now->format('G') >= 7 && app_setting_get('order_checks_run_on', '') !== $ocToday) {
        require_once __DIR__ . '/_partials/order_checks.php';
        $ocFind = oc_findings(db(), $ocFactory);
        app_setting_set('order_checks_run_on', $ocToday);
        app_setting_set('order_checks_last', json_encode(['at' => $now->format('Y-m-d H:i'), 'count' => count($ocFind)]));
        if ($ocFind) {
            require_once __DIR__ . '/mailer.php';
            $ocTo = db()->query("SELECT email FROM client_users WHERE is_super_admin = 1 AND active = 1 AND email LIKE '%@%'")
                        ->fetchAll(PDO::FETCH_COLUMN);
            $ocUrl = rtrim((string) (env('APP_URL', 'https://yourblinds.uk') ?? ''), '/') . '/factory/checks.php';
            $ocSent = 0;
            foreach (array_unique($ocTo) as $ocAddr) {
                if (mailer_send($ocAddr, 'Order checks: ' . count($ocFind) . ' to look at', oc_summary_text($ocFind, $ocUrl))) $ocSent++;
            }
            echo 'Order checks: ' . count($ocFind) . " to look at; emailed {$ocSent}.\n";
        } else {
            echo "Order checks: everything adds up.\n";
        }
    }
} catch (Throwable $e) {
    echo 'Order checks: ' . $e->getMessage() . "\n";
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
