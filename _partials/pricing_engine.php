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
 *   5. markup % = client_markups row (per product+system) OR 0
 *   6. discount % = client_discounts row (per product+system) OR 0
 *   7. sell_price = base × (1 − discount/100) × (1 + markup/100) + extras_total
 *      — discount comes off the price-table first, then the markup
 *        is added to the discounted price. Both apply ONLY to the
 *        base (the material price). Extras are pure pass-through;
 *        tenants set the customer price directly in the options page.
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
    // cost_price = optional wholesale cost the tenant pays for this
    // fabric per blind. Defaults to NULL (treated as 0 downstream)
    // until they fill it in on /admin/products/option-edit.php.
    $st = $pdo->prepare(
        'SELECT id, product_id, band_code, supplier_name, name, colour, code,
                cost_price
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
 * Find the price table for a NO-FABRIC product (products.requires_option
 * = 0), where there's no band axis. Such a product has one price table
 * per system (or a single one when it has no systems). We pick the
 * matching active table for (product, system) regardless of band_code —
 * the wizard creates these with an empty band, but ignoring band here
 * keeps us robust if it was set to anything else.
 *
 * Deterministic when more than one somehow exists: lowest id wins.
 */
function pe_find_price_table_no_fabric(
    PDO  $pdo,
    int  $clientId,
    int  $productId,
    ?int $systemId
): ?array {
    if ($systemId === null) {
        $st = $pdo->prepare(
            'SELECT id, client_id, product_id, system_id, band_code, name
               FROM price_tables
              WHERE client_id  = ?
                AND product_id = ?
                AND system_id IS NULL
                AND active     = 1
              ORDER BY id ASC
              LIMIT 1'
        );
        $st->execute([$clientId, $productId]);
    } else {
        $st = $pdo->prepare(
            'SELECT id, client_id, product_id, system_id, band_code, name
               FROM price_tables
              WHERE client_id  = ?
                AND product_id = ?
                AND system_id  = ?
                AND active     = 1
              ORDER BY id ASC
              LIMIT 1'
        );
        $st->execute([$clientId, $productId, $systemId]);
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
    float $basePrice,
    ?float $userValue = null
): array {
    // 1. Verify the extra belongs to this product + tenant. Option-level
    //    system scope no longer exists in this model — an option appears
    //    whenever any of its choices is available for the selected system,
    //    which the choice-level guard below enforces.
    //
    //    length_input_label = optional column (added by migrate_extra
    //    _length_input.php). When non-NULL, the quote builder renders a
    //    number input next to the choice picker; the typed value is
    //    passed in as $userValue and snapshotted on quote_item_extras.
    //    Try-fallback for pre-migration installs.
    try {
        $st = $pdo->prepare(
            'SELECT id, name, parent_choice_id, length_input_label
               FROM product_extras
              WHERE id = ? AND product_id = ? AND client_id = ? AND active = 1
              LIMIT 1'
        );
        $st->execute([$extraId, $productId, $clientId]);
        $extra = $st->fetch();
    } catch (Throwable $e) {
        $st = $pdo->prepare(
            'SELECT id, name, parent_choice_id
               FROM product_extras
              WHERE id = ? AND product_id = ? AND client_id = ? AND active = 1
              LIMIT 1'
        );
        $st->execute([$extraId, $productId, $clientId]);
        $extra = $st->fetch();
        if ($extra) $extra['length_input_label'] = null;
    }
    if (!$extra) {
        return ['error' => "Option #$extraId not found for this product."];
    }

    // 2. Look up the choice. system_id is now read straight off the
    //    choice row (NULL = "available on every system"). cost_price
    //    is the optional wholesale cost the tenant pays per use of
    //    this choice — flat number regardless of sell-pricing mode
    //    (flat/percent/per-metre/width-table all describe how it's
    //    CHARGED to the customer; cost is what's PAID to the supplier
    //    per fitted unit). NULL = treat as 0.
    //
    //    markup_pct_override: per-choice escape hatch. NULL = use the
    //    tenant default (client_settings.default_options_markup_pct);
    //    any number (including 0) = explicit override for this choice.
    //    Try-fallback in case the column isn't there yet on older
    //    schemas (migrate_default_margins.php not run).
    try {
        $st = $pdo->prepare(
            'SELECT id, product_extra_id, system_id, label,
                    price_delta, price_percent, price_per_metre,
                    cost_price, markup_pct_override
               FROM product_extra_choices
              WHERE id = ? AND product_extra_id = ? AND active = 1
              LIMIT 1'
        );
        $st->execute([$choiceId, $extraId]);
        $choice = $st->fetch();
    } catch (Throwable $colErr) {
        $st = $pdo->prepare(
            'SELECT id, product_extra_id, system_id, label,
                    price_delta, price_percent, price_per_metre,
                    cost_price
               FROM product_extra_choices
              WHERE id = ? AND product_extra_id = ? AND active = 1
              LIMIT 1'
        );
        $st->execute([$choiceId, $extraId]);
        $choice = $st->fetch();
        if ($choice) $choice['markup_pct_override'] = 0;
    }
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

    // 6. Apply the options markup. Per-choice override wins if set
    //    (including 0, which explicitly opts out); otherwise the
    //    tenant-wide default kicks in. The migration backfills
    //    override=0 on every choice that existed before this feature
    //    landed, so legacy prices never inflate unexpectedly — the
    //    default only acts on choices created after migration.
    //
    //    The markup is a uniform uplift across ALL four contribution
    //    modes (flat / percent / per-metre / width-table) since the
    //    tenant thinks of it as "my margin on top of supplier cost"
    //    regardless of how that cost is expressed.
    $markupOverride = isset($choice['markup_pct_override']) && $choice['markup_pct_override'] !== null
        ? (float) $choice['markup_pct_override']
        : null;
    $optionsMarkup = $markupOverride !== null
        ? $markupOverride
        : pe_tenant_defaults($pdo, $clientId)['options'];
    if ($optionsMarkup != 0.0) {
        $amount *= (1 + $optionsMarkup / 100.0);
    }

    // 7. Pick the primary mode for the snapshot.
    $primary = 'flat';
    foreach (['width_table', 'per_metre', 'percent', 'flat'] as $m) {
        if (in_array($m, $modesApplied, true)) { $primary = $m; break; }
    }

    // cost_snapshot rides alongside amount_applied. NULL on the
    // choice → 0 cost; tenant hasn't filled cost in yet, dashboard
    // will show this extra as pure profit until they do.
    $costSnapshot = isset($choice['cost_price']) && $choice['cost_price'] !== null
        ? round((float) $choice['cost_price'], 2)
        : 0.0;

    // user_value: pass-through for now. The salesperson typed e.g. 1230
    // (mm) and we record it so the supplier docs show the spec. Could
    // also drive pricing later (multiply choice.price_per_metre by
    // userValue/1000 instead of widthMm/1000) — out of scope for v1.
    $resolvedUserValue = null;
    if (!empty($extra['length_input_label']) && $userValue !== null && $userValue > 0) {
        $resolvedUserValue = round((float) $userValue, 2);
    }

    return [
        'extra_id'            => (int)    $extra['id'],
        'extra_name'          => (string) $extra['name'],
        'choice_id'           => (int)    $choice['id'],
        'choice_label'        => (string) $choice['label'],
        'mode'                => $primary,
        'amount_applied'      => round($amount, 2),
        'cost_snapshot'       => $costSnapshot,
        'length_input_label'  => $extra['length_input_label'] ?? null,
        'user_value'          => $resolvedUserValue,
    ];
}

/**
 * Read the two tenant-wide default margins from client_settings.
 * Cached per-request because they're hit for every price-table /
 * option lookup. Returns ['price_table' => float, 'options' => float]
 * — both 0.0 if the columns don't exist (older schema).
 */
function pe_tenant_defaults(PDO $pdo, int $clientId): array
{
    static $cache = [];
    if (isset($cache[$clientId])) return $cache[$clientId];
    $out = ['price_table' => 0.0, 'options' => 0.0];
    try {
        $st = $pdo->prepare(
            'SELECT default_price_table_markup_pct,
                    default_options_markup_pct
               FROM client_settings WHERE client_id = ? LIMIT 1'
        );
        $st->execute([$clientId]);
        $row = $st->fetch();
        if ($row) {
            $out['price_table'] = (float) ($row['default_price_table_markup_pct'] ?? 0);
            $out['options']     = (float) ($row['default_options_markup_pct']     ?? 0);
        }
    } catch (Throwable $e) {
        // Columns missing on older schemas — migration not yet run.
        // Defaults of 0 mean engine behaves identically to before.
    }
    return $cache[$clientId] = $out;
}

/**
 * Markup % for (client, product, system). Per-system is the only
 * source — premium / motorised / standard each carry their own margin
 * since they're priced very differently in real life.
 *
 * Resolution: look for an exact (client, product, system_id) row.
 * Products without systems use the (system_id IS NULL) row. If no
 * explicit row exists, fall back to the tenant-wide default from
 * client_settings.default_price_table_markup_pct (introduced by
 * migrate_default_margins.php). Lets tenants set their margin once
 * and only override on the products that need a different rate.
 *
 * The unique key uses a generated column IFNULL(system_id, 0), so we
 * can pass NULL for products with no systems without MySQL treating
 * each NULL as a fresh row.
 *
 * migrate_markup_per_system.php expanded each product's pre-migration
 * row across all its systems, so cut-over is price-neutral.
 */
function pe_markup_for_system(PDO $pdo, int $clientId, int $productId, ?int $systemId): float
{
    if ($systemId === null) {
        $st = $pdo->prepare(
            'SELECT markup_percent FROM client_markups
              WHERE client_id = ? AND product_id = ? AND system_id IS NULL
              LIMIT 1'
        );
        $st->execute([$clientId, $productId]);
    } else {
        $st = $pdo->prepare(
            'SELECT markup_percent FROM client_markups
              WHERE client_id = ? AND product_id = ? AND system_id = ?
              LIMIT 1'
        );
        $st->execute([$clientId, $productId, $systemId]);
    }
    $val = $st->fetchColumn();
    // 0 = "use the default" (matches the natural reading on the form
    // — a tenant who types 0 means "no specific value", not "make
    // this loss-leader at cost"). Tenants who genuinely want 0%
    // override set the tenant default to 0 and override the products
    // that DO need markup. Same rule applies to a missing row.
    if ($val !== false && $val !== null && (float) $val > 0) {
        return (float) $val;
    }
    return pe_tenant_defaults($pdo, $clientId)['price_table'];
}

/**
 * Discount % for (client, product, system). Same per-system model as
 * markup. Missing row = 0%.
 */
function pe_discount_for_system(PDO $pdo, int $clientId, int $productId, ?int $systemId): float
{
    if ($systemId === null) {
        $st = $pdo->prepare(
            'SELECT discount_percent FROM client_discounts
              WHERE client_id = ? AND product_id = ? AND system_id IS NULL
              LIMIT 1'
        );
        $st->execute([$clientId, $productId]);
    } else {
        $st = $pdo->prepare(
            'SELECT discount_percent FROM client_discounts
              WHERE client_id = ? AND product_id = ? AND system_id = ?
              LIMIT 1'
        );
        $st->execute([$clientId, $productId, $systemId]);
    }
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
    // NOTE: the "Fabric is required" check is deferred until after the
    // product is loaded — a product flagged requires_option = 0 (e.g. a
    // headrail-only line) is priced on system × size alone, with no
    // fabric to pick. See the requires_option branch below.
    if ($widthMm   <= 0) return ['error' => 'Width must be greater than zero.'];
    if ($dropMm    <= 0) return ['error' => 'Drop must be greater than zero.'];

    // 1. Product (tenant scope). cost_price = default wholesale cost
    //    per blind for this product (set on /admin/products/edit.php).
    //    requires_option = 0 marks a "no-fabric" product (headrail/track/
    //    spares): no fabric axis, price resolved straight off the
    //    (product, system) price table. Try-fallback so the engine still
    //    runs on schemas where migrate_requires_option.php hasn't run yet
    //    (absent column ⇒ treated as 1 = needs a fabric, the old default).
    $product = false;
    foreach ([
        'id, name, cost_price, requires_option',
        'id, name, cost_price',
    ] as $cols) {
        try {
            $st = $pdo->prepare(
                "SELECT $cols FROM products
                  WHERE id = ? AND client_id = ? AND active = 1
                  LIMIT 1"
            );
            $st->execute([$productId, $clientId]);
            $product = $st->fetch();
            break;
        } catch (Throwable $e) {
            $product = false;
        }
    }
    if (!$product) return ['error' => 'Product not found or inactive.'];

    $requiresOption = !isset($product['requires_option'])
        || (int) $product['requires_option'] === 1;

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

    // 3. Fabric + 4. Price table.
    //
    // Normal products: resolve the picked fabric, take its band_code,
    // then find the (product, system, band) price table.
    //
    // No-fabric products (requires_option = 0): there is nothing to pick
    // on the fabric axis, so we go straight to the single price table for
    // (product, system) — band is irrelevant. All the fabric_* snapshot
    // fields are blanked.
    if ($requiresOption) {
        if ($optionId <= 0) return ['error' => 'Fabric is required.'];

        $fabric = pe_resolve_fabric($pdo, $clientId, $optionId);
        if ($fabric === null) {
            return ['error' => 'Fabric not found or inactive.'];
        }
        if ((int) $fabric['product_id'] !== $productId) {
            return ['error' => 'Fabric belongs to a different product.'];
        }

        $priceTable = pe_find_price_table(
            $pdo, $clientId, $productId, $systemId, (string) $fabric['band_code']
        );
        if ($priceTable === null) {
            $sysHint = $system ? " on system '" . $system['name'] . "'" : '';
            return ['error' => "No price table for "
                              . $product['name'] . " band " . $fabric['band_code']
                              . $sysHint . '.'];
        }
    } else {
        $fabric = null;   // no fabric axis on this product
        $priceTable = pe_find_price_table_no_fabric(
            $pdo, $clientId, $productId, $systemId
        );
        if ($priceTable === null) {
            $sysHint = $system ? " for system '" . $system['name'] . "'" : '';
            return ['error' => "No price table set up for "
                              . $product['name'] . $sysHint . '.'];
        }
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

    // 6. Apply extras. Each $sel may now carry a `user_value` (typed
    //    length / count / etc.) — pass through to pe_apply_extra so it
    //    can snapshot the value alongside the choice.
    $extrasApplied = [];
    $extrasTotal   = 0.0;
    foreach ($extras as $sel) {
        $eid = (int) ($sel['extra_id']  ?? 0);
        $cid = (int) ($sel['choice_id'] ?? 0);
        if ($eid <= 0 || $cid <= 0) continue;

        // Accept either 'user_value' or 'value' (the JS form might use
        // either name). Empty / non-numeric → null.
        $rawUserValue = $sel['user_value'] ?? $sel['value'] ?? null;
        $userValue = (is_numeric($rawUserValue) && (float) $rawUserValue > 0)
            ? (float) $rawUserValue
            : null;

        $applied = pe_apply_extra(
            $pdo, $clientId, $productId, $systemId, $eid, $cid,
            $widthMm, $basePrice, $userValue
        );
        if (isset($applied['error'])) {
            return ['error' => $applied['error']];
        }
        $extrasApplied[]  = $applied;
        $extrasTotal     += (float) $applied['amount_applied'];
    }
    $extrasTotal      = round($extrasTotal, 2);
    $subtotalPerBlind = round($basePrice + $extrasTotal, 2);

    // 7. Markup / discount — resolved per (product, system) so premium /
    //    motorised / standard can each have their own margin.
    $markup   = pe_markup_for_system  ($pdo, $clientId, $productId, $systemId);
    $discount = pe_discount_for_system($pdo, $clientId, $productId, $systemId);

    // 8. Sell price + line total.
    //
    //    Order matches how the tenant thinks about it:
    //      a) Take the discount off the price-table base
    //      b) Apply the markup to that discounted price
    //      c) Add the extras at face value (pass-through)
    //
    //    Note: a + b are mathematically equivalent to "markup then
    //    discount" because they're both percentage multipliers, but
    //    writing it in the natural reading order makes the code
    //    easier to follow.
    //
    //    Extras (price_delta, price_percent, price_per_metre,
    //    width_table) are NEVER marked up or discounted here — the
    //    tenant sets the customer-facing price directly in the
    //    options page.
    $discountedBase = $basePrice    * (1 - $discount / 100);
    $sellBase       = $discountedBase * (1 + $markup / 100);
    $sellPrice      = round($sellBase + $extrasTotal, 2);
    $lineTotal      = round($sellPrice * $quantity, 2);

    // 9. Cost snapshot — per-blind cost = product + fabric (both NULL
    //    columns treated as 0). Extras' cost is summed separately so
    //    the dashboard can break it down later if useful.
    $productCost = isset($product['cost_price']) && $product['cost_price'] !== null
        ? (float) $product['cost_price'] : 0.0;
    $fabricCost  = isset($fabric['cost_price']) && $fabric['cost_price'] !== null
        ? (float) $fabric['cost_price']  : 0.0;
    $costPricePerBlind = round($productCost + $fabricCost, 2);

    $extrasCostTotal = 0.0;
    foreach ($extrasApplied as $ea) {
        $extrasCostTotal += (float) ($ea['cost_snapshot'] ?? 0);
    }
    $extrasCostTotal = round($extrasCostTotal, 2);

    return [
        // Resolved FKs (for quote_items). option_id is NULL for a
        // no-fabric product — quote_items.option_id is nullable
        // (migrate_requires_option.php).
        'product_id'         => $productId,
        'system_id'          => $systemId,
        'option_id'          => $fabric ? (int) $fabric['id'] : null,
        'price_table_id'     => (int) $priceTable['id'],
        'price_table_row_id' => (int) $row['id'],

        // Snapshot fields (for quote_items). All fabric_* blank when the
        // product has no fabric axis.
        'product_name'       => (string) $product['name'],
        'system_name'        => $system ? (string) $system['name'] : null,
        'fabric_band'        => $fabric ? (string) $fabric['band_code']            : '',
        'fabric_supplier'    => $fabric ? (string) ($fabric['supplier_name'] ?? '') : '',
        'fabric_name'        => $fabric ? (string) $fabric['name']                 : '',
        'fabric_colour'      => $fabric ? (string) ($fabric['colour'] ?? '')        : '',
        'fabric_code'        => $fabric ? (string) ($fabric['code']   ?? '')        : '',

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

        // Cost breakdown (per-blind). Stored on quote_items as the
        // cost_price_snapshot (blind) + extras_cost_snapshot (extras),
        // both frozen at save-time so editing the product later
        // doesn't move historic gross-profit numbers.
        'cost_price_per_blind' => $costPricePerBlind,
        'extras_cost_total'    => $extrasCostTotal,
    ];
}
