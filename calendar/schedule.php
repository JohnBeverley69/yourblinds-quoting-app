<?php
declare(strict_types=1);

/**
 * "My Schedule" — forward-looking, mobile-first list of the
 * logged-in user's own appointments. Sectioned into Today,
 * Tomorrow, Rest of this week, and Next week, plus an unscheduled
 * count link.
 *
 * Calendar grid view (index.php?mine=1) is great on a desktop but
 * unfriendly on a phone. This is the on-the-road counterpart: each
 * appointment is a card with tap-to-call phone, tap-to-navigate
 * address, and a link through to the full appointment detail.
 *
 * Always filtered to the logged-in user — no "all fitters" toggle
 * because it's literally MY schedule. Office staff who want the
 * whole-company view still use /calendar/index.php.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$user     = current_user();
$clientId = (int) $user['client_id'];
$userId   = (int) $user['user_id'];

$today    = new DateTimeImmutable('today');
$tomorrow = $today->modify('+1 day');

// Week boundaries — Monday-anchored, matches typical fitter mental
// model ("rest of this week" = today's bucket + everything before
// Sunday). Sunday is treated as the last day of the week.
$dow            = (int) $today->format('N');   // 1 = Mon, 7 = Sun
$startOfWeek    = $today->modify('-' . ($dow - 1) . ' days');
$endOfWeek      = $startOfWeek->modify('+6 days');
$startNextWeek  = $endOfWeek->modify('+1 day');
$endNextWeek    = $startNextWeek->modify('+6 days');

// Pull every dated appointment from today through the end of next
// week, time-ordered. One query, sliced into sections in PHP.
$stmt = db()->prepare(
    'SELECT a.id, a.title, a.status, a.quote_id,
            a.appointment_date, a.appointment_time, a.duration_minutes,
            a.installation_address1, a.installation_address2,
            a.installation_town, a.installation_county, a.installation_postcode,
            c.name  AS customer_name,
            c.phone AS customer_phone
       FROM appointments a
  LEFT JOIN customers c ON c.id = a.customer_id
      WHERE a.client_id      = ?
        AND a.client_user_id = ?
        AND a.appointment_date IS NOT NULL
        AND a.appointment_date BETWEEN ? AND ?
   ORDER BY a.appointment_date, a.appointment_time'
);
$stmt->execute([
    $clientId, $userId,
    $today->format('Y-m-d'),
    $endNextWeek->format('Y-m-d'),
]);
$rows = $stmt->fetchAll();

// Count of unscheduled appointments assigned to me — surfaced as
// a small link at the top so they're not forgotten.
$pStmt = db()->prepare(
    'SELECT COUNT(*) FROM appointments
      WHERE client_id      = ?
        AND client_user_id = ?
        AND appointment_date IS NULL'
);
$pStmt->execute([$clientId, $userId]);
$pendingCount = (int) $pStmt->fetchColumn();

// Slice the time-ordered rows into sections.
$buckets = [
    'today'    => ['title' => 'Today',                  'items' => [], 'date' => $today],
    'tomorrow' => ['title' => 'Tomorrow',               'items' => [], 'date' => $tomorrow],
    'thisWeek' => ['title' => 'Rest of this week',      'items' => []],
    'nextWeek' => ['title' => 'Next week',              'items' => []],
];
$todayStr        = $today->format('Y-m-d');
$tomorrowStr     = $tomorrow->format('Y-m-d');
$endOfWeekStr    = $endOfWeek->format('Y-m-d');
$startNextWkStr  = $startNextWeek->format('Y-m-d');
$endNextWkStr    = $endNextWeek->format('Y-m-d');

foreach ($rows as $r) {
    $d = (string) $r['appointment_date'];
    if      ($d === $todayStr)                                    $buckets['today']['items'][]    = $r;
    elseif  ($d === $tomorrowStr)                                 $buckets['tomorrow']['items'][] = $r;
    elseif  ($d > $tomorrowStr && $d <= $endOfWeekStr)            $buckets['thisWeek']['items'][] = $r;
    elseif  ($d >= $startNextWkStr && $d <= $endNextWkStr)        $buckets['nextWeek']['items'][] = $r;
}

$totalUpcoming = array_sum(array_map(static fn ($b) => count($b['items']), $buckets));

// Helpers — keep the template tidy.
$fmtTime = static function (?string $time): string {
    if (!$time) return '';
    $t = DateTimeImmutable::createFromFormat('H:i:s', $time)
       ?: DateTimeImmutable::createFromFormat('H:i', $time);
    return $t === false ? $time : strtolower($t->format('g:ia'));
};

$fmtDateLong = static function (DateTimeImmutable $d): string {
    return $d->format('l j F');
};

$fmtAddress = static function (array $r): string {
    $parts = array_values(array_filter([
        $r['installation_address1'] ?? null,
        $r['installation_address2'] ?? null,
        $r['installation_town']     ?? null,
        $r['installation_county']   ?? null,
        $r['installation_postcode'] ?? null,
    ], static fn ($v) => $v !== null && trim((string) $v) !== ''));
    return implode(', ', $parts);
};

$mapsLink = static function (string $address): string {
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address);
};

$telLink = static function (?string $phone): string {
    return 'tel:' . preg_replace('/[^0-9+]/', '', (string) $phone);
};

$activeNav = 'my-schedule';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Schedule &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .schedule-summary {
            display: flex; flex-wrap: wrap; gap: 0.5rem 1rem;
            margin: 0.25rem 0 1.25rem;
            font-size: 0.9375rem; color: var(--text-muted);
        }
        .schedule-summary strong { color: var(--text-body); }
        .schedule-summary .pending-link {
            background: #fef3c7; color: #92400e;
            padding: 0.125rem 0.625rem; border-radius: 999px;
            font-weight: 600; text-decoration: none; font-size: 0.8125rem;
        }
        .schedule-summary .pending-link:hover { background: #fde68a; }

        .sched-section { margin: 0 0 1.5rem; }
        .sched-section h2 {
            font-size: 0.8125rem; font-weight: 700; color: var(--text-body);
            text-transform: uppercase; letter-spacing: 0.06em;
            margin: 0 0 0.5rem;
        }
        .sched-section h2 .sub {
            font-weight: 500; color: var(--text-faint); text-transform: none;
            letter-spacing: 0; margin-left: 0.375rem;
        }
        .sched-empty {
            background: var(--bg-subtle); color: var(--text-faint);
            border: 1px dashed var(--border); border-radius: 10px;
            padding: 0.875rem 1rem; font-size: 0.9375rem; font-style: italic;
        }

        .sched-card {
            background: var(--bg-card);
            border: 1px solid var(--border); border-radius: 12px;
            padding: 0.875rem 1rem;
            display: grid; grid-template-columns: auto 1fr; gap: 0.25rem 1rem;
            margin: 0 0 0.625rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }
        .sched-card .time {
            font-weight: 700; color: var(--text-body); font-size: 1.0625rem;
            min-width: 4.5rem; text-align: right; padding-top: 0.0625rem;
            font-variant-numeric: tabular-nums;
        }
        .sched-card .time small { display: block; font-weight: 400; color: var(--text-faint); font-size: 0.75rem; }
        .sched-card .body { min-width: 0; }
        .sched-card .title {
            font-weight: 600; color: var(--text-primary);
            display: flex; align-items: baseline; gap: 0.5rem; flex-wrap: wrap;
            margin: 0;
        }
        .sched-card .customer { color: var(--text-muted); font-weight: 500; }
        .sched-card .address, .sched-card .phone {
            display: block; margin-top: 0.1875rem;
            font-size: 0.9375rem;
            text-decoration: none; color: var(--text-body);
            word-break: break-word;
        }
        .sched-card .address::before { content: '📍 '; }
        .sched-card .phone::before   { content: '📞 '; }
        .sched-card .actions {
            grid-column: 1 / -1; margin-top: 0.5rem;
            display: flex; gap: 0.5rem; flex-wrap: wrap;
        }
        .sched-card .actions a {
            font-size: 0.8125rem; padding: 0.25rem 0.625rem;
            border: 1px solid var(--border); border-radius: 999px;
            text-decoration: none; color: var(--text-body); background: var(--bg-subtle);
        }
        .sched-card .actions a:hover { background: var(--bg-subtle-2); }
        .sched-card .status {
            display: inline-block;
            font-size: 0.6875rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
            padding: 0.125rem 0.5rem; border-radius: 999px;
            background: #e0e7ff; color: #3730a3;
        }
        .sched-card .status.completed { background: #d1fae5; color: #065f46; }
        .sched-card .status.cancelled,
        .sched-card .status.no_show   { background: #fee2e2; color: #991b1b; }

        /* On a phone the card collapses to a single column so titles get
           room to breathe. Time chip becomes inline above the body. */
        @media (max-width: 480px) {
            .sched-card { grid-template-columns: 1fr; gap: 0.375rem; padding: 0.75rem 0.875rem; }
            .sched-card .time { text-align: left; min-width: 0; }
        }

        .day-anchor {
            font-size: 0.75rem; font-weight: 600;
            color: var(--text-faint); text-transform: uppercase;
            letter-spacing: 0.04em;
            margin: 0.5rem 0 0.25rem 0;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">My Schedule</h1>
                <p class="page-subtitle">
                    What's coming up for <?= e($user['full_name']) ?>.
                </p>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <a href="/calendar/index.php?mine=1" class="btn btn-secondary">Calendar grid</a>
                <a href="/calendar/run.php?date=<?= e($today->format('Y-m-d')) ?>"
                   class="btn btn-secondary">Today's run</a>
            </div>
        </div>

        <div class="schedule-summary">
            <span><strong><?= $totalUpcoming ?></strong> job<?= $totalUpcoming === 1 ? '' : 's' ?> next 2 weeks</span>
            <?php if ($pendingCount > 0): ?>
                <a class="pending-link"
                   href="/calendar/index.php?mine=1#pending"
                   title="Unscheduled appointments assigned to you">
                    <?= $pendingCount ?> unscheduled
                </a>
            <?php endif; ?>
        </div>

        <?php
        $renderCard = function (array $r) use ($fmtTime, $fmtAddress, $mapsLink, $telLink): void {
            $addr = $fmtAddress($r);
            $status = (string) ($r['status'] ?? 'booked');
            ?>
            <div class="sched-card">
                <div class="time">
                    <?php if (!empty($r['appointment_time'])): ?>
                        <?= e($fmtTime((string) $r['appointment_time'])) ?>
                        <?php if (!empty($r['duration_minutes'])): ?>
                            <small><?= (int) $r['duration_minutes'] ?>m</small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:var(--text-faint);font-weight:500">TBC</span>
                    <?php endif; ?>
                </div>
                <div class="body">
                    <div class="title">
                        <span><?= e((string) ($r['title'] ?? 'Appointment')) ?></span>
                        <span class="status <?= e($status) ?>"><?= e($status) ?></span>
                    </div>
                    <?php if (!empty($r['customer_name'])): ?>
                        <div class="customer"><?= e((string) $r['customer_name']) ?></div>
                    <?php endif; ?>
                    <?php if ($addr !== ''): ?>
                        <a class="address"
                           href="<?= e($mapsLink($addr)) ?>"
                           target="_blank" rel="noopener">
                            <?= e($addr) ?>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($r['customer_phone'])): ?>
                        <a class="phone" href="<?= e($telLink((string) $r['customer_phone'])) ?>">
                            <?= e((string) $r['customer_phone']) ?>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="actions">
                    <a href="/calendar/view.php?id=<?= (int) $r['id'] ?>">Details</a>
                    <?php if (!empty($r['quote_id'])): ?>
                        <!-- Appointments only get a quote_id when their
                             source quote was accepted (auto-creation
                             on status=accepted), so it's always an
                             "order" by this point — never a draft quote. -->
                        <a href="/quote-builder/edit.php?id=<?= (int) $r['quote_id'] ?>">Open order</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        };
        ?>

        <!-- ================================================================ -->
        <!-- Today + Tomorrow: single-day buckets, show date in subtitle.     -->
        <!-- ================================================================ -->
        <?php foreach (['today', 'tomorrow'] as $k):
            $b = $buckets[$k];
            $d = $b['date'];
        ?>
            <section class="sched-section">
                <h2>
                    <?= e($b['title']) ?>
                    <span class="sub"><?= e($d->format('l j F')) ?></span>
                </h2>
                <?php if (!$b['items']): ?>
                    <div class="sched-empty">No jobs <?= e(strtolower($b['title'])) ?>.</div>
                <?php else: ?>
                    <?php foreach ($b['items'] as $r) $renderCard($r); ?>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

        <!-- ================================================================ -->
        <!-- Rest of this week + next week: multi-day, group by date heading. -->
        <!-- ================================================================ -->
        <?php foreach (['thisWeek', 'nextWeek'] as $k):
            $b = $buckets[$k];
        ?>
            <section class="sched-section">
                <h2><?= e($b['title']) ?></h2>
                <?php if (!$b['items']): ?>
                    <div class="sched-empty">Nothing scheduled.</div>
                <?php else: ?>
                    <?php $currentDate = null; ?>
                    <?php foreach ($b['items'] as $r):
                        $d = (string) $r['appointment_date'];
                        if ($d !== $currentDate):
                            $currentDate = $d;
                            $dObj = new DateTimeImmutable($d);
                    ?>
                            <div class="day-anchor"><?= e($dObj->format('l j F')) ?></div>
                    <?php endif; ?>
                        <?php $renderCard($r); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

    </main>
</div>
</body>
</html>
