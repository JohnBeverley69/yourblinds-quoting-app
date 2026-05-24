<?php
declare(strict_types=1);

/**
 * Per-user iCalendar subscription feed.
 *
 * URL: /calendar/feed.php?t=<token>
 *
 * Authentication: the token itself. No login session — Google
 * Calendar, Apple Calendar etc. poll this URL on their own schedule
 * from servers that don't carry our cookies. The token is generated
 * per-user from /calendar/sync-setup.php and stored in
 * user_calendar_tokens. Regenerating the token invalidates the old
 * URL — that's how a user "revokes access".
 *
 * Permission scoping: same rules as the in-app calendar. If the
 * user has can_view_all_customer_jobs they see everything in their
 * tenant; otherwise they see only appointments where they are the
 * assigned client_user_id. The feed is filtered server-side, no
 * leakage.
 *
 * Date window: 60 days back + 365 days forward. Calendar apps
 * generally want a reasonable horizon, not the full history.
 * Adjust if needed; longer windows make the response heavier with
 * little practical benefit.
 *
 * Cacheability: deliberately Cache-Control: no-store. The whole
 * point of the feed is freshness; we don't want any CDN caching it
 * for the few minutes between a tenant rescheduling a fitting and
 * the next poll.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../_partials/ics_export.php';

// Token-only auth. Validate format BEFORE hitting the DB so a
// brute-force scanner doesn't get free queries.
$token = (string) ($_GET['t'] ?? '');
if ($token === '' || !preg_match('/^[a-f0-9]{32,64}$/', $token)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Not found\n");
}

$pdo = db();
try {
    $st = $pdo->prepare(
        'SELECT user_id, client_id FROM user_calendar_tokens WHERE token = ? LIMIT 1'
    );
    $st->execute([$token]);
    $row = $st->fetch();
} catch (Throwable $e) {
    // Table missing (migration not yet run). Don't 500 — return
    // an empty (but valid) calendar so the consuming app doesn't
    // complain. Server log captures the real reason.
    error_log('calendar/feed.php: lookup failed: ' . $e->getMessage());
    $row = null;
}

if (!$row) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Not found\n");
}

$userId   = (int) $row['user_id'];
$clientId = (int) $row['client_id'];

// Update last_used_at so the operator can tell from the
// sync-setup page whether anyone's actually polling. Best-effort.
try {
    $pdo->prepare('UPDATE user_calendar_tokens SET last_used_at = NOW() WHERE token = ?')
        ->execute([$token]);
} catch (Throwable $e) { /* ignore */ }

// Load the user row to get their name + the can_view_all_customer_jobs
// permission flag. Permission flag may not exist on older schemas;
// default to false (most restrictive — show only their own jobs).
$canViewAll = false;
$userName   = 'You';
$companyName = '';
try {
    $st = $pdo->prepare(
        'SELECT u.full_name,
                COALESCE(u.can_view_all_customer_jobs, 0) AS can_view_all,
                c.company_name
           FROM client_users u
           JOIN clients c ON c.id = u.client_id
          WHERE u.id = ? AND u.client_id = ? LIMIT 1'
    );
    $st->execute([$userId, $clientId]);
    $u = $st->fetch();
    if ($u) {
        $userName    = trim((string) $u['full_name']) ?: 'You';
        $canViewAll  = ((int) $u['can_view_all']) === 1;
        $companyName = (string) $u['company_name'];
    }
} catch (Throwable $e) {
    // Permission column missing — fall through with default (restricted).
    try {
        $st = $pdo->prepare(
            'SELECT u.full_name, c.company_name
               FROM client_users u
               JOIN clients c ON c.id = u.client_id
              WHERE u.id = ? AND u.client_id = ? LIMIT 1'
        );
        $st->execute([$userId, $clientId]);
        $u = $st->fetch();
        if ($u) {
            $userName    = trim((string) $u['full_name']) ?: 'You';
            $companyName = (string) $u['company_name'];
        }
    } catch (Throwable $e2) { /* leave defaults */ }
}

// Admin role also bypasses the per-user filter — pull from
// client_users.role to check.
try {
    $st = $pdo->prepare('SELECT role FROM client_users WHERE id = ? AND client_id = ? LIMIT 1');
    $st->execute([$userId, $clientId]);
    if ((string) $st->fetchColumn() === 'admin') {
        $canViewAll = true;
    }
} catch (Throwable $e) { /* ignore */ }

// ── Pull appointments ─────────────────────────────────────────────────
//
// Window: 60d back to 365d forward. Skip rows where appointment_date
// IS NULL (pending tray) — those aren't scheduled yet, so they're
// not events.
$winStart = (new DateTimeImmutable('-60 days'))->format('Y-m-d');
$winEnd   = (new DateTimeImmutable('+365 days'))->format('Y-m-d');

