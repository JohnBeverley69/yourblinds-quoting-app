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
    // FK cascade wipes product_extra_choices for this extra.
    $stmt = db()->prepare(
        'DELETE FROM product_extras WHERE id = ? AND client_id = ?'
    );
    $stmt->execute([$id, $user['client_id']]);
    if ($stmt->rowCount() > 0) {
        $_SESSION['flash_success'] = 'Extra deleted.';
    }
}

if ($productId > 0) {
    header('Location: /admin/products/extras.php?product_id=' . $productId);
} else {
    header('Location: /admin/products/index.php');
}
exit;
