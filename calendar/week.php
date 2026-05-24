<?php
declare(strict_types=1);

/**
 * Week view — Mon–Sun grid with the same time-axis layout as
 * /calendar/day.php. Different question: "what's the WEEK look
 * like?" rather than "what's everyone doing TODAY?".
 *
 * Columns:
 *   1 time axis  + 7 days (Monday through Sunday)
 *
 * Cards are positioned at appointment_time + sized by
 * duration_minutes, same as day view. To distinguish fitters in
 * the mixed column, each card gets a coloured left-border indexed
 * by user_id — so a glance tells you "the orange ones are
 * Allan's, the blue ones are Simon's".
 *
 * Click an empty slot to create an appointment on that day + time
 * (no fitter pre-fill — week view is multi-fitter so the user
 * picks on the form).
 *
 * Permission scope: same as day.php / index.php — admins +
 * can_view_all_customer_jobs see everything; restricted users
 * see only their own appointments.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$user     = current_user();
$clientId = (int) $user['client_id'];
$myUserId = (int) $user['user_id'];
$isAdmin  = ($user['role'] ?? '') === 'admin';

$perms = function_exists('current_user_permissions')
    ? current_user_permissions()
    : ['can_view_all_customer_jobs' => false];
$canViewAll = $isAdmin || !empty($perms['can_view_all_customer_jobs']);

$tz = new DateTimeZone('Europe/London');
$dateStr = (string) ($_GET['date'] ?? '');
try {
    $date = $dateStr !== ''
        ? new DateTimeImmutable($dateStr, $tz)
        : new DateTimeImmutable('today', $tz);
} catch (Throwable $e) {
    $date = new DateTimeImmutable('today', $tz);
}

// Snap to Monday of this week. PHP's modify('Monday this week') does
// the right thing — handles Sunday by giving the PREVIOUS Monday.
$weekStart = $date->modify('Monday this week');
$weekEnd   = $weekStart->modify('+6 days');
$prevWeek  = $weekStart->modify('-7 days')->format('Y-m-d');
$nextWeek  = $weekStart->modify('+7 days')->format('Y-m-d');
$todayYmd  = (new DateTimeImmutable('today', $tz))->format('Y-m-d');

// Build the 7 day objects upfront.
$days = [];
for ($i = 0; $i < 7; $i++) {
    $d = $weekStart->modify('+' . $i . ' days');
    $days[] = [
        'date'    => $d,
        'ymd'     => $d->format('Y-m-d'),
        'isToday' => $d->format('Y-m-d') === $todayYmd,
    ];
}

// Pull every appointment that falls in the window.
$pdo = db();
$apStmt = $pdo->prepare(
    "SELECT a.id, a.title, a.appointment_date, a.appointment_time,
            a.duration_minutes, a.status, a.quote_id, a.client_user_id,
            a.installation_town, a.installation_postcode,
            c.name      AS customer_name,
            c.phone     AS customer_phone,
            u.full_name AS assignee_name
       FROM appointments a
  LEFT JOIN customers c    ON c.id = a.customer_id
  LEFT JOIN client_users u ON u.id = a.client_user_id
      WHERE a.client_id = ?
        AND a.appointment_date BETWEEN ? AND ?
        " . ($canViewAll ? '' : 'AND a.client_user_id = ?') . "
   ORDER BY a.appointment_date, a.appointment_time"
);
$params = [$clientId, $weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')];
if (!$canViewAll) $params[] = $myUserId;
$apStmt->execute($params);
$apRows = $apStmt->fetchAll();

$byDate = [];
foreach ($apRows as $r) {
    $byDate[(string) $r['appointment_date']][] = $r;
}

// Hour range — match day view.
$startHour = 7;
$endHour   = 22;
$pxPerHour = 60;
$gridHeight = ($endHour - $startHour) * $pxPerHour;

// Per-user colour for the left border. Hash on user_id into a small
// fixed palette so the same fitter is always the same colour.
$userPalette = ['#dc2626','#2563eb','#16a34a','#d97706','#7c3aed','#db2777','#0891b2','#65a30d','#ea580c','#4f46e5'];
$colorForUser = static function (?int $uid) use ($userPalette): string {
    if (!$uid) return '#6b7280';
    return $userPalette[$uid % count($userPalette)];
};

$statusColour = static function (string $s): array {
    return match ($s) {
        'confirmed'    => ['bg' => '#bbf7d0', 'fg' => '#166534'],
        'completed'    => ['bg' => '#e5e7eb', 'fg' => '#374151'],
        'cancelled'    => ['bg' => '#fecaca', 'fg' => '#991b1b'],
        'rescheduled'  => ['bg' => '#fed7aa', 'fg' => '#9a3412'],
        default        => ['bg' => '#fef3c7', 'fg' => '#78350f'],
    };
};

$timeToTop = static function (string $t) use ($startHour, $pxPerHour): float {
    [$h, $m] = array_pad(explode(':', $t), 2, 0);
    $minsFromStart = (((int) $h) - $startHour) * 60 + (int) $m;
    return ($minsFromStart / 60) * $pxPerHour;
};
$durationToHeight = static function (?int $minutes) use ($pxPerHour): float {
    $m = $minutes && $minutes > 0 ? $minutes : 60;
    return max(60, ($m / 60) * $pxPerHour);
};

$dashTag   = $isAdmin ? 'Admin Console' : 'Trade Portal';
$activeNav = 'calendar';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Week view &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .wk-head {
            display: flex; align-items: center; gap: 0.75rem;
            margin-bottom: 0.75rem; flex-wrap: wrap;
        }
        .wk-nav {
            display: inline-flex; align-items: center; gap: 0.25rem;
            background: #fff; border: 1px solid #d1d5db;
            border-radius: 8px; padding: 0.1875rem;
        }
        .wk-nav a, .wk-nav span {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 2rem; height: 2rem; padding: 0 0.5rem;
            text-decoration: none; color: #1f3b5b;
            font-weight: 600; font-size: 0.875rem;
            border-radius: 5px;
        }
        .wk-nav a:hover { background: #f3f4f6; }
        .wk-nav .today-pill { background: #1f3b5b; color: #fff; }
        .wk-range {
            font-size: 1.0625rem; font-weight: 700; color: #1f3b5b;
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

        .wk-board {
            background: #fff; border: 1px solid #e5e7eb;
            border-radius: 10px; overflow: hidden;
        }
        .wk-board-head, .wk-board-body {
            display: grid;
            grid-template-columns: 4rem repeat(7, minmax(9rem, 1fr));
        }
        .wk-board-head {
            background: #f9fafb; border-bottom: 1px solid #e5e7eb;
        }
        .wk-board-body { overflow-x: auto; }
        .wk-day-name {
            padding: 0.5rem 0.5rem; text-align: center;
            border-left: 1px solid #e5e7eb;
            font-size: 0.8125rem;
        }
        .wk-day-name .wk-dow {
            font-weight: 700; color: #1f3b5b;
            text-transform: uppercase; letter-spacing: 0.04em;
            font-size: 0.75rem;
        }
        .wk-day-name .wk-dom {
            font-size: 1.0625rem; font-weight: 700;
            color: #111827; margin-top: 0.125rem;
        }
        .wk-day-name.is-today .wk-dow { color: #dc2626; }
        .wk-day-name.is-today .wk-dom {
            background: #dc2626; color: #fff;
            display: inline-block; min-width: 1.625rem;
            border-radius: 999px; padding: 0.0625rem 0.4375rem;
        }
        .wk-day-name .wk-count { color: #6b7280; font-size: 0.6875rem; }

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
        .wk-day-col {
            position: relative; background: #fff;
            border-left: 1px solid #e5e7eb;
            cursor: cell;
        }
        .wk-day-col.is-today { background: #fffbeb; }
        .wk-day-col .hour-line {
            position: absolute; left: 0; right: 0;
            border-top: 1px solid #f3f4f6;
            pointer-events: none;
        }
        .wk-day-col:hover .new-hint { opacity: 0.6; }
        .wk-day-col .new-hint {
            position: absolute; pointer-events: none;
            left: 0.25rem; right: 0.25rem;
            opacity: 0; transition: opacity 100ms;
            font-size: 0.75rem; color: #6b7280;
            font-style: italic; text-align: center;
            padding: 0.125rem 0.375rem;
            background: #eff6ff; border: 1px dashed #93c5fd;
            border-radius: 4px;
        }

        .wk-card {
            position: absolute; left: 0.25rem; right: 0.25rem;
            padding: 0.3125rem 0.4375rem;
            border-radius: 4px;
            border-left: 4px solid transparent;
            font-size: 0.75rem; line-height: 1.3;
            overflow: hidden; text-decoration: none; color: inherit;
            transition: box-shadow 100ms;
        }
        .wk-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.12); z-index: 2; }
        .wk-card .wc-time {
            font-family: ui-monospace, Menlo, Consolas, monospace;
            font-size: 0.6875rem; color: #6b7280;
        }
        .wk-card .wc-title {
            font-weight: 700; color: #111827;
            text-transform: uppercase;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .wk-card .wc-assignee {
            font-size: 0.6875rem; color: #6b7280;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .wk-card .wc-placeholder { color: #6b7280; font-style: italic; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Week view</h1>
                <p class="page-subtitle">7-day grid. Card colour = fitter; background = status.</p>
            </div>
        </div>

        <div class="wk-head">
            <div class="wk-nav">
                <a href="/calendar/week.php?date=<?= e($prevWeek) ?>" aria-label="Previous week">&laquo;</a>
                <a href="/calendar/week.php?date=<?= e($todayYmd) ?>">Today</a>
                <a href="/calendar/week.php?date=<?= e($nextWeek) ?>" aria-label="Next week">&raquo;</a>
            </div>
            <div class="wk-range">
                <?= e($weekStart->format('j M')) ?>
                &ndash;
                <?= e($weekEnd->format('j M Y')) ?>
            </div>
            <form method="get" action="/calendar/week.php">
                <input type="date" name="date" value="<?= e($weekStart->format('Y-m-d')) ?>"
                       onchange="this.form.submit()"
                       style="padding:0.375rem 0.5rem;border:1px solid #d1d5db;border-radius:6px;font:inherit;font-size:0.875rem">
            </form>
            <div class="view-switch">
                <a href="/calendar/index.php?month=<?= e($weekStart->format('Y-m')) ?>">Month</a>
                <a href="/calendar/week.php?date=<?= e($weekStart->format('Y-m-d')) ?>" class="is-active">Week</a>
                <a href="/calendar/day.php?date=<?= e($todayYmd) ?>">Day</a>
            </div>
        </div>

        <div class="wk-board">
            <div class="wk-board-head">
                <div></div>
                <?php foreach ($days as $d): ?>
                    <div class="wk-day-name <?= $d['isToday'] ? 'is-today' : '' ?>">
                        <div class="wk-dow"><?= e($d['date']->format('D')) ?></div>
                        <div class="wk-dom"><?= e($d['date']->format('j')) ?></div>
                        <?php
                            $cnt = count($byDate[$d['ymd']] ?? []);
                            if ($cnt > 0):
                        ?>
                            <div class="wk-count"><?= $cnt ?> job<?= $cnt === 1 ? '' : 's' ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="wk-board-body" style="height: <?= $gridHeight + 24 ?>px">
                <div class="time-axis" style="height: <?= $gridHeight ?>px">
                    <?php for ($h = $startHour; $h < $endHour; $h++):
                        $top = ($h - $startHour) * $pxPerHour;
                    ?>
                        <div class="time-tick" style="top: <?= $top ?>px">
                            <?= sprintf('%d', $h > 12 ? $h - 12 : ($h === 0 ? 12 : $h)) ?>
                            <?= $h >= 12 ? 'pm' : 'am' ?>
                        </div>
                    <?php endfor; ?>
                </div>

                <?php foreach ($days as $d):
                    $dayRows = $byDate[$d['ymd']] ?? [];
                ?>
                    <div class="wk-day-col <?= $d['isToday'] ? 'is-today' : '' ?>"
                         data-date="<?= e($d['ymd']) ?>"
                         style="height: <?= $gridHeight ?>px">
                        <div class="new-hint">+ New</div>
                        <?php for ($h = $startHour; $h < $endHour; $h++): ?>
                            <div class="hour-line"
                                 style="top: <?= ($h - $startHour) * $pxPerHour ?>px"></div>
                        <?php endfor; ?>

                        <?php foreach ($dayRows as $appt):
                            $time   = (string) ($appt['appointment_time'] ?? '09:00:00');
                            $top    = $timeToTop($time);
                            $height = $durationToHeight((int) ($appt['duration_minutes'] ?? 60));
                            $palette = $statusColour((string) ($appt['status'] ?? ''));
                            $borderClr = $colorForUser((int) ($appt['client_user_id'] ?? 0));
                            $title    = trim((string) ($appt['title'] ?? ''));
                            $custName = trim((string) ($appt['customer_name'] ?? ''));
                            $heading  = $custName !== ''
                                ? $custName
                                : ($title !== '' ? $title : ('Appointment #' . (int) $appt['id']));
                            $hasOnly  = $custName === '' && $title === '';
                            $timeLabel = substr($time, 0, 5);
                        ?>
                            <a class="wk-card"
                               href="/calendar/edit.php?id=<?= (int) $appt['id'] ?>"
                               style="top:<?= $top ?>px;
                                      height:<?= $height ?>px;
                                      background:<?= $palette['bg'] ?>;
                                      border-left-color:<?= $borderClr ?>;
                                      color:<?= $palette['fg'] ?>;">
                                <div class="wc-time"><?= e($timeLabel) ?></div>
                                <div class="wc-title <?= $hasOnly ? 'wc-placeholder' : '' ?>">
                                    <?= e($heading) ?>
                                </div>
                                <?php if (!empty($appt['assignee_name'])): ?>
                                    <div class="wc-assignee">
                                        <?= e((string) $appt['assignee_name']) ?>
                                    </div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<script>
(function () {
    // Click empty area on a day column → new appointment for that
    // date + computed time (snapped to 15 min). Skips when the
    // click hit a card or hour-line.
    var startHour = <?= (int) $startHour ?>;
    var pxPerHour = <?= (int) $pxPerHour ?>;

    document.querySelectorAll('.wk-day-col').forEach(function (col) {
        col.addEventListener('click', function (ev) {
            if (ev.target !== col
                && !ev.target.classList.contains('hour-line')
                && !ev.target.classList.contains('new-hint')) {
                return;
            }
            var rect = col.getBoundingClientRect();
            var y    = ev.clientY - rect.top;
            var mins = (y / pxPerHour) * 60;
            mins     = Math.max(0, Math.round(mins / 15) * 15);
            var h    = startHour + Math.floor(mins / 60);
            var m    = mins % 60;
            if (h > 23) h = 23;
            var hh = (h < 10 ? '0' : '') + h;
            var mm = (m < 10 ? '0' : '') + m;
            window.location.href = '/calendar/new.php?date=' + encodeURIComponent(col.dataset.date)
                                 + '&time=' + hh + ':' + mm;
        });

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
                hint.textContent = '+ ' + hh + ':' + mm;
                hint.style.top = ((mins / 60) * pxPerHour) + 'px';
            });
        }
    });
})();
</script>
</body>
</html>
