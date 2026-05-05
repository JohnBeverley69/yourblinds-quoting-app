<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated.']);
    exit;
}

$user     = current_user();
$supplier = trim((string) ($_GET['supplier'] ?? ''));
$fabric   = trim((string) ($_GET['fabric']   ?? ''));

if ($supplier === '' || $fabric === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters: supplier, fabric']);
    exit;
}

$stmt = db()->prepare(
    'SELECT id, colour_name, band_code
       FROM vertical_fabrics
      WHERE client_id     = ?
        AND supplier_name = ?
        AND fabric_name   = ?
        AND active        = 1
      ORDER BY colour_name'
);
$stmt->execute([$user['client_id'], $supplier, $fabric]);
$rows = $stmt->fetchAll();

echo json_encode([
    'supplier' => $supplier,
    'fabric'   => $fabric,
    'colours'  => array_map(static fn ($r) => [
        'fabric_id' => (int)    $r['id'],
        'colour'    => (string) $r['colour_name'],
        'band'      => (string) $r['band_code'],
    ], $rows),
]);
