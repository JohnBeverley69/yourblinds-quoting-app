<?php
declare(strict_types=1);

/**
 * YourBlinds pricing engine — pure functions, no HTTP layer.
 *
 * High-level entry: pe_calculate_item().
 *
 * Resolution flow:
 *   1. option_id            → product_options row (band_code + snapshot fields)
 *   2. (client, product, system, band)
 *                           → price_tables    → price_table_id
 *   3. (price_table, w, d)  → price_table_rows → base_price (round-up optional)
 *   4. selected extras      → extras_applied[] with mode + amount each
 *   5. markup % = client_markups override OR client_settings.default_markup_percent
 *   6. discount % = client_discounts override OR 0
 *   7. sell_price = subtotal × (1 + markup/100) × (1 − discount/100)
 *   8. line_total = sell_price × quantity
 *
 * All helpers take $clientId explicitly. There is no global state — this is
 * unit-testable in isolation. Pass a PDO so callers can override (e.g. a
 * read-only replica or a mocked PDO in tests).
 *
 * Conventions:
 *   - Width / drop are integer millimetres. Matches the schema (INT UNSIGNED).
 *   - Money is GBP, DECIMAL(10,2), rounded to 2dp at every materialisation.
 *   - Percentages are 0..100 (NOT 0..1).
 *   - On any resolution failure (missing fabric, no price table, etc.) the
 *     entry point returns ['error' => '<message>'] — never throws.
 */

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Look up a product_options (fabric) row, tenant-scoped.
 */
function pe_resolve_fabric(PDO $pdo, int $clientId, int $optionId): ?array
{
    $st = $pdo->prepare(
        'SELECT id, product_id, band_code, supplier_name, name, colour, code
           FROM product_options
          WHERE id = ? AND client_id = ? AND active = 1
          LIMIT 1'
    );
    $st->execute([$optionId, $clientId]);
    $row = $st->fetch();
    return $row ?: null;
}

/**
 * Find the price_tables row for (client, product, system, band).
 * system_id can be null (for products without systems).
 */
function pe_find_price_table(
    PDO     $pdo,
    int     $clientId,
    int     $productId,
    ?int    $systemId,
    string  $bandCode
): ?array {
    if ($systemId === null) {
        $st = $pdo->prepare(
            'SELECT id, client_id, product_id, system_id, band_code, name
               FROM price_tables
              WHERE client_id  = ?
                AND product_id = ?
                AND system_id IS NULL
                AND band_code  = ?
                AND active     = 1
              LIMIT 1'
        );
        $st->execute([$clientId, $productId, $bandCode]);
    } else {
        $st = $pdo->prepare(
            'SELECT id, client_id, product_id, system_id, band_code, name
               FROM price_tables
              WHERE client_id  = ?
                AND product_id = ?
                AND system_id  = ?
                AND band_code  = ?
                AND active     = 1
              LIMIT 1'
        );
        $st->execute([$clientId, $productId, $systemId, $bandCode]);
    }
    $row = $st->fetch();
    return $row ?: null;
}

/**
 * Find the price_table_rows cell. Either exact match (width = AND drop =)
 * or round-up (smallest cell where width_mm >= AND drop_mm >=).
 */
function pe_find_matrix_row(
    PDO  $pdo,
    int  $priceTableId,
    int  $widthMm,
    int  $dropMm,
    bool $roundUp
): ?array {
    if ($roundUp) {
        $st = $pdo->prepare(
            'SELECT id, width_mm, drop_mm, price
               FROM price_table_rows
              WHERE price_table_id = ?
                AND width_mm      >= ?
                AND drop_mm       >= ?
              ORDER BY width_mm ASC, drop_mm ASC
              LIMIT 1'
        );
    } else {
        $st = $pdo->prepare(
            'SELECT id, width_mm, drop_mm, price
               FROM price_table_rows
              WHERE price_table_id = ?
                AND width_mm       = ?
                AND drop_mm        = ?
              LIMIT 1'
        );
    }
    $st->execute([$priceTableId, $widthMm, $dropMm]);
    $row = $st->fetch();
    return $row ?: null;
}

