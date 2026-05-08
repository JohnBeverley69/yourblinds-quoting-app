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

$user      = current_user();
$id        = (int) ($_POST['id']         ?? 0);
$productId = (int) ($_POST['product_id'] ?? 0);

if ($id > 0) {
    // FK cascades wipe price_tables and price_table_rows for this system.
    $stmt = db()->prepare(
        'DELETE FROM product_systems WHERE id = ? AND client_id = ?'
    );
    $stmt->execute([$id, $user['client_id']]);
    if ($stmt->rowCount() > 0) {
        $_SESSION['flash_success'] = 'System deleted.';
    }
}

if ($productId > 0) {
    header('Location: /admin/products/systems.php?product_id=' . $productId);
} else {
    header('Location: /admin/products/index.php');
}
exit;
