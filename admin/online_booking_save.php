<?php
declare(strict_types=1);

/**
 * Settings → Online booking: save the settings, or (GET ?qr=1) download the
 * booking link as a QR code SVG. The panel is
 * _partials/settings_online_booking_panel.php. Rules: _partials/online_booking.php.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/online_booking.php';

requireAdmin();

$user     = current_user();
$clientId = (int) $user['client_id'];
$back     = '/admin/settings.php#online-booking';

if (!ob_feature_enabled($clientId)) {
    $_SESSION['flash_error'] = 'Online booking is part of the Silver and Gold plans.';
    header('Location: ' . $back);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $slug = (string) (ob_settings($clientId)['slug'] ?? '');
    if (!empty($_GET['qr']) && $slug !== '') {
        require __DIR__ . '/../_partials/qr.php';
        header('Content-Type: image/svg+xml');
        header('Content-Disposition: attachment; filename="booking-qr-' . $slug . '.svg"');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . qr_svg(ob_link($slug), 60.0, 'M');
        exit;
    }
    header('Location: ' . $back);
    exit;
}
csrf_check();

$fail = static function (string $msg) use ($back): void {
    $_SESSION['flash_error'] = $msg;
    header('Location: ' . $back);
    exit;
};

$slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
if (!ob_slug_valid($slug)) $fail('The link name must be 3 to 50 lower-case letters or numbers, with single dashes between words.');
try {
    if (!ob_slug_available($slug, $clientId)) $fail('Another company already uses the link name "' . $slug . '". Please choose another.');
} catch (Throwable $e) {
    $fail('Could not save — has migrate_online_booking.php been run?');
}

$mode = (string) ($_POST['mode'] ?? 'request');
if (!in_array($mode, OB_MODES, true)) $mode = 'request';

$notice = max(0, min(OB_MAX_NOTICE, (int) ($_POST['min_notice_days'] ?? 1)));
$ahead  = max(1, min(OB_MAX_DAYS_AHEAD, (int) ($_POST['max_days_ahead'] ?? 28)));
if ($ahead < $notice) $fail('"Up to" must be further ahead than "Earliest booking".');

$days = [];
foreach ((array) ($_POST['weekdays'] ?? []) as $d) {
    $d = (int) $d;
    if (isset(OB_WEEKDAY_NAMES[$d])) $days[$d] = $d;
}
ksort($days);
if (!$days) $fail('Tick at least one day customers can book.');

$closed = trim(str_replace("\r\n", "\n", (string) ($_POST['closed_dates'] ?? '')));
ob_parse_closed($closed, $badDates);
if ($badDates) $fail('These closed days couldn\'t be read: ' . implode('; ', array_slice($badDates, 0, 5)) . '. Use dates like 24/12/2026, or a range like 24/12/2026 - 2/1/2027.');

$baseRaw = trim((string) ($_POST['base_postcode'] ?? ''));
$base    = $baseRaw === '' ? null : ob_postcode($baseRaw);
if ($baseRaw !== '' && $base === null) $fail('The base postcode isn\'t a full UK postcode.');
$radiusRaw = trim((string) ($_POST['radius_miles'] ?? ''));
$radius    = $radiusRaw === '' ? null : (int) $radiusRaw;
if ($radius !== null && ($radius < 1 || $radius > 300)) $fail('The distance must be between 1 and 300 miles.');
if (($base === null) !== ($radius === null)) $fail('For a distance, fill in both the base postcode and the miles (or leave both empty).');

$districtList = ob_parse_districts((string) ($_POST['districts'] ?? ''), $badDistricts);
if ($badDistricts) $fail('These postcodes couldn\'t be read: ' . implode(', ', array_slice($badDistricts, 0, 5)) . '. Use areas like HU, districts like YO25, or DN14 6.');

$style = (string) ($_POST['booking_style'] ?? 'slots') === 'times' ? 'times' : 'slots';
$visit = (int) ($_POST['visit_minutes'] ?? 60);
if (!in_array($visit, [30, 45, 60, 90, 120, 150, 180], true)) $visit = 60;
$gap = (int) ($_POST['gap_minutes'] ?? 30);
if (!in_array($gap, [0, 15, 30, 45, 60], true)) $gap = 30;
$hours = [];
$isTime = static fn ($t): bool => is_string($t) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) === 1;
foreach (OB_WEEKDAY_NAMES as $d => $lab) {
    $from = substr(trim((string) ($_POST['hours_from'][$d] ?? '09:00')), 0, 5);
    $to   = substr(trim((string) ($_POST['hours_to'][$d] ?? '17:00')), 0, 5);
    if (!$isTime($from) || !$isTime($to)) $fail("$lab's hours couldn't be read — use times like 09:00 and 17:00.");
    if ($style === 'times' && isset($days[$d]) && ob_minutes($from) + $visit > ob_minutes($to)) {
        $fail("On $lab there isn't room for a " . ob_duration_words($visit) . " visit between $from and $to.");
    }
    $hours[(string) $d] = ['from' => $from, 'to' => $to];
}

$notify = trim((string) ($_POST['notify_email'] ?? ''));
if ($notify !== '' && !filter_var($notify, FILTER_VALIDATE_EMAIL)) $fail('Please enter a valid email address for new bookings.');

$intro = mb_substr(trim(str_replace("\r\n", "\n", (string) ($_POST['intro_text'] ?? ''))), 0, 600);

$enabled = !empty($_POST['enabled']) ? 1 : 0;

try {
    db()->prepare(
        'INSERT INTO online_booking_settings
           (client_id, enabled, slug, mode, min_notice_days, max_days_ahead, weekdays, closed_dates,
            base_postcode, radius_miles, districts, intro_text, notify_email,
            booking_style, visit_minutes, gap_minutes, hours_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), slug = VALUES(slug), mode = VALUES(mode),
            min_notice_days = VALUES(min_notice_days), max_days_ahead = VALUES(max_days_ahead),
            weekdays = VALUES(weekdays), closed_dates = VALUES(closed_dates),
            base_postcode = VALUES(base_postcode), radius_miles = VALUES(radius_miles),
            districts = VALUES(districts), intro_text = VALUES(intro_text), notify_email = VALUES(notify_email),
            booking_style = VALUES(booking_style), visit_minutes = VALUES(visit_minutes),
            gap_minutes = VALUES(gap_minutes), hours_json = VALUES(hours_json)'
    )->execute([
        $clientId, $enabled, $slug, $mode, $notice, $ahead, implode(',', $days), $closed !== '' ? $closed : null,
        $base !== null ? ob_postcode_pretty($base) : null, $radius,
        $districtList ? implode(', ', $districtList) : null, $intro !== '' ? $intro : null, $notify !== '' ? $notify : null,
        $style, $visit, $gap, json_encode($hours),
    ]);
} catch (Throwable $e) {
    $fail('Could not save — have migrate_online_booking.php and migrate_online_booking_times.php been run?');
}

[$live, $why] = ob_status($clientId);
if ($enabled && !$live) {
    $_SESSION['flash_error'] = 'Saved, but customers can\'t book yet: ' . $why;
} else {
    $_SESSION['flash_success'] = $enabled ? 'Online booking saved — customers can book at your link.' : 'Online booking saved (switched off).';
}
header('Location: ' . $back);
exit;
