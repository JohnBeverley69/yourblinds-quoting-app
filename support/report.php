<?php
declare(strict_types=1);

/**
 * POST endpoint for the "Report a problem" widget (_partials/support_widget.php).
 *
 * Saves a support_tickets row with what the user typed plus the auto-captured
 * context (page, version, browser, recent JS errors, last clicks), then emails
 * the platform owner. Always answers JSON — the widget shows the result inline.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/support.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$reply = static function (int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $reply(405, ['ok' => false, 'error' => 'POST only.']);
}
if (!is_logged_in()) {
    $reply(401, ['ok' => false, 'error' => 'Your session has expired — sign in again, then send the report.']);
}
$supplied = $_POST['_csrf'] ?? '';
if (!is_string($supplied) || !hash_equals(csrf_token(), $supplied)) {
    $reply(419, ['ok' => false, 'error' => 'This page has expired — refresh it and try again.']);
}
// Master pause switch: while paused the widget is hidden, but refuse here too so
// a hand-crafted POST can't file a ticket before the owner has gone live.
if (support_widget_paused()) {
    $reply(403, ['ok' => false, 'error' => 'Support isn\'t open just yet — please email hello@yourblinds.uk.']);
}

// Light per-session throttle: 10 reports an hour is plenty for a real person
// and stops a stuck script flooding the inbox (and John's email).
$now    = time();
$recent = array_values(array_filter(
    (array) ($_SESSION['_support_sent'] ?? []),
    static fn ($t) => is_int($t) && $t > $now - 3600
));
if (count($recent) >= 10) {
    $reply(429, ['ok' => false, 'error' => 'That\'s a lot of reports in an hour — please email hello@yourblinds.uk instead.']);
}

$message = support_clip($_POST['message'] ?? '', 5000);
if ($message === '') {
    $reply(422, ['ok' => false, 'error' => 'Please describe the problem first.']);
}

$ticketId = support_create_ticket(current_user(), (string) ($_POST['category'] ?? 'problem'), $message, support_capture_ctx($_POST), 'form');
if ($ticketId === null) {
    $reply(500, ['ok' => false, 'error' => 'Sorry — the report couldn\'t be saved. Please email hello@yourblinds.uk.']);
}

$recent[] = $now;
$_SESSION['_support_sent'] = $recent;

$reply(200, ['ok' => true, 'id' => $ticketId]);
