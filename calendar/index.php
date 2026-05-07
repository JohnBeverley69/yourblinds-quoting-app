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
// ---------------------------------------------------------------------------
$stmt = db()->prepare(
    'SELECT a.id, a.title, a.appointment_date, a.appointment_time,
            a.duration_minutes, a.status,
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

$byDate = [];
foreach ($stmt->fetchAll() as $row) {
    $byDate[$row['appointment_date']][] = $row;
}

$dashHref = $isAdmin ? '/admin/index.php' : '/quote-builder/index.php';
$dashTag  = $isAdmin ? 'Admin Console'    : 'Trade Portal';

$weekdayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

$fmtTime = static function (string $time): string {
    // 14:30:00 -> 2:30pm
    $t = DateTimeImmutable::createFromFormat('H:i:s', $time)
        ?: DateTimeImmutable::createFromFormat('H:i', $time);
    return $t === false ? $time : strtolower($t->format('g:ia'));
};
$activeNav = 'calendar';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
        .cal-cell.is-today { background: #eff6ff; }
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
                    Appointments for <?= e($user['company_name']) ?>.
                </p>
            </div>
            <a href="/calendar/new.php" class="btn btn-primary">+ Book Appointment</a>
        </div>

        <section class="section">
            <div class="cal-toolbar">
                <div class="cal-nav">
                    <a class="cal-nav-btn"
                       href="?month=<?= e($prevMonth) ?>"
                       aria-label="Previous month"
                       rel="prev">&lsaquo;</a>
                    <span class="cal-month-label"><?= e($firstOfMonth->format('F Y')) ?></span>
                    <a class="cal-nav-btn"
                       href="?month=<?= e($nextMonth) ?>"
                       aria-label="Next month"
                       rel="next">&rsaquo;</a>
                    <?php if ($firstOfMonth->format('Y-m') !== $thisMonth): ?>
                        <a class="cal-nav-btn cal-nav-today"
                           href="/calendar/index.php">Today</a>
                    <?php endif; ?>
                </div>
                <div class="cal-legend" aria-label="Status colours">
                    <span><i style="background:#2563eb"></i> Booked</span>
                    <span><i style="background:#16a34a"></i> Completed</span>
                    <span><i style="background:#dc2626"></i> Cancelled</span>
                    <span><i style="background:#6b7280"></i> No-show</span>
                </div>
            </div>

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
                    <div class="cal-cell<?= $isToday ? ' is-today' : '' ?>" role="gridcell">
                        <a class="cal-cell-add"
                           href="/calendar/new.php?date=<?= e($iso) ?>"
                           aria-label="New appointment on <?= e($cellDate->format('j F Y')) ?>"></a>
                        <span class="cal-day-num" data-weekday="<?= e($weekday3) ?>"><?= $d ?></span>
                        <?php if ($appts): ?>
                            <div class="cal-appts">
                                <?php foreach ($appts as $a): ?>
                                    <a class="cal-appt status-<?= e((string) $a['status']) ?>"
                                       href="/calendar/view.php?id=<?= (int) $a['id'] ?>"
                                       title="<?= e((string) $a['title']) ?> &mdash; <?= e((string) $a['status']) ?>">
                                        <span class="cal-appt-time"><?= e($fmtTime((string) $a['appointment_time'])) ?></span>
                                        <span class="cal-appt-title"><?= e((string) $a['title']) ?></span>
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
</body>
</html>
