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
require __DIR__ . '/../_partials/job_status_colours.php';

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
// LEFT JOIN quotes too so each card can show the job reference
// number and a status-progress indicator derived from the quote
// lifecycle. Quotes table may be missing on Phase 2 builds — the
// LEFT JOIN handles that (NULLs everywhere on the quote columns).
$apStmt = $pdo->prepare(
    "SELECT a.id, a.title, a.appointment_time, a.duration_minutes,
            a.status, a.appt_kind, a.quote_id, a.client_user_id,
            a.has_issue, a.issue_note,
            a.installation_town, a.installation_postcode,
            c.name      AS customer_name,
            c.phone     AS customer_phone,
            c.email     AS customer_email,
            c.address1  AS customer_address1,
            c.address2  AS customer_address2,
            c.town      AS customer_town,
            c.postcode  AS customer_postcode,
            q.quote_number AS quote_number,
            q.status       AS quote_status
       FROM appointments a
  LEFT JOIN customers c ON c.id = a.customer_id
  LEFT JOIN quotes    q ON q.id = a.quote_id
      WHERE a.client_id = ?
        AND a.appointment_date = ?
   ORDER BY a.appointment_time"
);
try {
    $apStmt->execute([$clientId, $dateYmd]);
    $apRows = $apStmt->fetchAll();
} catch (Throwable $e) {
    // Quotes table missing → fallback to the no-join query so the
    // page still loads. Quote-info columns will just be NULL.
    error_log('day.php: quotes JOIN failed, falling back: ' . $e->getMessage());
    $fallback = $pdo->prepare(
        "SELECT a.id, a.title, a.appointment_time, a.duration_minutes,
                a.status, a.quote_id, a.client_user_id,
                a.has_issue, a.issue_note,
                a.installation_town, a.installation_postcode,
                c.name      AS customer_name,
                c.phone     AS customer_phone,
                c.email     AS customer_email,
                c.address1  AS customer_address1,
                c.address2  AS customer_address2,
                c.town      AS customer_town,
                c.postcode  AS customer_postcode,
                'measure' AS appt_kind,
                NULL AS quote_number,
                NULL AS quote_status
           FROM appointments a
      LEFT JOIN customers c ON c.id = a.customer_id
          WHERE a.client_id = ?
            AND a.appointment_date = ?
       ORDER BY a.appointment_time"
    );
    $fallback->execute([$clientId, $dateYmd]);
    $apRows = $fallback->fetchAll();
}

// Group by user_id so each column knows what to render. Unassigned
// rows (NULL client_user_id) go into the "Unassigned" virtual column
// at the end.
// "Fittings only" users (e.g. fitters) see just the fitting jobs.
$fittingsOnly = !empty(current_user_permissions()['can_view_fittings_only']);
$byUser = [];
foreach ($apRows as $r) {
    if ($fittingsOnly && (string) ($r['appt_kind'] ?? 'measure') !== 'fitting') continue;
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
        'completed'    => ['bg' => 'var(--border)', 'fg' => 'var(--text-secondary)', 'border' => 'var(--border-strong)'],
        'cancelled'    => ['bg' => '#fecaca', 'fg' => '#991b1b', 'border' => '#fca5a5'],
        'rescheduled'  => ['bg' => '#fed7aa', 'fg' => '#9a3412', 'border' => '#fdba74'],
        default        => ['bg' => '#fef3c7', 'fg' => '#78350f', 'border' => '#fde68a'],
    };
};

// Same traffic-light palette as the calendar + Settings. On these rich cards
// we wash the background with a tint of the stage colour and use the solid
// colour for the left accent, so the hue matches without swamping the text.
$stagePalette = job_client_palette($clientId);
$issueColour  = $stagePalette['issue'] ?? '#e11d48';

