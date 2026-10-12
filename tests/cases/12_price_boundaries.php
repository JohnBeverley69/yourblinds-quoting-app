<?php
declare(strict_types=1);

/**
 * PRICE EDGES. The awkward sizes and sums, on two small made-up products
 * (fixtures/15_catalogue_additions.php) so the expected figures can be worked
 * out by hand and written here:
 *
 *   "Bev ZZ Grid Test" — 3×3 grid, widths/drops 1000·1500·2000 mm:
 *                 drop 1000   1500    2000
 *     width 1000:     20.10   25.30   30.55
 *     width 1500:     27.45   (hole)  41.20
 *     width 2000:     34.95   44.15   (too big)
 *     + £4.99 line charge once per line; "Panels" splits the width; a 12.5% surcharge.
 *
 *   "Bev ZZ Sqm Test" — £41.67 per m², minimum 1.5 m² billed (per panel when split).
 */

require_once APP_ROOT . '/tests/lib/prices.php';

$grid = static fn (string $w, string $d, array $extra = [], array $over = []) => qb_preview_response(db(), 3, $over + [
    'product_id' => fx('grid_product'), 'system_id' => fx('grid_system'), 'option_id' => 0,
    'width' => $w, 'drop' => $d, 'quantity' => 1, 'round_up' => 1, 'unit' => 'mm', 'extras' => $extra,
], true, false, 0);
$sqm = static fn (string $w, string $d, array $extra = []) => qb_preview_response(db(), 3, [
    'product_id' => fx('sqm_product'), 'system_id' => fx('sqm_system'), 'option_id' => 0,
    'width' => $w, 'drop' => $d, 'quantity' => 1, 'round_up' => 1, 'unit' => 'mm', 'extras' => $extra,
], true, false, 0);
$base = static function (array $r, string $what): float {
    if (isset($r['error'])) fail("$what: no price — {$r['error']}");
    return (float) $r['base_price'];
};

test('an exact grid size uses that cell (round-up on or off)', function () use ($grid, $base) {
    check_money(20.10, $base($grid('1000', '1000'), '1000×1000'), '1000×1000');
    check_money(20.10, $base($grid('1000', '1000', [], ['round_up' => 0]), '1000×1000 exact'), '1000×1000 with round-up off');
    check_money(27.45, $base($grid('1500', '1000'), '1500×1000'), '1500×1000');
});

test('1 mm over a width or a drop goes to the next column or row', function () use ($grid, $base) {
    check_money(27.45, $base($grid('1001', '1000'), '1001×1000'), '1001 wide → the 1500 column');
    check_money(25.30, $base($grid('1000', '1001'), '1000×1001'), '1001 drop → the 1500 row');
    check(isset($grid('1001', '1000', [], ['round_up' => 0])['error']), '1001×1000 with round-up off must be refused (no such cell)');
});

test('the largest size prices; 1 mm beyond it is a clear "no price"', function () use ($grid, $base) {
    check_money(44.15, $base($grid('2000', '1500'), '2000×1500'), 'largest width');
    check_money(41.20, $base($grid('1500', '2000'), '1500×2000'), 'largest drop');
    foreach ([['2001', '1000'], ['1000', '2001']] as [$w, $d]) {
        $r = $grid($w, $d);
        check(isset($r['error']) && stripos($r['error'], 'outside this price table') !== false, "{$w}×{$d}: expected 'outside this price table', got " . t_show($r['error'] ?? $r['line_total'] ?? null));
    }
});

test('a size in the "too big to make" corner has no price', function () use ($grid) {
    $r = $grid('1900', '1900');
    check(isset($r['error']), '1900×1900 (only the missing 2000×2000 cell would fit) must not price');
});

test('a hole in the MIDDLE of a grid: today it takes the narrower, longer cell (pinned)', function () use ($grid, $base) {
    // 1400×1400 would be the 1500×1500 cell, which is missing. The lookup takes the
    // smallest width that fits, then the smallest drop: 1500×2000 = £41.20 — not 2000×1500.
    // If this changes, it's a pricing change: tell John.
    check_money(41.20, $base($grid('1400', '1400'), '1400×1400'), '1400×1400 over a missing middle cell');
});

test('the same grid cell can\'t be stored twice', function () {
    $t = (int) t_val('SELECT id FROM price_tables WHERE product_id = ?', [fx('grid_product')]);
    try {
        db()->prepare('INSERT INTO price_table_rows (price_table_id, width_mm, drop_mm, price) VALUES (?, 1000, 1000, 99)')->execute([$t]);
        db()->prepare('DELETE FROM price_table_rows WHERE price_table_id = ? AND price = 99')->execute([$t]);
        fail('a second 1000×1000 cell was accepted — the grid would price unpredictably');
    } catch (PDOException $e) {
        check_same('23000', (string) $e->getCode(), 'error refusing the duplicate cell');
    }
});

