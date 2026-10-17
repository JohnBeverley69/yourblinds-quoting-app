<?php
declare(strict_types=1);

/**
 * HOW LONG A QUOTE LASTS (John, 2026-10-17). Settings → Quoting → Quote
 * defaults: "Quotes can be accepted for N days". Was a fixed 30 for everyone.
 */

require_once APP_ROOT . '/_partials/quote_expiry.php';

test('quote validity: Settings shows the box, and a company\'s own days decide when a quote expires', function () {
    $page = as_user('alpha.admin')->get('/admin/settings.php?tab=quoting');
    check_page_ok($page, 'Settings as a company admin');
    check(str_contains($page->body, 'name="quote_valid_days"'), 'the "Quotes can be accepted for … days" box is on Settings');

    $before = t_val('SELECT quote_valid_days FROM client_settings WHERE client_id = 101');
    db()->exec('UPDATE client_settings SET quote_valid_days = 7 WHERE client_id = 101');
    try {
        check_same(7, quote_valid_days(101), 'Alpha\'s own setting');
        $sent = static fn (int $daysAgo) => ['client_id' => 101, 'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s', time() - $daysAgo * 86400 - 60)];
        check(!quote_is_expired($sent(6)), 'a quote sent 6 days ago can still be accepted (7-day setting)');
        check(quote_is_expired($sent(8)), 'a quote sent 8 days ago has expired (7-day setting)');
        check_same(30, quote_valid_days(999999), 'a company with no setting keeps 30 days');
    } finally {
        db()->prepare('UPDATE client_settings SET quote_valid_days = ? WHERE client_id = 101')->execute([(int) ($before ?: 30)]);
    }
});
