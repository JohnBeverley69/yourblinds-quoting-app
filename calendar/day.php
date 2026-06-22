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
require __DIR__ . '/../_partials/calendar_money.php';
require __DIR__ . '/../_partials/maps.php';

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

// Show money on cards? (Settings checkbox.) Resolved here because it sets the
// row scale below — a card with the money line needs a taller hour to fit.
$showMoney = false;
try {
    $mqStmt = $pdo->prepare('SELECT COALESCE(calendar_show_money, 0) FROM client_settings WHERE client_id = ?');
    $mqStmt->execute([$clientId]);
    $showMoney = ((int) $mqStmt->fetchColumn()) === 1;
} catch (Throwable $e) { /* column not migrated — figures stay off */ }

// Hour range. 7am → 10pm covers early starts, normal daytime, and
// evening fittings (some tenants quote installs after the homeowner
// gets in from work). Vertical scroll handles the height.
$startHour = 7;
$endHour   = 22;
$totalHours = $endHour - $startHour;
// Pixels per hour. Tall enough that a normal (~1h) appointment's card — money
// line included — fills its own hour slot, so the axis stays a TRUE linear
// ruler and only genuinely-overlapping bookings stretch it (and it recovers
// afterwards). Compact when money figures are off.
$pxPerHour  = $showMoney ? 90 : 60;
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

// Money figures per quote for the cards (the flag was resolved above).
$moneyByQuote = [];
if ($showMoney) {
    $qids = [];
    foreach ($apRows as $r) { if (!empty($r['quote_id'])) $qids[] = (int) $r['quote_id']; }
    $moneyByQuote = calendar_money_for_quotes($pdo, $clientId, $qids);
}
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
// Minimum card height — kept below one hour's pixels so a normal appointment
// fills its slot exactly (no false stretch). Taller when the money line shows.
$minCardPx = $showMoney ? 84 : 60;
$durationToHeight = static function (?int $minutes) use ($pxPerHour, $minCardPx): float {
    $m = $minutes && $minutes > 0 ? $minutes : 60;
    return max($minCardPx, ($m / 60) * $pxPerHour);
};

// Expanding timeline. Tightly-packed bookings need more vertical room than
// their literal duration — but we want the TIME AXIS to stretch with them so
// an hour label always sits level with the cards booked around that hour,
// rather than the cards drifting away from a fixed ruler.
//
// We build one shared, monotonic time→pixel map ($ymap) for the whole board:
//   - it starts out linear (pxPerHour),
//   - wherever a column's cards can't fit at full height, it stretches there
//     and everything below shifts down with it (it never snaps back up), and
//   - the SAME map positions the hour ticks AND the cards, so they stay aligned.
//
// It's a longest-path over "break points" (every hour boundary + every
// appointment start time): each card adds the rule that the next card in its
// column must sit at least a full card-height below it, which is what forces
// the stretch.
$timeToMin = static function (string $t) use ($startHour): int {
    [$h, $m] = array_pad(explode(':', $t), 2, 0);
    return (((int) $h) - $startHour) * 60 + (int) $m;
};
$linearY = static fn (int $min): float => ($min / 60) * $pxPerHour;

// Break points: every hour boundary, plus every appointment's start minute.
$breakSet = [];
for ($h = $startHour; $h <= $endHour; $h++) { $breakSet[($h - $startHour) * 60] = true; }

