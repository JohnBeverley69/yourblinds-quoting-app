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
