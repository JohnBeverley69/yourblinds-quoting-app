<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Location: /admin/users.php');
    exit;
}

csrf_check();

$user = current_user();
$id   = (int) ($_POST['id'] ?? 0);

if ($id <= 0 || $id === $user['user_id']) {
    $_SESSION['flash_error'] = 'You cannot delete that user.';
    header('Location: /admin/users.php');
    exit;
}

// Never let a tenant admin delete a super-admin (platform owner) who sits in
// their tenant — see admin/users_edit.php.
$stmt = db()->prepare(
    is_super_admin()
        ? 'DELETE FROM client_users WHERE id = ? AND client_id = ?'
        : 'DELETE FROM client_users WHERE id = ? AND client_id = ? AND COALESCE(is_super_admin, 0) = 0'
);
$stmt->execute([$id, $user['client_id']]);

if ($stmt->rowCount() > 0) {
    $_SESSION['flash_success'] = 'User deleted. Their existing quotes are kept but unlinked.';
} else {
    $_SESSION['flash_error'] = 'User not found.';
}

header('Location: /admin/users.php');
exit;
