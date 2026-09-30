<?php
declare(strict_types=1);

/**
 * The live price for the quote-builder line form: request fields in, JSON-ready
 * answer out. No HTTP, no session. The SAME function answers both:
 *   • the server:  quote-builder/api/preview.php (logged-in and public InstaPrice)
 *   • the tablet:  offline/_device_preview.php, running this file inside
 *                  PHP-in-WebAssembly against the tablet's copy of the catalogue.
 * One copy of the rules, so an offline price can't drift from the server's.
 *
 * $q is the preview request (what preview.php gets in $_GET):
 *   product_id, system_id, option_id, width, drop (free text), quantity, round_up,
 *   unit, markup_override, discount_override,
 *   extras[i][extra_id / choice_id / choice_ids[] / user_value],
 *   multi_fascia[active / widths[] / drops[]]
 *
 * Callers must have loaded: _partials/pricing_engine.php,
 * _partials/price_table_parser.php (ptp_parse_dimension), _partials/units.php
 * (unit_is_valid) and _partials/multi_fascia_pricer.php.
 *
 * $canCosts: may this user see wholesale cost / trade-discount figures?
 * $isPublic: anonymous public InstaPrice (hides the fabric supplier too).
 * $forAccountId: super-admin quoting FOR a trade account (0 = normal).
 */
