<?php
declare(strict_types=1);

/**
 * AJAX endpoint for the calendar's drag-and-drop rescheduling.
 *
 * Two flows:
 *   - Drag from the Pending Fitting tray onto a calendar date
 *     → set appointment_date to that date.
 *   - Drag from one calendar date to another → set appointment_date.
 *   - Drag from a calendar date back to the tray (drop on the tray
 *     panel) → set appointment_date NULL ("unschedule").
 *
 * The endpoint only ever touches appointment_date. Time, duration,
 * fitter assignment, status etc. all stay as-is — those are still
 * edited via /calendar/edit.php for anything more involved than a
 * date move.
 *
 * Payload:
 *   POST appointment_id  (int, required)
 *   POST appointment_date (YYYY-MM-DD, or empty string to unschedule)
 *   X-CSRF-Token header (or _csrf POST field as fallback)
 *
 * Response: { ok: bool, error?: string }
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

$apptId   = (int)    ($_POST['appointment_id']  ?? 0);
$dateRaw  = (string) ($_POST['appointment_date'] ?? '');

if ($apptId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'appointment_id required']);
    exit;
}

// Empty date = unschedule (back to the pending tray). Otherwise must
// match YYYY-MM-DD and resolve to a real calendar date.
$newDate = null;
if ($dateRaw !== '') {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $dateRaw);
    if ($d === false || $d->format('Y-m-d') !== $dateRaw) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid date format. Expected YYYY-MM-DD.']);
        exit;
    }
    $newDate = $d->format('Y-m-d');
}

$pdo = db();

try {
    // Tenant scope check + only-touch-it-if-it-belongs-to-us.
    $u = $pdo->prepare(
        'UPDATE appointments
            SET appointment_date = ?
          WHERE id = ? AND client_id = ?
          LIMIT 1'
    );
    $u->execute([$newDate, $apptId, $clientId]);
    if ($u->rowCount() === 0) {
        // Either the row doesn't belong to this client OR the date
        // was already that value. Re-check existence to disambiguate.
        $check = $pdo->prepare(
            'SELECT 1 FROM appointments WHERE id = ? AND client_id = ? LIMIT 1'
        );
        $check->execute([$apptId, $clientId]);
        if (!$check->fetchColumn()) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Appointment not found.']);
            exit;
        }
        // Same date as before — silently ok, no work needed.
    }

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not reschedule: ' . $e->getMessage()]);
}
