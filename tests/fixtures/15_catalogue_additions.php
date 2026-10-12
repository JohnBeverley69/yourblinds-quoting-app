<?php
declare(strict_types=1);

/**
 * Catalogue features newer than the test copy the factory catalogue was taken
 * from, added to the FACTORY's products before the companies are seeded (so the
 * copies carry them too). Made-up prices.
 *
 *   Forest Wood (85):  "Accessories" — per-unit items (price × quantity typed)
 *                      "Pelmet"      — a custom pelmet size typed in mm, priced
 *                                      from a width table at that size
 *   PF Shutter (199):  "Number of Panels" splits the base price into panels
 *                      (base = N × grid(width ÷ N), PR #862)
 */

$pdo = db();

$addExtra = static function (int $productId, string $name, array $cols = []) use ($pdo): int {
    $cols += ['is_required' => 0, 'allow_multi' => 0, 'sort_order' => 900, 'active' => 1];
    $names = array_merge(['client_id', 'product_id', 'name'], array_keys($cols));
    $vals  = array_merge([3, $productId, $name], array_values($cols));
    $pdo->prepare('INSERT INTO product_extras (' . implode(',', $names) . ') VALUES (' . implode(',', array_fill(0, count($vals), '?')) . ')')
        ->execute($vals);
    return (int) $pdo->lastInsertId();
};
$addChoice = static function (int $extraId, string $label, array $cols = []) use ($pdo): int {
    $cols += ['price_delta' => 0, 'sort_order' => 10, 'active' => 1];
    $names = array_merge(['product_extra_id', 'label'], array_keys($cols));
    $vals  = array_merge([$extraId, $label], array_values($cols));
    $pdo->prepare('INSERT INTO product_extra_choices (' . implode(',', $names) . ') VALUES (' . implode(',', array_fill(0, count($vals), '?')) . ')')
        ->execute($vals);
    return (int) $pdo->lastInsertId();
};

// Forest Wood — per-unit accessories (multi-pick, quantity typed per item).
$acc = $addExtra(85, 'Accessories', ['allow_multi' => 1, 'code' => 'ACC']);
fx_set('fw_accessories', $acc);
fx_set('fw_holddown', $addChoice($acc, 'Hold-down bracket', ['price_per_unit' => 2.50, 'code' => 'HDB', 'sort_order' => 10]));
fx_set('fw_extra_bracket', $addChoice($acc, 'Extra bracket', ['price_per_unit' => 1.75, 'code' => 'XBR', 'sort_order' => 20]));
fx_set('fw_wand', $addChoice($acc, 'Spare wand', ['price_delta' => 6.00, 'face_value' => 0, 'sort_order' => 30]));

// Forest Wood — pelmet at a custom size (typed width overrides the blind's width
// for the width-table lookup).
$pel = $addExtra(85, 'Pelmet');
fx_set('fw_pelmet', $pel);
$none = $addChoice($pel, 'No pelmet', ['is_default' => 1, 'sort_order' => 10]);
$cus  = $addChoice($pel, 'Custom size pelmet', ['length_input_label' => 'Pelmet width (mm)', 'sort_order' => 20]);
fx_set('fw_pelmet_none', $none);
fx_set('fw_pelmet_custom', $cus);
foreach ([600 => 12.00, 1200 => 18.50, 1800 => 24.00, 2400 => 31.25] as $w => $p) {
    $pdo->prepare('INSERT INTO extra_choice_price_rows (product_extra_choice_id, width_mm, price) VALUES (?, ?, ?)')->execute([$cus, $w, $p]);
}

// PF Shutter — panel split on "Number of Panels".
$pdo->exec('UPDATE product_extras SET splits_panels = 1 WHERE id = 1175');

// ── Two small made-up products for the price boundary checks (tests/cases/12_*) ──
// Own-priced, no fabric. Kept out of the random price lines ("Bev ZZ" prefix).
$addProduct = static function (string $name, array $cols) use ($pdo): array {
    $cols += ['requires_option' => 0, 'active' => 1, 'price_source' => 'own', 'sort_order' => 990];
    $names = array_merge(['client_id', 'name'], array_keys($cols));
    $vals  = array_merge([3, $name], array_values($cols));
    $pdo->prepare('INSERT INTO products (' . implode(',', $names) . ') VALUES (' . implode(',', array_fill(0, count($vals), '?')) . ')')->execute($vals);
    $pid = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO product_systems (client_id, product_id, name, sort_order, active) VALUES (3, ?, ?, 1, 1)')->execute([$pid, 'Standard']);
    $sid = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO price_tables (client_id, product_id, system_id, band_code, name, active) VALUES (3, ?, ?, ?, ?, 1)')->execute([$pid, $sid, '', $name . ' grid']);
    return [$pid, $sid, (int) $pdo->lastInsertId()];
};
$cell = static fn (int $t, int $w, int $d, float $p) => $pdo->prepare('INSERT INTO price_table_rows (price_table_id, width_mm, drop_mm, price) VALUES (?, ?, ?, ?)')->execute([$t, $w, $d, $p]);

// A 3×3 grid (1000/1500/2000 mm) with a hole in the MIDDLE (1500×1500) and in
// the "too big to make" corner (2000×2000). Line charge £4.99 once per line.
[$gp, $gs, $gt] = $addProduct('Bev ZZ Grid Test', ['line_charge' => 4.99]);
foreach ([1000 => [1000 => 20.10, 1500 => 25.30, 2000 => 30.55], 1500 => [1000 => 27.45, 2000 => 41.20], 2000 => [1000 => 34.95, 1500 => 44.15]] as $w => $drops) {
    foreach ($drops as $d => $p) $cell($gt, $w, $d, $p);
}
$gpan = $addExtra($gp, 'Panels', ['splits_panels' => 1]);
$addChoice($gpan, '1', ['is_default' => 1, 'sort_order' => 10]);
fx_set('grid_2_panels', $addChoice($gpan, '2', ['sort_order' => 20]));
$gsur = $addExtra($gp, 'Surcharge');
fx_set('grid_surcharge', $addChoice($gsur, '12.5% surcharge', ['price_percent' => 12.5]));
fx_set('grid_product', $gp); fx_set('grid_system', $gs); fx_set('grid_panels', $gpan); fx_set('grid_surcharge_extra', $gsur);

// £/m² at £41.67, minimum billable area 1.5 m² (per panel when split).
[$sp, $ss, $st] = $addProduct('Bev ZZ Sqm Test', ['price_per_sqm' => 1, 'min_area_m2' => 1.5]);
$cell($st, 1000, 1000, 41.67);
$span = $addExtra($sp, 'Panels', ['splits_panels' => 1]);
$addChoice($span, '1', ['is_default' => 1, 'sort_order' => 10]);
fx_set('sqm_2_panels', $addChoice($span, '2', ['sort_order' => 20]));
fx_set('sqm_product', $sp); fx_set('sqm_system', $ss); fx_set('sqm_panels', $span);
