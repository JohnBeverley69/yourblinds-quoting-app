<?php
declare(strict_types=1);

/**
 * Multi-blind-in-one-fascia pricing (roller): the shared join gap, the fascia
 * option metadata and the group pricer. Read-only (no writes), so the same file
 * runs on the server (quote-builder/_helpers.php, preview) and on the tablet
 * offline (offline/engine_bundle.php).
 */

// Callers load _partials/pricing_engine.php themselves (pe_calculate_item), as
// they always have: several use a plain require, so requiring it here too would
// redeclare its functions.

/**
 * Editable per-gap join allowance (mm) for a shared fascia. allowance_rows is a
 * global set; default 0 (blinds butt together).
 */
function qb_fascia_join_gap(PDO $pdo): float
{
    try {
        $g = $pdo->prepare("SELECT value FROM allowance_rows WHERE table_name='roller_fascia_join' AND key_norm='gap' LIMIT 1");
        $g->execute();
        $v = $g->fetchColumn();
        if ($v !== false && $v !== null) return (float) $v;
    } catch (Throwable $e) { /* default */ }
    return 0.0;
}

/**
 * Resolve a product's fascia-sharing option metadata: the Fascia Options extra +
 * its real/No-Fascia choices, the "Multiple Blinds in One Fascia" extra + its Yes
 * choice, the "Fascia width" (is_width_source) extra the carrier carries the group
 * total on, and the fascia-colour child extras to drop on a No-Fascia member.
 * Returns null when the product has no Fascia Options extra. Cached per request.
 */
function qb_resolve_fascia_meta(PDO $pdo, int $productId): ?array
{
    static $cache = [];
    if (array_key_exists($productId, $cache)) return $cache[$productId];
    $out = ['fascia_extra' => 0, 'no_fascia_choice' => 0, 'fascia_real_choices' => [],
            'multiple_extra' => 0, 'multiple_yes' => 0, 'child_extras' => [], 'fascia_width_extra' => 0];
    $fe = $pdo->prepare("SELECT id FROM product_extras WHERE product_id = ? AND name = 'Fascia Options' LIMIT 1");
    $fe->execute([$productId]);
    $out['fascia_extra'] = (int) $fe->fetchColumn();
    $fw = $pdo->prepare("SELECT id FROM product_extras WHERE product_id = ? AND name = 'Fascia width' LIMIT 1");
    $fw->execute([$productId]);
    $out['fascia_width_extra'] = (int) $fw->fetchColumn();
    if ($out['fascia_extra'] > 0) {
        $ch = $pdo->prepare("SELECT id, label FROM product_extra_choices WHERE product_extra_id = ?");
        $ch->execute([$out['fascia_extra']]);
        foreach ($ch->fetchAll(PDO::FETCH_ASSOC) as $c) {
            if (strcasecmp(trim((string) $c['label']), 'No Fascia') === 0) $out['no_fascia_choice'] = (int) $c['id'];
            else $out['fascia_real_choices'][(int) $c['id']] = (string) $c['label'];
        }
    }
    $me = $pdo->prepare("SELECT id FROM product_extras WHERE product_id = ? AND name = 'Multiple Blinds in One Fascia' LIMIT 1");
    $me->execute([$productId]);
    $out['multiple_extra'] = (int) $me->fetchColumn();
    if ($out['multiple_extra'] > 0) {
        $yc = $pdo->prepare("SELECT id FROM product_extra_choices WHERE product_extra_id = ? AND label = 'Yes' LIMIT 1");
        $yc->execute([$out['multiple_extra']]);
        $out['multiple_yes'] = (int) $yc->fetchColumn();
    }
    $ce = $pdo->prepare("SELECT id, name FROM product_extras WHERE product_id = ?");
    $ce->execute([$productId]);
    foreach ($ce->fetchAll(PDO::FETCH_ASSOC) as $x) {
        if (in_array(strtolower(trim((string) $x['name'])),
                ['senses profile colour', 'senses end cap colour', 'll profile colour', 'll end cap colour'], true)) {
            $out['child_extras'][] = (int) $x['id'];
        }
    }
    return $cache[$productId] = ($out['fascia_extra'] > 0 ? $out : null);
}

/**
 * Price a multi-blind-in-one-fascia GROUP without touching the database, applying
 * the same "fascia once" rule as qb_reconcile_fascia_groups: the first blind is the
 * carrier (keeps the real fascia + carries the group total width on the Fascia width
 * option); every other blind is switched to No Fascia (fascia colours dropped); all
 * get "Multiple Blinds in One Fascia = Yes". Each blind is priced at its own width
 * and drop, so per-blind extras (motors, bottom bars, chains, fabric) are charged
 * per blind and the fascia is charged once.
 *
 * $baseInput is a standard pe_calculate_item input (product_id, system_id, option_id,
 * extras[], quantity, round_up). $blinds is [['width_mm'=>int,'drop_mm'=>int], ...].
 * Returns per-blind prices, the group total, the fascia width used, and whether the
 * blinds fit (sum of widths <= the typed fascia width; not checked when none typed).
 * The single source of truth for multi-blind pricing across preview + both saves.
 */
