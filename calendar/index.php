<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$user     = current_user();
$clientId = $user['client_id'];
$isAdmin  = $user['role'] === 'admin';

// ---------------------------------------------------------------------------
// Resolve the month being viewed. ?month=YYYY-MM, defaulting to current month.
// All date maths is done in the app timezone (set in bootstrap.php).
// ---------------------------------------------------------------------------
$monthParam = (string) ($_GET['month'] ?? '');
if ($monthParam !== '' && preg_match('/^\d{4}-\d{2}$/', $monthParam) === 1) {
    $cursor = DateTimeImmutable::createFromFormat('!Y-m', $monthParam);
    if ($cursor === false) {
        $cursor = new DateTimeImmutable('first day of this month');
    }
} else {
    $cursor = new DateTimeImmutable('first day of this month');
}

$firstOfMonth = $cursor->modify('first day of this month')->setTime(0, 0);
$lastOfMonth  = $cursor->modify('last day of this month')->setTime(23, 59, 59);
$prevMonth    = $firstOfMonth->modify('-1 month')->format('Y-m');
$nextMonth    = $firstOfMonth->modify('+1 month')->format('Y-m');
$thisMonth    = (new DateTimeImmutable('first day of this month'))->format('Y-m');
$todayStr     = (new DateTimeImmutable('today'))->format('Y-m-d');

// UK convention: weeks start Monday. PHP 'N' is 1=Mon..7=Sun, so leading
// blanks = ('N' of first-of-month) - 1.
$leadingBlanks = ((int) $firstOfMonth->format('N')) - 1;
$daysInMonth   = (int) $firstOfMonth->format('t');
$totalCells    = $leadingBlanks + $daysInMonth;
$trailingBlanks = (7 - ($totalCells % 7)) % 7;

// ---------------------------------------------------------------------------
// Fetch appointments visible to this client, falling within the month window.
// Grouped by date for fast per-cell rendering.
//
// ?mine=1 filters to appointments assigned to the logged-in user only.
// Non-admin users without the "view all customer jobs" permission have
// mineOnly FORCED on — they can only ever see their own appointments,
// regardless of URL. Admins and users with the permission see the toggle
// and can switch freely.
// ---------------------------------------------------------------------------
$mineOnly = isset($_GET['mine']) && (string) $_GET['mine'] === '1';

// Permission check: anyone non-admin without can_view_all_customer_jobs
// is locked to mineOnly. Look the flag up once for this request.
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
    $mineOnly = true;   // forced — URL ?mine=0 / no param is ignored
}

if ($mineOnly) {
    $stmt = db()->prepare(
        'SELECT a.id, a.title, a.appointment_date, a.appointment_time,
                a.duration_minutes, a.status, a.quote_id,
                a.installation_town, a.installation_postcode,
                c.name AS customer_name
           FROM appointments a
      LEFT JOIN customers c ON c.id = a.customer_id
          WHERE a.client_id = ?
            AND a.client_user_id = ?
            AND a.appointment_date BETWEEN ? AND ?
       ORDER BY a.appointment_date, a.appointment_time'
    );
    $stmt->execute([
        $clientId,
        (int) $user['user_id'],
        $firstOfMonth->format('Y-m-d'),
        $lastOfMonth->format('Y-m-d'),
    ]);
} else {
    $stmt = db()->prepare(
        'SELECT a.id, a.title, a.appointment_date, a.appointment_time,
                a.duration_minutes, a.status, a.quote_id,
                a.installation_town, a.installation_postcode,
                c.name AS customer_name
           FROM appointments a
      LEFT JOIN customers c ON c.id = a.customer_id
          WHERE a.client_id = ?
            AND a.appointment_date BETWEEN ? AND ?
       ORDER BY a.appointment_date, a.appointment_time'
    );
    $stmt->execute([
        $clientId,
        $firstOfMonth->format('Y-m-d'),
        $lastOfMonth->format('Y-m-d'),
    ]);
}

$byDate = [];
foreach ($stmt->fetchAll() as $row) {
    $byDate[$row['appointment_date']][] = $row;
}