$sql = "SELECT a.id, a.title, a.appointment_date, a.appointment_time,
               a.duration_minutes, a.status, a.quote_id,
               a.installation_town, a.installation_postcode,
               c.id        AS customer_id,
               c.name      AS customer_name,
               c.phone     AS customer_phone,
               c.address1  AS customer_address1,
               c.address2  AS customer_address2,
               c.town      AS customer_town,
               c.postcode  AS customer_postcode
          FROM appointments a
     LEFT JOIN customers c ON c.id = a.customer_id
         WHERE a.client_id = ?
           AND a.appointment_date IS NOT NULL
           AND a.appointment_date BETWEEN ? AND ? ";

$params = [$clientId, $winStart, $winEnd];

if (!$canViewAll) {
    $sql .= ' AND a.client_user_id = ? ';
    $params[] = $userId;
}
$sql .= ' ORDER BY a.appointment_date, a.appointment_time';

try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();
} catch (Throwable $e) {
    error_log('calendar/feed.php: appointments query failed: ' . $e->getMessage());
    $rows = [];
}

// Stable host portion of the UID — uses the request's Host header
// if available so events from a sandbox / staging env don't
// collide with prod ones in the same calendar app.
$uidHost = (string) ($_SERVER['HTTP_HOST'] ?? 'yourblinds.uk');

// London timezone — appointment_date / appointment_time are stored
// as naive local UK time. Coerce to UTC for the ICS export so
// calendar apps everywhere convert back to the user's zone.
$tzLondon = new DateTimeZone('Europe/London');

$events = [];
foreach ($rows as $r) {
    $dateStr = (string) $r['appointment_date'];
    $timeStr = (string) ($r['appointment_time'] ?? '09:00:00');
    if ($timeStr === '') $timeStr = '09:00:00';

    try {
        $start = new DateTimeImmutable($dateStr . ' ' . $timeStr, $tzLondon);
    } catch (Throwable $e) {
        // Bad date/time on the row — skip rather than break the feed.
        continue;
    }
    $duration = (int) ($r['duration_minutes'] ?? 60);
    if ($duration <= 0) $duration = 60;
    $end = $start->add(new DateInterval('PT' . $duration . 'M'));

    // Summary: prefer the explicit title, else fall back to
    // customer name + status hint.
    $summary = trim((string) ($r['title'] ?? ''));
    if ($summary === '') {
        $summary = trim((string) ($r['customer_name'] ?? '')) ?: 'Appointment';
    }

    // Location: combine the installation address bits if present,
    // else the customer's home address.
    $locParts = [];
    if (!empty($r['installation_town']) || !empty($r['installation_postcode'])) {
        if (!empty($r['installation_town']))     $locParts[] = (string) $r['installation_town'];
        if (!empty($r['installation_postcode'])) $locParts[] = (string) $r['installation_postcode'];
    } else {
        foreach (['customer_address1', 'customer_address2', 'customer_town', 'customer_postcode'] as $col) {
            if (!empty($r[$col])) $locParts[] = (string) $r[$col];
        }
    }
    $location = implode(', ', $locParts);

    // Description: a few useful bits a fitter wants on their phone
    // — customer name, phone (tap-to-call), status, deep-link.
    $descBits = [];
    if (!empty($r['customer_name']))  $descBits[] = 'Customer: ' . (string) $r['customer_name'];
    if (!empty($r['customer_phone'])) $descBits[] = 'Phone: '    . (string) $r['customer_phone'];
    if (!empty($r['status']))         $descBits[] = 'Status: '   . (string) $r['status'];
    if (!empty($r['quote_id'])) {
        $descBits[] = 'Open in YourBlinds: https://' . $uidHost
                    . '/calendar/edit.php?id=' . (int) $r['id'];
    }
    $description = implode("\n", $descBits);

    $events[] = [
        'uid'         => 'appt-' . (int) $r['id'] . '@' . $uidHost,
        'summary'     => $summary,
        'description' => $description,
        'location'    => $location,
        'start'       => $start,
        'end'         => $end,
        'url'         => 'https://' . $uidHost . '/calendar/edit.php?id=' . (int) $r['id'],
    ];
}

// Calendar name shown inside the consuming app: "YourBlinds —
// John Beverley (Beverley Roller Blinds)" — distinctive so users
// who subscribe across multiple tenants can tell them apart.
$calName = 'YourBlinds';
if ($userName !== '')    $calName .= ' — ' . $userName;
if ($companyName !== '') $calName .= ' (' . $companyName . ')';

header('Content-Type: text/calendar; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Content-Disposition: inline; filename="yourblinds.ics"');

echo ics_build($calName, $events);
