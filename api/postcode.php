<?php
declare(strict_types=1);

/**
 * Server-side proxy for getAddress.io postcode lookup.
 *
 * Two actions:
 *   ?action=autocomplete&term=BS1+4ST  -> returns { suggestions: [{id, address}, ...] }
 *   ?action=get&id=ABCDEF              -> returns { line1, line2, town, county, postcode }
 *
 * Why a proxy?
 *   - The API key never leaves the server
 *   - Auth + per-client feature-flag check is enforced
 *   - We can rate-limit or log abuse later
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

$action = (string) ($_GET['action'] ?? 'autocomplete');

if ($action === 'autocomplete') {
    autocomplete();
} elseif ($action === 'get') {
    getById();
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown action.']);
}

// ---------------------------------------------------------------------------

function autocomplete(): void
{
    $raw  = (string) ($_GET['term'] ?? '');
    $term = trim($raw);
    if ($term === '' || strlen($term) < 2) {
        http_response_code(400);
        echo json_encode(['error' => 'Search term required (min 2 characters).']);
        return;
    }
    if (strlen($term) > 100) {
        http_response_code(400);
        echo json_encode(['error' => 'Search term too long.']);
        return;
    }

    $url = 'https://api.getAddress.io/autocomplete/' . rawurlencode($term)
         . '?top=20&all=true';

    [$status, $data] = call_getaddress($url);
    if ($status === null) {
        return; // call_getaddress already emitted the JSON error
    }

    if ($status !== 200) {
        http_response_code(502);
        echo json_encode(['error' => 'Address service returned ' . $status . '.']);
        return;
    }

    $suggestions = is_array($data['suggestions'] ?? null) ? $data['suggestions'] : [];
    $out = [];
    foreach ($suggestions as $s) {
        if (!is_array($s)) {
            continue;
        }
        $id = (string) ($s['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $out[] = [
            'id'      => $id,
            'address' => (string) ($s['address'] ?? ''),
        ];
    }
    echo json_encode(['suggestions' => $out]);
}

function getById(): void
{
    $raw = (string) ($_GET['id'] ?? '');
    $id  = trim($raw);
    // getAddress.io ids are short alnum tokens with dashes/underscores. Be strict.
    if ($id === '' || !preg_match('/^[A-Za-z0-9_\-]{1,80}$/', $id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid address id.']);
        return;
    }

    $url = 'https://api.getAddress.io/get/' . rawurlencode($id);

    [$status, $data] = call_getaddress($url);
    if ($status === null) {
        return;
    }

    if ($status !== 200) {
        http_response_code(502);
        echo json_encode(['error' => 'Address service returned ' . $status . '.']);
        return;
    }

    echo json_encode([
        'line1'    => trim((string) ($data['line_1']       ?? '')),
        'line2'    => trim((string) ($data['line_2']       ?? '')),
        'town'     => trim((string) ($data['town_or_city'] ?? '')),
        'county'   => trim((string) ($data['county']       ?? '')),
        'postcode' => strtoupper(trim((string) ($data['postcode'] ?? ''))),
    ]);
}

/**
 * Returns [status, decoded body] on success, [null, null] after emitting an
 * error response on transport failure (so the caller can simply early-return).
 */
function call_getaddress(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'YourBlinds/1.0 (+postcode-lookup)',
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Authorization: api-key ' . GETADDRESS_API_KEY,
        ],
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($body === false || $err !== '') {
        http_response_code(502);
        echo json_encode(['error' => 'Address service unreachable.']);
        return [null, null];
    }

    $decoded = json_decode((string) $body, true);
    if (!is_array($decoded)) {
        $decoded = [];
    }
    return [(int) $status, $decoded];
}
