<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/job_status_colours.php';
require __DIR__ . '/../_partials/calendar_money.php';

requireLogin();

$user     = current_user();
$clientId = $user['client_id'];
$isAdmin  = $user['role'] === 'admin';

// Resolved traffic-light palette for this tenant (defaults + their overrides).
// Shared by the card render, the legend, and the live-refresh JS below.
$stagePalette = job_client_palette((int) $clientId);
$issueColour  = $stagePalette['issue'] ?? '#e11d48';

// ---------------------------------------------------------------------------
// Resolve the date range being viewed.
//
// Rolling 6-week window anchored on a specific Monday. Trade businesses
// plan week-by-week, not month-by-month — and the previous "current
// month" view forced an awkward flip whenever the planning week
// straddled a month boundary. The new model: pick a Monday, render the
// 42 days starting from it.
//
// URL conventions:
//   ?week=YYYY-MM-DD  — explicit Monday. Snaps to the Monday of the
//                       containing week if given a non-Monday date,
//                       so deep-links from elsewhere don't blow up.
//   ?month=YYYY-MM    — legacy. Used by older bookmarks and a few
//                       sibling pages. Anchors to the Monday of the
//                       week containing the 1st of that month, so
//                       the user's chosen month still mostly fills
//                       the view.
//   (no param)        — Monday of this week (i.e. "what's coming up").
//
// All date maths in the app timezone (set in bootstrap.php).
// ---------------------------------------------------------------------------
$weekParam  = (string) ($_GET['week']  ?? '');
$monthParam = (string) ($_GET['month'] ?? '');

$anchorMonday = null;
if ($weekParam !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekParam) === 1) {
    $maybe = DateTimeImmutable::createFromFormat('!Y-m-d', $weekParam);
    if ($maybe !== false) {
        // Snap to the Monday of the week that contains this date.
        $dayOfWeek = (int) $maybe->format('N');     // 1=Mon..7=Sun
        $anchorMonday = $maybe->modify('-' . ($dayOfWeek - 1) . ' days')->setTime(0, 0);
    }
}
if ($anchorMonday === null && $monthParam !== '' && preg_match('/^\d{4}-\d{2}$/', $monthParam) === 1) {
    $maybeMonth = DateTimeImmutable::createFromFormat('!Y-m', $monthParam);
    if ($maybeMonth !== false) {
        $firstOfMonth = $maybeMonth->modify('first day of this month');
        $dow          = (int) $firstOfMonth->format('N');
        $anchorMonday = $firstOfMonth->modify('-' . ($dow - 1) . ' days')->setTime(0, 0);
    }
}
if ($anchorMonday === null) {
    $today        = new DateTimeImmutable('today');
    $dow          = (int) $today->format('N');
    $anchorMonday = $today->modify('-' . ($dow - 1) . ' days')->setTime(0, 0);
}

// Range: 42 days starting at the anchor Monday → 6 full weeks.
$WEEKS_VISIBLE = 6;
$rangeStart    = $anchorMonday;
$rangeEnd      = $anchorMonday->modify('+' . ($WEEKS_VISIBLE * 7 - 1) . ' days')->setTime(23, 59, 59);

// Helpers for nav links.
$prevWeek      = $anchorMonday->modify('-7 days')->format('Y-m-d');
$nextWeek      = $anchorMonday->modify('+7 days')->format('Y-m-d');
$thisWeekMonday = (new DateTimeImmutable('today'))
    ->modify('-' . ((int) (new DateTimeImmutable('today'))->format('N') - 1) . ' days')
    ->format('Y-m-d');
$todayStr      = (new DateTimeImmutable('today'))->format('Y-m-d');
$isOnThisWeek  = $anchorMonday->format('Y-m-d') === $thisWeekMonday;

// Dominant month — for the title + for the "this is the focal month"
// visual cue. Count how many days of each month fall in the 42-day
// window; the highest count wins (later month on ties).
$monthDayCounts = [];
for ($i = 0; $i < $WEEKS_VISIBLE * 7; $i++) {
    $d  = $anchorMonday->modify('+' . $i . ' days');
    $ym = $d->format('Y-m');
    $monthDayCounts[$ym] = ($monthDayCounts[$ym] ?? 0) + 1;
}
arsort($monthDayCounts);   // most days first
$dominantYm = array_key_first($monthDayCounts);
$dominantLabel = $dominantYm
    ? (new DateTimeImmutable($dominantYm . '-01'))->format('F Y')
    : '';

