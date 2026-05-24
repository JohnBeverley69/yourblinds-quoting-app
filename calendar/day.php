<?php
declare(strict_types=1);

/**
 * Day view — multi-fitter dispatch grid.
 *
 * Mirrors the workflow our prospect's existing tool ("Once") uses
 * because their dispatchers have years of muscle memory in that
 * shape. One day at a time, one column per active user (fitter or
 * salesperson), vertical time grid showing where each appointment
 * sits AND how long it runs. The existing /calendar/index.php
 * (month grid) stays — this is an alternative view for the
 * "what's everyone doing today?" question.
 *
 * Layout:
 *   Header   — date nav (◀ today ▶ + date picker), view switch
 *   Body     — sticky time axis on the left, scrollable horizontally
 *              for tenants with many fitters, scrollable vertically
 *              for the time range
 *   Card     — customer name (caps), full address, phone, action
 *              icons (📍 maps, 📧 mail, 💬 sms), status colour.
 *              Positioned with absolute top/height from appointment
 *              time + duration so duration is VISIBLE not just a
 *              number.
 *
 * Permission scope: same as everywhere else in calendar — admins
 * and users with can_view_all_customer_jobs see all columns;
 * restricted users get a single-column view of their own day
 * (functionally similar to /calendar/schedule.php).
 *
 * V1 is READ-ONLY for drag-drop. Click a card to open the
 * appointment. Drag-to-reschedule has real side effects (SMS
 * notifications, status transitions) that need confirms; that's
 * V2.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$user        = current_user();
$clientId    = (int) $user['client_id'];
$myUserId    = (int) $user['user_id'];
$isAdmin     = ($user['role'] ?? '') === 'admin';

$perms = function_exists('current_user_permissions')
    ? current_user_permissions()
    : ['can_view_all_customer_jobs' => false];
$canViewAll = $isAdmin || !empty($perms['can_view_all_customer_jobs']);

// ── Date nav ─────────────────────────────────────────────────────────
$tz = new DateTimeZone('Europe/London');
$dateStr = (string) ($_GET['date'] ?? '');
try {
    $date = $dateStr !== ''
        ? new DateTimeImmutable($dateStr, $tz)
        : new DateTimeImmutable('today', $tz);
} catch (Throwable $e) {
    $date = new DateTimeImmutable('today', $tz);
}
$dateYmd  = $date->format('Y-m-d');
$prevYmd  = $date->modify('-1 day')->format('Y-m-d');
$nextYmd  = $date->modify('+1 day')->format('Y-m-d');
$todayYmd = (new DateTimeImmutable('today', $tz))->format('Y-m-d');
$isToday  = $dateYmd === $todayYmd;

// ── Bookable users ───────────────────────────────────────────────────
//
// Restricted users get a single-column view of themselves — that's
// the same data they'd see on /calendar/schedule.php, but in the
// new time-grid layout. Everyone else sees every active user.
$pdo = db();
if ($canViewAll) {
    $st = $pdo->prepare(
        'SELECT id, full_name, role
           FROM client_users
          WHERE client_id = ? AND active = 1
          ORDER BY full_name'
    );
    $st->execute([$clientId]);
    $columns = $st->fetchAll();
} else {
    $st = $pdo->prepare(
        'SELECT id, full_name, role
           FROM client_users
          WHERE client_id = ? AND id = ? LIMIT 1'
    );
    $st->execute([$clientId, $myUserId]);
    $columns = $st->fetchAll();
}

// ── Appointments for this day ────────────────────────────────────────
//
// JOIN customers for the address-first card layout and tap-to-call
// phone. installation_* fields override the customer's home address
// when filled in (which is most of the time — that's where the
// fitter actually goes).
$apStmt = $pdo->prepare(
    "SELECT a.id, a.title, a.appointment_time, a.duration_minutes,
            a.status, a.quote_id, a.client_user_id,
            a.installation_town, a.installation_postcode,
            c.name      AS customer_name,
            c.phone     AS customer_phone,
            c.email     AS customer_email,
            c.address1  AS customer_address1,
            c.address2  AS customer_address2,
            c.town      AS customer_town,
            c.postcode  AS customer_postcode
       FROM appointments a
  LEFT JOIN customers c ON c.id = a.customer_id
      WHERE a.client_id = ?
        AND a.appointment_date = ?
   ORDER BY a.appointment_time"
);
$apStmt->execute([$clientId, $dateYmd]);
$apRows = $apStmt->fetchAll();

// Group by user_id so each column knows what to render. Unassigned
// rows (NULL client_user_id) go into the "Unassigned" virtual column
// at the end.
$byUser = [];
foreach ($apRows as $r) {
    $uid = (int) ($r['client_user_id'] ?? 0);
    $byUser[$uid][] = $r;
}

// Hour range. 7am → 10pm covers early starts, normal daytime, and
// evening fittings (some tenants quote installs after the homeowner
// gets in from work). Vertical scroll handles the height.
$startHour = 7;
$endHour   = 22;
$totalHours = $endHour - $startHour;
$pxPerHour  = 60;   // 1 hour = 60 px → roughly 1 minute = 1 px (easy maths)
$gridHeight = $totalHours * $pxPerHour;

// Status → colour. yellow = pending/scheduled, green = confirmed,
// orange = needs-attention (rebooked? recall?), grey = done, red =
// cancelled. Defaults to yellow if the status isn't recognised.
$statusColour = static function (string $s): array {
    return match ($s) {
        'confirmed'    => ['bg' => '#bbf7d0', 'fg' => '#166534', 'border' => '#86efac'],
        'completed'    => ['bg' => '#e5e7eb', 'fg' => '#374151', 'border' => '#d1d5db'],
        'cancelled'    => ['bg' => '#fecaca', 'fg' => '#991b1b', 'border' => '#fca5a5'],
        'rescheduled'  => ['bg' => '#fed7aa', 'fg' => '#9a3412', 'border' => '#fdba74'],
        default        => ['bg' => '#fef3c7', 'fg' => '#78350f', 'border' => '#fde68a'],
    };
};

// Helpers: appointment_time "HH:MM:SS" → top offset in px from the
// 7am baseline; duration_minutes → height in px (clamped to a
// minimum so very short jobs are still readable).
$timeToTop = static function (string $t) use ($startHour, $pxPerHour): float {
    [$h, $m] = array_pad(explode(':', $t), 2, 0);
    $minsFromStart = (((int) $h) - $startHour) * 60 + (int) $m;
    return ($minsFromStart / 60) * $pxPerHour;
};
$durationToHeight = static function (?int $minutes) use ($pxPerHour): float {
    $m = $minutes && $minutes > 0 ? $minutes : 60;
    // Min height 60px = enough for time chip + heading line + a bit
    // of breathing room. Below this the card looked blank.
    return max(60, ($m / 60) * $pxPerHour);
};

$dashTag   = $isAdmin ? 'Admin Console' : 'Trade Portal';
$activeNav = 'calendar';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Day view &middot; <?= e($date->format('D j M')) ?> &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        /* Header — date nav + view switch. */
        .day-head {
            display: flex; align-items: center; gap: 0.75rem;
            margin-bottom: 0.75rem; flex-wrap: wrap;
        }
        .day-nav {
            display: inline-flex; align-items: center; gap: 0.25rem;
            background: #fff; border: 1px solid #d1d5db;
            border-radius: 8px; padding: 0.1875rem;
        }
        .day-nav a, .day-nav span {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 2rem; height: 2rem; padding: 0 0.5rem;
            text-decoration: none; color: #1f3b5b;
            font-weight: 600; font-size: 0.875rem;
            border-radius: 5px;
        }
        .day-nav a:hover { background: #f3f4f6; }
        .day-nav .today-pill {
            background: #1f3b5b; color: #fff;
        }
        .day-date {
            font-size: 1.125rem; font-weight: 700; color: #1f3b5b;
            min-width: 12rem;
        }
        .day-date .day-of-week { color: #6b7280; font-weight: 500; }
        .day-jump input[type="date"] {
            padding: 0.375rem 0.5rem; border: 1px solid #d1d5db;
            border-radius: 6px; font: inherit; font-size: 0.875rem;
        }
        .view-switch {
            display: inline-flex; background: #f3f4f6; border-radius: 8px;
            padding: 0.125rem; margin-left: auto;
        }
        .view-switch a {
            padding: 0.3125rem 0.75rem; border-radius: 6px;
            text-decoration: none; color: #6b7280;
            font-size: 0.875rem; font-weight: 600;
        }
        .view-switch a.is-active { background: #fff; color: #1f3b5b;
                                    box-shadow: 0 1px 2px rgba(0,0,0,0.06); }

        /* Grid layout. Time axis is fixed on the left; fitter columns
           scroll horizontally on narrow screens. Time markers + grid
           lines align with hour boundaries. */
        .day-board {
            background: #fff; border: 1px solid #e5e7eb;
            border-radius: 10px; overflow: hidden;
        }
        .day-board-head {
            display: grid;
            /* Fixed 18rem columns (not 1fr) so a single-fitter day
               doesn't sprawl across the whole page. Wider tenants
               get horizontal scroll, which is right — Once does
               this too. */
            grid-template-columns: 4rem repeat(var(--cols, 1), 18rem);
            background: #f9fafb; border-bottom: 1px solid #e5e7eb;
            position: sticky; top: 0; z-index: 3;
        }
        .day-board-head .col-spacer { /* corner above the time axis */ }
        .day-board-head .col-name {
            padding: 0.625rem 0.75rem; font-weight: 700; font-size: 0.9375rem;
            text-align: center;
            border-left: 1px solid #e5e7eb;
        }
        .day-board-body {
            display: grid;
            grid-template-columns: 4rem repeat(var(--cols, 1), 18rem);
            overflow-x: auto;
        }
        .time-axis {
            position: relative; background: #fff;
            border-right: 1px solid #e5e7eb;
        }
        .time-axis .time-tick {
            position: absolute; left: 0; right: 0;
            padding: 0.125rem 0.375rem;
            font-size: 0.6875rem; color: #6b7280; text-align: right;
            border-top: 1px solid #f3f4f6;
        }
        .fitter-col {
            position: relative; background: #fff;
            border-left: 1px solid #e5e7eb;
            cursor: cell;       /* hint: click anywhere to create */
        }
        .fitter-col .hour-line {
            position: absolute; left: 0; right: 0;
            border-top: 1px solid #f3f4f6;
            pointer-events: none;   /* don't steal clicks from the column */
        }
        .fitter-col:hover .new-hint {
            opacity: 0.6;
        }
        .fitter-col .new-hint {
            position: absolute; pointer-events: none;
            left: 0.25rem; right: 0.25rem;
            opacity: 0; transition: opacity 100ms;
            font-size: 0.75rem; color: #6b7280;
            font-style: italic; text-align: center;
            padding: 0.25rem 0.5rem;
            background: #eff6ff; border: 1px dashed #93c5fd;
            border-radius: 4px;
        }
        .appt-card {
            position: absolute; left: 0.25rem; right: 0.25rem;
            padding: 0.375rem 0.5rem;
            border-radius: 6px; border-left: 3px solid transparent;
            font-size: 0.8125rem; line-height: 1.35;
            overflow: hidden;
            text-decoration: none; color: inherit;
            transition: box-shadow 100ms;
        }
        .appt-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.12); z-index: 2; }
        .appt-card .ac-time {
            font-family: ui-monospace, Menlo, Consolas, monospace;
            font-size: 0.6875rem; color: #6b7280;
            margin-bottom: 0.125rem;
        }
        .appt-card .ac-title {
            font-weight: 700; color: #111827;
            text-transform: uppercase;
            font-size: 0.8125rem;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .appt-card .ac-placeholder {
            color: #6b7280; font-style: italic;
        }
        .appt-card .ac-desc {
            font-weight: 600; font-style: italic; color: #374151;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            font-size: 0.75rem;
        }
        .appt-card .ac-addr {
            color: #374151; font-size: 0.75rem;
            margin-top: 0.125rem;
        }
        .appt-card .ac-phone {
            font-family: ui-monospace, Menlo, Consolas, monospace;
            font-size: 0.75rem; color: #374151;
        }
        .appt-card .ac-actions {
            position: absolute; top: 0.25rem; right: 0.25rem;
            display: flex; flex-direction: column; gap: 0.1875rem;
        }
        .appt-card .ac-actions a {
            display: inline-flex; align-items: center; justify-content: center;
            width: 1.25rem; height: 1.25rem;
            background: rgba(255,255,255,0.7);
            border-radius: 4px; text-decoration: none;
            font-size: 0.8125rem;
        }
        .appt-card .ac-actions a:hover { background: #fff; }

        .day-empty {
            padding: 2rem; text-align: center; color: #6b7280;
            font-style: italic;
        }
        @media (max-width: 700px) {
            .day-board-head, .day-board-body {
                grid-template-columns: 3rem repeat(var(--cols, 1), 14rem);
            }
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Day view</h1>
                <p class="page-subtitle">
                    Who's doing what today, with full address + tap-to-call.
                </p>
            </div>
        </div>

        <div class="day-head">
            <div class="day-nav">
                <a href="/calendar/day.php?date=<?= e($prevYmd) ?>" aria-label="Previous day">&laquo;</a>
                <?php if ($isToday): ?>
                    <span class="today-pill">Today</span>
                <?php else: ?>
                    <a href="/calendar/day.php?date=<?= e($todayYmd) ?>">Today</a>
                <?php endif; ?>
                <a href="/calendar/day.php?date=<?= e($nextYmd) ?>" aria-label="Next day">&raquo;</a>
            </div>
            <div class="day-date">
                <span class="day-of-week"><?= e($date->format('D')) ?></span>
                <?= e($date->format('j M Y')) ?>
            </div>
            <form method="get" action="/calendar/day.php" class="day-jump">
                <input type="date" name="date" value="<?= e($dateYmd) ?>"
                       onchange="this.form.submit()">
            </form>
            <div class="view-switch">
                <a href="/calendar/index.php?month=<?= e($date->format('Y-m')) ?>">Month</a>
                <a href="/calendar/week.php?date=<?= e($dateYmd) ?>">Week</a>
                <a href="/calendar/day.php?date=<?= e($dateYmd) ?>" class="is-active">Day</a>
                <?php if (!$canViewAll): ?>
                    <a href="/calendar/schedule.php">List</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($columns)): ?>
            <div class="day-empty">No bookable users on this account.</div>
        <?php else: ?>
            <div class="day-board" style="--cols: <?= count($columns) ?>;">
                <!-- Header row: corner + one cell per user -->
                <div class="day-board-head">
                    <div class="col-spacer"></div>
                    <?php foreach ($columns as $col): ?>
                        <div class="col-name"
                             title="<?= e((string) $col['role']) ?>">
                            <?= e((string) $col['full_name']) ?>
                            <?php
                                $count = count($byUser[(int) $col['id']] ?? []);
                                if ($count > 0):
                            ?>
                                <span style="color:#6b7280;font-size:0.75rem;font-weight:500">
                                    &middot; <?= $count ?> job<?= $count === 1 ? '' : 's' ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Body: time axis + fitter columns -->
                <div class="day-board-body"
                     style="height: <?= $gridHeight + 24 ?>px">
                    <!-- Time axis -->
                    <div class="time-axis" style="height: <?= $gridHeight ?>px">
                        <?php for ($h = $startHour; $h < $endHour; $h++):
                            $top = ($h - $startHour) * $pxPerHour;
                        ?>
                            <div class="time-tick" style="top: <?= $top ?>px">
                                <?= sprintf('%d', $h > 12 ? $h - 12 : $h) ?>
                                <?= $h >= 12 ? 'pm' : 'am' ?>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <!-- One column per user -->
                    <?php foreach ($columns as $col):
                        $colId = (int) $col['id'];
                        $colRows = $byUser[$colId] ?? [];
                    ?>
                        <div class="fitter-col"
                             data-user-id="<?= $colId ?>"
                             data-user-name="<?= e((string) $col['full_name']) ?>"
                             style="height: <?= $gridHeight ?>px">
                            <div class="new-hint">Click to create appointment</div>
                            <!-- Faint hour grid lines so eyes can snap to the time axis -->
                            <?php for ($h = $startHour; $h < $endHour; $h++): ?>
                                <div class="hour-line"
                                     style="top: <?= ($h - $startHour) * $pxPerHour ?>px"></div>
                            <?php endfor; ?>

                            <?php foreach ($colRows as $appt):
                                $time = (string) ($appt['appointment_time'] ?? '09:00:00');
                                $top    = $timeToTop($time);
                                $height = $durationToHeight((int) ($appt['duration_minutes'] ?? 60));
                                $palette = $statusColour((string) ($appt['status'] ?? ''));

                                // Build address: prefer installation_* fields,
                                // fall back to customer's home address.
                                $addrLines = [];
                                if (!empty($appt['installation_town']) || !empty($appt['installation_postcode'])) {
                                    $bits = array_filter([
                                        (string) ($appt['installation_town']     ?? ''),
                                        (string) ($appt['installation_postcode'] ?? ''),
                                    ]);
                                    $addrLines[] = implode(', ', $bits);
                                } else {
                                    foreach (['customer_address1', 'customer_address2', 'customer_town', 'customer_postcode'] as $col2) {
                                        if (!empty($appt[$col2])) $addrLines[] = (string) $appt[$col2];
                                    }
                                }
                                $addr = implode(', ', $addrLines);

                                $phone = (string) ($appt['customer_phone'] ?? '');
                                $email = (string) ($appt['customer_email'] ?? '');

                                // Maps deep-link — Google's universal cross-platform
                                // URL. Works on iOS / Android / desktop. If the
                                // address is empty we just skip the icon.
                                $mapsUrl = $addr !== ''
                                    ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($addr)
                                    : '';

                                $title = trim((string) ($appt['title'] ?? ''));
                                $custName = trim((string) ($appt['customer_name'] ?? ''));
                                $durMin = (int) ($appt['duration_minutes'] ?? 60);

                                // Time chip — always shown so the user can
                                // glance at the card and read the booked
                                // window without inferring from row position.
                                $timeLabel = substr($time, 0, 5);
                                if ($durMin > 0 && $durMin !== 60) {
                                    $timeLabel .= ' · ' . $durMin . 'min';
                                }

                                // Heading — customer name if known, else
                                // the job title, else "Appointment #N" so
                                // the card never renders blank.
                                $heading = $custName !== ''
                                    ? $custName
                                    : ($title !== '' ? $title : ('Appointment #' . (int) $appt['id']));
                                $hasOnlyHeading = $custName === '' && $title === ''
                                                  && $addr === '' && $phone === '';
                            ?>
                                <a class="appt-card"
                                   href="/calendar/edit.php?id=<?= (int) $appt['id'] ?>"
                                   style="top:<?= $top ?>px;
                                          height:<?= $height ?>px;
                                          background:<?= $palette['bg'] ?>;
                                          border-left-color:<?= $palette['border'] ?>;
                                          color:<?= $palette['fg'] ?>;">
                                    <div class="ac-time"><?= e($timeLabel) ?></div>
                                    <div class="ac-title <?= $hasOnlyHeading ? 'ac-placeholder' : '' ?>">
                                        <?= e($heading) ?>
                                    </div>
                                    <?php if ($custName !== '' && $title !== ''): ?>
                                        <div class="ac-desc"><?= e($title) ?></div>
                                    <?php endif; ?>
                                    <?php if ($addr !== ''): ?>
                                        <div class="ac-addr"><?= e($addr) ?></div>
                                    <?php endif; ?>
                                    <?php if ($phone !== ''): ?>
                                        <div class="ac-phone"><?= e($phone) ?></div>
                                    <?php endif; ?>

                                    <!-- Action icons — tap on phone goes
                                         straight into the right native app
                                         (dialer, mail, sms, Maps). Stops
                                         clicks bubbling so they don't
                                         trigger the card's main href. -->
                                    <div class="ac-actions">
                                        <?php if ($mapsUrl !== ''): ?>
                                            <a href="<?= e($mapsUrl) ?>" target="_blank" rel="noopener"
                                               title="Open in Maps"
                                               onclick="event.stopPropagation();">📍</a>
                                        <?php endif; ?>
                                        <?php if ($phone !== ''): ?>
                                            <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"
                                               title="Call <?= e($phone) ?>"
                                               onclick="event.stopPropagation();">📞</a>
                                            <a href="sms:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"
                                               title="SMS <?= e($phone) ?>"
                                               onclick="event.stopPropagation();">💬</a>
                                        <?php endif; ?>
                                        <?php if ($email !== ''): ?>
                                            <a href="mailto:<?= e($email) ?>"
                                               title="Email <?= e($email) ?>"
                                               onclick="event.stopPropagation();">📧</a>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php
                // Unassigned tray — appointments on this date with NULL
                // client_user_id. Only worth surfacing when there are some,
                // and only to users who can see all (a restricted user
                // wouldn't be assigning these anyway).
                $unassigned = $byUser[0] ?? [];
                if ($canViewAll && $unassigned):
            ?>
                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;
                            padding:0.75rem 1rem;margin-top:0.875rem">
                    <strong style="color:#78350f;font-size:0.9375rem">
                        ⚠️ Unassigned (<?= count($unassigned) ?>)
                    </strong>
                    <div style="margin-top:0.375rem;font-size:0.8125rem;color:#78350f;line-height:1.5">
                        <?php foreach ($unassigned as $u): ?>
                            <div>
                                <a href="/calendar/edit.php?id=<?= (int) $u['id'] ?>"
                                   style="color:#78350f;font-weight:600">
                                    <?= e((string) ($u['appointment_time'] ?? '')) ?>
                                    &middot;
                                    <?= e((string) ($u['customer_name'] ?? $u['title'] ?? 'Appointment')) ?>
                                </a>
                                — needs a fitter assigned
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </main>
</div>

<script>
(function () {
    // Click on empty space in a fitter column → open the new-
    // appointment form with the date, time (snapped to 15-min slots
    // from where you clicked), and assigned-to (the fitter whose
    // column you clicked) pre-filled. Existing appointment cards
    // intercept clicks via their own anchor, so this only fires on
    // genuine empty-column clicks.
    var startHour = <?= (int) $startHour ?>;
    var pxPerHour = <?= (int) $pxPerHour ?>;
    var dateYmd   = '<?= e($dateYmd) ?>';

    document.querySelectorAll('.fitter-col').forEach(function (col) {
        col.addEventListener('click', function (ev) {
            // Only act on direct empty-area clicks, not bubbled from
            // cards / hour-lines / hint banners.
            if (ev.target !== col && !ev.target.classList.contains('hour-line')
                                 && !ev.target.classList.contains('new-hint')) {
                return;
            }
            var rect = col.getBoundingClientRect();
            var y    = ev.clientY - rect.top;        // 0 = top of column
            var mins = (y / pxPerHour) * 60;         // minutes since startHour
            mins     = Math.max(0, Math.round(mins / 15) * 15);   // snap to 15min
            var h    = startHour + Math.floor(mins / 60);
            var m    = mins % 60;
            if (h > 23) h = 23;
            var hh = (h < 10 ? '0' : '') + h;
            var mm = (m < 10 ? '0' : '') + m;
            var url = '/calendar/new.php?date=' + encodeURIComponent(dateYmd)
                    + '&time=' + hh + ':' + mm
                    + '&assigned_to=' + (col.dataset.userId || '');
            window.location.href = url;
        });

        // Move the hint banner to track the cursor so the user
        // sees WHERE clicking will create the appointment.
        var hint = col.querySelector('.new-hint');
        if (hint) {
            col.addEventListener('mousemove', function (ev) {
                var rect = col.getBoundingClientRect();
                var y    = ev.clientY - rect.top;
                var mins = (y / pxPerHour) * 60;
                mins     = Math.max(0, Math.round(mins / 15) * 15);
                var h    = startHour + Math.floor(mins / 60);
                var m    = mins % 60;
                var hh = (h < 10 ? '0' : '') + h;
                var mm = (m < 10 ? '0' : '') + m;
                hint.textContent = '+ New at ' + hh + ':' + mm;
                hint.style.top = ((mins / 60) * pxPerHour) + 'px';
            });
        }
    });
})();
</script>
</body>
</html>
