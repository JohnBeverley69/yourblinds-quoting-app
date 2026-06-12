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
 * duration_minutes, same as day view. Each card carries its job's
 * status colour — a translucent wash for the body plus a thick
 * solid strip down the left — matching the month + day views so a
 * job is the same colour everywhere. The assignee's name is printed
 * on the card for who's-doing-what.
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
require __DIR__ . '/../_partials/job_status_colours.php';
require __DIR__ . '/../_partials/calendar_money.php';

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

// Pull every appointment that falls in the window. LEFT JOIN quotes
// for the Q-number + status indicator, same as day.php. Try/catch
// around the JOIN handles the Phase-2-schema case where quotes is
// missing.
$pdo = db();
$sql = "SELECT a.id, a.title, a.appointment_date, a.appointment_time,
               a.duration_minutes, a.status, a.appt_kind, a.quote_id, a.client_user_id,
               a.has_issue, a.issue_note,
               a.installation_town, a.installation_postcode,
               c.name      AS customer_name,
               c.phone     AS customer_phone,
               u.full_name AS assignee_name,
               q.quote_number AS quote_number,
               q.status       AS quote_status
          FROM appointments a
     LEFT JOIN customers    c ON c.id = a.customer_id
     LEFT JOIN client_users u ON u.id = a.client_user_id
     LEFT JOIN quotes       q ON q.id = a.quote_id
         WHERE a.client_id = ?
           AND a.appointment_date BETWEEN ? AND ?
           " . ($canViewAll ? '' : 'AND a.client_user_id = ?') . "
      ORDER BY a.appointment_date, a.appointment_time";