function qb_price_multi_fascia(PDO $pdo, int $clientId, array $baseInput, array $blinds, int $accountClientId = 0): array
{
    $blinds     = array_values($blinds);
    $productId  = (int) ($baseInput['product_id'] ?? 0);
    $baseExtras = array_values($baseInput['extras'] ?? []);
    $meta       = qb_resolve_fascia_meta($pdo, $productId);
    $gap        = qb_fascia_join_gap($pdo);

    $sum = 0.0;
    foreach ($blinds as $b) $sum += (float) ($b['width_mm'] ?? 0);

    // Typed overall fascia width (the "Fascia width" option's user_value), if any.
    $typed = 0.0;
    if ($meta && $meta['fascia_width_extra'] > 0) {
        foreach ($baseExtras as $row) {
            if ((int) ($row['extra_id'] ?? 0) === $meta['fascia_width_extra'] && (float) ($row['user_value'] ?? 0) > 0) {
                $typed = (float) $row['user_value']; break;
            }
        }
    }
    $total = $typed > 0 ? $typed : ($sum + max(0, count($blinds) - 1) * $gap);

    // The real fascia choice the user picked (applies to the carrier).
    $carrierFasciaChoice = 0;
    if ($meta) {
        foreach ($baseExtras as $row) {
            if ((int) ($row['extra_id'] ?? 0) === $meta['fascia_extra']
                && isset($meta['fascia_real_choices'][(int) ($row['choice_id'] ?? 0)])) {
                $carrierFasciaChoice = (int) $row['choice_id']; break;
            }
        }
    }
    $canDedup = ($meta && $meta['no_fascia_choice'] > 0 && $carrierFasciaChoice > 0);

    $out = [
        'blinds' => [], 'total' => 0.0,
        'fascia_width_mm' => (int) round($total), 'sum_widths_mm' => (int) round($sum),
        'checked' => ($typed > 0), 'fits' => ($typed > 0 ? ($sum <= $typed + 0.5) : true),
        'carrier_index' => 0, 'has_fascia' => ($carrierFasciaChoice > 0),
    ];

    foreach ($blinds as $i => $b) {
        $isCarrier = ($i === 0);
        $extras = $baseExtras;
        if ($canDedup) {
            $kept = [];
            foreach ($baseExtras as $row) {
                $eid = (int) ($row['extra_id'] ?? 0);
                if ($eid === $meta['fascia_extra'])   continue;
                if ($eid === $meta['multiple_extra'])  continue;
                if ($meta['fascia_width_extra'] > 0 && $eid === $meta['fascia_width_extra']) continue;
                if (!$isCarrier && in_array($eid, $meta['child_extras'], true)) continue;
                $kept[] = $row;
            }
            if ($isCarrier) {
                $kept[] = ['extra_id' => $meta['fascia_extra'], 'choice_id' => $carrierFasciaChoice];
                if ($meta['fascia_width_extra'] > 0 && $total > 0) {
                    $kept[] = ['extra_id' => $meta['fascia_width_extra'], 'choice_id' => 0, 'user_value' => $total];
                }
            } else {
                $kept[] = ['extra_id' => $meta['fascia_extra'], 'choice_id' => $meta['no_fascia_choice']];
            }
            if ($meta['multiple_extra'] > 0 && $meta['multiple_yes'] > 0) {
                $kept[] = ['extra_id' => $meta['multiple_extra'], 'choice_id' => $meta['multiple_yes']];
            }
            $extras = $kept;
        }

        $input = $baseInput;
        $input['width_mm'] = (int) ($b['width_mm'] ?? 0);
        $input['drop_mm']  = (int) ($b['drop_mm'] ?? ($baseInput['drop_mm'] ?? 0));
        $input['extras']   = array_values($extras);
        $priced = pe_calculate_item($pdo, $clientId, $input, $accountClientId);
        $err = $priced['error'] ?? null;
        $lineTotal = $err ? 0.0 : (float) ($priced['line_total'] ?? 0);
        $out['total'] += $lineTotal;
        $out['blinds'][] = [
            'width_mm' => $input['width_mm'], 'drop_mm' => $input['drop_mm'], 'is_carrier' => $isCarrier,
            'sell_price' => $err ? 0.0 : (float) ($priced['sell_price'] ?? 0),
            'line_total' => $lineTotal, 'error' => $err,
        ];
    }
    return $out;
}
