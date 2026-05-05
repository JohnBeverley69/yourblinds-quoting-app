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

$stmt = db()->prepare('DELETE FROM client_users WHERE id = ? AND client_id = ?');
$stmt->execute([$id, $user['client_id']]);

if ($stmt->rowCount() > 0) {
    $_SESSION['flash_success'] = 'User deleted. Their existing quotes are kept but unlinked.';
} else {
    $_SESSION['flash_error'] = 'User not found.';
}

header('Location: /admin/users.php');
exit;
