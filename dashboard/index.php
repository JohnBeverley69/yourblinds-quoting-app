<?php
declare(strict_types=1);

/**
 * Sales dashboard — at-a-glance numbers for the current tenant.
 *
 * Five panels, in priority order:
 *   1. KPI tiles                — revenue, # of accepted jobs, AOV, close rate
 *   2. Sales-person leaderboard — sent / accepted / close-rate / revenue per
 *                                 user, ranked by revenue
 *   3. Product mix              — top selling product types this period,
 *                                 by revenue and unit count
 *   4. Margin panel             — sell_price − base_price per line, summed.
 *                                 NOT true gross profit (no cost column yet);
 *                                 labelled "estimated margin" to be honest
 *                                 about what it is. Gated by can_view_costs.
 *   5. Recent activity          — latest 10 accepted quotes, click through
 *
 * Time-window selector lives at the top — defaults to "this month".
 * All queries are tenant-scoped by client_id; the dashboard is the same
 * for everyone in the tenant (no per-user filtering — the leaderboard
 * intentionally shows everyone's numbers so the team can see them too).
 *
 * Gating:
 *   - Admins and staff (anyone with can_create_quotes / can_create_orders
 *     / can_view_all_customer_jobs) see the dashboard.
 *   - Pure fitters don't — it's a back-office tool. They land on the
 *     Calendar as before.
 *   - The margin panel further requires can_view_costs (admins pass).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/pie_chart.php';
require __DIR__ . '/../_partials/joke_of_the_day.php';
require __DIR__ . '/../_partials/pricing_basis.php';

requireLogin();

$user      = current_user();
$clientId  = (int) $user['client_id'];
$pricingBasis = pricing_basis_for(db(), $clientId); // markup vs margin wording
$myUserId  = (int) ($user['user_id'] ?? $user['id'] ?? 0);
$isAdmin   = ($user['role'] ?? '') === 'admin';

// Permission gating.
//
// Admins always see the full Dashboard. For non-admin users, each
// panel is gated by its own flag from /admin/users_edit.php. If a
// non-admin user has NONE of the panel flags ticked, we bounce them
// back to Calendar.
$perms = function_exists('current_user_permissions') ? current_user_permissions() : [];
$canViewCosts    = $isAdmin || !empty($perms['can_view_costs']);

$canSeeRevenue  = $isAdmin || !empty($perms['dash_view_revenue']);
$canSeeTeam     = $isAdmin || !empty($perms['dash_view_team']);
$canSeeProducts = $isAdmin || !empty($perms['dash_view_products']);
$canSeeProfit   = ($isAdmin || !empty($perms['dash_view_profit'])) && $canViewCosts;
$canSeeRecent   = $isAdmin || !empty($perms['dash_view_recent']);

$canSeeAnything = $canSeeRevenue || $canSeeTeam || $canSeeProducts
               || $canSeeProfit  || $canSeeRecent;

if (!$canSeeAnything) {
    header('Location: /calendar/index.php');
    exit;
}

// ---- Period selector -------------------------------------------------
//
// Preset periods, plus a custom from/to range:
//   'mtd'    = this month-to-date
//   '30d'    = trailing 30 days
//   'qtd'    = this calendar quarter-to-date
//   'ytd'    = this calendar year-to-date
//   'all'    = no date filter (since the tenant existed)
//   'custom' = use ?from=YYYY-MM-DD&to=YYYY-MM-DD
//
// Date filter is applied to quotes.created_at — that's the moment the
// salesperson started the quote. accepted_at would be alternative, but
// it'd only count "won" jobs in the period and miss the close-rate
// denominator (sent quotes that didn't close yet).
$period = (string) ($_GET['period'] ?? 'mtd');
$validPeriods = ['mtd', '30d', 'qtd', 'ytd', 'all', 'custom'];
if (!in_array($period, $validPeriods, true)) $period = 'mtd';

// $periodFrom = inclusive lower bound on created_at, or null = open
// $periodTo   = exclusive upper bound (next-day midnight), or null = open
// "Exclusive upper" so a user picking "to: 31 May" gets all of 31 May
// without timezone or HH:MM:SS edge-cases. We add a day in the binder
// for the comparison.
$periodFrom  = null;
$periodTo    = null;
$periodLabel = '';
$customFrom  = '';
$customTo    = '';

if ($period === 'custom') {
    $customFrom = trim((string) ($_GET['from'] ?? ''));
    $customTo   = trim((string) ($_GET['to']   ?? ''));

    // Sanity-check both — bad strings fall back to "all time" rather
    // than 500ing. Empty either side = open on that end.
    $validate = static function (string $s): ?string {
        if ($s === '') return null;
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s);
        return $d ? $d->format('Y-m-d') : null;
    };
    $fromOk = $validate($customFrom);
    $toOk   = $validate($customTo);

    if ($fromOk === null && $toOk === null && ($customFrom !== '' || $customTo !== '')) {
        // Both inputs supplied but neither parsed — surface a soft error.
        $_SESSION['flash_error'] = 'Bad date format — use the date pickers.';
    }
    $periodFrom = $fromOk;
    // For an inclusive "to", bump one day forward and treat upper bound
    // as exclusive. Avoids "I picked 31 May but the 31st doesn't show"
    // confusion that bites on raw < / <= comparisons.
    if ($toOk !== null) {
        $periodTo = (new DateTimeImmutable($toOk))->modify('+1 day')->format('Y-m-d');
    }

    $labelFrom = $fromOk !== null ? date('j M Y', strtotime($fromOk)) : 'beginning';
    $labelTo   = $toOk   !== null ? date('j M Y', strtotime($toOk))   : 'today';
    $periodLabel = "$labelFrom → $labelTo";

    // Keep the picker pre-filled with what the URL had (even if invalid)
    // so the user can correct without retyping.
    $customFrom = $fromOk ?? $customFrom;
    $customTo   = $toOk   ?? $customTo;
} else {
    [$periodFrom, $periodLabel] = match ($period) {
        'mtd' => [date('Y-m-01'),                              'this month'],
        '30d' => [date('Y-m-d', strtotime('-30 days')),        'last 30 days'],
        'qtd' => [(function () {
            $m = (int) date('n'); $y = (int) date('Y');
            $qStartMonth = (int) (floor(($m - 1) / 3) * 3) + 1;
            return sprintf('%04d-%02d-01', $y, $qStartMonth);
        })(), 'this quarter'],
        'ytd' => [date('Y-01-01'),                             'this year'],
        'all' => [null,                                        'all time'],
    };
    // Default the custom pickers to "last 30 days" so opening Custom
    // lands you somewhere sensible to tweak.
    $customFrom = date('Y-m-d', strtotime('-30 days'));
    $customTo   = date('Y-m-d');
}

// "Won" = quote turned into actual revenue. Excludes sent/draft/declined.
$wonStatuses     = ['accepted', 'ordered', 'invoiced', 'paid'];
$decidedStatuses = ['accepted', 'declined', 'ordered', 'invoiced', 'paid'];
// "decided" = quote where the customer has made up their mind (won OR
// lost). Used as the close-rate denominator so quotes still sitting in
// 'sent' don't get counted against the salesperson — they might still
// close.

// Helper: build the date-filter SQL clause + params for the period.
// Bound names ($alias.created_at vs. created_at) handled by caller.
// Returns [clause, params]. Caller appends both to its query/binding.
$buildDateFilter = static function (string $col) use ($periodFrom, $periodTo): array {
    $clauses = [];
    $params  = [];
    if ($periodFrom !== null) { $clauses[] = "$col >= ?"; $params[] = $periodFrom; }
    if ($periodTo   !== null) { $clauses[] = "$col <  ?"; $params[] = $periodTo;   }
    return [$clauses ? ' AND ' . implode(' AND ', $clauses) : '', $params];
};
// Used by every panel below — built once for the bare-column form, and
// each panel separately builds its own with the right alias prefix.
[$dateClause, $dateParams] = $buildDateFilter('created_at');

$pdo = db();
$inWon     = implode(',', array_fill(0, count($wonStatuses), '?'));
$inDecided = implode(',', array_fill(0, count($decidedStatuses), '?'));

// ---- Salesperson filter ----------------------------------------------
//
// Optional ?user_id=N narrows every panel to just that person's quotes.
// 0 / missing = whole team (default).
//
// The dropdown lists every user who has created at least one quote in
// the tenant — that's the meaningful set of "salespeople". Listing
// every active user including office staff would be a long, noisy
// dropdown. Anyone with zero quotes wouldn't surface useful data on
// the dashboard anyway.
//
// We validate the requested user_id against that same list so a hand-
// crafted ?user_id=999 can't be used to peek at another tenant's user
// (the list is already tenant-scoped via the quotes join).
$salesPeople = $pdo->prepare(
    'SELECT DISTINCT u.id, u.full_name
       FROM client_users u
       JOIN quotes q ON q.created_by_user_id = u.id
      WHERE q.client_id = ?
   ORDER BY u.full_name'
);
$salesPeople->execute([$clientId]);
$salesPeople = $salesPeople->fetchAll();

$filterUserId = (int) ($_GET['user_id'] ?? 0);
$filterUser   = null;
foreach ($salesPeople as $sp) {
    if ((int) $sp['id'] === $filterUserId) {
        $filterUser = $sp;
        break;
    }
}
if (!$filterUser) $filterUserId = 0;   // invalid → clear

// Helper: returns the per-query AND clause + params for the user
// filter. Caller appends to its own clause string and parameter array.
$buildUserFilter = static function (string $col) use ($filterUserId): array {
    return $filterUserId > 0
        ? [" AND $col = ?", [$filterUserId]]
        : ['', []];
};

// ---- 1. KPI tiles ----------------------------------------------------
$kpi       = ['won_count' => 0, 'revenue' => 0, 'aov' => 0];
$rate      = ['accepted_cnt' => 0, 'decided_cnt' => 0];
$closeRate = null;
if ($canSeeRevenue) {
    [$kpiUser, $kpiUserParams] = $buildUserFilter('created_by_user_id');
    $st = $pdo->prepare(
        "SELECT COUNT(*)        AS won_count,
                COALESCE(SUM(total), 0)  AS revenue,
                COALESCE(AVG(total), 0)  AS aov
           FROM quotes
          WHERE client_id = ?
            AND status IN ($inWon)
            $dateClause
            $kpiUser"
    );
    $st->execute(array_merge([$clientId], $wonStatuses, $dateParams, $kpiUserParams));
    $kpi = $st->fetch() ?: $kpi;

    // Close-rate: accepted / decided. Excludes still-pending 'sent' quotes.
    $st = $pdo->prepare(
        "SELECT
            SUM(CASE WHEN status IN ($inWon) THEN 1 ELSE 0 END) AS accepted_cnt,
            COUNT(*) AS decided_cnt
           FROM quotes
          WHERE client_id = ?
            AND status IN ($inDecided)
            $dateClause
            $kpiUser"
    );
    $st->execute(array_merge($wonStatuses, [$clientId], $decidedStatuses, $dateParams, $kpiUserParams));
    $rate = $st->fetch() ?: $rate;
    $closeRate = (int) $rate['decided_cnt'] > 0
        ? ((int) $rate['accepted_cnt'] / (int) $rate['decided_cnt']) * 100
        : null;
}

// ---- 2. Sales-person leaderboard -------------------------------------
$leaderboard = [];
if ($canSeeTeam) {
    [$lbDate, $lbDateParams] = $buildDateFilter('q.created_at');
    [$lbUser, $lbUserParams] = $buildUserFilter('q.created_by_user_id');
    $st = $pdo->prepare(
        "SELECT q.created_by_user_id AS uid,
                COALESCE(u.full_name, '(unknown)') AS name,
                SUM(CASE WHEN q.status IN ($inDecided) OR q.status = 'sent' THEN 1 ELSE 0 END) AS pipeline,
                SUM(CASE WHEN q.status IN ($inDecided) THEN 1 ELSE 0 END) AS decided,
                SUM(CASE WHEN q.status IN ($inWon)     THEN 1 ELSE 0 END) AS won,
                COALESCE(SUM(CASE WHEN q.status IN ($inWon) THEN q.total ELSE 0 END), 0) AS revenue
           FROM quotes q
           LEFT JOIN client_users u ON u.id = q.created_by_user_id
          WHERE q.client_id = ?
            $lbDate
            $lbUser
       GROUP BY q.created_by_user_id, u.full_name
       ORDER BY revenue DESC, won DESC"
    );
    $args = array_merge($decidedStatuses, $decidedStatuses, $wonStatuses, $wonStatuses, [$clientId], $lbDateParams, $lbUserParams);
    $st->execute($args);
    $leaderboard = $st->fetchAll();
}

// ---- 3. Product mix --------------------------------------------------
$productMix      = [];
$productMixTotal = 1;
if ($canSeeProducts) {
    [$pmDate, $pmDateParams] = $buildDateFilter('q.created_at');
    [$pmUser, $pmUserParams] = $buildUserFilter('q.created_by_user_id');
    $st = $pdo->prepare(
        "SELECT qi.product_id,
                COALESCE(p.name, qi.product_name_snapshot, '(unknown)') AS product_name,
                SUM(qi.quantity)   AS units,
                SUM(qi.line_total) AS revenue
           FROM quote_items qi
           JOIN quotes q     ON q.id = qi.quote_id
           LEFT JOIN products p ON p.id = qi.product_id
          WHERE q.client_id = ?
            AND q.status IN ($inWon)
            $pmDate
            $pmUser
       GROUP BY qi.product_id, product_name
       ORDER BY revenue DESC
       LIMIT 8"
    );
    $args = array_merge([$clientId], $wonStatuses, $pmDateParams, $pmUserParams);
    $st->execute($args);
    $productMix = $st->fetchAll();
    $productMixTotal = array_sum(array_column($productMix, 'revenue')) ?: 1;
}

// ---- 4. Gross profit panel (gated) -----------------------------------
//
// Cost = base_price + extras_total (both pre-markup, per blind, × qty).
//
// In this tenant's model, the price tables themselves ARE the cost
// basis: tenants enter what they pay per width × drop into the
// pricing table, and the per-(product, system) Markup % adds the
// retail margin. Extras work the same way — their amount_applied is
// the pre-markup value too. So gross profit lands cleanly as
// sell − (base + extras) per line.
//
// No per-product/per-fabric cost_price needed; no quote-line snapshot
// needed beyond what the engine already writes. Schema columns for
// cost_price_snapshot/etc. were left in place (in case a future
// tenant wants finer overhead allocation), but the dashboard ignores
// them and works straight from the price-table figures.
$marginData = null;
if ($canSeeProfit) {
    [$mgDate, $mgDateParams] = $buildDateFilter('q.created_at');
    [$mgUser, $mgUserParams] = $buildUserFilter('q.created_by_user_id');
    $st = $pdo->prepare(
        "SELECT
            COALESCE(SUM(((qi.base_price + COALESCE(qi.extras_total, 0))
                          * qi.quantity)), 0)                AS cost_basis,
            COALESCE(SUM(qi.sell_price * qi.quantity), 0)    AS sell_total,
            COUNT(DISTINCT q.id)                             AS jobs
           FROM quote_items qi
           JOIN quotes q ON q.id = qi.quote_id
          WHERE q.client_id = ?
            AND q.status IN ($inWon)
            $mgDate
            $mgUser"
    );
    $args = array_merge([$clientId], $wonStatuses, $mgDateParams, $mgUserParams);
    $st->execute($args);
    $row = $st->fetch() ?: null;
    if ($row) {
        $sell = (float) $row['sell_total'];
        $cost = (float) $row['cost_basis'];
        $marginData = [
            'margin'         => max(0.0, $sell - $cost),
            'cost_basis'     => $cost,
            'sell_total'     => $sell,
            'jobs'           => (int) $row['jobs'],
            'margin_pct'     => $sell > 0 ? (($sell - $cost) / $sell) * 100 : null,
            'margin_per_job' => (int) $row['jobs'] > 0
                                ? ($sell - $cost) / (int) $row['jobs']
                                : null,
        ];
    }
}

// ---- 6. Upcoming jobs (calendar peek) -------------------------------
//
// Forward-looking widget — next N booked appointments from the
// calendar. Not subject to the period filter (that's a sales-history
// scope; "upcoming" is forward-looking and always = next-from-now).
//
// Permission scope: matches the calendar — users without
// can_view_all_customer_jobs see only appointments assigned to them.
// Status filter: only 'booked' (active future jobs). Completed,
// cancelled and no-show aren't "upcoming" by any useful definition.
//
// Schema safety: appointments table may be absent on pre-Phase-2
// builds; the try/catch lets the page still render.
$upcomingJobs   = [];
$upcomingLimit  = 8;
$canViewAllJobs = $isAdmin || !empty($perms['can_view_all_customer_jobs']);

try {
    $upWhere  = [
        'a.client_id = ?',
        "a.status = 'booked'",
        "(a.appointment_date > CURDATE()
          OR (a.appointment_date = CURDATE() AND a.appointment_time >= CURTIME()))",
    ];
    $upParams = [$clientId];
    if (!$canViewAllJobs) {
        $upWhere[]  = 'a.client_user_id = ?';
        $upParams[] = $myUserId;
    }
    $st = $pdo->prepare(
        "SELECT a.id, a.title, a.appointment_date, a.appointment_time,
                a.duration_minutes, a.client_user_id,
                a.installation_town, a.installation_postcode,
                c.name      AS customer_name,
                c.postcode  AS customer_postcode,
                u.full_name AS fitter_name,
                q.id        AS quote_id,
                q.quote_number,
                q.status    AS quote_status
           FROM appointments a
      LEFT JOIN customers    c ON c.id = a.customer_id
      LEFT JOIN client_users u ON u.id = a.client_user_id
      LEFT JOIN quotes       q ON q.id = a.quote_id
          WHERE " . implode(' AND ', $upWhere) . "
       ORDER BY a.appointment_date ASC, a.appointment_time ASC
          LIMIT " . (int) $upcomingLimit
    );
    $st->execute($upParams);
    $upcomingJobs = $st->fetchAll();
} catch (Throwable $e) {
    // appointments table absent or schema mismatch — silent degrade.
    error_log('dashboard upcoming jobs query failed: ' . $e->getMessage());
}

// ---- 5. Recent activity ----------------------------------------------
$recent = [];
if ($canSeeRecent) {
    [$rcDate, $rcDateParams] = $buildDateFilter('q.created_at');
    [$rcUser, $rcUserParams] = $buildUserFilter('q.created_by_user_id');
    $st = $pdo->prepare(
        "SELECT q.id, q.quote_number, q.total, q.status, q.accepted_at, q.created_at,
                COALESCE(c.name, q.end_customer_name, '(no customer)') AS customer_name,
                COALESCE(u.full_name, '(unknown)') AS user_name
           FROM quotes q
           LEFT JOIN customers c    ON c.id = q.customer_id
           LEFT JOIN client_users u ON u.id = q.created_by_user_id
          WHERE q.client_id = ?
            AND q.status IN ($inWon)
            $rcDate
            $rcUser
       ORDER BY q.accepted_at DESC, q.created_at DESC
       LIMIT 10"
    );
    $args = array_merge([$clientId], $wonStatuses, $rcDateParams, $rcUserParams);
    $st->execute($args);
    $recent = $st->fetchAll();
}

$activeNav = 'dashboard';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .period-bar {
            display: flex; gap: 0.375rem; flex-wrap: wrap;
            margin: 0 0 1.25rem; align-items: center;
        }
        .period-bar a, .period-bar button {
            padding: 0.4375rem 0.875rem;
            background: var(--bg-card); color: var(--text-secondary);
            border: 1px solid var(--border-strong); border-radius: 8px;
            text-decoration: none; font-size: 0.875rem; font-weight: 500;
            cursor: pointer; font-family: inherit;
        }
        .period-bar a.is-active, .period-bar button.is-active {
            background: var(--brand); color: #fff; border-color: var(--brand);
        }
        .period-bar a:hover, .period-bar button:hover {
            border-color: var(--text-faint);
        }
        .custom-range {
            display: inline-flex; gap: 0.375rem; align-items: center;
            padding: 0.25rem 0.5rem;
            background: var(--bg-subtle); border: 1px solid var(--border);
            border-radius: 8px;
            margin-left: 0.25rem;
        }
        .custom-range input[type="date"] {
            padding: 0.3125rem 0.4375rem; border: 1px solid var(--border-strong);
            border-radius: 6px; font: inherit; font-size: 0.8125rem;
            background: var(--bg-input); color: var(--text-primary);
        }
        .custom-range input[type="hidden"] + label,
        .custom-range label {
            font-size: 0.75rem; color: var(--text-faint); font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .custom-range button[type="submit"] {
            padding: 0.3125rem 0.75rem; background: var(--brand); color: #fff;
            border: 0; border-radius: 6px; cursor: pointer;
            font-size: 0.8125rem; font-weight: 600;
        }
        .custom-range button[type="submit"]:hover { background: var(--brand-hover); }
        .person-filter {
            display: inline-flex; align-items: center; gap: 0.5rem;
            margin: 0 0 1.25rem;
            padding: 0.4375rem 0.75rem;
            background: var(--bg-subtle); border: 1px solid var(--border);
            border-radius: 8px;
        }
        .person-filter label {
            font-size: 0.75rem; color: var(--text-faint); font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .person-filter select {
            padding: 0.3125rem 0.5rem; border: 1px solid var(--border-strong);
            border-radius: 6px; font: inherit; font-size: 0.875rem;
            background: var(--bg-input); color: var(--text-primary); min-width: 12rem;
        }
        .kpi-grid {
            display: grid; gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            margin-bottom: 1.5rem;
        }
        .kpi-tile {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px;
            padding: 1rem 1.125rem;
        }
        .kpi-tile .kpi-label {
            font-size: 0.75rem; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--text-faint); font-weight: 600;
            margin-bottom: 0.375rem;
        }
        .kpi-tile .kpi-value {
            font-size: 1.75rem; font-weight: 800; color: var(--text-primary);
            line-height: 1.1;
        }
        .kpi-tile .kpi-sub { color: var(--text-faint); font-size: 0.8125rem; margin-top: 0.25rem; }
        /* These two stay literal — semantic colours for "money" and
           "close-rate" tiles that should read the same in both themes. */
        .kpi-tile.kpi-revenue .kpi-value { color: #16a34a; }
        .kpi-tile.kpi-rate    .kpi-value { color: #d97706; }
        .panel {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px;
            padding: 1.125rem 1.25rem; margin-bottom: 1.25rem;
        }
        .panel h2 {
            margin: 0 0 0.875rem; font-size: 1.0625rem;
            color: var(--text-primary); font-weight: 700;
        }
        .panel .panel-sub { color: var(--text-faint); font-size: 0.8125rem; margin: -0.625rem 0 0.875rem; }
        table.lb {
            width: 100%; border-collapse: collapse; font-size: 0.9375rem;
        }
        table.lb th, table.lb td {
            text-align: left; padding: 0.5rem 0.625rem;
            border-bottom: 1px solid var(--border-faint);
            color: var(--text-body);
        }
        table.lb th {
            font-size: 0.6875rem; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--text-faint); font-weight: 600;
            background: var(--bg-subtle);
        }
        table.lb td.num, table.lb th.num { text-align: right; font-variant-numeric: tabular-nums; }
        table.lb tr.medal-1 td:first-child::before { content: '🥇 '; }
        table.lb tr.medal-2 td:first-child::before { content: '🥈 '; }
        table.lb tr.medal-3 td:first-child::before { content: '🥉 '; }
        .mix-row {
            display: grid; grid-template-columns: 1fr 6rem 6rem;
            gap: 0.5rem; align-items: center;
            padding: 0.4375rem 0; border-bottom: 1px solid var(--border-faint);
        }
        .mix-row:last-child { border-bottom: 0; }
        .mix-bar {
            position: relative; height: 8px; background: var(--bg-subtle-2);
            border-radius: 999px; margin-top: 0.25rem;
        }
        .mix-bar > span {
            position: absolute; left: 0; top: 0; bottom: 0;
            background: linear-gradient(90deg, var(--brand), var(--link));
            border-radius: 999px;
        }
        .mix-name { font-weight: 600; color: var(--text-primary); }
        .mix-units, .mix-rev {
            text-align: right; color: var(--text-secondary); font-variant-numeric: tabular-nums;
            font-size: 0.875rem;
        }
        .margin-grid {
            display: grid; gap: 0.875rem;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        }
        .margin-cell .m-label {
            font-size: 0.75rem; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--text-faint); font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .margin-cell .m-value {
            font-size: 1.25rem; font-weight: 700; color: #16a34a;
            font-variant-numeric: tabular-nums;
        }
        .margin-disclaimer {
            background: var(--alert-info-bg); border: 1px solid var(--alert-info-border);
            border-radius: 8px; padding: 0.625rem 0.875rem;
            color: var(--alert-info-text); font-size: 0.8125rem; line-height: 1.45;
            margin-top: 0.875rem;
        }
        .recent-row {
            display: grid; grid-template-columns: 1fr 1fr 1fr 6rem;
            gap: 0.5rem; padding: 0.4375rem 0.25rem;
            border-bottom: 1px solid var(--border-faint); font-size: 0.875rem;
            align-items: center;
            color: var(--text-body);
        }
        .recent-row a { color: var(--link); text-decoration: none; font-weight: 600; }
        .recent-row a:hover { text-decoration: underline; }
        .recent-row .r-rev {
            text-align: right; font-variant-numeric: tabular-nums;
            color: #16a34a; font-weight: 700;
        }
        .recent-row .r-date { color: var(--text-faint); font-size: 0.8125rem; }
        .empty { color: var(--text-faint); font-style: italic; padding: 0.5rem 0; }
        @media (max-width: 700px) {
            .recent-row { grid-template-columns: 1fr 1fr; }
            .recent-row .r-user, .recent-row .r-date { display: none; }
            table.lb th.col-pipe, table.lb td.col-pipe { display: none; }
        }

        /* Pie chart layout — chart + legend or table sit side by side
           on desktop, stack on mobile. Keeps the data + the visual
           on one row so users can compare proportions vs. raw numbers
           without scrolling between them. */
        .panel-flex {
            display: flex; gap: 1.25rem; align-items: flex-start;
            flex-wrap: wrap;
        }
        .panel-flex-table { flex: 1 1 320px; min-width: 0; }
        .panel-flex-pie   { flex: 0 0 auto; }
        .panel-flex-pie .pie-heading {
            margin: 0 0 0.5rem; font-size: 0.6875rem;
            text-transform: uppercase; letter-spacing: 0.05em;
            color: var(--text-faint); font-weight: 600;
        }

        /* SVG pie + legend shared with anything that calls render_pie_chart(). */
        .pie-wrap {
            display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;
        }
        .pie-wrap > svg { flex: 0 0 auto; }
        .pie-legend {
            list-style: none; padding: 0; margin: 0;
            display: flex; flex-direction: column; gap: 0.3125rem;
            font-size: 0.8125rem; flex: 1 1 auto; min-width: 0;
        }
        .pie-legend li {
            display: grid;
            grid-template-columns: 12px 1fr auto auto;
            gap: 0.5rem; align-items: center;
        }
        .pie-swatch {
            display: inline-block; width: 12px; height: 12px;
            border-radius: 3px;
        }
        .pie-lbl { color: var(--text-primary); font-weight: 500;
                   white-space: nowrap; overflow: hidden;
                   text-overflow: ellipsis; }
        .pie-val { color: var(--text-secondary); font-variant-numeric: tabular-nums;
                   white-space: nowrap; }
        .pie-pct { color: var(--text-faint); font-variant-numeric: tabular-nums;
                   font-size: 0.75rem; white-space: nowrap; }

        @media (max-width: 700px) {
            .panel-flex { flex-direction: column; }
            .panel-flex-pie { width: 100%; }
        }

        /* Upcoming jobs widget — forward-looking calendar peek that
           sits above the KPI tiles. Each row is a click-through to
           the calendar's appointment view. The Today date pill is
           red so the eye lands on it first. */
        .upcoming-row {
            display: grid;
            grid-template-columns: 5.5rem 1fr 9rem 8rem;
            gap: 0.75rem; align-items: center;
            padding: 0.5rem 0.5rem;
            border-bottom: 1px solid var(--border-faint);
            text-decoration: none; color: inherit;
            font-size: 0.875rem;
            border-radius: 6px;
            transition: background-color 100ms;
        }
        .upcoming-row:hover { background: var(--bg-subtle-2); }
        .upcoming-row:last-of-type { border-bottom: 0; }
        .up-when .up-date {
            font-weight: 700; color: var(--text-primary); font-size: 0.8125rem;
        }
        .up-when .up-date.is-today { color: #ef4444; }
        .up-when .up-time { color: var(--text-faint); font-size: 0.75rem; }
        .up-customer .up-name {
            font-weight: 600; color: var(--link);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .up-customer .up-place { color: var(--text-faint); font-size: 0.75rem; }
        .up-fitter { color: var(--text-muted); font-size: 0.8125rem; }
        .up-quote {
            display: flex; align-items: center; gap: 0.375rem;
            font-size: 0.75rem; flex-wrap: wrap;
        }
        .up-quote .status-pill {
            margin: 0; font-size: 0.625rem; padding: 0.0625rem 0.375rem;
        }
        .up-quote-num {
            color: var(--text-faint);
            font-family: ui-monospace, Menlo, Consolas, monospace;
        }
        .upcoming-more {
            display: inline-block; margin-top: 0.625rem;
            color: var(--link); font-size: 0.8125rem; font-weight: 600;
            text-decoration: none;
        }
        .upcoming-more:hover { text-decoration: underline; }
        @media (max-width: 700px) {
            .upcoming-row { grid-template-columns: 4.5rem 1fr; }
            .up-fitter, .up-quote { display: none; }
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Dashboard</h1>
                <p class="page-subtitle">
                    Sales at a glance — <?= e($periodLabel) ?><?php
                        // Append "for <name>" when filtered to a single
                        // salesperson, so the page subtitle accurately
                        // reflects what the user is looking at.
                        if ($filterUser): ?> for <strong><?= e((string) $filterUser['full_name']) ?></strong><?php endif; ?>.
                </p>
            </div>
            <?php
                // Per Tyler's review (Quotes #3): "+ New quote" button
                // surfaced on the dashboard so the new-quote action is
                // never more than one click away from any landing page.
                $canCreateQuotesHere = $isAdmin || !empty($perms['can_create_quotes']);
            ?>
            <?php if ($canCreateQuotesHere): ?>
                <a href="/quote-builder/new.php" class="btn btn-primary">+ New quote</a>
            <?php endif; ?>
        </div>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-error" role="alert">
                <?= e((string) $_SESSION['flash_error']) ?>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <?php
            // Joke of the Day — per-client toggle (migrate_joke_toggle.php),
            // default on. Guarded so it still shows pre-migration.
            $jokeEnabled = true;
            try {
                $jSt = db()->prepare('SELECT COALESCE(feature_joke_of_day, 1) FROM client_settings WHERE client_id = ? LIMIT 1');
                $jSt->execute([$clientId]);
                $jVal = $jSt->fetchColumn();
                if ($jVal !== false) $jokeEnabled = ((int) $jVal) === 1;
            } catch (Throwable $e) { /* column not present — keep default on */ }
        ?>
        <?php if ($jokeEnabled): ?>
        <?php /* Joke of the Day — a bit of fun. Hidden by default; the JS shows
                 it once a day (dismissible), so a fresh page after dismissing
                 doesn't pop it back up. Staff-only (it's on the dashboard). */ ?>
        <div id="jotd" class="jotd" hidden>
            <span class="jotd-emoji" aria-hidden="true">😄</span>
            <div class="jotd-body">
                <div class="jotd-label">Joke of the day</div>
                <div class="jotd-text" id="jotd-text"><?= e(joke_of_the_day()) ?></div>
            </div>
            <div class="jotd-actions">
                <button type="button" id="jotd-another" class="jotd-btn" title="Give me another">🔁 Another</button>
                <button type="button" id="jotd-hide" class="jotd-btn" title="Hide until tomorrow">✕</button>
            </div>
        </div>
        <style>
            .jotd {
                display: flex; align-items: center; gap: 0.75rem;
                background: linear-gradient(90deg, #fffbeb, #fef9c3);
                border: 1px solid #fde68a; border-radius: 12px;
                padding: 0.625rem 0.875rem; margin-bottom: 1rem;
            }
            /* display:flex above overrides the default [hidden] display:none,
               so box.hidden=true (the ✕ button and the once-a-day guard)
               never actually hid it (Tyler: ✕ won't close it). Re-assert. */
            .jotd[hidden] { display: none; }
            [data-theme="dark"] .jotd {
                background: rgba(250, 204, 21, 0.08); border-color: rgba(250, 204, 21, 0.3);
            }
            .jotd-emoji { font-size: 1.5rem; line-height: 1; flex: 0 0 auto; }
            .jotd-body { flex: 1 1 auto; min-width: 0; }
            .jotd-label {
                font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.06em;
                font-weight: 700; color: #b45309;
            }
            [data-theme="dark"] .jotd-label { color: #fbbf24; }
            .jotd-text { font-size: 0.9375rem; color: var(--text-primary); margin-top: 0.0625rem; }
            .jotd-actions { display: flex; gap: 0.25rem; flex: 0 0 auto; }
            .jotd-btn {
                appearance: none; border: 1px solid transparent; background: transparent;
                cursor: pointer; font: inherit; font-size: 0.8125rem; color: #b45309;
                border-radius: 8px; padding: 0.25rem 0.5rem; line-height: 1;
            }
            .jotd-btn:hover { background: rgba(180, 83, 9, 0.1); }
        </style>
        <script>
        (function () {
            var box = document.getElementById('jotd');
            if (!box) return;
            var JOKES = <?= json_encode(jokes_list(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
            var textEl = document.getElementById('jotd-text');
            // Show once per day: if dismissed today, stay hidden.
            var today = new Date().toISOString().slice(0, 10);
            var hidden = '';
            try { hidden = localStorage.getItem('yb_jotd_hidden') || ''; } catch (e) {}
            if (hidden !== today) { box.hidden = false; }

            document.getElementById('jotd-hide').addEventListener('click', function () {
                box.hidden = true;
                try { localStorage.setItem('yb_jotd_hidden', today); } catch (e) {}
            });
            document.getElementById('jotd-another').addEventListener('click', function () {
                if (!JOKES.length) return;
                var cur = textEl.textContent, next = cur, guard = 0;
                while (next === cur && guard++ < 20) {
                    next = JOKES[Math.floor(Math.random() * JOKES.length)];
                }
                textEl.textContent = next;
            });
        })();
        </script>
        <?php endif; /* $jokeEnabled */ ?>

        <div class="period-bar">
            <?php
                // Each preset period button carries the current user
                // filter forward, so picking a different period doesn't
                // silently reset the salesperson dropdown.
                $userQs = $filterUserId > 0 ? '&user_id=' . $filterUserId : '';
            ?>
            <?php foreach ([
                'mtd' => 'This month',
                '30d' => 'Last 30 days',
                'qtd' => 'This quarter',
                'ytd' => 'This year',
                'all' => 'All time',
            ] as $key => $label): ?>
                <a href="?period=<?= e($key) ?><?= e($userQs) ?>"
                   class="<?= $period === $key ? 'is-active' : '' ?>">
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>

            <!--
                Custom range — a GET form (no CSRF needed, no side effects)
                that posts back to this same page with ?period=custom plus
                from / to date params. Carries user_id forward via hidden
                input. Defaults pre-fill to "last 30 days" so opening Custom
                for the first time lands somewhere usable.
            -->
            <form method="get" action="/dashboard/index.php" class="custom-range">
                <input type="hidden" name="period" value="custom">
                <?php if ($filterUserId > 0): ?>
                    <input type="hidden" name="user_id" value="<?= (int) $filterUserId ?>">
                <?php endif; ?>
                <label for="from-date">From</label>
                <input id="from-date" name="from" type="date"
                       value="<?= e($customFrom) ?>"
                       max="<?= e(date('Y-m-d')) ?>">
                <label for="to-date">To</label>
                <input id="to-date" name="to" type="date"
                       value="<?= e($customTo) ?>"
                       max="<?= e(date('Y-m-d')) ?>">
                <button type="submit"
                        class="<?= $period === 'custom' ? 'is-active' : '' ?>">
                    Apply
                </button>
            </form>
        </div>

        <?php if ($salesPeople): ?>
            <!--
                Salesperson filter — separate row below the period bar to
                avoid cramming everything onto one line. Auto-submits on
                change so it feels snappy without a separate Apply button.
                Preserves the current period via hidden inputs.
            -->
            <form method="get" action="/dashboard/index.php" class="person-filter">
                <label for="user-filter">View:</label>
                <select id="user-filter" name="user_id"
                        onchange="this.form.submit()">
                    <option value="0">All sales team</option>
                    <?php foreach ($salesPeople as $sp): ?>
                        <option value="<?= (int) $sp['id'] ?>"
                                <?= (int) $sp['id'] === $filterUserId ? 'selected' : '' ?>>
                            <?= e((string) $sp['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="period" value="<?= e($period) ?>">
                <?php if ($period === 'custom'): ?>
                    <input type="hidden" name="from" value="<?= e($customFrom) ?>">
                    <input type="hidden" name="to"   value="<?= e($customTo) ?>">
                <?php endif; ?>
                <noscript>
                    <button type="submit">Apply</button>
                </noscript>
            </form>
        <?php endif; ?>

        <!-- Upcoming jobs widget --------------------------------------->
        <!-- Forward-looking calendar peek. Shown to anyone who reaches
             the dashboard (the existing canSeeAnything gate already
             bounces pure fitters back to /calendar/index.php, so
             they get the calendar directly). -->
        <div class="panel">
            <h2>Upcoming jobs</h2>
            <p class="panel-sub">
                <?php if (!$upcomingJobs): ?>
                    Nothing booked yet — head to the calendar to add one.
                <?php else: ?>
                    Next <?= count($upcomingJobs) ?>
                    <?= count($upcomingJobs) === 1 ? 'appointment' : 'appointments' ?>
                    on the calendar — soonest first.
                <?php endif; ?>
            </p>
            <?php if (!$upcomingJobs): ?>
                <div class="empty">No upcoming jobs booked.</div>
            <?php else:
                $tomorrowYmd = date('Y-m-d', strtotime('tomorrow'));
                $todayYmd    = date('Y-m-d');
                foreach ($upcomingJobs as $j):
                    $apptDate = (string) ($j['appointment_date'] ?? '');
                    $apptTime = (string) ($j['appointment_time'] ?? '');
                    $whenTs   = $apptDate !== '' ? strtotime($apptDate . ' ' . $apptTime) : 0;
                    if ($apptDate === $todayYmd) {
                        $dateLabel = 'Today';
                        $isToday   = true;
                    } elseif ($apptDate === $tomorrowYmd) {
                        $dateLabel = 'Tomorrow';
                        $isToday   = false;
                    } else {
                        $dateLabel = $whenTs ? date('D j M', $whenTs) : '';
                        $isToday   = false;
                    }
                    $timeLabel = $whenTs ? date('g:ia', $whenTs) : '';
                    $custName  = trim((string) ($j['customer_name'] ?? $j['title'] ?? ''));
                    if ($custName === '') $custName = 'No customer';
                    $postcode  = trim((string) ($j['installation_postcode']
                                                ?? $j['customer_postcode']
                                                ?? ''));
            ?>
                <a class="upcoming-row" href="/calendar/view.php?id=<?= (int) $j['id'] ?>">
                    <div class="up-when">
                        <div class="up-date<?= $isToday ? ' is-today' : '' ?>">
                            <?= e($dateLabel) ?>
                        </div>
                        <div class="up-time"><?= e($timeLabel) ?></div>
                    </div>
                    <div class="up-customer">
                        <div class="up-name"><?= e($custName) ?></div>
                        <?php if ($postcode !== ''): ?>
                            <div class="up-place"><?= e($postcode) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="up-fitter">
                        <?php if ($canViewAllJobs && !empty($j['fitter_name'])): ?>
                            <?= e((string) $j['fitter_name']) ?>
                        <?php elseif ($canViewAllJobs): ?>
                            <em style="color:var(--text-faint)">Unassigned</em>
                        <?php else: ?>
                            &nbsp;
                        <?php endif; ?>
                    </div>
                    <div class="up-quote">
                        <?php if (!empty($j['quote_number'])): ?>
                            <span class="status-pill status-<?= e((string) $j['quote_status']) ?>">
                                <?= e((string) $j['quote_status']) ?>
                            </span>
                            <span class="up-quote-num"><?= e((string) $j['quote_number']) ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; endif; ?>
            <a href="/calendar/index.php" class="upcoming-more">Open calendar &rarr;</a>
        </div>

        <!-- KPI tiles ---------------------------------------------------->
        <?php if ($canSeeRevenue): ?>
        <div class="kpi-grid">
            <div class="kpi-tile kpi-revenue">
                <div class="kpi-label">Revenue (won)</div>
                <div class="kpi-value">£<?= number_format((float) $kpi['revenue'], 2) ?></div>
                <div class="kpi-sub"><?= (int) $kpi['won_count'] ?> job<?= (int) $kpi['won_count'] === 1 ? '' : 's' ?> accepted</div>
            </div>
            <div class="kpi-tile">
                <div class="kpi-label">Average order value</div>
                <div class="kpi-value">£<?= number_format((float) $kpi['aov'], 2) ?></div>
                <div class="kpi-sub">across won quotes</div>
            </div>
            <div class="kpi-tile kpi-rate">
                <div class="kpi-label">Close rate</div>
                <div class="kpi-value">
                    <?php if ($closeRate === null): ?>
                        —
                    <?php else: ?>
                        <?= number_format($closeRate, 1) ?>%
                    <?php endif; ?>
                </div>
                <div class="kpi-sub">
                    <?php if ($closeRate === null): ?>
                        no decided quotes yet
                    <?php else: ?>
                        <?= (int) $rate['accepted_cnt'] ?> of <?= (int) $rate['decided_cnt'] ?> decided
                    <?php endif; ?>
                </div>
            </div>
            <div class="kpi-tile">
                <div class="kpi-label">Jobs in period</div>
                <div class="kpi-value"><?= (int) $kpi['won_count'] ?></div>
                <div class="kpi-sub">accepted &amp; beyond</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Salesperson leaderboard ------------------------------------>
        <?php if ($canSeeTeam): ?>
        <div class="panel">
            <h2><?= $filterUser ? 'Their numbers' : 'Sales team' ?></h2>
            <p class="panel-sub">
                <?= $filterUser
                    ? 'Pipeline, close rate, and revenue for this salesperson.'
                    : 'Pipeline, close rate, and revenue per salesperson.' ?>
            </p>
            <?php
                // Pie data — only slices with > £0 revenue. Empty array
                // is fine; the helper draws a "no data" placeholder.
                // Always render the pie wrapper so layout doesn't shift
                // when data starts flowing.
                $teamPie = [];
                foreach ($leaderboard as $row) {
                    if ((float) $row['revenue'] <= 0) continue;
                    $teamPie[] = [
                        'label' => (string) $row['name'],
                        'value' => (float) $row['revenue'],
                    ];
                }
            ?>
            <div class="panel-flex">
                <div class="panel-flex-table">
                    <?php if (!$leaderboard): ?>
                        <div class="empty">No quotes raised in this period.</div>
                    <?php else: ?>
                        <table class="lb">
                            <thead>
                                <tr>
                                    <th>Person</th>
                                    <th class="num col-pipe">Pipeline</th>
                                    <th class="num">Decided</th>
                                    <th class="num">Won</th>
                                    <th class="num">Close rate</th>
                                    <th class="num">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 0; foreach ($leaderboard as $row):
                                    $rank++;
                                    $decided = (int) $row['decided'];
                                    $won     = (int) $row['won'];
                                    $rate    = $decided > 0 ? ($won / $decided) * 100 : null;
                                ?>
                                    <tr class="medal-<?= $rank <= 3 ? $rank : 0 ?>">
                                        <td><?= e((string) $row['name']) ?></td>
                                        <td class="num col-pipe"><?= (int) $row['pipeline'] ?></td>
                                        <td class="num"><?= $decided ?></td>
                                        <td class="num"><?= $won ?></td>
                                        <td class="num">
                                            <?= $rate === null ? '—' : number_format($rate, 1) . '%' ?>
                                        </td>
                                        <td class="num">£<?= number_format((float) $row['revenue'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <?php if (!$filterUser): /* revenue-share pie is meaningless with one person */ ?>
                <div class="panel-flex-pie">
                    <h3 class="pie-heading">Revenue share</h3>
                    <?= render_pie_chart($teamPie, ['size' => 170, 'donut' => true, 'unit' => '£']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Product mix ------------------------------------------------->
        <?php if ($canSeeProducts): ?>
        <div class="panel">
            <h2>What's selling</h2>
            <p class="panel-sub">Top products by revenue in this period.</p>
            <?php
                $mixPie = [];
                foreach ($productMix as $row) {
                    if ((float) $row['revenue'] <= 0) continue;
                    $mixPie[] = [
                        'label' => (string) $row['product_name'],
                        'value' => (float) $row['revenue'],
                    ];
                }
            ?>
            <div class="panel-flex">
                <div class="panel-flex-pie">
                    <?= render_pie_chart($mixPie, ['size' => 200, 'donut' => true, 'unit' => '£']) ?>
                </div>
                <div class="panel-flex-table">
                    <?php if (!$productMix): ?>
                        <div class="empty">No products sold in this period.</div>
                    <?php else: foreach ($productMix as $row):
                        $pct = $productMixTotal > 0 ? ((float) $row['revenue'] / $productMixTotal) * 100 : 0;
                    ?>
                        <div class="mix-row">
                            <div>
                                <div class="mix-name"><?= e((string) $row['product_name']) ?></div>
                                <div class="mix-bar"><span style="width:<?= number_format($pct, 1) ?>%"></span></div>
                            </div>
                            <div class="mix-units"><?= (int) $row['units'] ?> unit<?= (int) $row['units'] === 1 ? '' : 's' ?></div>
                            <div class="mix-rev">£<?= number_format((float) $row['revenue'], 2) ?></div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Gross profit (gated by dash_view_profit AND can_view_costs) -->
        <?php if ($canSeeProfit && $marginData): ?>
            <div class="panel">
                <h2>Gross profit</h2>
                <p class="panel-sub">
                    Sell price minus the price-table cost basis (material + extras).
                    Equivalent to your <?= e(strtolower(pricing_basis_label($pricingBasis))) ?> &amp; discount turned into pounds.
                </p>
                <div class="margin-grid">
                    <div class="margin-cell">
                        <div class="m-label">Total profit</div>
                        <div class="m-value">£<?= number_format((float) $marginData['margin'], 2) ?></div>
                    </div>
                    <div class="margin-cell">
                        <div class="m-label">Margin %</div>
                        <div class="m-value">
                            <?= $marginData['margin_pct'] === null
                                ? '—'
                                : number_format((float) $marginData['margin_pct'], 1) . '%' ?>
                        </div>
                    </div>
                    <div class="margin-cell">
                        <div class="m-label">Per job (avg)</div>
                        <div class="m-value">
                            <?= $marginData['margin_per_job'] === null
                                ? '—'
                                : '£' . number_format((float) $marginData['margin_per_job'], 2) ?>
                        </div>
                    </div>
                    <div class="margin-cell">
                        <div class="m-label">Cost of goods</div>
                        <div class="m-value" style="color:#92400e">
                            £<?= number_format((float) $marginData['cost_basis'], 2) ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent activity -------------------------------------------->
        <?php if ($canSeeRecent): ?>
        <div class="panel">
            <h2>Recent wins</h2>
            <p class="panel-sub">Latest 10 jobs accepted in this period.</p>
            <?php if (!$recent): ?>
                <div class="empty">No jobs accepted in this period yet.</div>
            <?php else: foreach ($recent as $r):
                $when = $r['accepted_at'] ?: $r['created_at'];
            ?>
                <div class="recent-row">
                    <div>
                        <a href="/quote-builder/edit.php?id=<?= (int) $r['id'] ?>">
                            <?= e((string) ($r['quote_number'] ?? '#' . $r['id'])) ?>
                        </a>
                        — <?= e((string) $r['customer_name']) ?>
                    </div>
                    <div class="r-user"><?= e((string) $r['user_name']) ?></div>
                    <div class="r-date"><?= e($when ? date('j M Y', strtotime((string) $when)) : '—') ?></div>
                    <div class="r-rev">£<?= number_format((float) $r['total'], 2) ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>
