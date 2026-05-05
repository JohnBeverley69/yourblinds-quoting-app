<?php
declare(strict_types=1);

// =============================================================================
// YourBlinds — pricing engine (pure functions, no HTTP layer).
//
// Resolution flow for a quote line:
//   1. (supplier, fabric, colour)        -> vertical_fabrics  -> band_code + fabric_id
//   2. (client_id, product_id, band_code) -> price_tables     -> price_table_id
//   3. (price_table_id, width, drop)      -> price_table_rows -> base_price
//   4. markup % = client_markups override OR client_settings.default_markup_percent
//   5. discount % = client_discounts override OR 0
//   6. sell_price = base_price * (1 + markup/100) * (1 - discount/100)
//   7. line_total = sell_price * quantity
//
// All functions are scoped by client_id. Callers MUST pass the logged-in
// user's client_id — there is no global pricing.
//
// Width and drop are in METRES, matching the schema (DECIMAL(10,3)).
// Money is GBP (DECIMAL(10,2)). All percentages are 0..100 (not 0..1).
// =============================================================================

/**
 * Resolve a (supplier, fabric, colour) selection to a vertical_fabrics row.
 * Returns the full row or null if no active match exists for this client.
 */
function pricing_resolve_fabric(
    int $clientId,
    string $supplier,
    string $fabric,
    string $colour
): ?array {
    $stmt = db()->prepare(
        'SELECT id, supplier_name, band_code, fabric_name, colour_name
           FROM vertical_fabrics
          WHERE client_id     = ?
            AND supplier_name = ?
            AND fabric_name   = ?
            AND colour_name   = ?
            AND active        = 1
          LIMIT 1'
    );
    $stmt->execute([$clientId, $supplier, $fabric, $colour]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Find the price table for a (client, product, band).
 * Returns the full row or null.
 */
function pricing_find_price_table(int $clientId, int $productId, string $bandCode): ?array
{
    $stmt = db()->prepare(
        'SELECT id, client_id, product_id, table_name, band_code
           FROM price_tables
          WHERE client_id  = ?
            AND product_id = ?
            AND band_code  = ?
            AND active     = 1
          LIMIT 1'
    );
    $stmt->execute([$clientId, $productId, $bandCode]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Find a matrix cell with EXACT width/drop match. Strict — caller asks
 * for the rounded-up version explicitly via pricing_find_matrix_row_round_up().
 */
function pricing_find_matrix_row(int $priceTableId, float $widthM, float $dropM): ?array
{
    $stmt = db()->prepare(
        'SELECT id, price_table_id, width_value, drop_value_exact, base_price
           FROM price_table_rows
          WHERE price_table_id   = ?
            AND width_value      = ?
            AND drop_value_exact = ?
          LIMIT 1'
    );
    // Format to 3dp explicitly so MySQL compares cleanly against DECIMAL(10,3)
    $stmt->execute([
        $priceTableId,
        sprintf('%.3f', $widthM),
        sprintf('%.3f', $dropM),
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Find the smallest cell whose width >= requested AND drop >= requested.
 * Used when the caller wants automatic "round up to next available size".
 */
function pricing_find_matrix_row_round_up(int $priceTableId, float $widthM, float $dropM): ?array
{
    $stmt = db()->prepare(
        'SELECT id, price_table_id, width_value, drop_value_exact, base_price
           FROM price_table_rows
          WHERE price_table_id   = ?
            AND width_value      >= ?
            AND drop_value_exact >= ?
          ORDER BY width_value ASC, drop_value_exact ASC
          LIMIT 1'
    );
    $stmt->execute([
        $priceTableId,
        sprintf('%.3f', $widthM),
        sprintf('%.3f', $dropM),
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Markup % for (client, product). Per-product override wins over the default
 * stored on client_settings; falls back to 0 if neither exists.
 */
function pricing_markup_for_product(int $clientId, int $productId): float
{
    $stmt = db()->prepare(
        'SELECT markup_percent FROM client_markups
          WHERE client_id = ? AND product_id = ?
          LIMIT 1'
    );
    $stmt->execute([$clientId, $productId]);
    $val = $stmt->fetchColumn();
    if ($val !== false && $val !== null) {
        return (float) $val;
    }

    $stmt = db()->prepare(
        'SELECT default_markup_percent FROM client_settings WHERE client_id = ? LIMIT 1'
    );
    $stmt->execute([$clientId]);
    $val = $stmt->fetchColumn();
    return ($val !== false && $val !== null) ? (float) $val : 0.0;
}

/**
 * Discount % for (client, product). Returns 0 if no row.
 */
function pricing_discount_for_product(int $clientId, int $productId): float
{
    $stmt = db()->prepare(
        'SELECT discount_percent FROM client_discounts
          WHERE client_id = ? AND product_id = ?
          LIMIT 1'
    );
    $stmt->execute([$clientId, $productId]);
    $val = $stmt->fetchColumn();
    return ($val !== false && $val !== null) ? (float) $val : 0.0;
}

/**
 * sell_price = base * (1 + markup/100) * (1 - discount/100), rounded to 2dp.
 */
function pricing_compute_sell_price(float $baseCost, float $markupPct, float $discountPct): float
{
    $marked = $baseCost * (1 + $markupPct / 100);
    $netted = $marked   * (1 - $discountPct / 100);
    return round($netted, 2);
}

/**
 * High-level: full price calculation for one quote line.
 *
 * Returns either a result array (all fields a quote_items row needs to be
 * populated) or ['error' => '<message>'] if any step in the resolution
 * chain fails.
 */
function pricing_quote_line(
    int    $clientId,
    int    $productId,
    string $supplier,
    string $fabric,
    string $colour,
    float  $widthM,
    float  $dropM,
    int    $quantity = 1,
    bool   $roundUp  = false
): array {
    if ($quantity < 1) {
        return ['error' => 'Quantity must be at least 1.'];
    }
    if ($widthM <= 0 || $dropM <= 0) {
        return ['error' => 'Width and drop must be greater than zero.'];
    }

    $fabricRow = pricing_resolve_fabric($clientId, $supplier, $fabric, $colour);
    if ($fabricRow === null) {
        return ['error' => 'Fabric / colour combination not found for this supplier.'];
    }

    $priceTable = pricing_find_price_table(
        $clientId,
        $productId,
        (string) $fabricRow['band_code']
    );
    if ($priceTable === null) {
        return [
            'error' => sprintf(
                'No price table for this product in band %s.',
                $fabricRow['band_code']
            ),
        ];
    }

    $matrix = $roundUp
        ? pricing_find_matrix_row_round_up((int) $priceTable['id'], $widthM, $dropM)
        : pricing_find_matrix_row((int) $priceTable['id'], $widthM, $dropM);

    if ($matrix === null) {
        return [
            'error' => sprintf(
                $roundUp
                    ? 'Size %.3f x %.3f m exceeds the largest cell in this price table.'
                    : 'No exact price for size %.3f x %.3f m. Try the next available size.',
                $widthM,
                $dropM
            ),
        ];
    }

    $markup   = pricing_markup_for_product($clientId, $productId);
    $discount = pricing_discount_for_product($clientId, $productId);
    $base     = (float) $matrix['base_price'];
    $unit     = pricing_compute_sell_price($base, $markup, $discount);
    $line     = round($unit * $quantity, 2);

    return [
        'base_cost'          => $base,
        'markup_percent'     => $markup,
        'discount_percent'   => $discount,
        'sell_price'         => $unit,
        'quantity'           => $quantity,
        'line_total'         => $line,
        'price_table_id'     => (int)    $priceTable['id'],
        'price_table_row_id' => (int)    $matrix['id'],
        'vertical_fabric_id' => (int)    $fabricRow['id'],
        'band_code'          => (string) $fabricRow['band_code'],
        'matrix_width'       => (float)  $matrix['width_value'],
        'matrix_drop'        => (float)  $matrix['drop_value_exact'],
        'rounded_up'         => $roundUp
            && (
                (float) $matrix['width_value']      != $widthM
                || (float) $matrix['drop_value_exact'] != $dropM
            ),
    ];
}