test('panels: 1000 vs 1001 mm split in two (500 vs 501 per panel) price from the same column', function () use ($grid, $base) {
    $two = [['extra_id' => fx('grid_panels'), 'choice_id' => fx('grid_2_panels')]];
    check_money(2 * 20.10, $base($grid('1000', '1000', $two), '1000 in 2 panels'), '1000 wide in 2 panels = 2 × the 1000 column');
    check_money(2 * 20.10, $base($grid('1001', '1000', $two), '1001 in 2 panels'), '1001 wide in 2 panels (501 each) = 2 × the 1000 column');
    check_money(2 * 27.45, $base($grid('2001', '1000', $two), '2001 in 2 panels'), '2001 wide in 2 panels (1001 each) = 2 × the 1500 column');
    // Split, a width beyond the grid prices; unsplit it doesn't.
    check(isset($grid('2500', '1000')['error']), '2500 unsplit must not price');
    check_money(2 * 27.45, $base($grid('2500', '1000', $two), '2500 in 2 panels'), '2500 wide in 2 panels (1250 each)');
});

test('£/m²: the minimum area applies just below, at and above it', function () use ($sqm, $base) {
    check_money(round(41.67 * 1.5, 2), $base($sqm('1000', '1000'), '1 m²'), '1.0 m² is billed as 1.5 m²');
    check_money(round(41.67 * 1.5, 2), $base($sqm('1500', '1000'), '1.5 m²'), 'exactly 1.5 m²');
    check_money(round(41.67 * 1.5, 2), $base($sqm('1499.9', '1000'), '1.4999 m²'), 'just under 1.5 m² is billed as 1.5 m²');
    check_money(round(41.67 * 1.5, 2), $base($sqm('1000', '1500'), '1×1.5'), '1 × 1.5 m');
    check_money(round(41.67 * 1.6, 2), $base($sqm('1600', '1000'), '1.6 m²'), '1.6 m² is billed as 1.6 m²');
});

test('£/m² split into panels: the minimum area applies to EACH panel', function () use ($sqm, $base) {
    $two = [['extra_id' => fx('sqm_panels'), 'choice_id' => fx('sqm_2_panels')]];
    check_money(round(2 * 41.67 * 1.5, 2), $base($sqm('2000', '1000', $two), '2 × 1 m²'), '2 panels of 1 m² each billed as 2 × 1.5 m²');
    check_money(round(2 * 41.67 * 1.6, 2), $base($sqm('3200', '1000', $two), '2 × 1.6 m²'), '2 panels of 1.6 m² each');
});

test('money rounding: a 12.5% surcharge, markup, discount, quantity 3 and the line charge', function () use ($grid) {
    // Base £27.45; 12.5% surcharge = £3.43125. 33.3% markup and 10% discount typed in.
    $r = $grid('1500', '1000', [['extra_id' => fx('grid_surcharge_extra'), 'choice_id' => fx('grid_surcharge')]],
        ['quantity' => 3, 'markup_override' => '33.3', 'discount_override' => '10']);
    check(!isset($r['error']), 'did not price: ' . ($r['error'] ?? ''));
    check_money(27.45, $r['base_price'], 'base');
    // The unit sell price is rounded to the penny FIRST, then × quantity, then the line charge once.
    $unit = (float) $r['sell_price'];
    check(abs($unit - round($unit, 2)) < 0.00001, "unit sell price $unit is not whole pennies");
    check_money(round($unit * 3 + 4.99, 2), $r['line_total'], 'line total = unit × 3 + £4.99 once');
    check_money(4.99, $r['line_charge'], 'line charge');
    // Pinned figure (worked from the engine today; a change here is a price change).
    $g = json_decode((string) @file_get_contents(APP_ROOT . '/tests/goldens/price_edges.json'), true) ?: [];
    if (getenv('YB_UPDATE_GOLDENS') === '1') {
        file_put_contents(APP_ROOT . '/tests/goldens/price_edges.json', json_encode(['rounding_line' => price_figures($r)], JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . "\n");
        return;
    }
    check(isset($g['rounding_line']), 'tests/goldens/price_edges.json missing — run --update-goldens');
    check_same(t_show($g['rounding_line']), t_show(price_figures($r)), 'rounding line figures');
});

test('the price grid screen warns about a hole in the middle, not about the "too big" corner', function () {
    $t = (int) t_val('SELECT id FROM price_tables WHERE product_id = ?', [fx('grid_product')]);
    $page = as_user('super')->get("/admin/products/price-table.php?id=$t");
    check_page_ok($page, 'price grid screen');
    check_contains('data-grid-holes="1"', $page->body, 'warning for exactly one inside gap (1500 × 1500)');
    check_contains('1500 × 1500', $page->body, 'the gap named');
    check_not_contains('2000 × 2000', $page->body, 'the empty top-right corner warned about');
});
