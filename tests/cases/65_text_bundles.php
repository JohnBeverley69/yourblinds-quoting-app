<?php
declare(strict_types=1);

/**
 * TEXT BUNDLES (John, 2026-10-13). Texts are never free: the monthly number is
 * an allowance, and past it a company texts from prepaid bundles. Only the
 * main factory (ours) has texting without a plan. PayPal itself is never
 * called here — no keys exist in a test run.
 */

require_once APP_ROOT . '/_partials/sms.php';

$alpha = 101;
$sendRow = static function (int $client, int $segments, int $fromBundle = 0): void {
    db()->prepare("INSERT INTO messages (client_id, channel, direction, kind, to_addr, from_addr, body, segments, from_bundle, status, provider)
                   VALUES (?, 'sms', 'out', 'test', '+447700900000', 'TEST', 'x', ?, ?, 'sent', 'smsworks')")
        ->execute([$client, $segments, $fromBundle]);
};

test('only the main factory texts without a plan — a white-label factory needs one', function () {
    check(sms_feature_enabled(3), 'the main factory lost texting');
    check(!sms_feature_enabled(103), 'the white-label factory texts for free');
});

test('the allowance first, then the bundle, then stop', function () use ($alpha, $sendRow) {
    // Alpha on Silver (a comp), so the daily plan check keeps its texting on.
    db()->prepare("INSERT INTO client_plan_overrides (client_id, plan_code, override_type, notes) VALUES (?, 'silver', 'comp', 'test')")
        ->execute([$alpha]);
    db()->prepare('UPDATE client_settings SET feature_sms = 1, sms_monthly_limit = 2, sms_sender_name = ? WHERE client_id = ?')
        ->execute(['AlphaBlind', $alpha]);
    sms_settings($alpha, true);   // refresh the cached settings
    $sendRow($alpha, 2);           // the month's 2 texts, used
    $c = sms_allowance_check($alpha, 1);
    check(!$c['ok'], 'a text went out with the allowance used and no bundle');
    check_contains('Buy more texts', (string) $c['block'], 'the reason given');

    db()->prepare("INSERT INTO sms_bundles (client_id, texts, price, status, paid_at) VALUES (?, 5, 2.40, 'paid', NOW())")->execute([$alpha]);
    db()->prepare("INSERT INTO sms_bundles (client_id, texts, price, status) VALUES (?, 500, 20.00, 'pending')")->execute([$alpha]);
    check_same(5, sms_bundle_balance($alpha), 'bundle balance (a pending, unpaid bundle doesn\'t count)');
    $c = sms_allowance_check($alpha, 1);
    check($c['ok'] && $c['from_bundle'], 'past the allowance, the text should come from the bundle');

    $sendRow($alpha, 3, 1);
    check_same(2, sms_bundle_balance($alpha), 'bundle balance after 3 bundle texts');
    check_same(2, sms_used_this_month($alpha), 'allowance used (bundle texts don\'t count against it)');
    check(!sms_allowance_check($alpha, 3)['ok'], 'a 3-part text went out with only 2 bundle texts left');
});

test('a blocked send is logged with the reason', function () use ($alpha) {
    $r = sms_send($alpha, '07700 900123', str_repeat('A long text. ', 40), ['kind' => 'test']);   // 4 segments > 2 left
    check_same('blocked', $r['status'], 'send status');
    check_contains('used up', $r['message'], 'block reason');
});

test('Buy more texts: admins only; no bundles on sale until the super-admin sets them', function () {
    check_refused(as_user('alpha.sales')->get('/billing/texts.php'), 'Buy more texts for a salesperson');
    $a = as_user('alpha.admin');
    $p = $a->get('/billing/texts.php');
    check_page_ok($p, 'Buy more texts');
    check_contains('aren&rsquo;t on sale yet', $p->body, 'no packs yet');
});

test('the super-admin sets the packs; the page offers them; a made-up pack is refused', function () {
    $r = t_submit(as_user('super'), '/master-admin/texts.php', ['_action' => 'bundles', 'packs' => "500 = 18.00\n250 = £10\nrubbish\n0 = 5"]);
    check(!$r->phpErrors, 'save packs: PHP problems');
    check_same([['texts' => 250, 'price' => '10.00'], ['texts' => 500, 'price' => '18.00']], sms_bundle_packs(), 'packs saved');
    $a = as_user('alpha.admin');
    check_contains('250 texts', $a->get('/billing/texts.php')->body, 'the 250 pack on sale');
    $before = (int) t_val('SELECT COUNT(*) FROM sms_bundles');
    t_submit($a, '/billing/texts.php', ['_action' => 'buy', 'texts' => 999]);
    t_submit($a, '/billing/texts.php', ['_action' => 'buy', 'texts' => 250]);   // real pack, but PayPal isn't set up in tests
    check_same($before, (int) t_val('SELECT COUNT(*) FROM sms_bundles'), 'bundle rows created without a PayPal order');
});

test('the super-admin can add free texts to a client', function () use ($alpha) {
    $before = sms_bundle_balance($alpha);
    t_submit(as_user('super'), '/master-admin/texts.php', ['_action' => 'limits', 'limit' => [$alpha => '2'], 'grant' => [$alpha => '10']]);
    check_same($before + 10, sms_bundle_balance($alpha), 'balance after Add texts');
    check_same('added by YourBlinds', (string) t_val("SELECT note FROM sms_bundles WHERE client_id = ? ORDER BY id DESC LIMIT 1", [$alpha]), 'note');
});