// Legacy variables kept for sibling pages that read $thisMonth etc.
$firstOfMonth = $anchorMonday;   // kept name for downstream references
$thisMonth    = (new DateTimeImmutable('first day of this month'))->format('Y-m');

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
                a.duration_minutes, a.status, a.appt_kind, a.quote_id, a.access_note,
                a.has_issue, a.issue_note,
                a.installation_town, a.installation_postcode,
                c.name AS customer_name,
                q.status AS quote_status
           FROM appointments a
      LEFT JOIN customers c ON c.id = a.customer_id
      LEFT JOIN quotes q ON q.id = a.quote_id
          WHERE a.client_id = ?
            AND a.client_user_id = ?
            AND a.appointment_date BETWEEN ? AND ?
       ORDER BY a.appointment_date, a.appointment_time'
    );
    $stmt->execute([
        $clientId,
        (int) $user['user_id'],
        $rangeStart->format('Y-m-d'),
        $rangeEnd->format('Y-m-d'),
    ]);
} else {
    $stmt = db()->prepare(
        'SELECT a.id, a.title, a.appointment_date, a.appointment_time,
                a.duration_minutes, a.status, a.appt_kind, a.quote_id, a.access_note,
                a.has_issue, a.issue_note,
                a.installation_town, a.installation_postcode,
                c.name AS customer_name,
                q.status AS quote_status
           FROM appointments a
      LEFT JOIN customers c ON c.id = a.customer_id
      LEFT JOIN quotes q ON q.id = a.quote_id
          WHERE a.client_id = ?
            AND a.appointment_date BETWEEN ? AND ?
       ORDER BY a.appointment_date, a.appointment_time'
    );
    $stmt->execute([
        $clientId,
        $rangeStart->format('Y-m-d'),
        $rangeEnd->format('Y-m-d'),
    ]);
}

// "Fittings only" users (typically fitters) see just the fitting jobs —
// measure/sales visits are hidden from their calendar.
$fittingsOnly = !empty(current_user_permissions()['can_view_fittings_only']);

// ?issue=1 → show only flagged-issue jobs. $issueCount is the total in view
// (counted before the filter) so the toggle can show a badge.
$issueOnly  = isset($_GET['issue']) && (string) $_GET['issue'] === '1';
$issueCount = 0;

