<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/customer_access.php';

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
    // A restricted user (fitter) may only delete their own appointments —
    // same rule as reschedule.php / edit.php.
    $sql    = 'DELETE FROM appointments WHERE id = ? AND client_id = ?';
    $params = [$id, $user['client_id']];
    if (!cm_can_view_all_customers($user)) {
        $sql     .= ' AND client_user_id = ?';
        $params[] = (int) $user['user_id'];
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        $_SESSION['flash_success'] = 'Appointment deleted.';
    }
}

header('Location: /calendar/index.php');
exit;
