<?php
declare(strict_types=1);

/**
 * Price checks: named real-world lines + a fixed batch of random lines, priced
 * through qb_preview_response() — the SAME function the quote builder's live
 * price, InstaPrice and the offline tablet use — and compared to the expected
 * ("golden") figures in tests/goldens/prices.json.
 */

require_once APP_ROOT . '/_partials/pricing_engine.php';
require_once APP_ROOT . '/_partials/price_table_parser.php';
require_once APP_ROOT . '/_partials/units.php';
require_once APP_ROOT . '/_partials/multi_fascia_pricer.php';
require_once APP_ROOT . '/quote-builder/_preview_core.php';

const PRICE_GOLDENS = APP_ROOT . '/tests/goldens/prices.json';

/** Where a line is priced: [client whose catalogue, trade account quoted FOR (0 = none), may see costs]. */
function price_contexts(): array
{
    return [
        'factory'           => [3,   0,   true],    // the factory's own retail quote
        'alpha'             => [101, 0,   true],    // a trade account quoting its customer (copied catalogue)
        'alpha_no_costs'    => [101, 0,   false],   // ... as a salesperson who can't see costs
        'factory_for_alpha' => [3,   101, true],    // the factory keying an order FOR Alpha (Alpha's trade discount)
    ];
}