// Per-column card list (start minute + pixel height), and the "next card must
// clear this one" edges that force the timeline to stretch.
$colCards = [];   // cid => [ ['idx'=>rowIdx, 'min'=>startMin, 'h'=>height], ... ] (sorted)
$edges    = [];   // toMin => [ ['from'=>fromMin, 'w'=>weight], ... ]
foreach ($columns as $col) {
    $cid  = (int) $col['id'];
    $list = [];
    foreach (($byUser[$cid] ?? []) as $rowIdx => $r) {
        $sMin = $timeToMin((string) ($r['appointment_time'] ?? '09:00:00'));
        $list[] = ['idx' => $rowIdx, 'min' => $sMin,
                   'h'   => $durationToHeight((int) ($r['duration_minutes'] ?? 60))];
        $breakSet[$sMin] = true;
    }
    usort($list, static fn ($a, $b) => $a['min'] <=> $b['min']);
    for ($i = 0, $n = count($list); $i < $n - 1; $i++) {
        // Only between distinct times — identical-time cards in one column
        // can't both align to a tick; the per-column push-down below stacks
        // those.
        if ($list[$i + 1]['min'] > $list[$i]['min']) {
            $edges[$list[$i + 1]['min']][] = ['from' => $list[$i]['min'], 'w' => $list[$i]['h']];
        }
    }
    $colCards[$cid] = $list;
}

// Longest-path forward pass → a pixel y for every break point.
$mins = array_keys($breakSet);
sort($mins);
$ymap = [];
$prevMin = null;
foreach ($mins as $m) {
    $y = $linearY($m);
    if ($prevMin !== null) {
        // Monotonic only (never go backwards). NOT "previous + linear gap" —
        // that would carry an earlier stretch forward forever. Using the raw
        // previous y lets the axis RECOVER to the true linear ruler as soon as
        // the natural time positions catch up (i.e. once there's a gap to
        // absorb a cluster's extra height).
        $y = max($y, $ymap[$prevMin]);
    }
    foreach ($edges[$m] ?? [] as $e) {
        $y = max($y, $ymap[$e['from']] + $e['w']);   // a card below it must clear
    }
    $ymap[$m] = $y;
    $prevMin  = $m;
}

// Pixel y for an hour boundary (axis ticks + grid lines).
$hourY = static fn (int $h): float => $ymap[($h - $startHour) * 60] ?? (($h - $startHour) * $pxPerHour);

