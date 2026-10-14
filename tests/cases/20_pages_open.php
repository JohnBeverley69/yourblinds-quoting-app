<?php
declare(strict_types=1);

/**
 * PAGES OPEN. The key screens open for the people who use them with no PHP
 * errors or warnings, and turn away the people who shouldn't see them.
 * Every menu link each person has is also opened once.
 */

$state = [];

test('a trade account can start a quote and add a blind (sets up the pages below)', function () use (&$state) {
    $h = as_user('alpha.admin');
    $qid = flow_new_quote($h, fx('customer_alpha'), 'Pages test');
    $line = flow_add_line($h, $qid, 101, price_scenarios()['Roller, side winder, 1200 × 1600']);
    check((float) $line['line_total'] > 0, 'the blind saved with no price');
    $state['quote'] = $qid;
    $state['product'] = (int) $line['product_id'];
});

/** [path, people it must open for, people it must refuse] */
$pages = static function (array $s): array {
    $q = $s['quote'] ?? 0; $p = $s['product'] ?? 0; $c = fx('customer_alpha');
    return [
        ["/quote-builder/edit.php?id=$q",       ['alpha.admin', 'alpha.sales'],               ['bravo.admin', 'alpha.fitter', 'charlie.office']],
        ['/quote-builder/new.php',              ['alpha.admin', 'alpha.sales'],               []],
        ['/orders/index.php',                   ['alpha.admin', 'bravo.admin'],               []],
        ['/dashboard/index.php',                ['alpha.admin', 'bravo.admin'],               []],
        ['/customer-manager/index.php',         ['alpha.admin', 'alpha.sales'],               []],
        ["/customer-manager/edit.php?id=$c",    ['alpha.admin'],                              ['bravo.admin', 'charlie.office']],
        ['/admin/settings.php',                 ['alpha.admin', 'bravo.admin'],               ['alpha.sales', 'alpha.fitter']],
        ['/admin/products/index.php',           ['alpha.admin'],                              ['alpha.sales']],
        ["/admin/products/edit.php?id=$p",      ['alpha.admin'],                              ['bravo.admin', 'alpha.sales']],
        ['/calendar/index.php',                 ['alpha.admin', 'alpha.fitter'],              []],
        ['/help/index.php',                     ['alpha.admin', 'alpha.fitter', 'factory.office'], []],
        ['/factory/pipeline.php',               ['factory.office', 'super', 'charlie.office'], ['factory.floor', 'alpha.admin']],
        ['/factory/incoming-orders.php',        ['factory.office', 'factory.floor', 'super'], ['alpha.admin', 'bravo.admin']],
        ['/factory/orders.php',                 ['factory.office', 'super'],                  ['factory.floor', 'alpha.admin']],
        ['/factory/settings.php',               ['factory.office', 'super', 'charlie.office'], ['factory.floor', 'alpha.admin']],
        ['/factory/dashboard.php',              ['super'],                                    ['alpha.admin', 'factory.floor']],
        ['/master-admin/wholesale.php',         ['factory.office', 'super'],                  ['charlie.office', 'factory.floor', 'alpha.admin']],
        ['/master-admin/dispatch.php',          ['factory.office', 'super'],                  ['charlie.office', 'alpha.admin']],
        ['/master-admin/statement-run.php',     ['factory.office', 'super'],                  ['charlie.office', 'alpha.admin']],
        ['/master-admin/index.php',             ['super'],                                    ['factory.office', 'alpha.admin']],
    ];
};

test('key pages open for the right people with no PHP errors', function () use (&$state, $pages) {
    if (empty($state['quote'])) skip('set-up failed');
    $people = []; $problems = [];
    foreach ($pages($state) as [$path, $ok, $no]) {
        foreach ($ok as $who) {
            $people[$who] ??= as_user($who);
            $r = $people[$who]->get($path);
            // A console user is sent on to the console version of some pages — that's opening it.
            if ($r->status === 302 && $r->location && strpos($r->location, '/auth/login.php') === false && !$r->phpErrors) {
                $r = $people[$who]->get(parse_url($r->location, PHP_URL_PATH) . (($qs = parse_url($r->location, PHP_URL_QUERY)) ? "?$qs" : ''));
            }
            try { check_page_ok($r, "$path as $who"); } catch (TestFailure $e) { $problems[] = $e->getMessage(); }
        }
    }
    if ($problems) fail(implode("\n", $problems));
});

