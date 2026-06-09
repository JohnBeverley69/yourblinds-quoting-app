<?php
declare(strict_types=1);

/**
 * AJAX endpoint — flag/clear an appointment's "Issue" from the calendar's
 * quick ⚠ popover. Mirrors save_note.php's auth/CSRF/permission pattern; only
 * touches has_issue + issue_note.
 *
 * Payload:
 *   POST appointment_id (int, required)
 *   POST has_issue      ('1' to flag, '0' to clear)
 *   POST issue_note     (string; the problem detail, optional)
 *   X-CSRF-Token header (or _csrf POST field as fallback)
 *
 * Response: { ok: bool, has_issue?: bool, issue_note?: string, error?: string }
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '');
if (!hash_equals(csrf_token(), $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF token invalid — reload the page.']);
    exit;
}

$user     = current_user();
$clientId = (int) $user['client_id'];
$isAdmin  = ($user['role'] ?? '') === 'admin';

$apptId   = (int) ($_POST['appointment_id'] ?? 0);
$hasIssue = (string) ($_POST['has_issue'] ?? '0') === '1' ? 1 : 0;
$note     = trim((string) ($_POST['issue_note'] ?? ''));
if (mb_strlen($note) > 280) {
    $note = mb_substr($note, 0, 280);
}
// Clearing the flag clears the note; flagging keeps whatever note was given.
$noteToStore = $hasIssue === 1 ? ($note === '' ? null : $note) : null;

if ($apptId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'appointment_id required']);
    exit;
}

// Same permission gate as save_note.php — a restricted user can only touch
// their own appointments.
$canViewAll = $isAdmin;
if (!$canViewAll) {
    $permSt = db()->prepare(
        'SELECT COALESCE(can_view_all_customer_jobs, 0)
           FROM client_users WHERE id = ? AND client_id = ? LIMIT 1'
    );
    $permSt->execute([(int) $user['user_id'], $clientId]);
    $canViewAll = ((int) $permSt->fetchColumn()) === 1;
}
if (!$canViewAll) {
    $ownChk = db()->prepare(
        'SELECT 1 FROM appointments
          WHERE id = ? AND client_id = ? AND client_user_id = ? LIMIT 1'
    );
    $ownChk->execute([$apptId, $clientId, (int) $user['user_id']]);
    if (!$ownChk->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Appointment not found.']);
        exit;
    }
}

$pdo = db();

try {
    $u = $pdo->prepare(
        'UPDATE appointments SET has_issue = ?, issue_note = ?
          WHERE id = ? AND client_id = ? LIMIT 1'
    );
    $u->execute([$hasIssue, $noteToStore, $apptId, $clientId]);
    if ($u->rowCount() === 0) {
        $check = $pdo->prepare('SELECT 1 FROM appointments WHERE id = ? AND client_id = ? LIMIT 1');
        $check->execute([$apptId, $clientId]);
        if (!$check->fetchColumn()) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Appointment not found.']);
            exit;
        }
    }
    echo json_encode(['ok' => true, 'has_issue' => $hasIssue === 1, 'issue_note' => (string) ($noteToStore ?? '')]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save issue: ' . $e->getMessage()]);
}