// Quote status → 5-segment progress bar. Mirrors what Once shows
// as the stacked horizontal bars on each card: a glanceable
// indicator of where the job is in the quote→ordered→fitted→paid
// pipeline. Returns:
//   filled (0–5): how many bars to colour
//   colour: progress colour (green normal, grey for "done",
//           red for declined/cancelled)
//   label: short word for the title attribute hover tooltip
// 6 segments now — one per lifecycle stage. Each status fills one
// more bar than the last, so a glance at the bar count tells you
// exactly where the job sits without reading the tooltip:
//   1 bar  → DRAFT
//   2 bars → SENT
//   3 bars → ACCEPTED
//   4 bars → ORDERED  (materials on the way)
//   5 bars → FITTED   (installed, awaiting invoice)
//   6 bars → INVOICED / PAID
$quoteProgress = static function (?string $status): array {
    if ($status === null || $status === '') {
        return ['filled' => 0, 'colour' => 'var(--border-strong)', 'label' => 'No quote linked'];
    }
    return match ($status) {
        'draft'     => ['filled' => 1, 'colour' => '#a78bfa', 'label' => 'Quote · DRAFT'],
        'sent'      => ['filled' => 2, 'colour' => '#fbbf24', 'label' => 'Quote · SENT to customer'],
        'accepted'  => ['filled' => 3, 'colour' => '#34d399', 'label' => 'ACCEPTED · ready to fit'],
        'ordered'   => ['filled' => 4, 'colour' => '#10b981', 'label' => 'ORDERED · materials inbound'],
        'fitted'    => ['filled' => 5, 'colour' => '#0d9488', 'label' => 'FITTED · awaiting invoice'],
        'invoiced'  => ['filled' => 6, 'colour' => '#059669', 'label' => 'INVOICED · awaiting payment'],
        'paid'      => ['filled' => 6, 'colour' => '#065f46', 'label' => 'PAID · job closed'],
        'declined'  => ['filled' => 0, 'colour' => '#dc2626', 'label' => 'Quote DECLINED'],
        default     => ['filled' => 0, 'colour' => 'var(--text-faint)', 'label' => (string) $status],
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

// Side-by-side layout for overlapping cards within one column. Without this,
// appointments close together in time (e.g. 10:00 / 10:30 / 11:00) render as
// full-height blocks stacked on top of each other and become unreadable —
// the min-height above makes even short visits tall enough to collide. This
// mirrors how Google/Once lay overlapping events out: group anything that
// overlaps into a cluster, slot each into the first free "lane", and split the
// column width between the lanes so they sit beside each other.
//
// Returns an array keyed by the row's position in $rows: each entry is
// ['lane' => int (0-based column), 'lanes' => int (how many to split into)].
// Non-overlapping cards come back as lane 0 of 1 → full width, unchanged.
$computeLayout = static function (array $rows) use ($timeToTop, $durationToHeight): array {
    // Pixel start/end for each row (end uses the same min-height as render).
    $iv = [];
    foreach ($rows as $i => $r) {
        $s = $timeToTop((string) ($r['appointment_time'] ?? '09:00:00'));
        $e = $s + $durationToHeight((int) ($r['duration_minutes'] ?? 60));
        $iv[$i] = ['s' => $s, 'e' => $e];
    }

    // Process in start order (ties broken by end) so lane assignment is stable.
    $order = array_keys($iv);
    usort($order, static fn ($a, $b) => ($iv[$a]['s'] <=> $iv[$b]['s']) ?: ($iv[$a]['e'] <=> $iv[$b]['e']));

    $layout = [];
    foreach ($rows as $i => $r) { $layout[$i] = ['lane' => 0, 'lanes' => 1]; }

    // Close a cluster: greedily place each card in the first lane whose
    // previous card has already ended, then stamp the lane count on them all.
    $closeCluster = static function (array $cluster) use ($iv, &$layout): void {
        if (!$cluster) return;
        $laneEnds = [];        // lane index → end px of its last card
        $laneOf   = [];
        foreach ($cluster as $idx) {
            $placed = false;
            foreach ($laneEnds as $ln => $end) {
                if ($iv[$idx]['s'] >= $end) {          // lane is free again
                    $laneOf[$idx] = $ln; $laneEnds[$ln] = $iv[$idx]['e']; $placed = true; break;
                }
            }
            if (!$placed) {
                $ln = count($laneEnds);
                $laneOf[$idx] = $ln; $laneEnds[$ln] = $iv[$idx]['e'];
            }
        }
        $lanes = count($laneEnds);
        foreach ($cluster as $idx) {
            $layout[$idx] = ['lane' => $laneOf[$idx], 'lanes' => $lanes];
        }
    };

    // Sweep: a card that starts at/after the cluster's furthest end begins a
    // fresh cluster (no overlap with anything before it).
    $cluster = [];
    $clusterMaxEnd = null;
    foreach ($order as $idx) {
        if ($clusterMaxEnd !== null && $iv[$idx]['s'] >= $clusterMaxEnd) {
            $closeCluster($cluster);
            $cluster = [];
            $clusterMaxEnd = null;
        }
        $cluster[] = $idx;
        $clusterMaxEnd = $clusterMaxEnd === null ? $iv[$idx]['e'] : max($clusterMaxEnd, $iv[$idx]['e']);
    }
    $closeCluster($cluster);

    return $layout;
};

$dashTag   = $isAdmin ? 'Admin Console' : 'Trade Portal';
$activeNav = 'calendar';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Day view &middot; <?= e($date->format('D j M')) ?> &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        /* Header — date nav + view switch. */
        .day-head {
            display: flex; align-items: center; gap: 0.75rem;
            margin-bottom: 0.75rem; flex-wrap: wrap;
        }
        .day-nav {
            display: inline-flex; align-items: center; gap: 0.25rem;
            background: var(--bg-card); border: 1px solid var(--border-strong);
            border-radius: 8px; padding: 0.1875rem;
        }
        .day-nav a, .day-nav span {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 2rem; height: 2rem; padding: 0 0.5rem;
            text-decoration: none; color: var(--text-primary);
            font-weight: 600; font-size: 0.875rem;
            border-radius: 5px;
        }
        .day-nav a:hover { background: var(--bg-subtle-2); }
        .day-nav .today-pill {
            background: var(--brand); color: #fff;
        }
        .day-date {
            font-size: 1.125rem; font-weight: 700; color: var(--text-primary);
            min-width: 12rem;
        }
        .day-date .day-of-week { color: var(--text-faint); font-weight: 500; }
        .day-jump input[type="date"] {
            padding: 0.375rem 0.5rem; border: 1px solid var(--border-strong);
            border-radius: 6px; font: inherit; font-size: 0.875rem;
            background: var(--bg-input); color: var(--text-body);
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
        .view-switch a.is-active { background: var(--bg-card); color: var(--text-primary);
                                    box-shadow: 0 1px 2px rgba(0,0,0,0.06); }

        /* Grid layout. Time axis is fixed on the left; fitter columns
           scroll horizontally on narrow screens. Time markers + grid
           lines align with hour boundaries. */
        .day-board {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 10px; overflow: hidden;
        }
        .day-board-head {
            display: grid;
            /* Fixed 18rem columns (not 1fr) so a single-fitter day
               doesn't sprawl across the whole page. Wider tenants
               get horizontal scroll, which is right — Once does
               this too. */
            grid-template-columns: 4rem repeat(var(--cols, 1), 18rem);
            background: var(--bg-subtle); border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 3;
        }
        .day-board-head .col-spacer { /* corner above the time axis */ }
        .day-board-head .col-name {
            padding: 0.625rem 0.75rem; font-weight: 700; font-size: 0.9375rem;
            text-align: center;
            border-left: 1px solid var(--border);
            color: var(--text-primary);
        }
        .day-board-body {
            display: grid;
            grid-template-columns: 4rem repeat(var(--cols, 1), 18rem);
            overflow-x: auto;
        }
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
        .fitter-col {
            position: relative; background: var(--bg-card);
            border-left: 1px solid var(--border);
            cursor: cell;       /* hint: click anywhere to create */
        }
        .fitter-col .hour-line {
            position: absolute; left: 0; right: 0;
            border-top: 1px solid var(--border-faint);
            pointer-events: none;   /* don't steal clicks from the column */
        }
        .fitter-col:hover .new-hint {
            opacity: 0.6;
        }
        .fitter-col .new-hint {
            position: absolute; pointer-events: none;
            left: 0.25rem; right: 0.25rem;
            opacity: 0; transition: opacity 100ms;
            font-size: 0.75rem; color: var(--text-faint);
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
            cursor: pointer;
        }
        .appt-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.12); z-index: 2; }
        .appt-card .ac-time {
            font-family: ui-monospace, Menlo, Consolas, monospace;
            font-size: 0.6875rem; color: var(--text-faint);
            margin-bottom: 0.125rem;
            /* Right padding leaves room for the absolutely-positioned
               action icons at top-right of the card so the time
               chip doesn't run under them. */
            padding-right: 5.5rem;
        }
        .appt-card .ac-title {
            font-weight: 700; color: var(--text-primary);
            text-transform: uppercase;
            font-size: 0.8125rem;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            padding-right: 5.5rem;
        }
        .appt-card .ac-placeholder {
            color: var(--text-faint); font-style: italic;
        }
        .appt-card .ac-desc {
            font-weight: 600; font-style: italic; color: var(--text-secondary);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            font-size: 0.75rem;
        }
        .appt-card .ac-addr {
            color: var(--text-secondary); font-size: 0.75rem;
            margin-top: 0.125rem;
        }
        .appt-card .ac-phone {
            font-family: ui-monospace, Menlo, Consolas, monospace;
            font-size: 0.75rem; color: var(--text-secondary);
        }
        /* Quote reference label — small monospace chip at the top
           of the card alongside the time. Once-style "260429-sc-2"
           identifier. Only renders when the appointment is linked
           to a quote. */
        .appt-card .ac-qref {
            display: inline-block;
            font-family: ui-monospace, Menlo, Consolas, monospace;
            font-size: 0.6875rem;
            color: var(--text-muted);
            background: rgba(255,255,255,0.6);
            border-radius: 3px;
            padding: 0 0.25rem;
            margin-left: 0.25rem;
        }
        /* Quote-progress bars — bottom-right of the card. 5 thin
           horizontal lines, "filled" ones get the status colour,
           rest stay light grey. Visible at a glance, doesn't compete
           with the action icons up top. */
        .appt-card .ac-progress {
            position: absolute;
            bottom: 0.3125rem; right: 0.375rem;
            display: flex; flex-direction: column;
            gap: 1px;
            pointer-events: none;
        }
        .appt-card .ac-progress span {
            display: block;
            width: 1.625rem;
            height: 2px;
            background: rgba(0,0,0,0.12);
            border-radius: 1px;
        }
        .appt-card .ac-progress span.is-on {
            background: var(--prog-clr, #10b981);
        }
        /* Action icons in a horizontal row at top-right of the
           card. Vertical stack ran out of room on short (60px)
           cards — 4 icons would overflow the bottom. Row layout
           always fits. Icons are smaller (18px) to leave more
           room for the heading text alongside. */
        .appt-card .ac-actions {
            position: absolute; top: 0.25rem; right: 0.25rem;
            display: flex; flex-direction: row; gap: 0.1875rem;
            z-index: 2;
        }
        .appt-card .ac-actions a {
            display: inline-flex; align-items: center; justify-content: center;
            width: 1.125rem; height: 1.125rem;
            background: rgba(255,255,255,0.85);
            border-radius: 3px; text-decoration: none;
            font-size: 0.6875rem;
            line-height: 1;
        }
        .appt-card .ac-actions a:hover { background: #fff; }
        .appt-card .ac-actions a:hover { background: #fff; }

        .day-empty {
            padding: 2rem; text-align: center; color: var(--text-faint);
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
                                <span style="color:var(--text-faint);font-size:0.75rem;font-weight:500">
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

                            <?php
                                // Pre-compute side-by-side lanes for this
                                // column so overlapping cards sit beside each
                                // other instead of piling up.
                                $colLayout = $computeLayout($colRows);
                            ?>
                            <?php foreach ($colRows as $rowIdx => $appt):
                                $time = (string) ($appt['appointment_time'] ?? '09:00:00');
                                $top    = $timeToTop($time);
                                $height = $durationToHeight((int) ($appt['duration_minutes'] ?? 60));

                                // Lane geometry — when this card shares its slot
                                // with others, split the column width so they
                                // render side by side. Single cards keep the
                                // CSS default (full width).
                                $lane     = (int) ($colLayout[$rowIdx]['lane']  ?? 0);
                                $laneCnt  = (int) ($colLayout[$rowIdx]['lanes'] ?? 1);
                                $laneStyle = $laneCnt > 1
                                    ? sprintf(
                                        'left:calc(0.25rem + (100%% - 0.5rem) * %d / %d);'
                                        . 'width:calc((100%% - 0.5rem) / %d - 2px);right:auto;',
                                        $lane, $laneCnt, $laneCnt
                                      )
                                    : '';
                                $apptKind  = (string) ($appt['appt_kind'] ?? 'measure');
                                $stageClr  = job_stage_colour((string) ($appt['status'] ?? ''), $appt['quote_status'] ?? null, $stagePalette, $apptKind);
                                $stageTint = job_status_tint($stageClr);
                                $isIssue   = !empty($appt['has_issue']);
                                $issueTxt  = trim((string) ($appt['issue_note'] ?? ''));
                                $dayOutline = $isIssue ? ';outline:2px solid ' . $issueColour . ';outline-offset:-2px'
                                            : ($apptKind === 'fitting' ? ';outline:2px solid #111827;outline-offset:-2px' : '');

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
                                $qref   = trim((string) ($appt['quote_number'] ?? ''));
                                $prog   = $quoteProgress($appt['quote_status'] ?? null);
                                // Progress-dot colour from the shared palette too.
                                $dqs = (string) ($appt['quote_status'] ?? '');
                                if ($dqs !== '' && isset($stagePalette[$dqs])) $prog['colour'] = $stagePalette[$dqs];

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
                                <!--
                                    Card is a <div>, NOT an <a>. We have <a>
                                    children (the action icons), and nested
                                    anchors are illegal in HTML5 — the
                                    browser auto-closes the parent on
                                    encountering a nested <a>, ejecting all
                                    the card content. data-href + the JS
                                    click handler at the bottom of the page
                                    gives us the navigate-on-click behaviour
                                    without the nesting trap.
                                -->
                                <div class="appt-card<?= $apptKind === 'fitting' ? ' is-fitting' : '' ?><?= $isIssue ? ' is-issue' : '' ?>"
                                     data-href="/calendar/edit.php?id=<?= (int) $appt['id'] ?>"
                                     title="<?= $isIssue ? '⚠ ISSUE' . ($issueTxt !== '' ? ': ' . e($issueTxt) : '') : '' ?>"
                                     style="top:<?= $top ?>px;
                                            height:<?= $height ?>px;
                                            <?= $laneStyle ?>
                                            background:<?= e($stageTint) ?>;
                                            border-left-color:<?= e($stageClr) ?>;
                                            color:var(--text-primary);
                                            --prog-clr:<?= e($prog['colour']) ?><?= e($dayOutline) ?>;">
                                    <div class="ac-time">
                                        <?= e($timeLabel) ?>
                                        <?php if ($qref !== ''): ?>
                                            <span class="ac-qref"><?= e($qref) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ac-title <?= $hasOnlyHeading ? 'ac-placeholder' : '' ?>">
                                        <?= $isIssue ? '⚠️ ' : '' ?><?= e($heading) ?>
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

                                    <?php if (!empty($appt['quote_status'])): ?>
                                        <!-- Status-progress bars — 6 thin lines bottom-right,
                                             one per lifecycle stage. Hover the card to see
                                             the verbal label via title=. -->
                                        <div class="ac-progress" title="<?= e($prog['label']) ?>">
                                            <?php for ($i = 0; $i < 6; $i++): ?>
                                                <span class="<?= $i < (int) $prog['filled'] ? 'is-on' : '' ?>"></span>
                                            <?php endfor; ?>
                                        </div>
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
                                </div>
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
    // column you clicked) pre-filled. Click on an existing card →
    // open the appointment edit page (the card is a div, not an
    // anchor, because nested <a> tags from action icons would
    // auto-close the parent and eject all the card content).
    var startHour = <?= (int) $startHour ?>;
    var pxPerHour = <?= (int) $pxPerHour ?>;
    var dateYmd   = '<?= e($dateYmd) ?>';

    // Existing card → navigate to edit page on click. Icon
    // anchors inside the card have their own onclick=stopPropagation
    // so the right action runs (tel:, mailto:, etc.) without ALSO
    // firing this navigate.
    document.querySelectorAll('.appt-card[data-href]').forEach(function (card) {
        card.addEventListener('click', function () {
            window.location.href = card.dataset.href;
        });
    });

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