test('the same pages refuse the wrong people', function () use (&$state, $pages) {
    if (empty($state['quote'])) skip('set-up failed');
    $people = []; $problems = [];
    foreach ($pages($state) as [$path, $ok, $no]) {
        foreach ($no as $who) {
            $people[$who] ??= as_user($who);
            try { check_refused($people[$who]->get($path), "$path as $who"); } catch (TestFailure $e) { $problems[] = $e->getMessage(); }
        }
    }
    if ($problems) fail(implode("\n", $problems));
});

test('a signed-out visitor is sent to the login page', function () use (&$state) {
    $anon = new YbHttp();
    foreach (['/dashboard/index.php', '/quote-builder/edit.php?id=' . ($state['quote'] ?? 1), '/factory/pipeline.php', '/master-admin/wholesale.php'] as $path) {
        check_refused($anon->get($path), "$path signed out");
    }
});

test('every help guide opens (or is politely hidden) without PHP errors', function () {
    $people = ['alpha.admin' => as_user('alpha.admin'), 'super' => as_user('super')];
    $hidden = ['quote-new-order'];   // kept on file, shown to nobody (help/_guides.php $HIDDEN)
    $problems = [];
    foreach (glob(APP_ROOT . '/help/guides/*.php') as $f) {
        $slug = basename($f, '.php');
        foreach ($people as $who => $h) {
            $r = $h->get('/help/guide.php?g=' . urlencode($slug));
            if (in_array($slug, $hidden, true)) {
                if ($r->status !== 404) $problems[] = "guide $slug as $who: should be hidden from everyone, got HTTP {$r->status}";
                if (strpos($h->get('/help/index.php')->body, 'g=' . $slug) !== false) $problems[] = "guide $slug is listed on Help for $who";
                continue;
            }
            if ($r->phpErrors || !in_array($r->status, [200, 403], true)) {
                $problems[] = "guide $slug as $who: HTTP {$r->status} " . implode(' | ', array_slice($r->phpErrors, 0, 2));
            }
        }
    }
    if ($problems) fail(implode("\n", $problems));
});

test('every menu link opens without a PHP error, for each kind of person', function () {
    $landing = ['alpha.admin' => '/dashboard/index.php', 'alpha.sales' => '/calendar/index.php',
                'factory.office' => '/factory/pipeline.php', 'factory.floor' => '/factory/incoming-orders.php',
                'charlie.office' => '/factory/pipeline.php', 'super' => '/factory/dashboard.php'];
    $problems = []; $opened = 0;
    foreach ($landing as $who => $start) {
        $h = as_user($who);
        $page = $h->get($start);
        check_page_ok($page, "$start as $who");
        // Links in the sidebar navigation.
        $nav = preg_match('/<nav[^>]*sidebar.*?<\/nav>|<aside[^>]*sidebar.*?<\/aside>/s', $page->body, $m) ? $m[0] : $page->body;
        preg_match_all('/href="(\/[^"#?]*\.php(?:\?[^"#]*)?)"/', $nav, $mm);
        foreach (array_unique($mm[1]) as $link) {
            $link = html_entity_decode($link);
            if (preg_match('#/(logout|auth/|setup/|tools/|offline/proof|pdf-generator/|.*export|.*download|.*backup)#i', $link)) continue;
            $r = $h->get($link);
            $opened++;
            if ($r->phpErrors || $r->status >= 500) {
                $problems[] = "$link as $who: HTTP {$r->status} " . implode(' | ', array_slice($r->phpErrors, 0, 2));
            }
        }
    }
    check($opened > 20, "only $opened menu links found — the menu scrape is broken");
    if ($problems) fail(implode("\n", $problems));
});
