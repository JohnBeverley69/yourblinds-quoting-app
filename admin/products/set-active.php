<?php
declare(strict_types=1);

/**
 * Activate / deactivate a product from the Products list — quicker than opening
 * the edit page when toggling several. POST + CSRF, tenant-scoped.
 */

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

$user     = current_user();
$clientId = (int) $user['client_id'];
$id       = (int) ($_POST['id'] ?? 0);
$active   = !empty($_POST['active']) ? 1 : 0;

if ($id > 0) {
    $st = db()->prepare('UPDATE products SET active = ? WHERE id = ? AND client_id = ?');
    $st->execute([$active, $id, $clientId]);
    if ($st->rowCount() > 0) {
        $_SESSION['flash_success'] = $active ? 'Product activated.' : 'Product deactivated.';
    }
}

header('Location: /admin/products/index.php');
exit;
