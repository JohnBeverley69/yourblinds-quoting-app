<?php
declare(strict_types=1);

/**
 * AJAX endpoint — current calendar state for the live-refresh loop.
 *
 * Two payloads in one round-trip:
 *   pending  — appointments with NULL date (the Pending Scheduling tray)
 *   grid     — scheduled appointments for ?month=YYYY-MM, grouped by date
 *
 * Powers the calendar page's auto-refresh so newly-accepted quotes,
 * drag-rescheduled appointments and edits made by other team members
 * show up without anyone manually reloading.
 *
 * Tenant-scoped. Honours ?mine=1 (fitters polling their diary see
 * only their own jobs in both halves of the response).
 *
 * Response:
 * {
 *   ok: true,
 *   pending: [ {id, title, town, postcode}, ... ],
 *   grid:    { "YYYY-MM-DD": [ {id, title, time, status, quote_id, town, postcode, customer_name}, ... ], ... }
 * }
 *
 * Cheap by design: two small SELECTs scoped tightly. 15s polling is fine.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user     = current_user();
$clientId = (int) $user['client_id'];
$mineOnly = isset($_GET['mine']) && (string) $_GET['mine'] === '1';

if ($mineOnly) {
    $st = db()->prepare(
        'SELECT a.id, a.title, a.installation_town, a.installation_postcode
           FROM appointments a
          WHERE a.client_id = ?
            AND a.client_user_id = ?
            AND a.appointment_date IS NULL
       ORDER BY a.id DESC'
    );
    $st->execute([$clientId, (int) $user['user_id']]);
} else {
    $st = db()->prepare(
        'SELECT a.id, a.title, a.installation_town, a.installation_postcode
           FROM appointments a
          WHERE a.client_id = ?
            AND a.appointment_date IS NULL
       ORDER BY a.id DESC'
    );
    $st->execute([$clientId]);
}

$pending = [];
foreach ($st->fetchAll() as $r) {
    $pending[] = [
        'id'       => (int) $r['id'],
        'title'    => (string) $r['title'],
        'town'     => (string) ($r['installation_town']     ?? ''),
        'postcode' => (string) ($r['installation_postcode'] ?? ''),
    ];
}

// ---------------------------------------------------------------------------
// Grid payload — only built when the client tells us which month it's
// looking at. Same tenant + mine=1 scoping as the page-render query.
// ---------------------------------------------------------------------------
$grid = [];
$monthParam = (string) ($_GET['month'] ?? '');
if ($monthParam !== '' && preg_match('/^\d{4}-\d{2}$/', $monthParam) === 1) {
    $cursor = DateTimeImmutable::createFromFormat('!Y-m', $monthParam);
    if ($cursor !== false) {
        $first = $cursor->modify('first day of this month')->setTime(0, 0);
        $last  = $cursor->modify('last day of this month')->setTime(23, 59, 59);

        $gridSql = $mineOnly
            ? 'SELECT a.id, a.title, a.appointment_date, a.appointment_time,
                      a.duration_minutes, a.status, a.quote_id,
                      a.installation_town, a.installation_postcode,
                      c.name AS customer_name
                 FROM appointments a
            LEFT JOIN customers c ON c.id = a.customer_id
                WHERE a.client_id = ?
                  AND a.client_user_id = ?
                  AND a.appointment_date BETWEEN ? AND ?
             ORDER BY a.appointment_date, a.appointment_time'
            : 'SELECT a.id, a.title, a.appointment_date, a.appointment_time,
                      a.duration_minutes, a.status, a.quote_id,
                      a.installation_town, a.installation_postcode,
                      c.name AS customer_name
                 FROM appointments a
            LEFT JOIN customers c ON c.id = a.customer_id
                WHERE a.client_id = ?
                  AND a.appointment_date BETWEEN ? AND ?
             ORDER BY a.appointment_date, a.appointment_time'  ;

        $gStmt = db()->prepare($gridSql);
        $gStmt->execute(
            $mineOnly
                ? [$clientId, (int) $user['user_id'], $first->format('Y-m-d'), $last->format('Y-m-d')]
                : [$clientId, $first->format('Y-m-d'), $last->format('Y-m-d')]
        );

        // Format time the same way the JS expects ("9:00am" lowercase).
        $fmt = static function (string $time): string {
            $t = DateTimeImmutable::createFromFormat('H:i:s', $time)
                ?: DateTimeImmutable::createFromFormat('H:i', $time);
            return $t === false ? $time : strtolower($t->format('g:ia'));
        };

        foreach ($gStmt->fetchAll() as $r) {
            $date = (string) $r['appointment_date'];
            $grid[$date][] = [
                'id'            => (int)    $r['id'],
                'title'         => (string) $r['title'],
                'time'          => $fmt((string) $r['appointment_time']),
                'status'        => (string) $r['status'],
                'quote_id'      => $r['quote_id'] !== null ? (int) $r['quote_id'] : null,
                'town'          => (string) ($r['installation_town']     ?? ''),
                'postcode'      => (string) ($r['installation_postcode'] ?? ''),
                'customer_name' => (string) ($r['customer_name']         ?? ''),
            ];
        }
    }
}

echo json_encode(['ok' => true, 'pending' => $pending, 'grid' => (object) $grid]);
