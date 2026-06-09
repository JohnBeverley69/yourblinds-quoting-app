<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/job_status_colours.php';

requireLogin();

$user     = current_user();
$clientId = $user['client_id'];

// ---------------------------------------------------------------------------
// Resolve the day being viewed. ?date=YYYY-MM-DD, defaulting to today.
// ---------------------------------------------------------------------------
$dateParam = (string) ($_GET['date'] ?? '');
$dateValid = $dateParam !== ''
    && DateTimeImmutable::createFromFormat('!Y-m-d', $dateParam) !== false;
$date    = $dateValid ? $dateParam : (new DateTimeImmutable('today'))->format('Y-m-d');
$dateObj = new DateTimeImmutable($date);

$prevDate = $dateObj->modify('-1 day')->format('Y-m-d');
$nextDate = $dateObj->modify('+1 day')->format('Y-m-d');
$todayStr = (new DateTimeImmutable('today'))->format('Y-m-d');
$isToday  = $date === $todayStr;

// ---------------------------------------------------------------------------
// Feature flag — maps add-on must be on for this client.
// ---------------------------------------------------------------------------
$fStmt = db()->prepare(
    'SELECT COALESCE(feature_maps, 0) FROM client_settings WHERE client_id = ?'
);
$fStmt->execute([$clientId]);
$mapsEnabled = ((int) $fStmt->fetchColumn()) === 1;

// ---------------------------------------------------------------------------
// Logged-in user's home address — used as origin + destination for the run
// so the route reflects the actual day's driving (home → app1 → ... → home).
// Falls back to no-home if all home_* fields are blank.
// ---------------------------------------------------------------------------
$homeStmt = db()->prepare(
    'SELECT home_address1, home_address2, home_town, home_county, home_postcode
       FROM client_users WHERE id = ? LIMIT 1'
);
$homeStmt->execute([(int) $user['user_id']]);
$homeRow     = $homeStmt->fetch();
$homeParts   = $homeRow
    ? array_values(array_filter([
        $homeRow['home_address1'] ?? null,
        $homeRow['home_address2'] ?? null,
        $homeRow['home_town']     ?? null,
        $homeRow['home_county']   ?? null,
        $homeRow['home_postcode'] ?? null,
    ], static fn ($v) => $v !== null && $v !== ''))
    : [];
$homeAddress = $homeParts ? implode(', ', $homeParts) : '';

// ---------------------------------------------------------------------------
// Day's appointments, time-ordered.
// ---------------------------------------------------------------------------
$stmt = db()->prepare(
    'SELECT a.id, a.title, a.appointment_time, a.duration_minutes, a.status, a.appt_kind,
            a.installation_address1, a.installation_address2,
            a.installation_town, a.installation_county, a.installation_postcode,
            c.name AS customer_name,
            u.full_name AS assignee_name,
            q.status AS quote_status
       FROM appointments a
  LEFT JOIN customers    c ON c.id = a.customer_id
  LEFT JOIN client_users u ON u.id = a.client_user_id
  LEFT JOIN quotes       q ON q.id = a.quote_id
      WHERE a.client_id = ? AND a.appointment_date = ?
   ORDER BY a.appointment_time'
);
$stmt->execute([$clientId, $date]);
$appts = $stmt->fetchAll();

// "Fittings only" users see just fitting jobs.
if (!empty(current_user_permissions()['can_view_fittings_only'])) {
    $appts = array_values(array_filter($appts, static fn ($a) => (string) ($a['appt_kind'] ?? 'measure') === 'fitting'));
}

// Same traffic-light palette as the main calendar + Settings, so the stop
// badges match everywhere.
$stagePalette = job_client_palette((int) $clientId);
$stageLabels  = job_status_labels();

// Strip down to just the rows that have *some* address — a stop with no
// address can't be plotted, but we still want to show it in the list below.
$plottable = [];
foreach ($appts as $a) {
    $parts = array_values(array_filter([
        $a['installation_address1'] ?? null,
        $a['installation_address2'] ?? null,
        $a['installation_town']     ?? null,
        $a['installation_county']   ?? null,
        $a['installation_postcode'] ?? null,
    ], static fn ($v) => $v !== null && $v !== ''));
    if ($parts) {
        $a['_address'] = implode(', ', $parts);
        $plottable[]   = $a;
    }
}

