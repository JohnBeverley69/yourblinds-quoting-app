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

// Hard delete is office-only: admins and users with can_view_all_customer_jobs.
// A restricted user (fitter) can't erase a booking — they record that it didn't
// happen by setting the status to Cancelled / No-show on the appointment page.
if (!cm_can_view_all_customers($user)) {
    $_SESSION['flash_error'] = 'Only the office can delete appointments — set the status to Cancelled or No-show instead.';
    header('Location: ' . ($id > 0 ? '/calendar/view.php?id=' . $id : '/calendar/index.php'));
    exit;
}

if ($id > 0) {
    $stmt = db()->prepare('DELETE FROM appointments WHERE id = ? AND client_id = ?');
    $stmt->execute([$id, $user['client_id']]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['flash_success'] = 'Appointment deleted.';
    }
}

header('Location: /calendar/index.php');
exit;
