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
require __DIR__ . '/../_partials/appointment_conflict.php';

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

$apptId   = (int)    ($_POST['appointment_id']  ?? 0);
$dateRaw  = (string) ($_POST['appointment_date'] ?? '');

if ($apptId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'appointment_id required']);
    exit;
}

// Permission gate: non-admin users without can_view_all_customer_jobs
// can only move their own appointments. Without this, a restricted
// fitter could crafted-POST to reschedule someone else's job.
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
    // Confirm the appointment is actually assigned to this user before
    // any UPDATE. 404 (not 403) to avoid leaking existence of records
    // belonging to other fitters.
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

// Double-booking guard — dragging onto a date can't put an assigned person
// in two places at once. Only relevant when actually scheduling to a date
// (unschedule → pending tray is always fine). Look up this appointment's
// own assignee / time / duration, then check the target date for an overlap.
// override=1 means the user already saw the warning and chose "Book anyway".
if ($newDate !== null && empty($_POST['override'])) {
    $self = $pdo->prepare(
        'SELECT client_user_id, appointment_time, duration_minutes
           FROM appointments WHERE id = ? AND client_id = ? LIMIT 1'
    );
    $self->execute([$apptId, $clientId]);
    $selfRow = $self->fetch();
    if ($selfRow && $selfRow['client_user_id'] !== null) {
        $clash = appointment_find_conflict(
            $pdo, $clientId, (int) $selfRow['client_user_id'],
            $newDate, (string) $selfRow['appointment_time'],
            (int) ($selfRow['duration_minutes'] ?? 60), $apptId
        );
        if ($clash !== null) {
            // Pull the assignee's name for a friendly message. conflict=true
            // tells the front-end this is an overridable warning, not a hard
            // failure — it confirms, then retries with override=1.
            $an = $pdo->prepare('SELECT full_name FROM client_users WHERE id = ? LIMIT 1');
            $an->execute([(int) $selfRow['client_user_id']]);
            $assigneeName = (string) ($an->fetchColumn() ?: '');
            echo json_encode([
                'ok'       => false,
                'conflict' => true,
                'error'    => appointment_conflict_message($clash, $assigneeName),
            ]);
            exit;
        }
    }
}

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