/**
 * Compute the £ contribution of one (extra, choice) selection to a single
 * blind, applying all four surcharge modes that have non-zero data.
 *
 * The four modes can technically combine on the same choice (e.g. a flat
 * £ plus a £/m), and we sum them for a single amount_applied figure. The
 * 'mode' field on the result is the *primary* mode for snapshot/display:
 * width_table > per_metre > percent > flat (in that priority order).
 *
 * Returns the result row ready for quote_item_extras INSERT, or
 * ['error' => '...'] on lookup failure.
 */
function pe_apply_extra(
    PDO   $pdo,
    int   $clientId,
    int   $productId,
    ?int  $systemId,
    int   $extraId,
    int   $choiceId,
    int   $widthMm,
    float $basePrice
): array {
    // 1. Verify the extra belongs to this product + tenant. Option-level
    //    system scope no longer exists in this model — an option appears
    //    whenever any of its choices is available for the selected system,
    //    which the choice-level guard below enforces.
    $st = $pdo->prepare(
        'SELECT id, name, parent_choice_id
           FROM product_extras
          WHERE id = ? AND product_id = ? AND client_id = ? AND active = 1
          LIMIT 1'
    );
    $st->execute([$extraId, $productId, $clientId]);
    $extra = $st->fetch();
    if (!$extra) {
        return ['error' => "Option #$extraId not found for this product."];
    }

    // 2. Look up the choice. system_id is now read straight off the
    //    choice row (NULL = "available on every system").
    $st = $pdo->prepare(
        'SELECT id, product_extra_id, system_id, label,
                price_delta, price_percent, price_per_metre
           FROM product_extra_choices
          WHERE id = ? AND product_extra_id = ? AND active = 1
          LIMIT 1'
    );
    $st->execute([$choiceId, $extraId]);
    $choice = $st->fetch();
    if (!$choice) {
        return ['error' => "Choice #$choiceId not found for option '" . $extra['name'] . "'."];
    }

    // 3. System-scope check on the choice row directly. NULL = every
    //    system; otherwise the item's system_id must match.
    if ($choice['system_id'] !== null
        && (int) $choice['system_id'] !== ($systemId ?? 0)) {
        return ['error' => "Choice '" . $choice['label'] . "' on '" . $extra['name']
                          . "' is not available on the selected system."];
    }

    // 4. Sum surcharge modes that have data.
    $delta    = (float) $choice['price_delta'];
    $percent  = (float) $choice['price_percent'];
    $perMetre = (float) $choice['price_per_metre'];

    $amount        = 0.0;
    $modesApplied  = [];

    if ($delta != 0.0) {
        $amount         += $delta;
        $modesApplied[]  = 'flat';
    }
    if ($percent != 0.0) {
        $amount         += $basePrice * $percent / 100.0;
        $modesApplied[]  = 'percent';
    }
    if ($perMetre != 0.0) {
        $amount         += ($widthMm / 1000.0) * $perMetre;
        $modesApplied[]  = 'per_metre';
    }

    // 5. Width-based price table (the 4th mode).
    //    Find smallest entry where width_mm >= request.
    $st = $pdo->prepare(
        'SELECT width_mm, price
           FROM extra_choice_price_rows
          WHERE product_extra_choice_id = ?
            AND width_mm                >= ?
          ORDER BY width_mm ASC
          LIMIT 1'
    );
    $st->execute([$choiceId, $widthMm]);
    $widthRow = $st->fetch();
    if ($widthRow) {
        $amount         += (float) $widthRow['price'];
        $modesApplied[]  = 'width_table';
    } else {
        // Distinguish "no width table at all" (fine, no contribution) from
        // "width table exists but request exceeds the largest cell" (error).
        $check = $pdo->prepare(
            'SELECT 1 FROM extra_choice_price_rows
              WHERE product_extra_choice_id = ? LIMIT 1'
        );
        $check->execute([$choiceId]);
        if ($check->fetchColumn()) {
            return ['error' => "Width $widthMm mm exceeds the largest entry in the "
                              . "width table for '" . $choice['label'] . "'."];
        }
    }

    // 6. Pick the primary mode for the snapshot.
    $primary = 'flat';
    foreach (['width_table', 'per_metre', 'percent', 'flat'] as $m) {
        if (in_array($m, $modesApplied, true)) { $primary = $m; break; }
    }

    return [
        'extra_id'       => (int)    $extra['id'],
        'extra_name'     => (string) $extra['name'],
        'choice_id'      => (int)    $choice['id'],
        'choice_label'   => (string) $choice['label'],
        'mode'           => $primary,
        'amount_applied' => round($amount, 2),
    ];
}