// ---------------------------------------------------------------------------
// Pre-compute the inputs JS needs to rebuild the URL with the user's GPS
// position as the origin (the "Start from my location" button below). We
// always emit these so the button can rewrite the iframe on click without
// a round-trip — the API key, destination, and waypoint list are all known.
// ---------------------------------------------------------------------------
$gpsDestination = '';
$gpsWaypoints   = [];

// ---------------------------------------------------------------------------
// Build the Google Maps Embed URL.
// Embed API (Directions mode) supports up to 10 stops total: origin +
// destination + up to 8 waypoints.
//
// If the user has a home address set, it's the origin AND the destination
// (round trip), with all appointments as waypoints — leaving 8 waypoint
// slots before truncation kicks in. Without a home address we fall back to
// the legacy first-app-as-origin / last-app-as-dest behaviour.
// ---------------------------------------------------------------------------
$embedUrl    = '';
$truncatedTo = null;
$usingHome   = $homeAddress !== '';
$totalStops  = count($plottable);

if ($mapsEnabled && GOOGLE_MAPS_API_KEY !== '' && $totalStops > 0) {
    if ($usingHome) {
        // Home → appointments → Home. Origin + dest take 2 of the 10 slots,
        // leaving 8 waypoints for actual stops.
        $maxApps = 8;
        $stops   = $totalStops > $maxApps ? array_slice($plottable, 0, $maxApps) : $plottable;
        $truncatedTo = $totalStops > $maxApps ? $maxApps : null;

        $stopAddrs = array_map(static fn ($s) => $s['_address'], $stops);
        $wp = implode('|', $stopAddrs);
        $embedUrl = 'https://www.google.com/maps/embed/v1/directions'
                  . '?key=' . urlencode(GOOGLE_MAPS_API_KEY)
                  . '&origin='      . urlencode($homeAddress)
                  . '&destination=' . urlencode($homeAddress)
                  . '&waypoints='   . urlencode($wp)
                  . '&mode=driving';

        // GPS override: GPS → apps → home. All apps stay as waypoints.
        $gpsDestination = $homeAddress;
        $gpsWaypoints   = $stopAddrs;
    } else {
        // Legacy fallback: no home set, so the first appointment is the
        // origin and the last is the destination.
        $stops = $totalStops > 10 ? array_slice($plottable, 0, 10) : $plottable;
        $truncatedTo = $totalStops > 10 ? 10 : null;
        $stopAddrs = array_map(static fn ($s) => $s['_address'], $stops);

        if (count($stops) === 1) {
            $embedUrl = 'https://www.google.com/maps/embed/v1/place'
                      . '?key=' . urlencode(GOOGLE_MAPS_API_KEY)
                      . '&q='   . urlencode($stops[0]['_address']);

            // GPS override: GPS → that one app.
            $gpsDestination = $stops[0]['_address'];
            $gpsWaypoints   = [];
        } else {
            $origin = $stops[0]['_address'];
            $dest   = $stops[count($stops) - 1]['_address'];
            $mid    = array_slice($stops, 1, count($stops) - 2);
            $wp     = $mid
                ? implode('|', array_map(static fn ($s) => $s['_address'], $mid))
                : '';

            $embedUrl = 'https://www.google.com/maps/embed/v1/directions'
                      . '?key=' . urlencode(GOOGLE_MAPS_API_KEY)
                      . '&origin=' . urlencode($origin)
                      . '&destination=' . urlencode($dest)
                      . ($wp !== '' ? '&waypoints=' . urlencode($wp) : '')
                      . '&mode=driving';

            // GPS override: GPS → all apps in order → last app stays as dest.
            $gpsDestination = $dest;
            $gpsWaypoints   = array_slice($stopAddrs, 0, -1);
        }
    }
}