// Pending Fitting tray — appointments with NO date set yet, e.g.
// auto-created on quote acceptance and waiting for the trade user to
// drag them onto the right day. Always queried for the same tenant
// scope as the main grid (and the same mine=1 filter — fitters see
// only their own pending jobs).
$pendingSql = $mineOnly
    ? 'SELECT a.id, a.title, a.status, a.quote_id,
              a.installation_town, a.installation_postcode,
              c.name AS customer_name
         FROM appointments a
    LEFT JOIN customers c ON c.id = a.customer_id
        WHERE a.client_id = ?
          AND a.client_user_id = ?
          AND a.appointment_date IS NULL
     ORDER BY a.id DESC'
    : 'SELECT a.id, a.title, a.status, a.quote_id,
              a.installation_town, a.installation_postcode,
              c.name AS customer_name
         FROM appointments a
    LEFT JOIN customers c ON c.id = a.customer_id
        WHERE a.client_id = ?
          AND a.appointment_date IS NULL
     ORDER BY a.id DESC';
$pStmt = db()->prepare($pendingSql);
$pStmt->execute($mineOnly ? [$clientId, (int) $user['user_id']] : [$clientId]);
$pendingAppts = $pStmt->fetchAll();

$dashTag = $isAdmin ? 'Admin Console' : 'Trade Portal';

// Whether to surface the "Today's run" link in the page header.
$mapsStmt = db()->prepare(
    'SELECT COALESCE(feature_maps, 0) FROM client_settings WHERE client_id = ?'
);
$mapsStmt->execute([$clientId]);
$mapsEnabled = ((int) $mapsStmt->fetchColumn()) === 1;

$weekdayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