$byDate = [];
foreach ($stmt->fetchAll() as $row) {
    if ($fittingsOnly && (string) ($row['appt_kind'] ?? 'measure') !== 'fitting') continue;
    if (!empty($row['has_issue'])) $issueCount++;
    if ($issueOnly && empty($row['has_issue'])) continue;
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

// Calendar money figures — gated by the per-tenant Settings checkbox. When on,
// batch-load each linked quote's value / received / balance, and pre-render the
// money HTML keyed by quote id so both the PHP cells AND the JS re-render (used
// after a drag) can show the same line.
$showMoney = false;
try {
    $mqStmt = db()->prepare('SELECT COALESCE(calendar_show_money, 0) FROM client_settings WHERE client_id = ?');
    $mqStmt->execute([$clientId]);
    $showMoney = ((int) $mqStmt->fetchColumn()) === 1;
} catch (Throwable $e) { /* column not migrated — figures stay off */ }
$moneyByQuote     = [];
$moneyHtmlByQuote = [];   // quote_id => pre-rendered HTML (white text, for the coloured pills)
if ($showMoney) {
    $qids = [];
    foreach ($byDate as $rows) {
        foreach ($rows as $r) { if (!empty($r['quote_id'])) $qids[] = (int) $r['quote_id']; }
    }
    foreach ($pendingAppts as $r) { if (!empty($r['quote_id'])) $qids[] = (int) $r['quote_id']; }
    $moneyByQuote = calendar_money_for_quotes(db(), (int) $clientId, $qids);
    foreach ($moneyByQuote as $qid => $m) {
        $moneyHtmlByQuote[$qid] = calendar_money_html($m, true);
    }
}

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
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
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
            border: 1px solid var(--border-strong);
            border-radius: 8px;
            background: var(--bg-card);
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 1.125rem;
            line-height: 1;
        }
        .cal-nav-btn:hover { background: var(--bg-subtle-2); }
        .cal-nav-today {
            font-size: 0.9375rem;
            padding: 0 0.875rem;
            min-width: 0;
        }
        .cal-month-label {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            min-width: 11rem;
            text-align: center;
        }
        /* Everyone / Just-me pill toggle in the page header — replaces
           the old "My Diary" sidebar entry. Two adjacent anchors,
           filled when active, outlined when not. */
        .cal-view-toggle {
            display: inline-flex;
            border: 1px solid var(--border-strong);
            border-radius: 999px;
            overflow: hidden;
            font-size: 0.875rem;
            background: var(--bg-card);
        }
        .cal-toggle-btn {
            padding: 0.4375rem 0.875rem;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
            white-space: nowrap;
        }
        .cal-toggle-btn:hover { background: var(--bg-subtle-2); }
        .cal-toggle-btn.is-active {
            background: var(--brand);
            color: #fff;
        }
        .cal-toggle-btn + .cal-toggle-btn { border-left: 1px solid var(--border-strong); }

        .cal-legend {
            display: flex;
            gap: 0.875rem;
            font-size: 0.8125rem;
            color: var(--text-muted);
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
        .cal-issue-toggle {
            display: inline-flex; align-items: center; gap: 0.375rem;
            text-decoration: none; color: var(--text-muted);
            border: 1px solid transparent; border-radius: 999px;
            padding: 0.0625rem 0.5rem; cursor: pointer; font-size: 0.8125rem;
        }
        .cal-issue-toggle i { display: inline-block; width: 10px; height: 10px; border-radius: 2px; }
        .cal-issue-toggle:hover { border-color: var(--border-strong); color: var(--text-primary); }
        .cal-issue-toggle.is-active { background: var(--issue-clr, #e11d48); color: #fff; border-color: transparent; }
        .cal-issue-toggle.is-active i { outline-color: #fff !important; }
        .cal-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 1px;
            background: var(--border);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
        }
        .cal-weekday {
            background: var(--bg-sidebar);
            color: #fff;
            text-align: center;
            font-size: 0.8125rem;
            font-weight: 600;
            padding: 0.625rem 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .cal-cell {
            background: var(--bg-card);
            min-height: 120px;
            padding: 0.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
            position: relative;
        }
        /* Days that fall outside the dominant month in the rolling
           6-week view get a muted background — the user still has
           a "what month am I mostly looking at" anchor without
           those cells reading as disabled. Add affordance,
           appointments, and drag-and-drop work normally on them. */
        .cal-cell.is-outside-focus { background: var(--bg-subtle); }
        .cal-cell.is-outside-focus .cal-day-num { opacity: 0.7; }
        /* Today: darker blue tint + navy inset stripe down the left
           edge. Tyler reported #eff6ff was too washed out on his
           monitor to spot at a glance. The stripe gives a strong
           visual anchor without making the whole cell read as an
           alert state. In dark mode, uses a translucent overlay so
           appt cards still read against it. */
        .cal-cell.is-today {
            background: #dbeafe;
            box-shadow: inset 3px 0 0 #1f3b5b;
        }
        [data-theme="dark"] .cal-cell.is-today {
            background: rgba(96, 165, 250, 0.15);
            box-shadow: inset 3px 0 0 #60a5fa;
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
            color: var(--link);
            font-size: 1.25rem;
            font-weight: 600;
            line-height: 1;
            opacity: 0;
            transition: opacity 0.15s ease;
            pointer-events: none;
        }
        .cal-cell:hover .cal-cell-add::after { opacity: 0.45; }
        .cal-day-num {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-faint);
            line-height: 1;
            pointer-events: none;
        }
        .cal-cell.is-today .cal-day-num {
            color: #fff;
            background: #1f3b5b;
            border-radius: 999px;
            padding: 0.1875rem 0.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            /* Pill, not a fixed circle — the DD/MM date needs the width.
               flex-start stops the span stretching across the cell. */
            align-self: flex-start;
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
        .cal-appt.status-no_show   { background: var(--text-faint); }

        /* Cards born from an accepted-quote (quote_id is set) — a more
           specific signal: "this is work the customer has signed off,
           schedule with confidence". Purple so it doesn't clash with
           the green Completed swatch in the legend. Once the
           appointment is marked completed the .status-completed rule
           still wins and the card turns green as expected (no .from-
           quote override on completed). */
        .cal-appt.from-quote.status-booked { background: #7c3aed; }

        /* Measure vs fitting. The card colour shows the job STAGE; this dark
           outline marks FITTINGS (the install visits) so they stand apart from
           measure/survey visits at a glance. Measures have no outline. */
        .cal-appt.is-fitting {
            outline: 2px solid #111827;
            outline-offset: -2px;
        }
        [data-theme="dark"] .cal-appt.is-fitting { outline-color: #f9fafb; }

        /* Issue flag — a job that's gone sideways. A red ⚠ marker (full-colour
           when flagged) plus a red ring that OVERRIDES the fitting outline, so a
           problem job jumps out whatever stage/kind it is. The stage colour
           underneath still tells you where it sits in the chain. */
        .cal-appt.is-issue {
            outline: 2px solid var(--issue-clr, #e11d48) !important;
            outline-offset: -2px;
        }
        .cal-appt-issue {
            flex: 0 0 auto;
            margin-left: 0.2rem;
            font-size: 0.72rem;
            line-height: 1;
            cursor: pointer;
            /* Faded when inactive, but legible on the coloured cards: lift the
               opacity and brighten the greyscale toward white, with a soft dark
               halo so the glyph reads on any card colour. Active states below
               restore full colour + opacity. */
            opacity: 0.8;
            filter: grayscale(1) brightness(1.4);
            text-shadow: 0 1px 1px rgba(0, 0, 0, 0.4);
        }
        .cal-appt-issue:hover, .cal-appt-issue:focus { opacity: 1; filter: none; outline: none; }
        .cal-appt.is-issue .cal-appt-issue { opacity: 1; filter: none; }

        /* Quick "access note" marker. A small 📝 on every card to add/edit a
           note; when a note exists the card gets a bold amber left bar so it's
           obvious at a glance, and the marker goes full-colour. */
        .cal-appt-note {
            flex: 0 0 auto;
            margin-left: 0.2rem;
            font-size: 0.72rem;
            line-height: 1;
            cursor: pointer;
            /* Faded when inactive, but legible on the coloured cards: lift the
               opacity and brighten the greyscale toward white, with a soft dark
               halo so the glyph reads on any card colour. Active states below
               restore full colour + opacity. */
            opacity: 0.8;
            filter: grayscale(1) brightness(1.4);
            text-shadow: 0 1px 1px rgba(0, 0, 0, 0.4);
        }
        .cal-appt-note:hover, .cal-appt-note:focus { opacity: 1; filter: none; outline: none; }
        .cal-appt.has-note { box-shadow: inset 4px 0 0 #f59e0b; }
        .cal-appt.has-note .cal-appt-note { opacity: 1; filter: none; }

        /* Quick-note popover */
        .note-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.45);
                      display: flex; align-items: center; justify-content: center;
                      z-index: 1000; padding: 1rem; }
        .note-modal[hidden] { display: none; }
        .note-modal-box { background: var(--bg-card); color: var(--text-body);
                          border-radius: 12px; padding: 1.25rem; width: 100%;
                          max-width: 26rem; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        .note-modal-box h3 { margin: 0 0 0.25rem; font-size: 1.0625rem; }
        .note-modal-sub { margin: 0 0 0.75rem; font-size: 0.8125rem; color: var(--text-faint); }
        .note-modal-box textarea { width: 100%; border: 1px solid var(--border-strong);
                          border-radius: 8px; padding: 0.625rem 0.75rem; font: inherit;
                          background: var(--bg-input); color: var(--text-body); resize: vertical; }
        .note-modal-actions { display: flex; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap; }
        .note-modal-err { margin-top: 0.5rem; color: #b91c1c; font-size: 0.8125rem; }

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
                border-bottom: 1px solid var(--border);
            }
            .cal-day-num::after {
                content: attr(data-weekday);
                font-weight: 400;
                color: var(--text-faint);
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
            /* Card background is a hardcoded light (#fff), so the text must
               be a hardcoded dark too — var(--text-primary) goes near-white
               in dark mode and the card became unreadable (Tyler). */
            font-size: 0.8125rem; color: #1f2937;
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
                </p>
                <?php if ($canViewAll): ?>
                    <!-- Everyone / Just-me view toggle. Sits under the
                         subtitle alongside the other view switches (Week /
                         Day), deliberately kept clear of the primary action
                         buttons on the right — a rounded pill next to the
                         rectangular buttons read as clustered (Tyler). Only
                         shown to admins and users with can_view_all_customer_jobs;
                         others are locked to their own appointments. -->
                    <div class="cal-view-toggle" role="group" aria-label="Calendar view" style="margin-top:0.625rem">
                        <a href="/calendar/index.php?week=<?= e($anchorMonday->format('Y-m-d')) ?>"
                           class="cal-toggle-btn <?= $mineOnly ? '' : 'is-active' ?>">
                            Everyone
                        </a>
                        <a href="/calendar/index.php?mine=1&week=<?= e($anchorMonday->format('Y-m-d')) ?>"
                           class="cal-toggle-btn <?= $mineOnly ? 'is-active' : '' ?>">
                            Just me
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="actions-bar">
                <?php if ($mapsEnabled): ?>
                    <a href="/calendar/run.php" class="btn btn-success">Today's run &rarr;</a>
                <?php endif; ?>
                <?php
                    // "+ New quote" button alongside the existing
                    // "+ Book Appointment". Per Tyler's review: the
                    // new-quote action shouldn't be hidden behind a
                    // sidebar entry — it lives on every landing.
                    $calPerms = function_exists('current_user_permissions')
                        ? current_user_permissions()
                        : [];
                    $canCreateQuotesHere = $isAdmin
                        || !empty($calPerms['can_create_quotes']);
                ?>
                <?php if ($canCreateQuotesHere): ?>
                    <a href="/quote-builder/new.php" class="btn btn-secondary">+ New quote</a>
                <?php endif; ?>
                <a href="/calendar/new.php" class="btn btn-primary">+ Book Appointment</a>
            </div>
        </div>

        <?php
            // Preserve mine=1 across week navigation so the diary view
            // doesn't break out to "show all" when the user clicks
            // prev/next week. Helper hands back a fully-formed href.
            $weekQs = static fn (string $ymd): string =>
                '?week=' . urlencode($ymd) . ($mineOnly ? '&mine=1' : '');
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
                       href="<?= e($weekQs($prevWeek)) ?>"
                       aria-label="Previous week"
                       rel="prev">&lsaquo;</a>
                    <span class="cal-month-label">
                        <?= e($dominantLabel) ?>
                        <small style="display:block;font-size:0.6875rem;font-weight:500;color:var(--text-faint);text-transform:none;letter-spacing:0;margin-top:0.0625rem">
                            <?= e($rangeStart->format('D j M')) ?> &mdash;
                            <?= e($rangeEnd->format('D j M')) ?>
                        </small>
                    </span>
                    <a class="cal-nav-btn"
                       href="<?= e($weekQs($nextWeek)) ?>"
                       aria-label="Next week"
                       rel="next">&rsaquo;</a>
                    <?php if (!$isOnThisWeek): ?>
                        <a class="cal-nav-btn cal-nav-today"
                           href="<?= e($todayHref) ?>">Today</a>
                    <?php endif; ?>
                </div>
                <div class="cal-legend" aria-label="Status colours">
                    <?php
                        // Legend generated from the same palette the cards use, so
                        // recolouring a stage never drifts out of sync. The calendar
                        // now shows the whole pipeline: a MEASURE entry walks the
                        // quote stages, a FITTING entry the install stages.
                        $legendLabels = job_status_labels();
                        foreach ($legendLabels as $stageKey => $stageLbl):
                            if ($stageKey === 'issue') continue;   // a flag, shown separately
                            $swatch = $stagePalette[$stageKey] ?? '#2563eb';
                    ?>
                        <span><i style="background:<?= e($swatch) ?>"></i> <?= e($stageLbl) ?></span>
                    <?php endforeach; ?>
                    <span title="Fittings carry a dark outline; measures don't.">
                        <i style="background:transparent;outline:2px solid #111827;outline-offset:-2px"></i> = Fitting
                    </span>
                    <?php
                        // Issues filter — also the legend entry for the red ring.
                        $issueHref = '/calendar/index.php?week=' . urlencode($anchorMonday->format('Y-m-d'))
                            . ($mineOnly ? '&mine=1' : '')
                            . ($issueOnly ? '' : '&issue=1');
                    ?>
                    <a href="<?= e($issueHref) ?>" class="cal-issue-toggle<?= $issueOnly ? ' is-active' : '' ?>"
                       style="--issue-clr:<?= e($issueColour) ?>"
                       title="<?= $issueOnly ? 'Showing only flagged issues — click to show all jobs' : 'Show only flagged-issue jobs' ?>">
                        <i style="background:transparent;outline:2px solid var(--issue-clr);outline-offset:-2px"></i>
                        &#9888;&#65039; Issues<?= $issueCount > 0 ? ' (' . (int) $issueCount . ')' : '' ?>
                    </a>
                </div>
            </div>

            <?php if ($mineOnly && empty($byDate)): ?>
                <!-- Empty-state hint when the user can't see Everyone and
                     hasn't got any appointments of their own yet. Without
                     this the grid just looks empty and broken. -->
                <div style="background:#eef2f7;border:1px dashed #cbd5e1;
                            border-radius:10px;padding:1rem 1.125rem;
                            margin:0 0 1rem;color:#1f3b5b;font-size:0.9375rem">
                    No appointments assigned to you in this 6-week window.
                    <?php if (!$canViewAll): ?>
                        Once an admin assigns you to a job, it'll appear
                        here and on your <a href="/calendar/schedule.php"
                        style="color:#1f3b5b;font-weight:600">My Schedule</a> page.
                    <?php else: ?>
                        Switch back to <a href="/calendar/index.php?week=<?= e($anchorMonday->format('Y-m-d')) ?>"
                        style="color:#1f3b5b;font-weight:600">Everyone</a>
                        to see the whole team's diary.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="cal-grid" role="grid" aria-label="<?= e($dominantLabel) ?>">
                <?php foreach ($weekdayLabels as $wd): ?>
                    <div class="cal-weekday" role="columnheader"><?= e($wd) ?></div>
                <?php endforeach; ?>

                <?php
                    // Rolling 6-week grid: 42 cells from $anchorMonday.
                    // Days that fall OUTSIDE the dominant month get a
                    // muted background (.is-outside-focus) so the user
                    // still has a visual anchor for "which month is
                    // mostly on screen" without losing functionality.
                    $totalCells = $WEEKS_VISIBLE * 7;
                    for ($i = 0; $i < $totalCells; $i++):
                        $cellDate    = $anchorMonday->modify('+' . $i . ' days');
                        $iso         = $cellDate->format('Y-m-d');
                        $isToday     = $iso === $todayStr;
                        $weekday3    = $cellDate->format('D');
                        $appts       = $byDate[$iso] ?? [];
                        // DD/MM rather than a bare day number — makes the
                        // date unambiguous in every cell (Tyler), regardless
                        // of which month the rolling 6-week window spans.
                        $dayLabel    = $cellDate->format('d/m');
                        $isOutside   = $dominantYm !== null
                                    && $cellDate->format('Y-m') !== $dominantYm;
                        $cellClasses = 'cal-cell';
                        if ($isOutside) $cellClasses .= ' is-outside-focus';
                        if ($isToday)   $cellClasses .= ' is-today';
                ?>
                    <div class="<?= e($cellClasses) ?>" role="gridcell"
                         data-date="<?= e($iso) ?>">
                        <a class="cal-cell-add"
                           href="/calendar/new.php?date=<?= e($iso) ?>"
                           aria-label="New appointment on <?= e($cellDate->format('j F Y')) ?>"></a>
                        <span class="cal-day-num" data-weekday="<?= e($weekday3) ?>"><?= e($dayLabel) ?></span>
                        <?php if ($appts): ?>
                            <div class="cal-appts">
                                <?php foreach ($appts as $a): ?>
                                    <?php
                                        $noteTxt  = trim((string) ($a['access_note'] ?? ''));
                                        $apptKind = (string) ($a['appt_kind'] ?? 'measure');
                                        $isFit    = $apptKind === 'fitting';
                                        $isIssue  = !empty($a['has_issue']);
                                        $issueTxt = trim((string) ($a['issue_note'] ?? ''));
                                    ?>
                                    <a class="cal-appt status-<?= e((string) $a['status']) ?><?= !empty($a['quote_id']) ? ' from-quote' : '' ?><?= $noteTxt !== '' ? ' has-note' : '' ?><?= $isFit ? ' is-fitting' : ' is-measure' ?><?= $isIssue ? ' is-issue' : '' ?>"
                                       style="background:<?= e(job_stage_colour((string) $a['status'], isset($a['quote_status']) ? (string) $a['quote_status'] : null, $stagePalette, $apptKind)) ?><?= $isIssue ? ';--issue-clr:' . e($issueColour) : '' ?>"
                                       href="/calendar/view.php?id=<?= (int) $a['id'] ?>"
                                       draggable="true"
                                       data-id="<?= (int) $a['id'] ?>"
                                       <?php if (!empty($a['quote_id'])): ?>
                                           data-quote-id="<?= (int) $a['quote_id'] ?>"
                                       <?php endif; ?>
                                       title="<?= $isFit ? 'Fitting' : 'Measure' ?>: <?= e((string) $a['title']) ?> &mdash; <?= e((string) $a['status']) ?><?= $isIssue ? ' &mdash; ⚠ ISSUE' . ($issueTxt !== '' ? ': ' . e($issueTxt) : '') : '' ?><?= $noteTxt !== '' ? ' &mdash; ' . e($noteTxt) : '' ?>">
                                        <span class="cal-appt-time"><?= e($fmtTime((string) $a['appointment_time'])) ?></span>
                                        <span class="cal-appt-title"><?= e((string) $a['title']) ?></span>
                                        <span class="cal-appt-issue" role="button" tabindex="0"
                                              data-id="<?= (int) $a['id'] ?>"
                                              data-issue="<?= $isIssue ? '1' : '0' ?>"
                                              data-issue-note="<?= e($issueTxt) ?>"
                                              title="<?= $isIssue ? ($issueTxt !== '' ? e($issueTxt) : 'Flagged issue') : 'Flag an issue' ?>"
                                              aria-label="<?= $isIssue ? 'Edit issue' : 'Flag issue' ?>">&#9888;&#65039;</span>
                                        <span class="cal-appt-note" role="button" tabindex="0"
                                              data-id="<?= (int) $a['id'] ?>"
                                              data-note="<?= e($noteTxt) ?>"
                                              title="<?= $noteTxt !== '' ? e($noteTxt) : 'Add a note' ?>"
                                              aria-label="<?= $noteTxt !== '' ? 'Edit note' : 'Add note' ?>">&#128221;</span>
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
                                        <?php if ($showMoney && !empty($a['quote_id']) && isset($moneyHtmlByQuote[(int) $a['quote_id']])): ?>
                                            <?= $moneyHtmlByQuote[(int) $a['quote_id']] ?>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </section>
    </main>
</div>

<div id="note-modal" class="note-modal" hidden>
    <div class="note-modal-box" role="dialog" aria-modal="true" aria-labelledby="note-modal-title">
        <h3 id="note-modal-title">Appointment note</h3>
        <p class="note-modal-sub">A short reminder for the day &mdash; e.g. &ldquo;tap gently, baby asleep&rdquo;.</p>
        <textarea id="note-modal-text" rows="3" maxlength="280" placeholder="Add a note…"></textarea>
        <div class="note-modal-actions">
            <button type="button" id="note-modal-save" class="btn btn-primary">Save</button>
            <button type="button" id="note-modal-remove" class="btn btn-secondary">Remove</button>
            <button type="button" id="note-modal-cancel" class="btn btn-secondary">Cancel</button>
        </div>
        <div id="note-modal-err" class="note-modal-err" hidden></div>
    </div>
</div>

<div id="issue-modal" class="note-modal" hidden>
    <div class="note-modal-box" role="dialog" aria-modal="true" aria-labelledby="issue-modal-title">
        <h3 id="issue-modal-title">⚠️ Flag an issue</h3>
        <p class="note-modal-sub">What's the problem? &mdash; e.g. &ldquo;wrong colour delivered&rdquo;, &ldquo;no access&rdquo;, &ldquo;remake needed&rdquo;.</p>
        <textarea id="issue-modal-text" rows="3" maxlength="280" placeholder="Describe the issue (optional)…"></textarea>
        <div class="note-modal-actions">
            <button type="button" id="issue-modal-save" class="btn btn-primary">Flag as issue</button>
            <button type="button" id="issue-modal-clear" class="btn btn-secondary">Clear issue</button>
            <button type="button" id="issue-modal-cancel" class="btn btn-secondary">Cancel</button>
        </div>
        <div id="issue-modal-err" class="note-modal-err" hidden></div>
    </div>
</div>
<script>
(function () {
    'use strict';

    var endpoint  = '/calendar/reschedule.php';
    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // Job-stage traffic-light palette. Mirrors _partials/job_status_colours.php
    // so cards re-rendered by the 15s poll colour identically to the server
    // render — single source of truth emitted from PHP.
    var STAGE_PALETTE = <?= json_encode($stagePalette, JSON_UNESCAPED_SLASHES) ?>;
    // Pre-rendered money line per quote id (empty {} when the figures are off),
    // so a drag re-render shows the same value/balance line as the initial load.
    var CAL_MONEY = <?= json_encode($moneyHtmlByQuote ?: new stdClass(), JSON_UNESCAPED_SLASHES) ?>;
    var ISSUE_CLR = STAGE_PALETTE['issue'] || '#e11d48';
    function jobStage(apptStatus, quoteStatus, apptKind) {
        if (apptStatus === 'cancelled') return 'cancelled';
        if (apptStatus === 'no_show')   return 'no_show';
        var qs = quoteStatus || '';
        if (apptKind === 'fitting') {
            if (qs === 'fitted' || qs === 'invoiced' || qs === 'paid') return qs;
            if (apptStatus === 'completed') return 'fitted';
            return 'booked';   // fitting booked
        }
        // Measure / survey visit — follows the quote through its early life.
        if (qs === '') return 'appointment_booked';
        if (['draft','sent','accepted','declined','ordered','fitted','invoiced','paid'].indexOf(qs) !== -1) return qs;
        return 'appointment_booked';
    }
    function jobStageColour(apptStatus, quoteStatus, apptKind) {
        return STAGE_PALETTE[jobStage(apptStatus, quoteStatus, apptKind)] || '#2563eb';
    }

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
    var rangeStart   = '<?= e($rangeStart->format('Y-m-d')) ?>';
    var rangeEnd     = '<?= e($rangeEnd->format('Y-m-d')) ?>';
    var pollEndpoint = '/calendar/pending.php?start=' + encodeURIComponent(rangeStart)
                     + '&end=' + encodeURIComponent(rangeEnd)
        + (window.location.search.indexOf('mine=1') !== -1 ? '&mine=1' : '')
        + (window.location.search.indexOf('issue=1') !== -1 ? '&issue=1' : '');
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
                var note       = a.access_note || '';
                var hasNoteCls = note ? ' has-note' : '';
                var kind       = a.appt_kind || 'measure';
                var kindCls    = kind === 'fitting' ? ' is-fitting' : ' is-measure';
                var kindLabel  = kind === 'fitting' ? 'Fitting' : 'Measure';
                var isIssue    = !!(a.has_issue && a.has_issue != 0);
                var issueNote  = a.issue_note || '';
                var issueCls   = isIssue ? ' is-issue' : '';
                var issueStyle = isIssue ? ';--issue-clr:' + ISSUE_CLR : '';
                var issueHtml  = '<span class="cal-appt-issue" role="button" tabindex="0"'
                      + ' data-id="' + a.id + '"'
                      + ' data-issue="' + (isIssue ? '1' : '0') + '"'
                      + ' data-issue-note="' + escapeAttr(issueNote) + '"'
                      + ' title="' + escapeAttr(isIssue ? (issueNote || 'Flagged issue') : 'Flag an issue') + '"'
                      + ' aria-label="' + (isIssue ? 'Edit issue' : 'Flag issue') + '">⚠️</span>';
                var noteHtml   = '<span class="cal-appt-note" role="button" tabindex="0"'
                      + ' data-id="' + a.id + '"'
                      + ' data-note="' + escapeAttr(note) + '"'
                      + ' title="' + escapeAttr(note || 'Add a note') + '"'
                      + ' aria-label="' + (note ? 'Edit note' : 'Add note') + '">📝</span>';
                html += '<a class="cal-appt status-' + escapeAttr(a.status) + fromQuoteCls + hasNoteCls + kindCls + issueCls + '"'
                     +  ' style="background:' + escapeAttr(jobStageColour(a.status, a.quote_status, kind)) + issueStyle + '"'
                     +  ' href="/calendar/view.php?id=' + a.id + '"'
                     +  ' draggable="true" data-id="' + a.id + '"'
                     +  quoteAttr
                     +  ' title="' + escapeAttr(kindLabel + ': ' + a.title + ' — ' + a.status + (isIssue ? ' — ⚠ ISSUE' + (issueNote ? ': ' + issueNote : '') : '') + (note ? ' — ' + note : '')) + '">'
                     +    '<span class="cal-appt-time">'  + escapeHtml(a.time)  + '</span>'
                     +    '<span class="cal-appt-title">' + escapeHtml(a.title) + '</span>'
                     +    issueHtml
                     +    noteHtml
                     +    openOrderHtml
                     +    (a.quote_id && CAL_MONEY[a.quote_id] ? CAL_MONEY[a.quote_id] : '')
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
    function reschedule(id, date, override) {
        var fd = new FormData();
        fd.append('appointment_id',  id);
        fd.append('appointment_date', date);
        if (override) fd.append('override', '1');

        fetch(endpoint, {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-Token': csrfToken },
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json();
        }).then(function (data) {
            if (data.ok) {
                // Pull the freshest state from the server (also picks up
                // anything any other open tab / colleague has done in
                // between). Surgical DOM update via the same path the
                // polling loop uses — no full page reload, no flash.
                refreshAll();
                return;
            }
            // Overridable double-booking warning — confirm, then retry.
            if (data.conflict && !override) {
                if (confirm((data.error || 'Double-booking.') + '\n\nBook anyway?')) {
                    reschedule(id, date, true);
                } else {
                    refreshAll();   // user declined — snap the card back to truth
                }
                return;
            }
            throw new Error(data.error || 'Save failed.');
        }).catch(function (err) {
            alert(err.message || 'Could not reschedule.');
        });
    }

    // -- Quick access-note popover -----------------------------------
    var noteModal  = document.getElementById('note-modal');
    var noteText   = document.getElementById('note-modal-text');
    var noteErr    = document.getElementById('note-modal-err');
    var noteApptId = null;

    function openNote(id, current) {
        noteApptId = id;
        noteText.value = current || '';
        noteErr.hidden = true;
        noteModal.hidden = false;
        noteText.focus();
    }
    function closeNote() { if (noteModal) { noteModal.hidden = true; } noteApptId = null; }

    function saveNote(value) {
        if (!noteApptId) return;
        var fd = new FormData();
        fd.append('appointment_id', noteApptId);
        fd.append('access_note', value);
        fetch('/calendar/save_note.php', {
            method: 'POST', body: fd,
            headers: { 'X-CSRF-Token': csrfToken },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); })
          .then(function (data) {
              if (!data.ok) throw new Error(data.error || 'Save failed.');
              closeNote();
              refreshAll();   // re-render cards so the marker updates
          }).catch(function (err) {
              noteErr.textContent = err.message || 'Could not save note.';
              noteErr.hidden = false;
          });
    }

    // Open on note-marker click/tap — delegated so it works for the cards
    // the poll loop re-renders too. Stops the card's view.php link firing.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.cal-appt-note');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        openNote(btn.getAttribute('data-id'), btn.getAttribute('data-note') || '');
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var el = document.activeElement;
        if (!el || !el.classList || !el.classList.contains('cal-appt-note')) return;
        e.preventDefault();
        openNote(el.getAttribute('data-id'), el.getAttribute('data-note') || '');
    });

    if (noteModal) {
        document.getElementById('note-modal-save').addEventListener('click', function () { saveNote(noteText.value); });
        document.getElementById('note-modal-remove').addEventListener('click', function () { saveNote(''); });
        document.getElementById('note-modal-cancel').addEventListener('click', closeNote);
        noteModal.addEventListener('click', function (e) { if (e.target === noteModal) closeNote(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !noteModal.hidden) closeNote(); });
    }

    // -- Quick issue popover -----------------------------------------
    var issueModal  = document.getElementById('issue-modal');
    var issueText   = document.getElementById('issue-modal-text');
    var issueErr    = document.getElementById('issue-modal-err');
    var issueApptId = null;

    function openIssue(id, note) {
        issueApptId = id;
        issueText.value = note || '';
        issueErr.hidden = true;
        issueModal.hidden = false;
        issueText.focus();
    }
    function closeIssue() { if (issueModal) { issueModal.hidden = true; } issueApptId = null; }

    function saveIssue(hasIssue, note) {
        if (!issueApptId) return;
        var fd = new FormData();
        fd.append('appointment_id', issueApptId);
        fd.append('has_issue', hasIssue ? '1' : '0');
        fd.append('issue_note', note || '');
        fetch('/calendar/save_issue.php', {
            method: 'POST', body: fd,
            headers: { 'X-CSRF-Token': csrfToken },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); })
          .then(function (data) {
              if (!data.ok) throw new Error(data.error || 'Save failed.');
              closeIssue();
              refreshAll();   // re-render cards so the flag/ring updates
          }).catch(function (err) {
              issueErr.textContent = err.message || 'Could not save issue.';
              issueErr.hidden = false;
          });
    }

    // Open on ⚠-marker click/tap — delegated for poll-rendered cards too.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.cal-appt-issue');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        openIssue(btn.getAttribute('data-id'), btn.getAttribute('data-issue-note') || '');
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var el = document.activeElement;
        if (!el || !el.classList || !el.classList.contains('cal-appt-issue')) return;
        e.preventDefault();
        openIssue(el.getAttribute('data-id'), el.getAttribute('data-issue-note') || '');
    });

    if (issueModal) {
        document.getElementById('issue-modal-save').addEventListener('click', function () { saveIssue(true, issueText.value); });
        document.getElementById('issue-modal-clear').addEventListener('click', function () { saveIssue(false, ''); });
        document.getElementById('issue-modal-cancel').addEventListener('click', closeIssue);
        issueModal.addEventListener('click', function (e) { if (e.target === issueModal) closeIssue(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !issueModal.hidden) closeIssue(); });
    }
})();
</script>
</body>
</html>
