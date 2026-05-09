<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

csrf_check();

$user = current_user();
$ids  = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];

// Filter to positive ints, dedupe, preserve order.
$clean = [];
foreach ($ids as $i) {
    $n = (int) $i;
    if ($n > 0 && !in_array($n, $clean, true)) $clean[] = $n;
}

if (!$clean) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No ids supplied']);
    exit;
}

$pdo = db();
$pdo->beginTransaction();
try {
    $u = $pdo->prepare(
        'UPDATE products SET sort_order = ?
          WHERE id = ? AND client_id = ?'
    );
    foreach ($clean as $position => $id) {
        $u->execute([$position, $id, $user['client_id']]);
    }
    $pdo->commit();
    echo json_encode(['ok' => true, 'count' => count($clean)]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