/**
 * Markup % for (client, product). Per-product override > client default > 0.
 */
function pe_markup_for_product(PDO $pdo, int $clientId, int $productId): float
{
    $st = $pdo->prepare(
        'SELECT markup_percent FROM client_markups
          WHERE client_id = ? AND product_id = ? LIMIT 1'
    );
    $st->execute([$clientId, $productId]);
    $val = $st->fetchColumn();
    if ($val !== false && $val !== null) {
        return (float) $val;
    }

    $st = $pdo->prepare(
        'SELECT default_markup_percent FROM client_settings WHERE client_id = ? LIMIT 1'
    );
    $st->execute([$clientId]);
    $val = $st->fetchColumn();
    return ($val !== false && $val !== null) ? (float) $val : 0.0;
}

/**
 * Discount % for (client, product). Per-product override or 0.
 */
function pe_discount_for_product(PDO $pdo, int $clientId, int $productId): float
{
    $st = $pdo->prepare(
        'SELECT discount_percent FROM client_discounts
          WHERE client_id = ? AND product_id = ? LIMIT 1'
    );
    $st->execute([$clientId, $productId]);
    $val = $st->fetchColumn();
    return ($val !== false && $val !== null) ? (float) $val : 0.0;
}

// ---------------------------------------------------------------------------
// High-level entry point
// ---------------------------------------------------------------------------

/**
 * Calculate the full price breakdown for one quote line.
 *
 * $input shape:
 * [
 *     'product_id' => int,
 *     'system_id'  => int|null,    // null for products without systems
 *     'option_id'  => int,         // the fabric (product_options.id)
 *     'width_mm'   => int,
 *     'drop_mm'    => int,
 *     'quantity'   => int,         // defaults to 1, clamped to >= 1
 *     'extras'     => [            // user-selected extras + chosen choice each
 *         ['extra_id' => int, 'choice_id' => int],
 *         ...
 *     ],
 *     'round_up'   => bool,        // whether to round up to next available cell
 * ]
 *
 * On success, returns an array with:
 *   - All resolved IDs (for FK columns on quote_items / quote_item_extras)
 *   - All snapshot fields (product_name, fabric_*, etc.) ready for INSERT
 *   - The full pricing breakdown (base, extras, markup, discount, sell, line_total)
 *
 * On any resolution failure, returns ['error' => '<human-readable message>'].
 */
