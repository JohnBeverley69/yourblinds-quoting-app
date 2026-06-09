<?php
declare(strict_types=1);

/**
 * AJAX endpoint — current calendar state for the live-refresh loop.
 *
 * Two payloads in one round-trip:
 *   pending  — appointments with NULL date (the Pending Fitting tray)
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
$isAdmin  = ($user['role'] ?? '') === 'admin';
$mineOnly = isset($_GET['mine']) && (string) $_GET['mine'] === '1';

// Same permission gate as /calendar/index.php — non-admin users
// without can_view_all_customer_jobs are forced to mine-only here too,
// regardless of what the polling URL sends. Without this, the JS
// auto-refresh would silently leak everyone's appointments back to a
// restricted fitter ~5s after page load.
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
    $mineOnly = true;
}

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
// Grid payload — only built when the client tells us which date range
// it's looking at. Same tenant + mine=1 scoping as the page-render query.
//
// Two accepted shapes (in order of preference):
//   ?start=YYYY-MM-DD&end=YYYY-MM-DD   (rolling-6-weeks view)
//   ?month=YYYY-MM                      (legacy month view)
// ---------------------------------------------------------------------------
$grid       = [];
$startParam = (string) ($_GET['start'] ?? '');
$endParam   = (string) ($_GET['end']   ?? '');
$monthParam = (string) ($_GET['month'] ?? '');
$first = null;
$last  = null;

if ($startParam !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startParam) === 1
 && $endParam   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endParam)   === 1) {
    $maybeStart = DateTimeImmutable::createFromFormat('!Y-m-d', $startParam);
    $maybeEnd   = DateTimeImmutable::createFromFormat('!Y-m-d', $endParam);
    if ($maybeStart !== false && $maybeEnd !== false) {
        $first = $maybeStart->setTime(0, 0);
        $last  = $maybeEnd->setTime(23, 59, 59);
    }
} elseif ($monthParam !== '' && preg_match('/^\d{4}-\d{2}$/', $monthParam) === 1) {
    $cursor = DateTimeImmutable::createFromFormat('!Y-m', $monthParam);
    if ($cursor !== false) {
        $first = $cursor->modify('first day of this month')->setTime(0, 0);
        $last  = $cursor->modify('last day of this month')->setTime(23, 59, 59);
    }
}

if ($first !== null && $last !== null) {
    $gridSql = $mineOnly
        ? 'SELECT a.id, a.title, a.appointment_date, a.appointment_time,
                  a.duration_minutes, a.status, a.appt_kind, a.quote_id, a.access_note,
                  a.installation_town, a.installation_postcode,
                  c.name AS customer_name,
                  q.status AS quote_status
             FROM appointments a
        LEFT JOIN customers c ON c.id = a.customer_id
        LEFT JOIN quotes q ON q.id = a.quote_id
            WHERE a.client_id = ?
              AND a.client_user_id = ?
              AND a.appointment_date BETWEEN ? AND ?
         ORDER BY a.appointment_date, a.appointment_time'
        : 'SELECT a.id, a.title, a.appointment_date, a.appointment_time,
                  a.duration_minutes, a.status, a.appt_kind, a.quote_id, a.access_note,
                  a.installation_town, a.installation_postcode,
                  c.name AS customer_name,
                  q.status AS quote_status
             FROM appointments a
        LEFT JOIN customers c ON c.id = a.customer_id
        LEFT JOIN quotes q ON q.id = a.quote_id
            WHERE a.client_id = ?
              AND a.appointment_date BETWEEN ? AND ?
         ORDER BY a.appointment_date, a.appointment_time';

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

    $fittingsOnly = !empty(current_user_permissions()['can_view_fittings_only']);
    foreach ($gStmt->fetchAll() as $r) {
        if ($fittingsOnly && (string) ($r['appt_kind'] ?? 'measure') !== 'fitting') continue;
        $date = (string) $r['appointment_date'];
        $grid[$date][] = [
            'id'            => (int)    $r['id'],
            'title'         => (string) $r['title'],
            'time'          => $fmt((string) $r['appointment_time']),
            'status'        => (string) $r['status'],
            'appt_kind'     => (string) ($r['appt_kind'] ?? 'measure'),
            'quote_status'  => $r['quote_status'] !== null ? (string) $r['quote_status'] : null,
            'quote_id'      => $r['quote_id'] !== null ? (int) $r['quote_id'] : null,
            'access_note'   => (string) ($r['access_note']           ?? ''),
            'town'          => (string) ($r['installation_town']     ?? ''),
            'postcode'      => (string) ($r['installation_postcode'] ?? ''),
            'customer_name' => (string) ($r['customer_name']         ?? ''),
        ];
    }
}

echo json_encode(['ok' => true, 'pending' => $pending, 'grid' => (object) $grid]);
