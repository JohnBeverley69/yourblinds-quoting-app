<?php
declare(strict_types=1);

/**
 * AJAX endpoint — current Pending Scheduling list for this tenant.
 *
 * Powers the calendar page's auto-refresh so newly-accepted quotes
 * appear in the tray without the user having to reload. Returned
 * shape matches what calendar/index.php renders server-side.
 *
 * Cheap by design: a single small SELECT on appointments where date
 * IS NULL, scoped to the tenant (and to the user if mine=1 — fitters
 * polling their diary only see their own pending).
 *
 * Response: { ok: true, pending: [ {id, title, town, postcode}, ... ] }
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

echo json_encode(['ok' => true, 'pending' => $pending]);