$fmtTime = static function (?string $time): string {
    if ($time === null || $time === '') {
        return '';
    }
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
    <title>Today's run &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .run-toolbar {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        .run-date-label {
            font-weight: 700;
            font-size: 1.125rem;
            color: var(--text-primary);
            margin-left: 0.25rem;
        }
        .run-map {
            position: relative;
            width: 100%;
            height: 480px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid var(--border);
            margin-bottom: 1.25rem;
            background: var(--bg-subtle-2);
        }
        .run-map iframe {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }
        @media (max-width: 600px) {
            .run-map { height: 320px; }
        }
        .run-stops { list-style: none; margin: 0; padding: 0; counter-reset: stop; }
        .run-stops li {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 0.875rem;
            align-items: start;
            padding: 0.875rem 0;
            border-bottom: 1px solid var(--border);
        }
        .run-stops li:last-child { border-bottom: 0; }
        .run-stops .stop-num {
            counter-increment: stop;
            width: 30px;
            height: 30px;
            border-radius: 999px;
            background: #1f3b5b;
            color: #fff;
            font-weight: 700;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .run-stops .stop-num::before { content: counter(stop); }
        .run-stops li.is-cancelled .stop-num,
        .run-stops li.is-no_show   .stop-num { background: var(--text-faint); }
        .run-stops .stop-time {
            font-size: 0.8125rem;
            color: var(--text-faint);
            margin-top: 0.125rem;
        }
        .run-stops .stop-title {
            font-weight: 600;
            color: var(--text-primary);
            text-decoration: none;
        }
        .run-stops .stop-title:hover { text-decoration: underline; }
        .run-stops .stop-addr {
            margin-top: 0.125rem;
            color: var(--text-muted);
            font-size: 0.9375rem;
        }
        .run-stops .stop-status {
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.125rem 0.5rem;
            border-radius: 999px;
            color: #fff;
            white-space: nowrap;
        }
        /* Stop-status pill colours come inline from the tenant's traffic-light
           palette (same as the calendar + Settings) — see the render below. */
        .run-noaddr {
            color: #b45309;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            font-size: 0.8125rem;
            margin-bottom: 1rem;
        }
        .gps-toolbar {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 0.625rem;
            font-size: 0.875rem;
            color: var(--text-muted);
        }
        .gps-toolbar .gps-status { white-space: nowrap; }
        .gps-toolbar .gps-status strong { color: var(--text-primary); }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Today's run</h1>
                <p class="page-subtitle">
                    <a href="/calendar/index.php">&larr; Back to calendar</a>
                </p>
            </div>
        </div>

        <section class="section">
            <div class="run-toolbar">
                <a class="btn btn-secondary btn-sm"
                   href="/calendar/run.php?date=<?= e($prevDate) ?>">&larr; Prev</a>
                <?php if (!$isToday): ?>
                    <a class="btn btn-secondary btn-sm"
                       href="/calendar/run.php">Today</a>
                <?php endif; ?>
                <a class="btn btn-secondary btn-sm"
                   href="/calendar/run.php?date=<?= e($nextDate) ?>">Next &rarr;</a>
                <span class="run-date-label">
                    <?= e($dateObj->format('l, j F Y')) ?>
                </span>
            </div>

            <?php if (!$mapsEnabled): ?>
                <div class="alert alert-info">
                    The maps add-on is not enabled for your account. Contact your administrator.
                </div>
            <?php elseif (!$appts): ?>
                <div class="placeholder">
                    <p class="placeholder-title">No appointments</p>
                    <p class="placeholder-body">There's nothing booked for this day.</p>
                </div>
            <?php else: ?>
                <?php if (!$usingHome): ?>
                    <div class="run-noaddr">
                        No home address set on your user profile, so the route starts at the first
                        appointment instead of from home. Set it under
                        <a href="/admin/users_edit.php?id=<?= (int) $user['user_id'] ?>">Edit user</a>
                        &rarr; Home address.
                    </div>
                <?php endif; ?>
                <?php if (!$plottable): ?>
                    <div class="run-noaddr">
                        Appointments exist for this day but none have an installation address recorded, so the route can't be drawn.
                    </div>
                <?php elseif ($embedUrl === ''): ?>
                    <div class="run-noaddr">
                        Maps API key is missing. Set <code>GOOGLE_MAPS_API_KEY</code> in <code>.env</code>.
                    </div>
                <?php else: ?>
                    <?php if ($truncatedTo !== null): ?>
                        <div class="run-noaddr">
                            Showing the first <?= (int) $truncatedTo ?> stops only — Google's Embed API caps a single route at 10 waypoints. The full list appears below the map.
                        </div>
                    <?php endif; ?>

                    <div class="gps-toolbar">
                        <span class="gps-status" id="gps-status">
                            Start: <strong><?= $usingHome ? 'home address' : 'first appointment' ?></strong>
                        </span>
                        <button type="button" id="use-gps-btn" class="btn btn-secondary btn-sm">
                            📍 Start from my location
                        </button>
                        <button type="button" id="reset-gps-btn" class="btn btn-secondary btn-sm" hidden>
                            ↺ Reset start
                        </button>
                    </div>

                    <div class="run-map">
                        <iframe id="run-map-frame" loading="lazy" allowfullscreen
                                referrerpolicy="no-referrer-when-downgrade"
                                src="<?= e($embedUrl) ?>"></iframe>
                    </div>

                    <script>
                    (function () {
                        var cfg = {
                            apiKey: <?= json_encode(GOOGLE_MAPS_API_KEY) ?>,
                            destination: <?= json_encode($gpsDestination) ?>,
                            waypoints: <?= json_encode($gpsWaypoints) ?>,
                            originalSrc: <?= json_encode($embedUrl) ?>,
                            originalLabel: <?= json_encode($usingHome ? 'home address' : 'first appointment') ?>
                        };
                        var iframe   = document.getElementById('run-map-frame');
                        var btn      = document.getElementById('use-gps-btn');
                        var resetBtn = document.getElementById('reset-gps-btn');
                        var status   = document.getElementById('gps-status');

                        if (!('geolocation' in navigator)) {
                            btn.disabled = true;
                            btn.title    = 'Your browser does not support geolocation.';
                            return;
                        }

                        btn.addEventListener('click', function () {
                            var origLabel = btn.textContent;
                            btn.disabled    = true;
                            btn.textContent = 'Getting location…';

                            navigator.geolocation.getCurrentPosition(function (pos) {
                                var origin = pos.coords.latitude.toFixed(6)
                                           + ',' + pos.coords.longitude.toFixed(6);
                                var dest   = cfg.destination || origin;
                                var wp     = (cfg.waypoints || []).join('|');
                                var url = 'https://www.google.com/maps/embed/v1/directions'
                                        + '?key='         + encodeURIComponent(cfg.apiKey)
                                        + '&origin='      + encodeURIComponent(origin)
                                        + '&destination=' + encodeURIComponent(dest)
                                        + (wp ? '&waypoints=' + encodeURIComponent(wp) : '')
                                        + '&mode=driving';
                                iframe.src = url;
                                status.innerHTML = 'Start: <strong>your current location</strong>';
                                btn.hidden       = true;
                                btn.disabled     = false;
                                btn.textContent  = origLabel;
                                resetBtn.hidden  = false;
                            }, function (err) {
                                btn.disabled    = false;
                                btn.textContent = origLabel;
                                alert('Could not get your location: '
                                    + (err && err.message ? err.message : 'unknown error'));
                            }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 });
                        });

                        resetBtn.addEventListener('click', function () {
                            iframe.src       = cfg.originalSrc;
                            status.innerHTML = 'Start: <strong>' + cfg.originalLabel + '</strong>';
                            resetBtn.hidden  = true;
                            btn.hidden       = false;
                        });
                    })();
                    </script>
                <?php endif; ?>

                <ol class="run-stops">
                    <?php foreach ($appts as $a):
                        $hasAddress = isset($a['installation_address1'])
                                   || isset($a['installation_postcode']);
                        $addrLine = trim((string) implode(', ', array_filter([
                            $a['installation_address1'] ?? null,
                            $a['installation_town']     ?? null,
                            $a['installation_postcode'] ?? null,
                        ], static fn ($v) => $v !== null && $v !== '')));
                    ?>
                        <li class="is-<?= e((string) $a['status']) ?>">
                            <span class="stop-num"></span>
                            <div>
                                <a class="stop-title"
                                   href="/calendar/view.php?id=<?= (int) $a['id'] ?>">
                                    <?= e((string) $a['title']) ?>
                                </a>
                                <div class="stop-time">
                                    <?= e($fmtTime((string) $a['appointment_time'])) ?>
                                    <?php if (!empty($a['assignee_name'])): ?>
                                        &middot; <?= e((string) $a['assignee_name']) ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($addrLine !== ''): ?>
                                    <div class="stop-addr"><?= e($addrLine) ?></div>
                                <?php else: ?>
                                    <div class="stop-addr" style="color:#b45309">No address recorded — not on the map.</div>
                                <?php endif; ?>
                            </div>
                            <?php
                                $stKind   = (string) ($a['appt_kind'] ?? 'measure');
                                $stStage  = job_stage((string) $a['status'], $a['quote_status'] ?? null, $stKind);
                                $stColour = $stagePalette[$stStage] ?? '#2563eb';
                            ?>
                            <span class="stop-status"
                                  style="background:<?= e($stColour) ?>;color:<?= e(job_status_text_colour($stColour)) ?><?= $stKind === 'fitting' ? ';outline:2px solid #111827;outline-offset:-2px' : '' ?>">
                                <?= $stKind === 'fitting' ? '&#128295; ' : '' ?><?= e($stageLabels[$stStage] ?? ucfirst(str_replace('_', '-', (string) $a['status']))) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
