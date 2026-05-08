<?php
declare(strict_types=1);

/**
 * Server-side proxy for getAddress.io postcode lookup.
 *
 * Why a proxy and not a direct browser call?
 * - The API key never leaves the server (request originates from the host)
 * - Auth + per-client feature-flag check is enforced
 * - We can rate-limit and log abuse if it ever matters
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated.']);
    exit;
}

$user = current_user();

// Per-client feature gate.
$fStmt = db()->prepare(
    'SELECT COALESCE(feature_postcode_lookup, 0) FROM client_settings WHERE client_id = ?'
);
$fStmt->execute([$user['client_id']]);
if ((int) $fStmt->fetchColumn() !== 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Postcode lookup is not enabled for your account.']);
    exit;
}

if (GETADDRESS_API_KEY === '') {
    http_response_code(500);
    echo json_encode(['error' => 'Postcode lookup is not configured on the server.']);
    exit;
}

// Normalise input: strip whitespace, uppercase. Loose UK-postcode regex
// (rejects obvious junk; getAddress.io will validate further).
$raw      = (string) ($_GET['postcode'] ?? '');
$postcode = strtoupper(preg_replace('/\s+/', '', $raw) ?? '');
if ($postcode === ''
    || !preg_match('/^[A-Z]{1,2}\d[A-Z\d]?\d[A-Z]{2}$/', $postcode)
) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid UK postcode.']);
    exit;
}

$url = 'https://api.getAddress.io/find/' . urlencode($postcode)
     . '?api-key=' . urlencode(GETADDRESS_API_KEY)
     . '&expand=true';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_USERAGENT      => 'YourBlinds/1.0 (+postcode-lookup)',
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
]);
$body   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err    = curl_error($ch);
curl_close($ch);

if ($body === false || $err !== '') {
    http_response_code(502);
    echo json_encode(['error' => 'Address service unreachable.']);
    exit;
}

if ($status === 404) {
    // No matches — not an error, just return empty list.
    echo json_encode(['addresses' => []]);
    exit;
}

if ($status !== 200) {
    http_response_code(502);
    echo json_encode(['error' => 'Address service returned ' . $status . '.']);
    exit;
}

$data = json_decode((string) $body, true);
if (!is_array($data) || !isset($data['addresses']) || !is_array($data['addresses'])) {
    http_response_code(502);
    echo json_encode(['error' => 'Unexpected response from address service.']);
    exit;
}

$out      = [];
$displayP = strtoupper((string) ($data['postcode'] ?? $postcode));
foreach ($data['addresses'] as $a) {
    if (!is_array($a)) {
        continue;
    }
    $out[] = [
        'line1'    => trim((string) ($a['line_1']       ?? '')),
        'line2'    => trim((string) ($a['line_2']       ?? '')),
        'town'     => trim((string) ($a['town_or_city'] ?? '')),
        'county'   => trim((string) ($a['county']       ?? '')),
        'postcode' => $displayP,
    ];
}

echo json_encode(['addresses' => $out]);