// Card positions: anchor each to the shared map, then a light per-column
// push-down as a safety net for any residual same-time overlaps. Distinct-time
// cards already clear each other via $ymap, so they stay put on the ticks.
$GAP_PX = 4;
$columnLayouts = [];
$maxBottom = $ymap[($endHour - $startHour) * 60] ?? (float) $gridHeight;
foreach ($colCards as $cid => $list) {
    $pos = [];
    $runningBottom = 0.0;
    foreach ($list as $c) {
        $natural = $ymap[$c['min']];
        $top = $runningBottom > 0 ? max($natural, $runningBottom + $GAP_PX) : $natural;
        $pos[$c['idx']] = ['top' => $top, 'height' => $c['h']];
        $runningBottom  = $top + $c['h'];
        $maxBottom      = max($maxBottom, $runningBottom);
    }
    $columnLayouts[$cid] = ['pos' => $pos];
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
    <title>Day view &middot; <?= e($date->format('D d/m')) ?> &middot; YourBlinds</title>
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
            border-radius: 6px; border-left: 6px solid transparent;
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
        /* Solid-fill cards (parity with the month view). Inner text reads off
           the luminance-aware --card-fg set inline, so it stays legible on any
           stage colour (light or dark). */
        .appt-card { color: var(--card-fg, var(--text-primary)); }
        .appt-card .ac-title { color: var(--card-fg); }
        .appt-card .ac-time,
        .appt-card .ac-desc,
        .appt-card .ac-addr,
        .appt-card .ac-phone { color: var(--card-fg); opacity: 0.82; }
        .appt-card .ac-placeholder { color: var(--card-fg); opacity: 0.7; }
        .appt-card .ac-qref { color: var(--card-fg); background: rgba(127,127,127,0.28); }
        .appt-card .ac-progress span { background: var(--card-fg); opacity: 0.3; }
        .appt-card .ac-progress span.is-on { background: var(--card-fg); opacity: 1; }
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
                <!-- View switcher sits under the title (like the main Calendar
                     page) rather than pushed to the far right of the toolbar. -->
                <div class="view-switch" style="margin-top:0.625rem;margin-left:0">
                    <a href="/calendar/index.php?month=<?= e($date->format('Y-m')) ?>">Month</a>
                    <a href="/calendar/week.php?date=<?= e($dateYmd) ?>">Week</a>
                    <a href="/calendar/day.php?date=<?= e($dateYmd) ?>" class="is-active">Day</a>
                    <?php if (!$canViewAll): ?>
                        <a href="/calendar/schedule.php">List</a>
                    <?php endif; ?>
                </div>
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
                <?= e($date->format('d/m/Y')) ?>
            </div>
            <form method="get" action="/calendar/day.php" class="day-jump">
                <input type="date" name="date" value="<?= e($dateYmd) ?>"
                       onchange="this.form.submit()">
            </form>
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

                <!-- Body: time axis + fitter columns. Height is the tallest
                     laid-out column (≥ the full time range) so pushed-down
                     cards in a busy column are never clipped. -->
                <div class="day-board-body"
                     style="height: <?= $boardHeight + 24 ?>px">
                    <!-- Time axis -->
                    <div class="time-axis" style="height: <?= $boardHeight ?>px">
                        <?php for ($h = $startHour; $h < $endHour; $h++):
                            $top = $hourY($h);
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
                             style="height: <?= $boardHeight ?>px">
                            <div class="new-hint">Click to create appointment</div>
                            <!-- Faint hour grid lines so eyes can snap to the time axis -->
                            <?php for ($h = $startHour; $h < $endHour; $h++): ?>
                                <div class="hour-line"
                                     style="top: <?= $hourY($h) ?>px"></div>
                            <?php endfor; ?>

                            <?php
                                // Precomputed push-down geometry for this column
                                // (full-width cards, collisions pushed down).
                                $colPos = $columnLayouts[$colId]['pos'] ?? [];
                            ?>
                            <?php foreach ($colRows as $rowIdx => $appt):
                                $time   = (string) ($appt['appointment_time'] ?? '09:00:00');
                                $top    = $colPos[$rowIdx]['top']    ?? $timeToTop($time);
                                $height = $colPos[$rowIdx]['height'] ?? $durationToHeight((int) ($appt['duration_minutes'] ?? 60));
                                $apptKind  = (string) ($appt['appt_kind'] ?? 'measure');
                                $stageClr  = job_stage_colour((string) ($appt['status'] ?? ''), $appt['quote_status'] ?? null, $stagePalette, $apptKind);
                                $stageFg   = job_status_text_colour($stageClr);   // readable text on the solid fill
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

                                // Maps deep-link — opens in the tenant's chosen
                                // app (Google Maps or Waze). Works on iOS /
                                // Android / desktop. Blank address → no icon.
                                $mapsUrl = map_nav_url($addr, map_provider_for($clientId));

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
                                            background:<?= e($stageClr) ?>;
                                            border-left-color:<?= e($stageClr) ?>;
                                            color:<?= e($stageFg) ?>;
                                            --card-fg:<?= e($stageFg) ?>;
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

                                    <?php if ($showMoney && !empty($appt['quote_id']) && isset($moneyByQuote[(int) $appt['quote_id']])): ?>
                                        <?= calendar_money_html($moneyByQuote[(int) $appt['quote_id']], false) ?>
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

    // Break points of the (possibly stretched) time axis as [y_px, minutes-
    // from-startHour], ascending. Used to convert a click's Y back into the
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
        // Below the last break point → extrapolate at the base scale.
        var last = axisPts[axisPts.length - 1];
        return last[1] + ((y - last[0]) / pxPerHour) * 60;
    }

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
            var mins = yToMin(y);                    // minutes since startHour
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
                var mins = yToMin(y);
                mins     = Math.max(0, Math.round(mins / 15) * 15);
                var h    = startHour + Math.floor(mins / 60);
                var m    = mins % 60;
                var hh = (h < 10 ? '0' : '') + h;
                var mm = (m < 10 ? '0' : '') + m;
                hint.textContent = '+ New at ' + hh + ':' + mm;
                hint.style.top = y + 'px';   // track the cursor (axis is non-linear)
            });
        }
    });
})();
</script>
</body>
</html>
