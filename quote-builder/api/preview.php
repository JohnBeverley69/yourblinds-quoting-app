<?php
declare(strict_types=1);

/**
 * Live-price preview endpoint for the quote-builder line-item form.
 *
 * Wraps the pricing engine (`pe_calculate_item`) over a JSON layer.
 * Accepts free-text width/drop and parses them through ptp_parse_dimension
 * so the user can type "1500", "150cm", "1.5m", "60in", etc.
 *
 * Tenant-scoped via the logged-in user's client_id.
 *
 * GET /quote-builder/api/preview.php
 *   ?product_id=N
 *   &system_id=N         (optional)
 *   &option_id=N         (the fabric)
 *   &width=...           (free-text — mm/cm/m/inches accepted)
 *   &drop=...
 *   &quantity=N          (defaults to 1)
 *   &round_up=1          (1 to enable round-up to next cell)
 *   &extras[N][extra_id]=X
 *   &extras[N][choice_id]=Y
 *
 * Response: JSON. On success, the engine's full breakdown.
 *           On failure, {"error": "...", "stage": "input"|"engine"}.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';
require __DIR__ . '/../../_partials/pricing_engine.php';
require __DIR__ . '/../../_partials/price_table_parser.php';
require __DIR__ . '/../../_partials/units.php';

requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user     = current_user();
$clientId = (int) $user['client_id'];

// Free-text width / drop, parsed via the shared dimension parser. A bare
// number is read in the caller's unit (the quote / tenant setting, passed
// as &unit=); explicit suffixes still override. Defaults to mm.
$unit     = unit_is_valid($_GET['unit'] ?? null) ? (string) $_GET['unit'] : 'mm';
$widthRaw = (string) ($_GET['width'] ?? '');
$dropRaw  = (string) ($_GET['drop']  ?? '');
$widthMm  = ptp_parse_dimension($widthRaw, $unit);
$dropMm   = ptp_parse_dimension($dropRaw, $unit);

// A blank width or drop is allowed through as 0 — some products have no
// width (per-slat) or no drop (width-only). The engine decides which are
// actually required. Only a non-blank, unparseable value is a hard error.
if ($widthMm === null) {
    if (trim($widthRaw) === '') {
        $widthMm = 0;
    } else {
        echo json_encode(['error' => 'Could not read width "' . $widthRaw . '".', 'stage' => 'input']);
        exit;
    }
}
if ($dropMm === null) {
    if (trim($dropRaw) === '') {
        $dropMm = 0;
    } else {
        echo json_encode(['error' => 'Could not read drop "' . $dropRaw . '".', 'stage' => 'input']);
        exit;
    }
}

// Each entry has extra_id + either choice_id (single-pick) or
// choice_ids[] (multi-pick). user_value optional. See add_item.php
// for the full notes — same parsing shape.
$extras = [];
if (isset($_GET['extras']) && is_array($_GET['extras'])) {
    foreach ($_GET['extras'] as $e) {
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
    'product_id' => (int) ($_GET['product_id'] ?? 0),
    'system_id'  => (int) ($_GET['system_id']  ?? 0),
    'option_id'  => (int) ($_GET['option_id']  ?? 0),
    'width_mm'   => $widthMm,
    'drop_mm'    => $dropMm,
    'quantity'   => (int) ($_GET['quantity']   ?? 1),
    'extras'     => $extras,
    'round_up'   => !empty($_GET['round_up']),
];

$result = pe_calculate_item(db(), $clientId, $input);
if (isset($result['error'])) {
    $result['stage'] = 'engine';
}

// Strip wholesale-cost figures for users who aren't allowed to see costs.
// The UI never displays these (cost_price_per_blind / extras_cost_total /
// per-extra cost_snapshot reveal what the business pays its suppliers), but
// the raw API response carried them to anyone logged in. The front-end
// doesn't read these fields, so removing them changes nothing it needs.
$isAdmin   = ($user['role'] ?? '') === 'admin';
$canCosts  = $isAdmin || !empty(current_user_permissions()['can_view_costs']);
if (!$canCosts && !isset($result['error'])) {
    unset($result['cost_price_per_blind'], $result['extras_cost_total']);
    if (!empty($result['extras_applied']) && is_array($result['extras_applied'])) {
        foreach ($result['extras_applied'] as &$exRow) {
            if (is_array($exRow)) unset($exRow['cost_snapshot']);
        }
        unset($exRow);
    }
}

echo json_encode($result);
