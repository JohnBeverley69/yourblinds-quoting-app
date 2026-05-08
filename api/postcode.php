<?php
declare(strict_types=1);

/**
 * Server-side proxy for Postcoder UK address lookup.
 *
 * Single-call flow:
 *   ?postcode=BS14ST  ->  { addresses: [{line1, line2, town, county, postcode}, ...] }
 *
 * Postcoder returns full structured addresses in one call (no
 * autocomplete-then-fetch dance like getAddress.io). Auth is by API key
 * embedded in the URL path; we keep it server-side so it never reaches
 * the browser.
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

if (POSTCODER_API_KEY === '') {
    http_response_code(500);
    echo json_encode(['error' => 'Postcode lookup is not configured on the server.']);
    exit;
}

// Normalise input — strip whitespace, uppercase, light sanity check.
// Postcoder accepts partial postcodes too; we only want full ones, so we
// validate the compact UK postcode shape.
$raw      = (string) ($_GET['postcode'] ?? '');
$postcode = strtoupper(preg_replace('/\s+/', '', $raw) ?? '');
if ($postcode === ''
    || !preg_match('/^[A-Z]{1,2}\d[A-Z\d]?\d[A-Z]{2}$/', $postcode)
) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid UK postcode.']);
    exit;
}

$url = 'https://ws.postcoder.com/pcw/' . rawurlencode(POSTCODER_API_KEY)
     . '/address/UK/' . rawurlencode($postcode)
     . '?format=json&lines=2';

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
    // Postcoder returns 404 when the postcode has no matches.
    echo json_encode(['addresses' => []]);
    exit;
}

if ($status !== 200) {
    http_response_code(502);
    echo json_encode(['error' => 'Address service returned ' . $status . '.']);
    exit;
}

$data = json_decode((string) $body, true);
if (!is_array($data)) {
    http_response_code(502);
    echo json_encode(['error' => 'Unexpected response from address service.']);
    exit;
}

$out = [];
foreach ($data as $a) {
    if (!is_array($a)) {
        continue;
    }
    $out[] = [
        'line1'    => trim((string) ($a['addressline1'] ?? '')),
        'line2'    => trim((string) ($a['addressline2'] ?? '')),
        'town'     => trim((string) ($a['posttown']     ?? '')),
        'county'   => trim((string) ($a['county']       ?? '')),
        'postcode' => strtoupper(trim((string) ($a['postcode'] ?? ''))),
    ];
}

echo json_encode(['addresses' => $out]);
