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

if ($supplier === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameter: supplier']);
    exit;
}

$stmt = db()->prepare(
    'SELECT fabric_name, COUNT(*) AS colour_count
       FROM vertical_fabrics
      WHERE client_id     = ?
        AND supplier_name = ?
        AND active        = 1
      GROUP BY fabric_name
      ORDER BY fabric_name'
);
$stmt->execute([$user['client_id'], $supplier]);
$rows = $stmt->fetchAll();

echo json_encode([
    'supplier' => $supplier,
    'fabrics'  => array_map(static fn ($r) => [
        'name'   => (string) $r['fabric_name'],
        'count'  => (int)    $r['colour_count'],
    ], $rows),
]);
