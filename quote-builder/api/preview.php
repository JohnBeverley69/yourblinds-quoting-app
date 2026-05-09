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

requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user     = current_user();
$clientId = (int) $user['client_id'];

// Free-text width / drop, parsed via the shared dimension parser (mm/cm/m/in).
$widthRaw = (string) ($_GET['width'] ?? '');
$dropRaw  = (string) ($_GET['drop']  ?? '');
$widthMm  = ptp_parse_dimension($widthRaw);
$dropMm   = ptp_parse_dimension($dropRaw);

if ($widthMm === null) {
    echo json_encode(['error' => 'Could not read width "' . $widthRaw . '".', 'stage' => 'input']);
    exit;
}
if ($dropMm === null) {
    echo json_encode(['error' => 'Could not read drop "' . $dropRaw . '".', 'stage' => 'input']);
    exit;
}

// Extras come in as extras[N][extra_id] / extras[N][choice_id].
$extras = [];
if (isset($_GET['extras']) && is_array($_GET['extras'])) {
    foreach ($_GET['extras'] as $e) {
        if (!is_array($e)) continue;
        $eid = (int) ($e['extra_id']  ?? 0);
        $cid = (int) ($e['choice_id'] ?? 0);
        if ($eid > 0 && $cid > 0) {
            $extras[] = ['extra_id' => $eid, 'choice_id' => $cid];
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
echo json_encode($result);
