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

$user = current_user();

$stmt = db()->prepare(
    'SELECT supplier_name, COUNT(*) AS fabric_count
       FROM vertical_fabrics
      WHERE client_id = ? AND active = 1
      GROUP BY supplier_name
      ORDER BY supplier_name'
);
$stmt->execute([$user['client_id']]);
$rows = $stmt->fetchAll();

echo json_encode([
    'suppliers' => array_map(static fn ($r) => [
        'name'   => (string) $r['supplier_name'],
        'count'  => (int)    $r['fabric_count'],
    ], $rows),
]);