$fmtTime = static function (string $time): string {
    // 14:30:00 -> 2:30pm
    $t = DateTimeImmutable::createFromFormat('H:i:s', $time)
        ?: DateTimeImmutable::createFromFormat('H:i', $time);
    return $t === false ? $time : strtolower($t->format('g:ia'));
};
// Calendar highlights the same sidebar entry in both modes — the
// "My Diary" entry was retired (it was just Calendar?mine=1, which is
// reachable via the toggle button on the page header now).
$activeNav = 'calendar';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>Calendar &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        /* Calendar-specific styles. Uses the app's navy palette
           (#1f3b5b primary, #93c5fd accent, #f4f7fb page bg). */
        .cal-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        .cal-nav {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .cal-nav-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 44px;
            min-height: 44px;
            padding: 0 0.875rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
            color: #1f3b5b;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.125rem;
            line-height: 1;
        }
        .cal-nav-btn:hover { background: #f3f4f6; }
        .cal-nav-today {
            font-size: 0.9375rem;
            padding: 0 0.875rem;
            min-width: 0;
        }
        .cal-month-label {
            font-size: 1.25rem;
            font-weight: 700;
            color: #111827;
            min-width: 11rem;
            text-align: center;
        }
        /* Everyone / Just-me pill toggle in the page header — replaces
           the old "My Diary" sidebar entry. Two adjacent anchors,
           filled when active, outlined when not. */
        .cal-view-toggle {
            display: inline-flex;
            border: 1px solid #d1d5db;
            border-radius: 999px;
            overflow: hidden;
            font-size: 0.875rem;
            background: #fff;
        }
        .cal-toggle-btn {
            padding: 0.4375rem 0.875rem;
            color: #4b5563;
            text-decoration: none;
            font-weight: 500;
            white-space: nowrap;
        }
        .cal-toggle-btn:hover { background: #f3f4f6; }
        .cal-toggle-btn.is-active {
            background: #1f3b5b;
            color: #fff;
        }
        .cal-toggle-btn + .cal-toggle-btn { border-left: 1px solid #d1d5db; }

        .cal-legend {
            display: flex;
            gap: 0.875rem;
            font-size: 0.8125rem;
            color: #4b5563;
            flex-wrap: wrap;
        }
        .cal-legend span {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }
        .cal-legend i {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 2px;
        }
        .cal-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 1px;
            background: #e5e7eb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }
        .cal-weekday {
            background: #1f3b5b;
            color: #fff;
            text-align: center;
            font-size: 0.8125rem;
            font-weight: 600;
            padding: 0.625rem 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .cal-cell {
            background: #fff;
            min-height: 120px;
            padding: 0.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
            position: relative;
        }
        .cal-cell.is-other-month { background: #f9fafb; }
        /* Today: darker blue tint + navy inset stripe down the left
           edge. Tyler reported #eff6ff was too washed out on his
           monitor to spot at a glance. The stripe gives a strong
           visual anchor without making the whole cell read as an
           alert state. */
        .cal-cell.is-today {
            background: #dbeafe;
            box-shadow: inset 3px 0 0 #1f3b5b;
        }
        .cal-cell-add {
            position: absolute;
            inset: 0;
            z-index: 1;
            text-decoration: none;
            border-radius: inherit;
        }
        .cal-cell-add::after {
            content: "+";
            position: absolute;
            bottom: 0.25rem;
            right: 0.5rem;
            color: #1f3b5b;
            font-size: 1.25rem;
            font-weight: 600;
            line-height: 1;
            opacity: 0;
            transition: opacity 0.15s ease;
            pointer-events: none;
        }
        .cal-cell:hover .cal-cell-add::after { opacity: 0.45; }
        .cal-cell.is-other-month .cal-cell-add { display: none; }
        .cal-day-num {
            font-size: 0.875rem;
            font-weight: 600;
            color: #6b7280;
            line-height: 1;
            pointer-events: none;
        }
        .cal-cell.is-today .cal-day-num {
            color: #fff;
            background: #1f3b5b;
            border-radius: 999px;
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .cal-appts {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            overflow: hidden;
            position: relative;
            z-index: 2;
        }
        .cal-appt {
            display: block;
            padding: 0.3125rem 0.5rem;
            border-radius: 6px;
            font-size: 0.8125rem;
            line-height: 1.2;
            color: #fff;
            text-decoration: none;
            border-left: 3px solid rgba(0, 0, 0, 0.18);
            min-height: 32px;
        }
        .cal-appt:hover { filter: brightness(0.95); }
        /* "Open order" tap target on quote-linked appointments. Sits
           inside the card on the right; one-tap goes straight to the
           order page. Bigger min-size than its visible content so
           thumbs can hit it on touch screens. */
        .cal-appt.from-quote { padding-right: 1.5rem; position: relative; }
        /* "Open order" tap target. Previously rendered as white-on-
           translucent-white which fell off most card colours. Now a
           solid white pill with a dark arrow — readable on any
           coloured card background, still small enough to not
           dominate the appointment card. */
        .cal-appt-open-order {
            position: absolute;
            right: 0.25rem; top: 50%;
            transform: translateY(-50%);
            min-width: 1.5rem; min-height: 1.5rem;
            padding: 0 0.375rem;
            display: inline-flex; align-items: center; justify-content: center;
            background: #fff;
            color: #1f3b5b;
            border-radius: 4px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            font-weight: 700;
            font-size: 0.9375rem;
            line-height: 1;
            cursor: pointer;
            user-select: none;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
        }
        .cal-appt-open-order:hover,
        .cal-appt-open-order:focus {
            background: #1f3b5b;
            color: #fff;
            outline: none;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.18);
        }
        .cal-appt-time {
            font-weight: 700;
            font-size: 0.75rem;
            display: block;
            opacity: 0.95;
        }
        .cal-appt-title {
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        /* Status colours */
        .cal-appt.status-booked    { background: #2563eb; }
        .cal-appt.status-completed { background: #16a34a; }
        .cal-appt.status-cancelled { background: #dc2626; }
        .cal-appt.status-no_show   { background: #6b7280; }

        /* Cards born from an accepted-quote (quote_id is set) — a more
           specific signal: "this is work the customer has signed off,
           schedule with confidence". Purple so it doesn't clash with
           the green Completed swatch in the legend. Once the
           appointment is marked completed the .status-completed rule
           still wins and the card turns green as expected (no .from-
           quote override on completed). */
        .cal-appt.from-quote.status-booked { background: #7c3aed; }

        /* ----- Tablet portrait & smaller: stack toolbar, slightly smaller cells. */
        @media (max-width: 900px) {
            .app-main { padding: 1rem; }
            .cal-cell { min-height: 96px; }
        }

        /* ----- Phone: collapse the grid into a stacked day list. */
        @media (max-width: 600px) {
            .cal-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .cal-weekday { display: none; }
            .cal-cell {
                min-height: 0;
                padding: 0.75rem 1rem;
                border-bottom: 1px solid #e5e7eb;
            }
            .cal-cell.is-other-month { display: none; }
            .cal-day-num::after {
                content: attr(data-weekday);
                font-weight: 400;
                color: #9ca3af;
                margin-left: 0.5rem;
            }
        }

        /* ===========================================================
           Pending Fitting tray + drag-and-drop affordances.
           Pending appointments live above the calendar grid as
           draggable cards. Drop targets:
             - any calendar cell with a real date → schedules to that date
             - the pending tray itself → unschedules (date back to NULL)
           Both pending cards and scheduled cards are draggable.
           =========================================================== */
        .pending-tray {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 0.875rem 1rem;
            margin-bottom: 1rem;
        }
        .pending-tray-head {
            display: flex; align-items: center; gap: 0.5rem;
            margin: 0 0 0.625rem;
        }
        .pending-tray-head h3 {
            margin: 0; font-size: 0.9375rem; color: #92400e;
            font-weight: 700;
        }
        .pending-tray-head .pending-count {
            font-size: 0.75rem; padding: 0.125rem 0.5rem;
            background: #f59e0b; color: #fff; border-radius: 999px;
            font-weight: 700;
        }
        .pending-tray-head .pending-hint {
            margin-left: auto; font-size: 0.8125rem; color: #92400e;
            font-style: italic;
        }
        .pending-cards {
            display: flex; flex-wrap: wrap; gap: 0.5rem;
        }
        .pending-card {
            display: inline-flex; flex-direction: column;
            min-width: 180px; max-width: 280px;
            padding: 0.5rem 0.625rem;
            background: #fff;
            border: 1px solid #fde68a; border-radius: 8px;
            cursor: grab; user-select: none;
            font-size: 0.8125rem; color: #111827;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .pending-card:active { cursor: grabbing; }
        .pending-card .pc-title {
            font-weight: 600; line-height: 1.3;
            overflow: hidden; text-overflow: ellipsis;
        }
        .pending-card .pc-meta {
            color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem;
        }
        .pending-card.dragging,
        .cal-appt.dragging { opacity: 0.4; }

        .cal-appt { cursor: grab; }
        .cal-appt:active { cursor: grabbing; }

        /* Drop-target highlights — swap to a soft blue while a
           draggable is hovering. */
        .cal-cell.is-drop-target { background: #dbeafe; outline: 2px dashed #2563eb; outline-offset: -2px; }
        .pending-tray.is-drop-target { background: #fef3c7; outline: 2px dashed #f59e0b; outline-offset: -2px; }

        .pending-empty {
            color: #92400e; font-size: 0.8125rem; font-style: italic;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Calendar</h1>
                <p class="page-subtitle">
                    <?php if (!$canViewAll): ?>
                        Your appointments.
                    <?php elseif ($mineOnly): ?>
                        Filtered to <?= e($user['full_name']) ?>'s appointments only.
                    <?php else: ?>
                        Appointments for <?= e($user['company_name']) ?>.
                    <?php endif; ?>
                    &middot;
                    <a href="/calendar/week.php"
                       style="color:#1f3b5b;font-weight:600">
                        📆 Week
                    </a>
                    &middot;
                    <a href="/calendar/day.php"
                       style="color:#1f3b5b;font-weight:600">
                        📅 Day
                    </a>
                    &middot;
                    <a href="/calendar/sync-setup.php"
                       style="color:#1f3b5b;font-weight:600">
                        📲 Sync phone
                    </a>
                </p>
            </div>
            <div class="actions-bar">
                <?php if ($canViewAll): ?>
                    <!-- View toggle. Only shown to admins and users with
                         can_view_all_customer_jobs — others are locked
                         to their own appointments and don't need it. -->
                    <div class="cal-view-toggle" role="group" aria-label="Calendar view">
                        <a href="/calendar/index.php?month=<?= e($firstOfMonth->format('Y-m')) ?>"
                           class="cal-toggle-btn <?= $mineOnly ? '' : 'is-active' ?>">
                            Everyone
                        </a>
                        <a href="/calendar/index.php?mine=1&month=<?= e($firstOfMonth->format('Y-m')) ?>"
                           class="cal-toggle-btn <?= $mineOnly ? 'is-active' : '' ?>">
                            Just me
                        </a>
                    </div>
                <?php endif; ?>
                <?php if ($mapsEnabled): ?>
                    <a href="/calendar/run.php" class="btn btn-success">Today's run &rarr;</a>
                <?php endif; ?>
                <a href="/calendar/new.php" class="btn btn-primary">+ Book Appointment</a>
            </div>
        </div>

        <?php
            // Preserve mine=1 across month navigation so the diary view
            // doesn't break out to "show all" when the user clicks
            // prev/next month.
            $monthQs = static fn (string $ym): string =>
                '?month=' . urlencode($ym) . ($mineOnly ? '&mine=1' : '');
            $todayHref = $mineOnly
                ? '/calendar/index.php?mine=1'
                : '/calendar/index.php';
        ?>

        <!-- Pending Fitting tray. Appointments with no date set
             (typically auto-created on quote acceptance) live here as
             draggable cards. Drop one onto a calendar cell to schedule
             it; drop a scheduled appointment back onto the tray to
             unschedule. -->
        <div class="pending-tray" id="pending-tray">
            <div class="pending-tray-head">
                <h3>Pending Fitting</h3>
                <span class="pending-count" id="pending-count"><?= count($pendingAppts) ?></span>
                <span class="pending-hint">Drag a card onto a date to schedule it.</span>
            </div>
            <div class="pending-cards" id="pending-cards">
                <?php if (empty($pendingAppts)): ?>
                    <span class="pending-empty">Nothing pending. Accepted quotes land here until you place them on a date.</span>
                <?php else: ?>
                    <?php foreach ($pendingAppts as $pa): ?>
                        <div class="pending-card"
                             draggable="true"
                             data-id="<?= (int) $pa['id'] ?>"
                             title="<?= e((string) $pa['title']) ?>">
                            <span class="pc-title"><?= e((string) $pa['title']) ?></span>
                            <?php
                                $metaBits = [];
                                if (!empty($pa['installation_town'])) $metaBits[] = (string) $pa['installation_town'];
                                if (!empty($pa['installation_postcode'])) $metaBits[] = (string) $pa['installation_postcode'];
                            ?>
                            <?php if ($metaBits): ?>
                                <span class="pc-meta"><?= e(implode(' · ', $metaBits)) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <section class="section">
            <div class="cal-toolbar">
                <div class="cal-nav">
                    <a class="cal-nav-btn"
                       href="<?= e($monthQs($prevMonth)) ?>"
                       aria-label="Previous month"
                       rel="prev">&lsaquo;</a>
                    <span class="cal-month-label"><?= e($firstOfMonth->format('F Y')) ?></span>
                    <a class="cal-nav-btn"
                       href="<?= e($monthQs($nextMonth)) ?>"
                       aria-label="Next month"
                       rel="next">&rsaquo;</a>
                    <?php if ($firstOfMonth->format('Y-m') !== $thisMonth): ?>
                        <a class="cal-nav-btn cal-nav-today"
                           href="<?= e($todayHref) ?>">Today</a>
                    <?php endif; ?>
                </div>
                <div class="cal-legend" aria-label="Status colours">
                    <span><i style="background:#2563eb"></i> Booked</span>
                    <span><i style="background:#7c3aed"></i> From quote</span>
                    <span><i style="background:#16a34a"></i> Completed</span>
                    <span><i style="background:#dc2626"></i> Cancelled</span>
                    <span><i style="background:#6b7280"></i> No-show</span>
                </div>
            </div>

            <?php if ($mineOnly && empty($byDate)): ?>
                <!-- Empty-state hint when the user can't see Everyone and
                     hasn't got any appointments of their own yet. Without
                     this the grid just looks empty and broken. -->
                <div style="background:#eef2f7;border:1px dashed #cbd5e1;
                            border-radius:10px;padding:1rem 1.125rem;
                            margin:0 0 1rem;color:#1f3b5b;font-size:0.9375rem">
                    No appointments assigned to you this month.
                    <?php if (!$canViewAll): ?>
                        Once an admin assigns you to a job, it'll appear
                        here and on your <a href="/calendar/schedule.php"
                        style="color:#1f3b5b;font-weight:600">My Schedule</a> page.
                    <?php else: ?>
                        Switch back to <a href="/calendar/index.php?month=<?= e($firstOfMonth->format('Y-m')) ?>"
                        style="color:#1f3b5b;font-weight:600">Everyone</a>
                        to see the whole team's diary.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="cal-grid" role="grid" aria-label="<?= e($firstOfMonth->format('F Y')) ?>">
                <?php foreach ($weekdayLabels as $wd): ?>
                    <div class="cal-weekday" role="columnheader"><?= e($wd) ?></div>
                <?php endforeach; ?>

                <?php for ($i = 0; $i < $leadingBlanks; $i++): ?>
                    <div class="cal-cell is-other-month" aria-hidden="true"></div>
                <?php endfor; ?>

                <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                    <?php
                        $cellDate = $firstOfMonth->setDate(
                            (int) $firstOfMonth->format('Y'),
                            (int) $firstOfMonth->format('n'),
                            $d
                        );
                        $iso       = $cellDate->format('Y-m-d');
                        $isToday   = $iso === $todayStr;
                        $weekday3  = $cellDate->format('D');
                        $appts     = $byDate[$iso] ?? [];
                    ?>
                    <div class="cal-cell<?= $isToday ? ' is-today' : '' ?>" role="gridcell"
                         data-date="<?= e($iso) ?>">
                        <a class="cal-cell-add"
                           href="/calendar/new.php?date=<?= e($iso) ?>"
                           aria-label="New appointment on <?= e($cellDate->format('j F Y')) ?>"></a>
                        <span class="cal-day-num" data-weekday="<?= e($weekday3) ?>"><?= $d ?></span>
                        <?php if ($appts): ?>
                            <div class="cal-appts">
                                <?php foreach ($appts as $a): ?>
                                    <a class="cal-appt status-<?= e((string) $a['status']) ?><?= !empty($a['quote_id']) ? ' from-quote' : '' ?>"
                                       href="/calendar/view.php?id=<?= (int) $a['id'] ?>"
                                       draggable="true"
                                       data-id="<?= (int) $a['id'] ?>"
                                       <?php if (!empty($a['quote_id'])): ?>
                                           data-quote-id="<?= (int) $a['quote_id'] ?>"
                                       <?php endif; ?>
                                       title="<?= e((string) $a['title']) ?> &mdash; <?= e((string) $a['status']) ?>">
                                        <span class="cal-appt-time"><?= e($fmtTime((string) $a['appointment_time'])) ?></span>
                                        <span class="cal-appt-title"><?= e((string) $a['title']) ?></span>
                                        <?php if (!empty($a['quote_id'])): ?>
                                            <!-- Single-tap shortcut to the order page. Stops the
                                                 click bubbling so the parent's view.php link
                                                 doesn't also fire. -->
                                            <span class="cal-appt-open-order"
                                                  role="link" tabindex="0"
                                                  data-quote-id="<?= (int) $a['quote_id'] ?>"
                                                  title="Open order"
                                                  aria-label="Open order">→</span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>

                <?php for ($i = 0; $i < $trailingBlanks; $i++): ?>
                    <div class="cal-cell is-other-month" aria-hidden="true"></div>
                <?php endfor; ?>
            </div>
        </section>
    </main>
</div>
<script>
(function () {
    'use strict';

    var endpoint  = '/calendar/reschedule.php';
    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    var pendingTray  = document.getElementById('pending-tray');
    var pendingCards = document.getElementById('pending-cards');
    var pendingCount = document.getElementById('pending-count');

    // Drag state — what's currently being dragged.
    var dragId   = null;
    var dragNode = null;

    // -- Make all cards (pending + scheduled) draggable -------------
    function bindDraggable(card) {
        card.addEventListener('dragstart', function (e) {
            dragId   = card.dataset.id;
            dragNode = card;
            // dataTransfer is required for Firefox to start a drag.
            e.dataTransfer.setData('text/plain', dragId);
            e.dataTransfer.effectAllowed = 'move';
            card.classList.add('dragging');
        });
        card.addEventListener('dragend', function () {
            card.classList.remove('dragging');
            dragId = null;
            dragNode = null;
            // Clear any leftover drop highlights (e.g. dragend without
            // ever firing dragleave on the last hovered cell).
            document.querySelectorAll('.is-drop-target').forEach(function (el) {
                el.classList.remove('is-drop-target');
            });
        });
        // Scheduled appts are <a> tags. Suppress click navigation
        // during the brief moment between drag start and ChromeAndroid
        // misfiring a click after a drop. Plain clicks (no drag) still
        // navigate normally because dragend resets dragNode synchronously.
        if (card.tagName === 'A') {
            card.addEventListener('click', function (e) {
                if (card.classList.contains('dragging')) e.preventDefault();
            });
        }
    }

    document.querySelectorAll('.pending-card, .cal-appt[draggable="true"]').forEach(bindDraggable);

    // -- Drop targets -----------------------------------------------
    function bindDropTarget(el, onDrop) {
        el.addEventListener('dragover', function (e) {
            // dragover must preventDefault to allow a drop.
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            el.classList.add('is-drop-target');
        });
        el.addEventListener('dragleave', function (e) {
            // dragleave fires when crossing into a child too; only
            // clear when the cursor really leaves the element.
            if (!el.contains(e.relatedTarget)) {
                el.classList.remove('is-drop-target');
            }
        });
        el.addEventListener('drop', function (e) {
            e.preventDefault();
            el.classList.remove('is-drop-target');
            if (!dragId) return;
            onDrop(dragId, el);
        });
    }

    // Each calendar cell drops to its own date.
    document.querySelectorAll('.cal-cell[data-date]').forEach(function (cell) {
        bindDropTarget(cell, function (id, target) {
            reschedule(id, target.dataset.date);
        });
    });

    // The pending tray drops to NULL (empty string) — unschedule.
    bindDropTarget(pendingTray, function (id) {
        reschedule(id, '');
    });

    // -- Live polling: Pending tray + calendar grid ------------------
    // One round-trip every 15s updates both halves of the page so trade
    // users see new acceptances, drag-rescheduling done by colleagues,
    // edits made in another tab — all without manually reloading.
    //
    // Shared-hosting friendly: small SELECTs, runs only while the tab
    // is visible, only swaps DOM if the relevant payload actually
    // changed (so a card the user is looking at doesn't get re-rendered
    // under their cursor every 15s).
    var currentMonth = '<?= e($firstOfMonth->format('Y-m')) ?>';
    var pollEndpoint = '/calendar/pending.php?month=' + encodeURIComponent(currentMonth)
        + (window.location.search.indexOf('mine=1') !== -1 ? '&mine=1' : '');
    var pollMs = 15000;
    var pollTimer = null;
    var lastPendingJson = null;
    var lastGridJson    = null;

    // "Open order" tap target on quote-linked appointments. Single tap
    // (not dblclick — natively fussy, especially on touch) jumps to
    // the order page. Stops propagation so the parent <a>'s view.php
    // link doesn't ALSO fire on the same gesture. Delegated to
    // document so it works for both server-rendered cards and the
    // cards re-rendered by the 15s poll refresh.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.cal-appt-open-order');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        var qid = btn.getAttribute('data-quote-id');
        if (qid) {
            window.location.href = '/quote-builder/edit.php?id=' + encodeURIComponent(qid);
        }
    });
    // Keyboard accessibility — Enter / Space on the focused → link.
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var btn = document.activeElement;
        if (!btn || !btn.classList || !btn.classList.contains('cal-appt-open-order')) return;
        e.preventDefault();
        var qid = btn.getAttribute('data-quote-id');
        if (qid) {
            window.location.href = '/quote-builder/edit.php?id=' + encodeURIComponent(qid);
        }
    });

    function refreshAll() {
        // Bail if the user is mid-drag — don't yank the card out from
        // under them, and don't shuffle the cells while they're aiming.
        if (dragId) return;

        fetch(pollEndpoint, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) return;

                var pendingJson = JSON.stringify(data.pending);
                if (pendingJson !== lastPendingJson) {
                    renderPending(data.pending);
                    lastPendingJson = pendingJson;
                }

                var gridJson = JSON.stringify(data.grid);
                if (gridJson !== lastGridJson) {
                    renderGrid(data.grid);
                    lastGridJson = gridJson;
                }
            })
            .catch(function () { /* network blip — try again next tick */ });
    }

    function renderPending(pending) {
        var html = '';
        if (pending.length === 0) {
            html = '<span class="pending-empty">Nothing pending. Accepted quotes land here until you place them on a date.</span>';
        } else {
            pending.forEach(function (p) {
                var meta = [];
                if (p.town)     meta.push(p.town);
                if (p.postcode) meta.push(p.postcode);
                html += '<div class="pending-card" draggable="true"'
                     +  ' data-id="' + p.id + '"'
                     +  ' title="' + escapeAttr(p.title) + '">'
                     +    '<span class="pc-title">' + escapeHtml(p.title) + '</span>'
                     +    (meta.length
                            ? '<span class="pc-meta">' + escapeHtml(meta.join(' · ')) + '</span>'
                            : '')
                     +  '</div>';
            });
        }
        pendingCards.innerHTML = html;
        pendingCount.textContent = pending.length;
        pendingCards.querySelectorAll('.pending-card').forEach(bindDraggable);
    }

    function renderGrid(byDate) {
        document.querySelectorAll('.cal-cell[data-date]').forEach(function (cell) {
            var date  = cell.dataset.date;
            var appts = (byDate && byDate[date]) ? byDate[date] : [];
            var existing = cell.querySelector('.cal-appts');

            if (appts.length === 0) {
                if (existing) existing.remove();
                return;
            }

            var html = '';
            appts.forEach(function (a) {
                var fromQuoteCls = a.quote_id ? ' from-quote' : '';
                var quoteAttr = a.quote_id
                    ? ' data-quote-id="' + a.quote_id + '"'
                    : '';
                // Mirrors the server-rendered card — a small "→" tap
                // target on quote-linked appointments that opens the
                // order page directly. Click handler is delegated on
                // document so this works for re-rendered cards too.
                var openOrderHtml = a.quote_id
                    ? '<span class="cal-appt-open-order" role="link" tabindex="0"'
                      + ' data-quote-id="' + a.quote_id + '"'
                      + ' title="Open order" aria-label="Open order">&rarr;</span>'
                    : '';
                html += '<a class="cal-appt status-' + escapeAttr(a.status) + fromQuoteCls + '"'
                     +  ' href="/calendar/view.php?id=' + a.id + '"'
                     +  ' draggable="true" data-id="' + a.id + '"'
                     +  quoteAttr
                     +  ' title="' + escapeAttr(a.title + ' — ' + a.status) + '">'
                     +    '<span class="cal-appt-time">'  + escapeHtml(a.time)  + '</span>'
                     +    '<span class="cal-appt-title">' + escapeHtml(a.title) + '</span>'
                     +    openOrderHtml
                     +  '</a>';
            });

            if (existing) {
                existing.innerHTML = html;
            } else {
                var wrap = document.createElement('div');
                wrap.className = 'cal-appts';
                wrap.innerHTML = html;
                cell.appendChild(wrap);
            }
            cell.querySelectorAll('.cal-appt[draggable="true"]').forEach(bindDraggable);
        });
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
        });
    }
    function escapeAttr(s) { return escapeHtml(s); }

    function startPolling() {
        if (pollTimer !== null) return;
        pollTimer = setInterval(refreshAll, pollMs);
    }
    function stopPolling() {
        if (pollTimer === null) return;
        clearInterval(pollTimer);
        pollTimer = null;
    }
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopPolling();
        } else {
            // Refresh once immediately on tab return, then resume.
            refreshAll();
            startPolling();
        }
    });
    if (!document.hidden) startPolling();

    // -- AJAX --------------------------------------------------------
    function reschedule(id, date) {
        var fd = new FormData();
        fd.append('appointment_id',  id);
        fd.append('appointment_date', date);

        fetch(endpoint, {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-Token': csrfToken },
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!data.ok) throw new Error(data.error || 'Save failed.');
                return data;
            });
        }).then(function () {
            // Pull the freshest state from the server (also picks up
            // anything any other open tab / colleague has done in
            // between). Surgical DOM update via the same path the
            // polling loop uses — no full page reload, no flash.
            refreshAll();
        }).catch(function (err) {
            alert(err.message || 'Could not reschedule.');
        });
    }
})();
</script>
</body>
</html>
