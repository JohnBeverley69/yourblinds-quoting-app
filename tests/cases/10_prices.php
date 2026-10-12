<?php
declare(strict_types=1);

/**
 * PRICES. Every line in tests/goldens/prices.json is priced again and must give
 * exactly the figures recorded there. A change in ANY price fails.
 *
 * If a price change is intended (a new rule, a corrected grid), re-capture with
 *     php tests/run.php --update-goldens
 * and check the diff of tests/goldens/prices.json line by line before committing.
 */

require_once APP_ROOT . '/tests/lib/prices.php';

if (getenv('YB_UPDATE_GOLDENS') === '1') {
    test('re-capture the expected prices (--update-goldens)', function () {
        $cases = [];
        foreach (price_scenarios() as $name => $q) {
            foreach (array_keys(price_contexts()) as $ctx) {
                $cases[] = ['name' => $name, 'context' => $ctx, 'input' => $q, 'expect' => price_figures(price_line($ctx, $q))];
            }
        }
        foreach (price_random_lines(240, 20261012) as $i => $q) {
            $ctx = array_keys(price_contexts())[$i % 4];
            $cases[] = ['name' => sprintf('Random line %03d', $i + 1), 'context' => $ctx, 'input' => $q,
                        'expect' => price_figures(price_line($ctx, $q))];
        }
        @mkdir(dirname(PRICE_GOLDENS), 0777, true);
        file_put_contents(PRICE_GOLDENS, json_encode(['note' => 'Expected prices — written by php tests/run.php --update-goldens. Review every change.',
            'cases' => $cases], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) . "\n");
        $priced = count(array_filter($cases, static fn ($c) => !isset($c['expect']['error'])));
        echo count($cases) . " lines captured, $priced priced\n";
    });
    return;
}

$golden = json_decode((string) @file_get_contents(PRICE_GOLDENS), true);

test('the expected-prices file is there', function () use ($golden) {
    check(is_array($golden) && !empty($golden['cases']), 'tests/goldens/prices.json is missing or empty — run php tests/run.php --update-goldens');
});

$compare = static function (array $c): void {
    $got = price_figures(price_line($c['context'], $c['input']));
    $diff = [];
    foreach (array_unique(array_merge(array_keys($c['expect']), array_keys($got))) as $k) {
        $e = $c['expect'][$k] ?? '(none)';
        $g = $got[$k] ?? '(none)';
        $same = (is_float($e) || is_int($e)) && (is_float($g) || is_int($g)) ? abs($e - $g) < 0.0001 : $e === $g;
        if (!$same) $diff[] = "$k: was " . t_show($e) . ', now ' . t_show($g);
    }
    if ($diff) fail("price changed ({$c['context']}):\n    " . implode("\n    ", array_slice($diff, 0, 8))
        . (count($diff) > 8 ? "\n    … and " . (count($diff) - 8) . ' more' : ''));
};

// Named lines: one test each, so a failure says which real-world line moved.
$named = [];
foreach ($golden['cases'] ?? [] as $c) {
    if (strpos($c['name'], 'Random line') === 0) continue;
    $named[$c['name']][] = $c;
}
foreach ($named as $name => $cs) {
    test("price: $name", function () use ($cs, $compare) {
        foreach ($cs as $c) $compare($c);
    });
}

test('price: 240 random lines across every product and option', function () use ($golden, $compare) {
    $bad = [];
    foreach ($golden['cases'] ?? [] as $c) {
        if (strpos($c['name'], 'Random line') !== 0) continue;
        try { $compare($c); } catch (TestFailure $e) { $bad[] = $c['name'] . ' — ' . $e->getMessage(); }
    }
    if ($bad) fail(count($bad) . " random lines changed price:\n" . implode("\n", array_slice($bad, 0, 5)));
});

test('most lines actually price (the expected-prices file is not mostly errors)', function () use ($golden) {
    $n = count($golden['cases'] ?? []);
    $ok = count(array_filter($golden['cases'] ?? [], static fn ($c) => !isset($c['expect']['error'])));
    check($n > 0 && $ok / $n > 0.5, "only $ok of $n expected lines are priced — the fixtures or generator have gone wrong");
});

test('a trade account pays less than the factory\'s retail price (trade discount applied)', function () {
    $q = price_scenarios()['Roller, side winder, 1200 × 1600'];
    $retail = price_line('factory', $q);
    $trade  = price_line('factory_for_alpha', $q);
    check(!isset($retail['error']) && !isset($trade['error']), 'roller line did not price');
    check((float) ($trade['trade_discount_percent'] ?? 0) > 0, 'Alpha\'s 10% roller discount was not applied: ' . t_show($trade['trade_discount_percent'] ?? null));
});

test('a salesperson without "view costs" never receives cost or trade figures', function () {
    foreach (price_scenarios() as $name => $q) {
        $r = price_line('alpha_no_costs', $q);
        foreach (['cost_price_per_blind', 'extras_cost_total', 'trade_price_per_blind', 'trade_discount_percent', 'trade_discount_amount'] as $k) {
            check(!array_key_exists($k, $r), "$name: '$k' leaked to a non-cost user");
        }
        foreach ($r['extras_applied'] ?? [] as $ex) {
            check(!array_key_exists('cost_snapshot', (array) $ex), "$name: option cost leaked to a non-cost user");
        }
    }
});