$params = [$clientId, $weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')];
if (!$canViewAll) $params[] = $myUserId;
try {
    $apStmt = $pdo->prepare($sql);
    $apStmt->execute($params);
    $apRows = $apStmt->fetchAll();
} catch (Throwable $e) {
    // Fallback without the quotes JOIN.
    error_log('week.php: quotes JOIN failed, falling back: ' . $e->getMessage());
    $fallbackSql = str_replace(
        ['LEFT JOIN quotes       q ON q.id = a.quote_id',
         'q.quote_number AS quote_number,',
         'q.status       AS quote_status'],
        ['', 'NULL AS quote_number,', 'NULL AS quote_status'],
        $sql
    );
    $apStmt = $pdo->prepare($fallbackSql);
    $apStmt->execute($params);
    $apRows = $apStmt->fetchAll();
}

// "Fittings only" users (e.g. fitters) see just the fitting jobs.
$fittingsOnly = !empty(current_user_permissions()['can_view_fittings_only']);
$byDate = [];
foreach ($apRows as $r) {
    if ($fittingsOnly && (string) ($r['appt_kind'] ?? 'measure') !== 'fitting') continue;
    $byDate[(string) $r['appointment_date']][] = $r;
}

// Money figures? (Settings checkbox) — resolved here as it sets the row scale.
$showMoney = false;
try {
    $mqStmt = $pdo->prepare('SELECT COALESCE(calendar_show_money, 0) FROM client_settings WHERE client_id = ?');
    $mqStmt->execute([$clientId]);
    $showMoney = ((int) $mqStmt->fetchColumn()) === 1;
} catch (Throwable $e) { /* column not migrated — figures stay off */ }

// Hour range — match day view. pxPerHour is tall enough that a normal (~1h)
// appointment's card (money line included) fills its slot, so the axis stays a
// true linear ruler and only genuinely-overlapping bookings stretch it.
$startHour = 7;
$endHour   = 22;
$pxPerHour = $showMoney ? 90 : 60;
$gridHeight = ($endHour - $startHour) * $pxPerHour;

// Same traffic-light palette as the calendar + Settings, so a job's hue is
// consistent everywhere. On these richer cards we wash the background with a
// tint of the stage colour (a solid fill would swamp the text).
$stagePalette = job_client_palette($clientId);

$moneyByQuote = [];
if ($showMoney) {
    $qids = [];
    foreach ($apRows as $r) { if (!empty($r['quote_id'])) $qids[] = (int) $r['quote_id']; }
    $moneyByQuote = calendar_money_for_quotes($pdo, $clientId, $qids);
}
$issueColour  = $stagePalette['issue'] ?? '#e11d48';

$statusColour = static function (string $s): array {
    return match ($s) {
        'confirmed'    => ['bg' => '#bbf7d0', 'fg' => '#166534'],
        'completed'    => ['bg' => 'var(--border)', 'fg' => 'var(--text-secondary)'],
        'cancelled'    => ['bg' => '#fecaca', 'fg' => '#991b1b'],
        'rescheduled'  => ['bg' => '#fed7aa', 'fg' => '#9a3412'],
        default        => ['bg' => '#fef3c7', 'fg' => '#78350f'],
    };
};

// Quote-status → 6-segment progress indicator. Matches day.php
// exactly so the same job shows the same bars across both views.
// One bar per stage now that 'fitted' joins the lifecycle.
$quoteProgress = static function (?string $status): array {
    if ($status === null || $status === '') {
        return ['filled' => 0, 'colour' => 'var(--border-strong)', 'label' => 'No quote linked'];
    }
    return match ($status) {
        'draft'     => ['filled' => 1, 'colour' => '#a78bfa', 'label' => 'Quote · DRAFT'],
        'sent'      => ['filled' => 2, 'colour' => '#fbbf24', 'label' => 'Quote · SENT'],
        'accepted'  => ['filled' => 3, 'colour' => '#34d399', 'label' => 'ACCEPTED'],
        'ordered'   => ['filled' => 4, 'colour' => '#10b981', 'label' => 'ORDERED'],
        'fitted'    => ['filled' => 5, 'colour' => '#0d9488', 'label' => 'FITTED'],
        'invoiced'  => ['filled' => 6, 'colour' => '#059669', 'label' => 'INVOICED'],
        'paid'      => ['filled' => 6, 'colour' => '#065f46', 'label' => 'PAID'],
        'declined'  => ['filled' => 0, 'colour' => '#dc2626', 'label' => 'DECLINED'],
        default     => ['filled' => 0, 'colour' => 'var(--text-faint)', 'label' => (string) $status],
    };
};

$timeToTop = static function (string $t) use ($startHour, $pxPerHour): float {
    [$h, $m] = array_pad(explode(':', $t), 2, 0);
    $minsFromStart = (((int) $h) - $startHour) * 60 + (int) $m;
    return ($minsFromStart / 60) * $pxPerHour;
};
// Minimum card height — kept below one hour's pixels so a normal appointment
// fills its slot exactly (no false stretch). Taller when the money line shows.
$minCardPx = $showMoney ? 84 : 60;
$durationToHeight = static function (?int $minutes) use ($pxPerHour, $minCardPx): float {
    $m = $minutes && $minutes > 0 ? $minutes : 60;
    return max($minCardPx, ($m / 60) * $pxPerHour);
};

// Expanding timeline — same approach as the day view, but here the columns are
// the 7 days, all sharing the single left axis. So the axis stretches at any
// hour where ANY day is packed too tight to fit at full height, and the same
// shared map ($ymap) positions the hour ticks AND every day's cards, keeping
// the hour labels level with the bookings. Longest-path over break points
// (hour boundaries + appointment starts); each card requires the next card in
// the same day to sit at least a full card-height below it.
$timeToMin = static function (string $t) use ($startHour): int {
    [$h, $m] = array_pad(explode(':', $t), 2, 0);
    return (((int) $h) - $startHour) * 60 + (int) $m;
};
$linearY = static fn (int $min): float => ($min / 60) * $pxPerHour;

$breakSet = [];
for ($h = $startHour; $h <= $endHour; $h++) { $breakSet[($h - $startHour) * 60] = true; }

$dayCards = [];   // ymd => [ ['idx'=>rowIdx, 'min'=>startMin, 'h'=>height], ... ] (sorted)
$edges    = [];   // toMin => [ ['from'=>fromMin, 'w'=>weight], ... ]
foreach ($days as $d) {
    $ymd  = $d['ymd'];
    $list = [];
    foreach (($byDate[$ymd] ?? []) as $rowIdx => $r) {
        $sMin = $timeToMin((string) ($r['appointment_time'] ?? '09:00:00'));
        $list[] = ['idx' => $rowIdx, 'min' => $sMin,
                   'h'   => $durationToHeight((int) ($r['duration_minutes'] ?? 60))];
        $breakSet[$sMin] = true;
    }
    usort($list, static fn ($a, $b) => $a['min'] <=> $b['min']);
    for ($i = 0, $n = count($list); $i < $n - 1; $i++) {
        if ($list[$i + 1]['min'] > $list[$i]['min']) {
            $edges[$list[$i + 1]['min']][] = ['from' => $list[$i]['min'], 'w' => $list[$i]['h']];
        }
    }
    $dayCards[$ymd] = $list;
}

$mins = array_keys($breakSet);
sort($mins);
$ymap = [];
$prevMin = null;
foreach ($mins as $m) {
    $y = $linearY($m);
    if ($prevMin !== null) {
        // Monotonic only — lets the axis RECOVER to the true linear ruler once
        // the natural positions catch up, instead of carrying a stretch forward.
        $y = max($y, $ymap[$prevMin]);
    }
    foreach ($edges[$m] ?? [] as $e) {
        $y = max($y, $ymap[$e['from']] + $e['w']);
    }
    $ymap[$m] = $y;
    $prevMin  = $m;
}

$hourY = static fn (int $h): float => $ymap[($h - $startHour) * 60] ?? (($h - $startHour) * $pxPerHour);

$GAP_PX = 4;
$columnLayouts = [];
$maxBottom = $ymap[($endHour - $startHour) * 60] ?? (float) $gridHeight;
foreach ($dayCards as $ymd => $list) {
    $pos = [];
    $runningBottom = 0.0;
    foreach ($list as $c) {
        $natural = $ymap[$c['min']];
        $top = $runningBottom > 0 ? max($natural, $runningBottom + $GAP_PX) : $natural;
        $pos[$c['idx']] = ['top' => $top, 'height' => $c['h']];
        $runningBottom  = $top + $c['h'];
        $maxBottom      = max($maxBottom, $runningBottom);
    }
    $columnLayouts[$ymd] = ['pos' => $pos];
}
$boardHeight = (int) ceil(max($gridHeight, $maxBottom));

// Break points as [y, minutes-from-start] for the click-to-create inverse map.
$axisPoints = [];
foreach ($mins as $m) { $axisPoints[] = [$ymap[$m], $m]; }

$dashTag   = $isAdmin ? 'Admin Console' : 'Trade Portal';
$activeNav = 'calendar';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Week view &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .wk-head {
            display: flex; align-items: center; gap: 0.75rem;
            margin-bottom: 0.75rem; flex-wrap: wrap;
        }
        .wk-nav {
            display: inline-flex; align-items: center; gap: 0.25rem;
            background: #fff; border: 1px solid var(--border-strong);
            border-radius: 8px; padding: 0.1875rem;
        }
        .wk-nav a, .wk-nav span {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 2rem; height: 2rem; padding: 0 0.5rem;
            text-decoration: none; color: #1f3b5b;
            font-weight: 600; font-size: 0.875rem;
            border-radius: 5px;
        }
        .wk-nav a:hover { background: var(--bg-subtle-2); }
        .wk-nav .today-pill { background: #1f3b5b; color: #fff; }
        .wk-range {
            font-size: 1.0625rem; font-weight: 700; color: #1f3b5b;
        }
        .view-switch {
            display: inline-flex; background: var(--bg-subtle-2); border-radius: 8px;
            padding: 0.125rem; margin-left: auto;
        }
        .view-switch a {
            padding: 0.3125rem 0.75rem; border-radius: 6px;
            text-decoration: none; color: var(--text-faint);
            font-size: 0.875rem; font-weight: 600;
        }
        .view-switch a.is-active { background: #fff; color: #1f3b5b;
                                    box-shadow: 0 1px 2px rgba(0,0,0,0.06); }

        .wk-board {
            background: #fff; border: 1px solid var(--border);
            border-radius: 10px; overflow: hidden;
        }
        .wk-board-head, .wk-board-body {
            display: grid;
            grid-template-columns: 4rem repeat(7, minmax(9rem, 1fr));
        }
        .wk-board-head {
            background: var(--bg-subtle); border-bottom: 1px solid var(--border);
        }
        .wk-board-body { overflow-x: auto; }
        .wk-day-name {
            padding: 0.5rem 0.5rem; text-align: center;
            border-left: 1px solid var(--border);
            font-size: 0.8125rem;
        }
        .wk-day-name .wk-dow {
            font-weight: 700; color: var(--text-primary);
            text-transform: uppercase; letter-spacing: 0.04em;
            font-size: 0.75rem;
        }
        .wk-day-name .wk-dom {
            font-size: 1.0625rem; font-weight: 700;
            color: var(--text-primary); margin-top: 0.125rem;
        }
        .wk-day-name.is-today .wk-dow { color: #ef4444; }
        .wk-day-name.is-today .wk-dom {
            background: #ef4444; color: #fff;
            display: inline-block; min-width: 1.625rem;
            border-radius: 999px; padding: 0.0625rem 0.4375rem;
        }
        .wk-day-name .wk-count { color: var(--text-faint); font-size: 0.6875rem; }

        .time-axis {
            position: relative; background: var(--bg-card);
            border-right: 1px solid var(--border);
        }
        .time-axis .time-tick {
            position: absolute; left: 0; right: 0;
            padding: 0.125rem 0.375rem;
            font-size: 0.6875rem; color: var(--text-faint); text-align: right;
            border-top: 1px solid var(--border-faint);
        }
        .wk-day-col {
            position: relative; background: var(--bg-card);
            border-left: 1px solid var(--border);
            cursor: cell;
        }
        .wk-day-col.is-today { background: #fffbeb; }
        [data-theme="dark"] .wk-day-col.is-today {
            background: rgba(251, 191, 36, 0.10);
        }
        .wk-day-col .hour-line {
            position: absolute; left: 0; right: 0;
            border-top: 1px solid var(--border-faint);
            pointer-events: none;
        }
        .wk-day-col:hover .new-hint { opacity: 0.6; }
        .wk-day-col .new-hint {
            position: absolute; pointer-events: none;
            left: 0.25rem; right: 0.25rem;
            opacity: 0; transition: opacity 100ms;
            font-size: 0.75rem; color: var(--text-faint);
            font-style: italic; text-align: center;
            padding: 0.125rem 0.375rem;
            background: #eff6ff; border: 1px dashed #93c5fd;
            border-radius: 4px;
        }

        .wk-card {
            position: absolute; left: 0.25rem; right: 0.25rem;
            padding: 0.3125rem 0.4375rem;
            border-radius: 4px;
            border-left: 6px solid transparent;
            font-size: 0.75rem; line-height: 1.3;
            overflow: hidden; text-decoration: none; color: inherit;
            transition: box-shadow 100ms;
        }
        .wk-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.12); z-index: 2; }
        .wk-card .wc-time {
            font-family: ui-monospace, Menlo, Consolas, monospace;
            font-size: 0.6875rem; color: var(--text-faint);
        }
        .wk-card .wc-title {
            font-weight: 700; color: var(--text-primary);
            text-transform: uppercase;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .wk-card .wc-assignee {
            font-size: 0.6875rem; color: var(--text-faint);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .wk-card .wc-placeholder { color: var(--text-faint); font-style: italic; }
        /* Q-number chip alongside time — same role as on day view,
           but tighter to fit the narrower week column. The chip
           background stays translucent-white because the parent
           card is colour-coded (the chip needs to read on any
           card colour, not just the theme). */
        .wk-card .wc-qref {
            display: inline-block;
            font-family: ui-monospace, Menlo, Consolas, monospace;
            font-size: 0.625rem;
            color: var(--text-muted);
            background: rgba(255,255,255,0.6);
            border-radius: 3px;
            padding: 0 0.1875rem;
            margin-left: 0.25rem;
        }
        /* Progress bars bottom-right of card. Slightly smaller than
           day view's because week-card cells are tighter. */
        .wk-card .wc-progress {
            position: absolute;
            bottom: 0.25rem; right: 0.3125rem;
            display: flex; flex-direction: column;
            gap: 1px;
            pointer-events: none;
        }
        .wk-card .wc-progress span {
            display: block;
            width: 1.25rem;
            height: 2px;
            background: rgba(0,0,0,0.12);
            border-radius: 1px;
        }
        .wk-card .wc-progress span.is-on {
            background: var(--prog-clr, #10b981);
        }
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
                       style="padding:0.375rem 0.5rem;border:1px solid var(--border-strong);border-radius:6px;font:inherit;font-size:0.875rem">
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

            <div class="wk-board-body" style="height: <?= $boardHeight + 24 ?>px">
                <div class="time-axis" style="height: <?= $boardHeight ?>px">
                    <?php for ($h = $startHour; $h < $endHour; $h++):
                        $top = $hourY($h);
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
                         style="height: <?= $boardHeight ?>px">
                        <div class="new-hint">+ New</div>
                        <?php for ($h = $startHour; $h < $endHour; $h++): ?>
                            <div class="hour-line"
                                 style="top: <?= $hourY($h) ?>px"></div>
                        <?php endfor; ?>

                        <?php
                            // Push-down positions for this day from the shared map.
                            $dayPos = $columnLayouts[$d['ymd']]['pos'] ?? [];
                        ?>
                        <?php foreach ($dayRows as $rowIdx => $appt):
                            $time   = (string) ($appt['appointment_time'] ?? '09:00:00');
                            $top    = $dayPos[$rowIdx]['top']    ?? $timeToTop($time);
                            $height = $dayPos[$rowIdx]['height'] ?? $durationToHeight((int) ($appt['duration_minutes'] ?? 60));
                            $apptKind  = (string) ($appt['appt_kind'] ?? 'measure');
                            $stageClr  = job_stage_colour((string) ($appt['status'] ?? ''), $appt['quote_status'] ?? null, $stagePalette, $apptKind);
                            $stageTint = job_status_tint($stageClr, 0.22);
                            $title    = trim((string) ($appt['title'] ?? ''));
                            $custName = trim((string) ($appt['customer_name'] ?? ''));
                            $heading  = $custName !== ''
                                ? $custName
                                : ($title !== '' ? $title : ('Appointment #' . (int) $appt['id']));
                            $hasOnly  = $custName === '' && $title === '';
                            $timeLabel = substr($time, 0, 5);
                            $qref     = trim((string) ($appt['quote_number'] ?? ''));
                            $prog     = $quoteProgress($appt['quote_status'] ?? null);
                            // Progress-dot colour from the shared palette too.
                            $wqs = (string) ($appt['quote_status'] ?? '');
                            if ($wqs !== '' && isset($stagePalette[$wqs])) $prog['colour'] = $stagePalette[$wqs];
                            $isIssue  = !empty($appt['has_issue']);
                            $issueTxt = trim((string) ($appt['issue_note'] ?? ''));
                            // Outline: issue (red) wins over the fitting (dark) outline.
                            $outline = $isIssue ? ';outline:2px solid ' . $issueColour . ';outline-offset:-2px'
                                     : ($apptKind === 'fitting' ? ';outline:2px solid #111827;outline-offset:-2px' : '');
                        ?>
                            <a class="wk-card<?= $apptKind === 'fitting' ? ' is-fitting' : '' ?><?= $isIssue ? ' is-issue' : '' ?>"
                               href="/calendar/edit.php?id=<?= (int) $appt['id'] ?>"
                               title="<?= $isIssue ? '⚠ ISSUE' . ($issueTxt !== '' ? ': ' . e($issueTxt) : '') : '' ?>"
                               style="top:<?= $top ?>px;
                                      height:<?= $height ?>px;
                                      background:<?= e($stageTint) ?>;
                                      border-left-color:<?= e($stageClr) ?>;
                                      color:var(--text-primary);
                                      --prog-clr:<?= e($prog['colour']) ?><?= e($outline) ?>;">
                                <div class="wc-time">
                                    <?= e($timeLabel) ?>
                                    <?php if ($qref !== ''): ?>
                                        <span class="wc-qref"><?= e($qref) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="wc-title <?= $hasOnly ? 'wc-placeholder' : '' ?>">
                                    <?= $isIssue ? '⚠️ ' : '' ?><?= e($heading) ?>
                                </div>
                                <?php if (!empty($appt['assignee_name'])): ?>
                                    <div class="wc-assignee">
                                        <?= e((string) $appt['assignee_name']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($showMoney && !empty($appt['quote_id']) && isset($moneyByQuote[(int) $appt['quote_id']])): ?>
                                    <?= calendar_money_html($moneyByQuote[(int) $appt['quote_id']], false) ?>
                                <?php endif; ?>
                                <?php if (!empty($appt['quote_status'])): ?>
                                    <div class="wc-progress" title="<?= e($prog['label']) ?>">
                                        <?php for ($i = 0; $i < 6; $i++): ?>
                                            <span class="<?= $i < (int) $prog['filled'] ? 'is-on' : '' ?>"></span>
                                        <?php endfor; ?>
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

    // Break points of the (possibly stretched) shared time axis as [y_px,
    // minutes-from-startHour], ascending — used to map a click's Y back to the
    // real time, since the axis is no longer a straight pxPerHour ruler.
    var axisPts = <?= json_encode($axisPoints, JSON_THROW_ON_ERROR) ?>;
    function yToMin(y) {
        if (!axisPts.length) return (y / pxPerHour) * 60;
        if (y <= axisPts[0][0]) return axisPts[0][1];
        for (var i = 1; i < axisPts.length; i++) {
            var a = axisPts[i - 1], b = axisPts[i];
            if (y <= b[0]) {
                var span = b[0] - a[0];
                return span <= 0 ? b[1] : a[1] + (y - a[0]) / span * (b[1] - a[1]);
            }
        }
        var last = axisPts[axisPts.length - 1];
        return last[1] + ((y - last[0]) / pxPerHour) * 60;
    }

    document.querySelectorAll('.wk-day-col').forEach(function (col) {
        col.addEventListener('click', function (ev) {
            if (ev.target !== col
                && !ev.target.classList.contains('hour-line')
                && !ev.target.classList.contains('new-hint')) {
                return;
            }
            var rect = col.getBoundingClientRect();
            var y    = ev.clientY - rect.top;
            var mins = yToMin(y);
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
                var mins = yToMin(y);
                mins     = Math.max(0, Math.round(mins / 15) * 15);
                var h    = startHour + Math.floor(mins / 60);
                var m    = mins % 60;
                var hh = (h < 10 ? '0' : '') + h;
                var mm = (m < 10 ? '0' : '') + m;
                hint.textContent = '+ ' + hh + ':' + mm;
                hint.style.top = y + 'px';   // track the cursor (axis is non-linear)
            });
        }
    });
})();
</script>
</body>
</html>