function qb_preview_response(PDO $pdo, int $clientId, array $q, bool $canCosts, bool $isPublic, int $forAccountId): array
{
    // Free-text width / drop, parsed via the shared dimension parser. A bare
    // number is read in the caller's unit (the quote / tenant setting, passed
    // as unit=); explicit suffixes still override. Defaults to mm.
    $unit     = unit_is_valid(isset($q['unit']) ? (string) $q['unit'] : null) ? (string) $q['unit'] : 'mm';
    $widthRaw = (string) ($q['width'] ?? '');
    $dropRaw  = (string) ($q['drop']  ?? '');
    $widthMm  = ptp_parse_dimension($widthRaw, $unit);
    $dropMm   = ptp_parse_dimension($dropRaw, $unit);

    // A blank width or drop is allowed through as 0 — some products have no
    // width (per-slat) or no drop (width-only). The engine decides which are
    // actually required. Only a non-blank, unparseable value is a hard error.
    if ($widthMm === null) {
        if (trim($widthRaw) !== '') return ['error' => 'Could not read width "' . $widthRaw . '".', 'stage' => 'input'];
        $widthMm = 0;
    }
    if ($dropMm === null) {
        if (trim($dropRaw) !== '') return ['error' => 'Could not read drop "' . $dropRaw . '".', 'stage' => 'input'];
        $dropMm = 0;
    }

    // Each entry has extra_id + either choice_id (single-pick) or
    // choice_ids[] (multi-pick). user_value optional. See add_item.php
    // for the full notes — same parsing shape.
    $extras = [];
    if (isset($q['extras']) && is_array($q['extras'])) {
        foreach ($q['extras'] as $e) {
            if (!is_array($e)) continue;
            $eid = (int) ($e['extra_id'] ?? 0);
            if ($eid <= 0) continue;

            $uv  = $e['user_value'] ?? null;
            $uvFloat = ($uv !== null && $uv !== '' && is_numeric($uv) && (float) $uv > 0)
                ? (float) $uv : null;

            $mkRow = static function (int $eid, int $cid) use ($uvFloat): array {
                $row = ['extra_id' => $eid, 'choice_id' => $cid];
                if ($uvFloat !== null) $row['user_value'] = $uvFloat;
                return $row;
            };

            if (isset($e['choice_ids']) && is_array($e['choice_ids'])) {
                foreach ($e['choice_ids'] as $rawCid) {
                    $cid = (int) $rawCid;
                    if ($cid > 0) $extras[] = $mkRow($eid, $cid);
                }
            } elseif (array_key_exists('choice_id', $e)) {
                // Single-pick dropdown — only counts when a choice was picked.
                $cid = (int) ($e['choice_id'] ?? 0);
                if ($cid > 0) $extras[] = $mkRow($eid, $cid);
            } else {
                // Number-only option: no choice_id submitted at all. Carry the
                // typed value through with no choice (choice_id 0).
                $row = ['extra_id' => $eid, 'choice_id' => 0];
                if ($uvFloat !== null) $row['user_value'] = $uvFloat;
                $extras[] = $row;
            }
        }
    }

    $input = [
        'product_id' => (int) ($q['product_id'] ?? 0),
        'system_id'  => (int) ($q['system_id']  ?? 0),
        'option_id'  => (int) ($q['option_id']  ?? 0),
        'width_mm'   => $widthMm,
        'drop_mm'    => $dropMm,
        'quantity'   => (int) ($q['quantity']   ?? 1),
        'extras'     => $extras,
        'round_up'   => !empty($q['round_up']),
    ];
    // Override arrives already converted to MARKUP by the client (margin tenants
    // convert before sending). Only honoured for cost-viewers and only when a
    // numeric value is actually supplied — otherwise the engine resolves normally.
    if ($canCosts) {
        if (isset($q['markup_override'])   && is_numeric($q['markup_override']))   $input['markup_override']   = (float) $q['markup_override'];
        if (isset($q['discount_override']) && is_numeric($q['discount_override'])) $input['discount_override'] = (float) $q['discount_override'];
    }

    // Multi-blind fascia: several blinds share one fascia. Price the whole group
    // live via the shared pricer (fascia once, other extras per blind) and return a
    // per-blind breakdown + fit check, so the builder shows a real total before save.
    // The caller sends the picked Fascia Options choice + Fascia width in extras[],
    // and each blind's width/drop in multi_fascia[widths][]/[drops][].
    $mf = (isset($q['multi_fascia']) && is_array($q['multi_fascia'])) ? $q['multi_fascia'] : [];
    if (!empty($mf['active']) && !empty($mf['widths']) && is_array($mf['widths'])) {
        $mfDrops = (isset($mf['drops']) && is_array($mf['drops'])) ? array_values($mf['drops']) : [];
        $blinds = [];
        foreach (array_values($mf['widths']) as $idx => $wRaw) {
            $wm = ptp_parse_dimension((string) $wRaw, $unit);
            if ($wm === null || $wm <= 0) continue;
            $dm = ptp_parse_dimension((string) ($mfDrops[$idx] ?? ''), $unit);
            if ($dm === null || $dm <= 0) $dm = $dropMm;   // per-blind drop defaults to the shared drop
            $blinds[] = ['width_mm' => (int) $wm, 'drop_mm' => (int) $dm];
        }
        if (count($blinds) >= 2) {
            $g = qb_price_multi_fascia($pdo, $clientId, $input, $blinds, $forAccountId);
            return [
                'multi'           => true,
                'count'           => count($blinds),
                'total'           => $g['total'],
                'fascia_width_mm' => $g['fascia_width_mm'],
                'sum_widths_mm'   => $g['sum_widths_mm'],
                'checked'         => $g['checked'],
                'fits'            => $g['fits'],
                'has_fascia'      => $g['has_fascia'],
                'blinds'          => array_map(static fn ($b) => [
                    'width_mm'   => $b['width_mm'], 'drop_mm' => $b['drop_mm'], 'is_carrier' => $b['is_carrier'],
                    'sell_price' => $b['sell_price'], 'line_total' => $b['line_total'], 'error' => $b['error'],
                ], $g['blinds']),
            ];
        }
    }

    $result = pe_calculate_item($pdo, $clientId, $input, $forAccountId);
    if (isset($result['error'])) {
        $result['stage'] = 'engine';
        return $result;
    }

    // Strip wholesale-cost figures for users who aren't allowed to see costs.
    // The UI never displays these (cost_price_per_blind / extras_cost_total /
    // per-extra cost_snapshot reveal what the business pays its suppliers), but
    // the raw API response carried them to anyone logged in. The front-end
    // doesn't read these fields, so removing them changes nothing it needs.
    if (!$canCosts) {
        unset(
            $result['cost_price_per_blind'], $result['extras_cost_total'],
            // Trade (buying) discount reveals the account's wholesale cost — cost-viewers only.
            $result['trade_price_per_blind'], $result['trade_discount_percent'], $result['trade_discount_amount']
        );
        if (!empty($result['extras_applied']) && is_array($result['extras_applied'])) {
            foreach ($result['extras_applied'] as &$exRow) {
                if (is_array($exRow)) unset($exRow['cost_snapshot'], $exRow['trade_amount'], $exRow['promo_discount_percent'], $exRow['promo_discount_amount']);
            }
            unset($exRow);
        }

        // SELL basis. base_price / extras_total / markup_percent / discount_percent
        // as the engine returns them are on the BUYING basis (price-table base
        // after buying discount, supplier list add-ons) — with the markup that's
        // enough to work out what the business pays. Re-express them so the same
        // client arithmetic gives the same sell price without revealing it:
        //   extras_total := the options part as SOLD (options_sell_total)
        //   base_price   := sell_price − that options part (the marked-up blind)
        //   markup / discount := 0, subtotal_per_blind := sell_price
        // InstaPrice's recompute() — round2(base·(1−d)·(1+m) + extras) — then gives
        // round2(base + extras) = sell_price exactly (d = m = 0), which is also the
        // figure the quote saves. sell_price / line_total are untouched.
        $sell    = (float) ($result['sell_price'] ?? 0);
        $optSell = isset($result['options_sell_total'])
            ? (float) $result['options_sell_total']
            : (float) ($result['extras_total'] ?? 0);
        // Supplier list add-ons (face_value=false) ride through discount+markup on
        // a supplier-priced product — restate each row's amount as sold too.
        $factor = (1 - (float) ($result['discount_percent'] ?? 0) / 100)
                * (1 + (float) ($result['markup_percent']   ?? 0) / 100);
        $isSupplier = function_exists('ps_for_product') && defined('PRICE_SOURCE_SUPPLIER')
            && ps_for_product($pdo, (int) ($result['product_id'] ?? 0)) === PRICE_SOURCE_SUPPLIER;
        if ($isSupplier && !empty($result['extras_applied']) && is_array($result['extras_applied'])) {
            foreach ($result['extras_applied'] as &$exRow) {
                if (is_array($exRow) && ($exRow['face_value'] ?? true) === false) {
                    $exRow['amount_applied'] = round((float) ($exRow['amount_applied'] ?? 0) * $factor, 2);
                }
            }
            unset($exRow);
        }
        $result['extras_total']       = round($optSell, 2);
        $result['base_price']         = round($sell - $optSell, 2);
        $result['subtotal_per_blind'] = round($sell, 2);
        $result['markup_percent']     = 0.0;
        $result['discount_percent']   = 0.0;
        unset($result['options_sell_total']);
    }

    // Anonymous public InstaPrice visitors never get the supplier's name (the
    // page doesn't show it; fabrics-search.php hides it the same way).
    if ($isPublic) {
        unset($result['fabric_supplier']);
    }

    return $result;
}
