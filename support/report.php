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
require_once __DIR__ . '/../mailer.php';

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

$clip = static function ($v, int $max): string {
    $s = trim(str_replace("\0", '', (string) $v));
    return function_exists('mb_substr') ? mb_substr($s, 0, $max) : substr($s, 0, $max);
};

$message = $clip($_POST['message'] ?? '', 5000);
if ($message === '') {
    $reply(422, ['ok' => false, 'error' => 'Please describe the problem first.']);
}
$category = (string) ($_POST['category'] ?? 'problem');
if (!array_key_exists($category, support_categories())) {
    $category = 'problem';
}

// Client-captured JSON lists — re-encode after decoding so only well-formed,
// size-capped data is stored.
$jsonList = static function ($raw, int $maxItems) use ($clip): ?string {
    $arr = json_decode((string) $raw, true);
    if (!is_array($arr) || !$arr) return null;
    $out = [];
    foreach (array_slice(array_values($arr), -$maxItems) as $item) {
        if (!is_array($item)) continue;
        $row = [];
        foreach ($item as $k => $v) {
            if (!is_scalar($v)) continue;
            $row[$clip($k, 20)] = $clip($v, 1000);
        }
        if ($row) $out[] = $row;
    }
    return $out ? json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
};

$user   = current_user();
$email  = null;
try {
    $st = db()->prepare('SELECT email FROM client_users WHERE id = ? LIMIT 1');
    $st->execute([(int) $user['user_id']]);
    $email = ($v = $st->fetchColumn()) !== false ? (string) $v : null;
} catch (Throwable $e) { /* optional */ }

$row = [
    'client_id'    => (int) $user['client_id'],
    'user_id'      => (int) $user['user_id'],
    'user_name'    => $clip($user['full_name'], 190),
    'company_name' => $clip($user['company_name'], 190),
    'user_email'   => $email !== null ? $clip($email, 190) : null,
    'category'     => $category,
    'message'      => $message,
    // Only a real http(s) address is kept — it's rendered as a link in the inbox.
    'page_url'     => preg_match('#^https?://#i', (string) ($_POST['page_url'] ?? ''))
                        ? $clip($_POST['page_url'], 1000) : '',
    'page_title'   => $clip($_POST['page_title'] ?? '', 255),
    'app_version'  => support_app_version(),
    'user_agent'   => $clip($_SERVER['HTTP_USER_AGENT'] ?? '', 500),
    'viewport'     => $clip($_POST['viewport'] ?? '', 40),
    'js_errors'    => $jsonList($_POST['js_errors'] ?? '', 10),
    'breadcrumbs'  => $jsonList($_POST['breadcrumbs'] ?? '', 20),
];

try {
    $cols = array_keys($row);
    $st = db()->prepare(
        'INSERT INTO support_tickets (' . implode(', ', $cols) . ')
         VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')'
    );
    $st->execute(array_values($row));
    $ticketId = (int) db()->lastInsertId();
} catch (Throwable $e) {
    error_log('[YourBlinds] support ticket insert failed (run migrate_support_tickets.php?): ' . $e->getMessage());
    $reply(500, ['ok' => false, 'error' => 'Sorry — the report couldn\'t be saved. Please email hello@yourblinds.uk.']);
}

$recent[] = $now;
$_SESSION['_support_sent'] = $recent;

// Email the owner. Best-effort: the ticket is already saved, so a mail
// failure never turns into an error for the reporter.
$to = support_notify_recipients();
if ($to) {
    $cats   = support_categories();
    $errs   = $row['js_errors'] ? count((array) json_decode($row['js_errors'], true)) : 0;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $link   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'yourblinds.uk') . '/master-admin/support.php?id=' . $ticketId;
    $body = "New support report #{$ticketId} — {$cats[$category]}\n\n"
          . "From:    {$row['user_name']} ({$row['company_name']})" . ($row['user_email'] ? " <{$row['user_email']}>" : '') . "\n"
          . "Page:    {$row['page_url']}\n"
          . "Version: " . ($row['app_version'] !== '' ? $row['app_version'] : 'unknown') . "\n"
          . "JS errors captured: {$errs}\n\n"
          . "----\n{$message}\n----\n\n"
          . "Open it: {$link}\n";
    try {
        mailer_send($to, "[YourBlinds support] #{$ticketId} " . $clip(preg_replace('/\s+/', ' ', $message), 60), $body,
            null, null,
            $row['user_email'] ? ['reply_to_email' => $row['user_email'], 'reply_to_name' => $row['user_name']] : null);
    } catch (Throwable $e) {
        error_log('[YourBlinds] support ticket email failed: ' . $e->getMessage());
    }
}

$reply(200, ['ok' => true, 'id' => $ticketId]);
