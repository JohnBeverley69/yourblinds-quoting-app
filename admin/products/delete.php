<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Location: /admin/products/index.php');
    exit;
}

csrf_check();

$user = current_user();
$id   = (int) ($_POST['id'] ?? 0);

if ($id > 0) {
    $stmt = db()->prepare('DELETE FROM products WHERE id = ? AND client_id = ?');
    $stmt->execute([$id, $user['client_id']]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['flash_success'] = 'Product deleted.';
    }
}

header('Location: /admin/products/index.php');
exit;