function pe_calculate_item(PDO $pdo, int $clientId, array $input): array
{
    $productId = (int) ($input['product_id'] ?? 0);
    $systemId  = (isset($input['system_id']) && (int) $input['system_id'] > 0)
               ? (int) $input['system_id'] : null;
    $optionId  = (int) ($input['option_id'] ?? 0);
    $widthMm   = (int) ($input['width_mm']  ?? 0);
    $dropMm    = (int) ($input['drop_mm']   ?? 0);
    $quantity  = max(1, (int) ($input['quantity'] ?? 1));
    $roundUp   = !empty($input['round_up']);
    $extras    = is_array($input['extras'] ?? null) ? $input['extras'] : [];

    if ($productId <= 0) return ['error' => 'Product is required.'];
    if ($optionId  <= 0) return ['error' => 'Fabric is required.'];
    if ($widthMm   <= 0) return ['error' => 'Width must be greater than zero.'];
    if ($dropMm    <= 0) return ['error' => 'Drop must be greater than zero.'];

    // 1. Product (tenant scope).
    $st = $pdo->prepare(
        'SELECT id, name FROM products
          WHERE id = ? AND client_id = ? AND active = 1
          LIMIT 1'
    );
    $st->execute([$productId, $clientId]);
    $product = $st->fetch();
    if (!$product) return ['error' => 'Product not found or inactive.'];

    // 2. System (optional).
    $system = null;
    if ($systemId !== null) {
        $st = $pdo->prepare(
            'SELECT id, name FROM product_systems
              WHERE id = ? AND product_id = ? AND client_id = ? AND active = 1
              LIMIT 1'
        );
        $st->execute([$systemId, $productId, $clientId]);
        $system = $st->fetch();
        if (!$system) return ['error' => 'System not found for this product.'];
    }

    // 3. Fabric.
    $fabric = pe_resolve_fabric($pdo, $clientId, $optionId);
    if ($fabric === null) {
        return ['error' => 'Fabric not found or inactive.'];
    }
    if ((int) $fabric['product_id'] !== $productId) {
        return ['error' => 'Fabric belongs to a different product.'];
    }

    // 4. Price table.
    $priceTable = pe_find_price_table(
        $pdo, $clientId, $productId, $systemId, (string) $fabric['band_code']
    );
    if ($priceTable === null) {
        $sysHint = $system ? " on system '" . $system['name'] . "'" : '';
        return ['error' => "No price table for "
                          . $product['name'] . " band " . $fabric['band_code']
                          . $sysHint . '.'];
    }

    // 5. Matrix cell.
    $row = pe_find_matrix_row(
        $pdo, (int) $priceTable['id'], $widthMm, $dropMm, $roundUp
    );
    if ($row === null) {
        return ['error' => $roundUp
            ? "Size $widthMm × $dropMm mm exceeds the largest cell in this price table."
            : "No exact price for $widthMm × $dropMm mm. Try the next available size."];
    }
    $basePrice = (float) $row['price'];

    // 6. Apply extras.
    $extrasApplied = [];
    $extrasTotal   = 0.0;
    foreach ($extras as $sel) {
        $eid = (int) ($sel['extra_id']  ?? 0);
        $cid = (int) ($sel['choice_id'] ?? 0);
        if ($eid <= 0 || $cid <= 0) continue;

        $applied = pe_apply_extra(
            $pdo, $clientId, $productId, $systemId, $eid, $cid, $widthMm, $basePrice
        );
        if (isset($applied['error'])) {
            return ['error' => $applied['error']];
        }
        $extrasApplied[]  = $applied;
        $extrasTotal     += (float) $applied['amount_applied'];
    }
    $extrasTotal      = round($extrasTotal, 2);
    $subtotalPerBlind = round($basePrice + $extrasTotal, 2);

    // 7. Markup / discount.
    $markup   = pe_markup_for_product  ($pdo, $clientId, $productId);
    $discount = pe_discount_for_product($pdo, $clientId, $productId);

    // 8. Sell price + line total.
    $sellPrice = round(
        $subtotalPerBlind * (1 + $markup / 100) * (1 - $discount / 100),
        2
    );
    $lineTotal = round($sellPrice * $quantity, 2);

    return [
        // Resolved FKs (for quote_items)
        'product_id'         => $productId,
        'system_id'          => $systemId,
        'option_id'          => (int) $fabric['id'],
        'price_table_id'     => (int) $priceTable['id'],
        'price_table_row_id' => (int) $row['id'],

        // Snapshot fields (for quote_items)
        'product_name'       => (string) $product['name'],
        'system_name'        => $system ? (string) $system['name'] : null,
        'fabric_band'        => (string) $fabric['band_code'],
        'fabric_supplier'    => (string) ($fabric['supplier_name'] ?? ''),
        'fabric_name'        => (string) $fabric['name'],
        'fabric_colour'      => (string) ($fabric['colour'] ?? ''),
        'fabric_code'        => (string) ($fabric['code']   ?? ''),

        // Dimensions: what was requested, what cell was actually used
        'width_mm'           => $widthMm,
        'drop_mm'            => $dropMm,
        'matrix_width_mm'    => (int) $row['width_mm'],
        'matrix_drop_mm'     => (int) $row['drop_mm'],
        'rounded_up'         => $roundUp && (
            (int) $row['width_mm'] !== $widthMm
         || (int) $row['drop_mm']  !== $dropMm
        ),

        // Pricing breakdown
        'base_price'         => round($basePrice, 2),
        'extras_applied'     => $extrasApplied,   // ready for quote_item_extras INSERTs
        'extras_total'       => $extrasTotal,
        'subtotal_per_blind' => $subtotalPerBlind,
        'markup_percent'     => round($markup, 2),
        'discount_percent'   => round($discount, 2),
        'sell_price'         => $sellPrice,
        'quantity'           => $quantity,
        'line_total'         => $lineTotal,
    ];
}