/** The first active fabric for a product + system (deterministic). */
function price_fabric(int $productId, int $systemId): int
{
    return (int) t_val('SELECT id FROM product_options WHERE product_id = ? AND active = 1
                         AND (system_id IS NULL OR system_id = ?) ORDER BY id LIMIT 1', [$productId, $systemId]);
}

/** Named lines, written against the FACTORY's ids (mapped to a copy's ids for other tenants). */
function price_scenarios(): array
{
    $ex = static fn (int $e, int $c, $v = null) => array_filter(['extra_id' => $e, 'choice_id' => $c, 'user_value' => $v], static fn ($x) => $x !== null);
    $roller = static fn (array $extra = [], array $over = []) => $over + [
        'product_id' => 5, 'system_id' => 17, 'option_id' => price_fabric(5, 17),
        'width' => '1200', 'drop' => '1600', 'quantity' => 1, 'round_up' => 1, 'unit' => 'mm',
        'extras' => array_merge([$ex(79, 240), $ex(80, 242)], $extra),
    ];
    $fw = static fn (array $extra = [], array $over = []) => $over + [
        'product_id' => 85, 'system_id' => 246, 'option_id' => price_fabric(85, 246),
        'width' => '1350', 'drop' => '1500', 'quantity' => 1, 'round_up' => 1, 'unit' => 'mm', 'extras' => $extra,
    ];
    return [
        'Roller, side winder, 1200 × 1600'            => $roller([$ex(81, 244)]),
        'Roller, 40mm motor, quantity 2'               => $roller([$ex(81, 248)], ['quantity' => 2]),
        'Roller, sizes typed in cm and m (120cm × 1.6m)' => $roller([$ex(81, 244)], ['width' => '120cm', 'drop' => '1.6m']),
        'Roller, exact size not in the grid (round-up off, refused)' => $roller([$ex(81, 244)], ['width' => '1234', 'drop' => '1611', 'round_up' => 0]),
        'Roller, Senses fascia oversize 1500mm'        => $roller([$ex(81, 244), $ex(185, 672), $ex(13076, 62716), ['extra_id' => 13077, 'user_value' => 1500]]),
        'Roller, 3 blinds on one Senses fascia'        => $roller([$ex(81, 244), $ex(185, 672), $ex(13076, 62717), $ex(13078, 62720)],
                                                              ['multi_fascia' => ['active' => 1, 'widths' => ['1200', '900', '1500'], 'drops' => ['1800', '', '1700']]]),
        'Roller, markup 25% and discount 10% typed in' => $roller([$ex(81, 244)], ['markup_override' => '25', 'discount_override' => '10']),
        'Roller, too wide for any price table'         => $roller([$ex(81, 244)], ['width' => '9000']),
        'Vertical Vogue, gold headrail + brackets per metre' => [
            'product_id' => 8, 'system_id' => 27, 'option_id' => price_fabric(8, 27), 'width' => '2000', 'drop' => '2100',
            'quantity' => 1, 'round_up' => 1, 'unit' => 'mm', 'extras' => [$ex(64, 190), $ex(77, 33583)]],
        'Forest Wood 50mm, 4 hold-downs + 2 brackets + wand, 1650mm pelmet' => $fw([
            ['extra_id' => fx('fw_accessories'), 'choice_ids' => [fx('fw_holddown')], 'user_value' => 4],
            ['extra_id' => fx('fw_accessories'), 'choice_ids' => [fx('fw_extra_bracket')], 'user_value' => 2],
            ['extra_id' => fx('fw_accessories'), 'choice_ids' => [fx('fw_wand')]],
            $ex(fx('fw_pelmet'), fx('fw_pelmet_custom'), 1650),
        ]),
        'Forest Wood 50mm, pelmet left at the blind width' => $fw([$ex(fx('fw_pelmet'), fx('fw_pelmet_custom'))]),
        'Forest Wood, hold-down ticked with no quantity (refused)' => $fw([['extra_id' => fx('fw_accessories'), 'choice_ids' => [fx('fw_holddown')]]]),
        'Venetian 25mm standard slat 1000 × 1200'      => ['product_id' => 35, 'system_id' => 10814, 'option_id' => price_fabric(35, 10814),
                                                           'width' => '1000', 'drop' => '1200', 'quantity' => 1, 'round_up' => 1, 'unit' => 'mm', 'extras' => []],
        'PF Shutter, 3 panels (panel split)'           => ['product_id' => 199, 'system_id' => 565, 'option_id' => price_fabric(199, 565),
                                                           'width' => '1800', 'drop' => '1500', 'quantity' => 1, 'round_up' => 1, 'unit' => 'mm',
                                                           'extras' => [$ex(1173, 5171, 1700), $ex(1175, 5175)]],
        'Infusions Expressions, tape +20% (supplier list add-on)' => ['product_id' => 86, 'system_id' => 258, 'option_id' => price_fabric(86, 258),
                                                           'width' => '1100', 'drop' => '1300', 'quantity' => 1, 'round_up' => 1, 'unit' => 'mm', 'extras' => [$ex(650, 3019)]],
        'Pleated roof, duo pull per metre'             => ['product_id' => 243, 'system_id' => 651, 'option_id' => price_fabric(243, 651),
                                                           'width' => '900', 'drop' => '1000', 'quantity' => 1, 'round_up' => 1, 'unit' => 'mm', 'extras' => [$ex(1477, 6515)]],
        'PF Roller, magnetic strip round the perimeter' => ['product_id' => 163, 'system_id' => 480, 'option_id' => price_fabric(163, 480),
                                                           'width' => '700', 'drop' => '1100', 'quantity' => 1, 'round_up' => 1, 'unit' => 'mm', 'extras' => [$ex(964, 4417)]],
        'Night & Day cassette'                         => ['product_id' => 188, 'system_id' => 544, 'option_id' => price_fabric(188, 544),
                                                           'width' => '1300', 'drop' => '1400', 'quantity' => 1, 'round_up' => 1, 'unit' => 'mm', 'extras' => []],
        'Embassy faux wood 63mm'                       => ['product_id' => 104, 'system_id' => 323, 'option_id' => price_fabric(104, 323),
                                                           'width' => '1500', 'drop' => '1600', 'quantity' => 1, 'round_up' => 1, 'unit' => 'mm', 'extras' => []],
    ];
}

/** A fixed batch of random lines over every factory product (seeded, so the same every time). */
function price_random_lines(int $n, int $seed): array
{
    mt_srand($seed);
    $pick = static fn (array $a) => $a ? $a[mt_rand(0, count($a) - 1)] : null;
    $products = array_map('intval', array_column(t_rows("SELECT id FROM products WHERE client_id = 3 AND active = 1 AND name NOT LIKE 'Bev ZZ%' ORDER BY id"), 'id'));
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        $pid = $pick($products);
        $sys = (int) $pick(array_column(t_rows('SELECT id FROM product_systems WHERE product_id = ? AND active = 1 ORDER BY id', [$pid]), 'id'));
        $fab = array_column(t_rows('SELECT id FROM product_options WHERE product_id = ? AND active = 1 AND (system_id IS NULL OR system_id = ?) ORDER BY id', [$pid, $sys]), 'id');
        $rng = t_row('SELECT MIN(r.width_mm) wmin, MAX(r.width_mm) wmax, MIN(r.drop_mm) dmin, MAX(r.drop_mm) dmax
                        FROM price_table_rows r JOIN price_tables t ON t.id = r.price_table_id WHERE t.product_id = ? AND t.active = 1', [$pid]) ?: [];
        $wmin = (int) ($rng['wmin'] ?? 300); $wmax = max($wmin, (int) ($rng['wmax'] ?? 3000));
        $dmin = (int) ($rng['dmin'] ?? 300); $dmax = max($dmin, (int) ($rng['dmax'] ?? 3000));
        $extras = [];
        foreach (t_rows('SELECT id, length_input_label FROM product_extras WHERE product_id = ? AND active = 1 ORDER BY id', [$pid]) as $e) {
            $choices = t_rows('SELECT id, system_id, length_input_label, price_per_unit FROM product_extra_choices
                                WHERE product_extra_id = ? AND active = 1 AND (system_id IS NULL OR system_id = ?) ORDER BY id', [(int) $e['id'], $sys]);
            if (!$choices) {
                if (!empty($e['length_input_label']) && mt_rand(0, 1)) $extras[] = ['extra_id' => (int) $e['id'], 'user_value' => mt_rand(10, 500)];
                continue;
            }
            if (mt_rand(1, 100) > 55) continue;
            $ch = $pick($choices);
            $row = ['extra_id' => (int) $e['id'], 'choice_id' => (int) $ch['id']];
            if (!empty($ch['length_input_label']) || (float) ($ch['price_per_unit'] ?? 0) != 0.0 || !empty($e['length_input_label'])) {
                $row['user_value'] = mt_rand(1, 10) <= 3 ? mt_rand(1, 6) : mt_rand(300, max(300, $wmax));
            }
            $extras[] = $row;
        }
        $out[] = [
            'product_id' => $pid, 'system_id' => $sys, 'option_id' => $fab ? (int) $pick($fab) : 0,
            'width' => $wmax > 0 ? (string) mt_rand($wmin, (int) ($wmax * (mt_rand(1, 15) === 1 ? 1.2 : 1))) : '',   // blank = product has no width
            'drop'  => $dmax > 0 ? (string) mt_rand($dmin, $dmax) : '',
            'quantity' => mt_rand(1, 6) === 1 ? mt_rand(2, 4) : 1, 'round_up' => mt_rand(1, 10) === 1 ? 0 : 1, 'unit' => 'mm', 'extras' => $extras,
        ];
    }
    return $out;
}

/**
 * Re-express a line written against the factory's catalogue in a copy's ids,
 * through the copy's source markers (source_product_id etc.).
 */
function price_map_to_tenant(array $q, int $clientId): array
{
    if ($clientId === 3) return $q;
    $map = static function (string $table, string $srcCol, int $id, string $scope) use ($clientId): int {
        if ($id <= 0) return $id;
        $sql = [
            'products'              => "SELECT id FROM products WHERE client_id = ? AND source_product_id = ?",
            'product_systems'       => "SELECT id FROM product_systems WHERE client_id = ? AND source_system_id = ?",
            'product_options'       => "SELECT id FROM product_options WHERE client_id = ? AND source_option_id = ?",
            'product_extras'        => "SELECT id FROM product_extras WHERE client_id = ? AND source_extra_id = ?",
            'product_extra_choices' => "SELECT c.id FROM product_extra_choices c JOIN product_extras e ON e.id = c.product_extra_id
                                         WHERE e.client_id = ? AND c.source_choice_id = ?",
        ][$table];
        $v = t_val($sql, [$clientId, $id]);
        if ($v === false && $table === 'product_options') {
            // Two factory fabrics with the same name + band (a duplicate row) are ONE
            // fabric in a copy: the push matches fabrics by name, so only one carries
            // the marker. Find the copy the same way.
            $v = t_val('SELECT c.id FROM product_options s
                          JOIN products cp ON cp.client_id = ? AND cp.source_product_id = s.product_id
                          JOIN product_options c ON c.product_id = cp.id AND c.name = s.name AND c.band_code <=> s.band_code
                         WHERE s.id = ? ORDER BY c.id LIMIT 1', [$clientId, $id]);
        }
        if ($v === false) fail("no copy of $table #$id in client $clientId ($scope) — the push lost its source marker");
        return (int) $v;
    };
    $q['product_id'] = $map('products', 'source_product_id', (int) $q['product_id'], 'product');
    $q['system_id']  = $map('product_systems', 'source_system_id', (int) $q['system_id'], 'system');
    $q['option_id']  = $map('product_options', 'source_option_id', (int) $q['option_id'], 'fabric');
    foreach ($q['extras'] as &$e) {
        $e['extra_id'] = $map('product_extras', 'source_extra_id', (int) $e['extra_id'], 'option');
        if (isset($e['choice_id']))  $e['choice_id'] = $map('product_extra_choices', 'source_choice_id', (int) $e['choice_id'], 'choice');
        if (isset($e['choice_ids'])) $e['choice_ids'] = array_map(static fn ($c) => $map('product_extra_choices', 'source_choice_id', (int) $c, 'choice'), $e['choice_ids']);
    }
    unset($e);
    return $q;
}

/** Price one line in a context. */
function price_line(string $context, array $q): array
{
    [$client, $forAccount, $canCosts] = price_contexts()[$context];
    $q = price_map_to_tenant($q, $client);
    return qb_preview_response(db(), $client, $q, $canCosts, false, $forAccount);
}

/**
 * The figures that matter, flattened: every number and yes/no in the answer,
 * plus any error message. Labels and other text are left out so re-wording a
 * snapshot doesn't count as a price change. Ids are left out too (they differ
 * between a tenant's copy and the factory's).
 */
function price_figures(array $result, string $prefix = ''): array
{
    $out = [];
    foreach ($result as $k => $v) {
        $path = $prefix === '' ? (string) $k : "$prefix.$k";
        if (is_array($v)) { $out += price_figures($v, $path); continue; }
        if ($k === 'error') { $out[$path] = (string) $v; continue; }
        if (preg_match('/(^|_)ids?$|_id$|^id$/', (string) $k)) continue;
        if (is_int($v) || is_float($v)) $out[$path] = round((float) $v, 4);
        elseif (is_bool($v)) $out[$path] = $v;
    }
    ksort($out);
    return $out;
}
