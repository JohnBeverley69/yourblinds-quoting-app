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

$user    = current_user();
$id      = (int) ($_POST['id']       ?? 0);
$extraId = (int) ($_POST['extra_id'] ?? 0);

if ($id > 0) {
    // Tenant-scoped DELETE: join through to product_extras to scope by client_id.
    $stmt = db()->prepare(
        'DELETE c FROM product_extra_choices c
           JOIN product_extras e ON e.id = c.product_extra_id
          WHERE c.id = ? AND e.client_id = ?'
    );
    $stmt->execute([$id, $user['client_id']]);
    if ($stmt->rowCount() > 0) {
        $_SESSION['flash_success'] = 'Choice deleted.';
    }
}

if ($extraId > 0) {
    header('Location: /admin/products/extra.php?id=' . $extraId);
} else {
    header('Location: /admin/products/index.php');
}
exit;
