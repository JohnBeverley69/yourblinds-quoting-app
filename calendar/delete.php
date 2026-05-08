<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

// POST-only — protects against drive-by GET deletions (image/link prefetch, etc.)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Location: /calendar/index.php');
    exit;
}

csrf_check();

$user = current_user();
$id   = (int) ($_POST['id'] ?? 0);

if ($id > 0) {
    $stmt = db()->prepare('DELETE FROM appointments WHERE id = ? AND client_id = ?');
    $stmt->execute([$id, $user['client_id']]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['flash_success'] = 'Appointment deleted.';
    }
}

header('Location: /calendar/index.php');
exit;
