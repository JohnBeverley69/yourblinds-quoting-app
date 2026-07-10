<?php
declare(strict_types=1);

/**
 * AJAX endpoint — remaining AM/PM slot capacity for a given date.
 *
 * Used by the booking form (calendar/new.php, calendar/edit.php) to update the
 * "Morning — 2 of 4 left" hints live when the user changes the date, without a
 * page reload. Server-side capacity is still enforced on save; this is UX only.
 *
 * Tenant-scoped. Only meaningful when feature_ampm_slots is on; if it's off the
 * response reports the feature disabled and the form falls back to the time
 * picker.
 *
 * Request:  ?date=YYYY-MM-DD[&exclude=<appointment id being edited>]
 * Response:
 * {
 *   ok: true,
 *   enabled: true,
 *   capacity: 4,
 *   windows: {
 *     am: {label:"Morning (9am–1pm)",   taken:2, remaining:2, full:false},
 *     pm: {label:"Afternoon (1pm–5pm)", taken:4, remaining:0, full:true}
 *   }
 * }
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/slot_window.php';

requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user     = current_user();
$clientId = (int) $user['client_id'];
$pdo      = db();

$cfg = ampm_settings($pdo, $clientId);
if (!$cfg['on']) {
    echo json_encode(['ok' => true, 'enabled' => false]);
    exit;
}

$date = (string) ($_GET['date'] ?? '');
$d    = DateTimeImmutable::createFromFormat('Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid or missing date.']);
    exit;
}

$excludeId = (int) ($_GET['exclude'] ?? 0);
$avail     = ampm_availability($pdo, $clientId, $date, $cfg['capacity'], $excludeId);

$windows = [];
foreach ($avail as $w => $info) {
    $windows[$w] = [
        'label'     => ampm_window_label($w),
        'taken'     => $info['taken'],
        'remaining' => $info['remaining'],
        'full'      => $info['full'],
    ];
}

echo json_encode([
    'ok'       => true,
    'enabled'  => true,
    'capacity' => $cfg['capacity'],
    'windows'  => $windows,
]);
