<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';
require __DIR__ . '/../engine.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated.']);
    exit;
}

// Accept GET (live preview as the user types) OR POST (form submit / commit).
// Both are read-only — no DB writes here, so CSRF protection is not required.
$src = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;

$user      = current_user();
$productId = (int)    ($src['product_id'] ?? 0);
$supplier  = trim((string) ($src['supplier'] ?? ''));
$fabric    = trim((string) ($src['fabric']   ?? ''));
$colour    = trim((string) ($src['colour']   ?? ''));
$width     = (float)  ($src['width']    ?? 0);
$drop      = (float)  ($src['drop']     ?? 0);
$quantity  = max(1, (int) ($src['quantity'] ?? 1));
// Default to round_up=true for preview — exact-only matching is too strict
// for live UX. Caller can opt out with round_up=0.
$roundUp   = !isset($src['round_up']) || (string) $src['round_up'] !== '0';

if ($productId <= 0 || $supplier === '' || $fabric === '' || $colour === ''
    || $width <= 0 || $drop <= 0
) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing or invalid parameters. '
                 . 'Required: product_id, supplier, fabric, colour, width, drop.',
    ]);
    exit;
}

// Defence in depth: confirm the product belongs to the logged-in client.
$check = db()->prepare(
    'SELECT id FROM products WHERE id = ? AND client_id = ? AND active = 1 LIMIT 1'
);
$check->execute([$productId, $user['client_id']]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Product not found.']);
    exit;
}

$result = pricing_quote_line(
    $user['client_id'],
    $productId,
    $supplier,
    $fabric,
    $colour,
    $width,
    $drop,
    $quantity,
    $roundUp
);

if (isset($result['error'])) {
    http_response_code(422);
    echo json_encode(['error' => $result['error']]);
    exit;
}

echo json_encode($result);
