<?php
declare(strict_types=1);

/**
 * HELP AUDIENCES (John, 2026-10-13): factory guides only on factory-enabled
 * accounts. Factory-side help (any factory, white-label included) and the main
 * factory's Console help are each shown only to the people who have those
 * screens — never to an ordinary shop. The help bot follows the same rule.
 */

require_once APP_ROOT . '/_partials/support_ai.php';

$guide = static fn (YbHttp $h, string $slug): int => $h->get('/help/guide.php?g=' . $slug)->status;

test('an ordinary shop sees no factory help at all', function () use ($guide) {
    $a = as_user('alpha.admin');
    $idx = $a->get('/help/index.php');
    check_page_ok($idx, 'Help as a shop admin');
    foreach (['Shop or Factory', 'Where has the Trade menu gone', 'Factory office or floor login', 'raise a delivery note', 'console-pipeline'] as $w) {
        check_not_contains($w, $idx->body, 'factory help on a shop\'s Help page');
    }
    foreach (['console-pipeline', 'console-orders', 'qbo-match-customers'] as $s) {
        check_same(403, $guide($a, $s), "shop admin opening guide $s");
    }
});

test('a white-label factory sees the factory side\'s help, not the main factory\'s Console help', function () use ($guide) {
    $c = as_user('charlie.office');
    $idx = $c->get('/help/index.php');
    check_contains('Shop or Factory', $idx->body, 'factory topic for a white-label factory');
    check_contains('console-pipeline', $idx->body, 'Pipeline guide listed');
    check_not_contains('raise a delivery note', $idx->body, 'Console accounts help for a white-label factory');
    check_same(200, $guide($c, 'console-pipeline'), 'white-label factory opening the Pipeline guide');
    foreach (['console-orders', 'console-remakes', 'qbo-match-customers'] as $s) {
        check_same(403, $guide($c, $s), "white-label factory opening Console guide $s");
    }
});

test('the main factory\'s office sees both; its floor login sees the factory side only', function () use ($guide) {
    $o = as_user('factory.office');
    check_same(200, $guide($o, 'console-orders'), 'office opening the Console Orders guide');
    check_contains('Where has the Trade menu gone', $o->get('/help/index.php')->body, 'Console topic for the office');
    $fl = as_user('factory.floor');
    $idx = $fl->get('/help/index.php');
    check_contains('Shop or Factory', $idx->body, 'factory topic for a floor login');
    check_not_contains('Where has the Trade menu gone', $idx->body, 'Console topic for a floor login');
});

test('the help bot only knows factory topics for the factory side', function () {
    check(strpos(support_ai_knowledge('admin'), 'Shop or Factory') === false, 'a shop admin\'s help bot knows factory topics');
    check(strpos(support_ai_knowledge('admin+factory'), 'Shop or Factory') !== false, 'a factory admin\'s help bot is missing factory topics');
    check(strpos(support_ai_knowledge('admin+factory'), 'raise a delivery note') === false, 'a white-label factory\'s help bot knows Console accounts topics');
    check(strpos(support_ai_knowledge('super'), 'raise a delivery note') !== false, 'the super-admin\'s help bot is missing Console topics');
});
