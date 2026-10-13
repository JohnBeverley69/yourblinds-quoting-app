<?php
declare(strict_types=1);

/**
 * Factory Console · Dashboard — JOHN'S money page (super-admin only).
 *
 * John, 2026-10-12: "very detailed and informative with pie charts, graphs, you
 * name it … a work in progress figure … profit margin … gross margins, anything
 * and everything. It can be quite a long page … it's only going to be me
 * looking at it." So this is a long, read-only financial analysis of the acting
 * factory (current_factory_id(), per John's per-client rule), with "The Numbers"
 * (factory/profit.php) folded in: the gross margin here comes from the same
 * costing code (_partials/factory_costing.php).
 *
 * Office logins on the factory account never see it: they land on the Pipeline
 * (factory_console_home()) and a GET here sends them there too.
 *
 * Every figure is EX VAT unless it says otherwise (cash received is money in the
 * bank, so it includes VAT). The definitions are printed at the foot of the page.
 *
 * No close rate or other quote figures: this is the trade portal, every order
 * is a close (John, 2026-10-12). Everything is placed orders, invoices, payments.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_console.php';
require_once __DIR__ . '/../_partials/factory_costing.php';
require_once __DIR__ . '/../_partials/remakes.php';

requireFactoryOffice();
if (!is_super_admin()) {
    header('Location: /factory/pipeline.php');
    exit;
}

$pdo     = db();
$factory = (int) current_factory_id();
$today   = date('Y-m-d');

// ---------------------------------------------------------------------------
// Period + the one it's compared with
// ---------------------------------------------------------------------------
/** $ymd moved by $months, the day clamped to the target month's last day. */
function fd_shift(string $ymd, int $months): string
{
    [$y, $m, $d] = array_map('intval', explode('-', $ymd));
    $first = (new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m)))->modify(($months >= 0 ? '+' : '') . $months . ' months');
    return $first->format('Y-m-') . sprintf('%02d', min($d, (int) $first->format('t')));
}
function fd_month_end(string $ymd): string { return (new DateTimeImmutable(substr($ymd, 0, 8) . '01'))->format('Y-m-t'); }

$periods = [
    'month'       => 'This month',
    'lastmonth'   => 'Last month',
    'quarter'     => 'This quarter',
    'lastquarter' => 'Last quarter',
    'year'        => 'This year',
    'lastyear'    => 'Last year',
    '12m'         => 'Last 12 months',
];
$period = (string) ($_GET['period'] ?? 'month');
$fromIn = trim((string) ($_GET['from'] ?? ''));
$toIn   = trim((string) ($_GET['to'] ?? ''));
$realDate = static fn (string $s): bool => (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
$rangeNote = null;
if ($period === 'custom') {
    if (!$realDate($fromIn) || !$realDate($toIn)) {
        $rangeNote = 'A custom range needs both a real From and To date, so this month is shown instead.';
        $period = 'month';
    } elseif ($fromIn > $toIn) {
        [$fromIn, $toIn] = [$toIn, $fromIn];
        $rangeNote = 'The dates were the wrong way round, so they\'ve been swapped.';
    }
}
$qStart = static fn (string $ymd): string => sprintf('%s-%02d-01', substr($ymd, 0, 4), intdiv((int) substr($ymd, 5, 2) - 1, 3) * 3 + 1);
switch ($period) {
    case 'lastmonth':
        $from = fd_shift(date('Y-m-01'), -1); $to = fd_month_end($from);
        $pFrom = fd_shift($from, -1); $pTo = fd_month_end($pFrom); $cmp = 'the month before'; break;
    case 'quarter':
        $from = $qStart($today); $to = $today;
        $pFrom = fd_shift($from, -3); $pTo = fd_shift($to, -3); $cmp = 'the same days last quarter'; break;
    case 'lastquarter':
        $from = fd_shift($qStart($today), -3); $to = fd_month_end(fd_shift($from, 2));
        $pFrom = fd_shift($from, -3); $pTo = fd_month_end(fd_shift($pFrom, 2)); $cmp = 'the quarter before'; break;
    case 'year':
        $from = date('Y-01-01'); $to = $today;
        $pFrom = fd_shift($from, -12); $pTo = fd_shift($to, -12); $cmp = 'the same days last year'; break;
    case 'lastyear':
        $from = (date('Y') - 1) . '-01-01'; $to = (date('Y') - 1) . '-12-31';
        $pFrom = (date('Y') - 2) . '-01-01'; $pTo = (date('Y') - 2) . '-12-31'; $cmp = 'the year before'; break;
    case '12m':
        $from = fd_shift(date('Y-m-01'), -11); $to = $today;
        $pFrom = fd_shift($from, -12); $pTo = fd_shift($to, -12); $cmp = 'the 12 months before'; break;
    case 'custom':
        $from = $fromIn; $to = $toIn;
        $days = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;
        $pTo = date('Y-m-d', strtotime($from . ' -1 day'));
        $pFrom = date('Y-m-d', strtotime($pTo . ' -' . ($days - 1) . ' days'));
        $cmp = 'the ' . $days . ' day' . ($days === 1 ? '' : 's') . ' before'; break;
    default:
        $period = 'month';
        $from = date('Y-m-01'); $to = $today;
        $pFrom = fd_shift($from, -1); $pTo = fd_shift($to, -1); $cmp = 'the same days last month';
}
$in   = static fn (?string $d, string $a, string $b): bool => $d !== null && $d !== '' && substr($d, 0, 10) >= $a && substr($d, 0, 10) <= $b;
$inP  = static fn (?string $d): bool => $in($d, $from, $to);
$inPP = static fn (?string $d): bool => $in($d, $pFrom, $pTo);

// The 12-month trend: this month and the 11 before it.
$trendStart = fd_shift(date('Y-m-01'), -11);
$months = [];
for ($i = 0; $i < 12; $i++) { $k = substr(fd_shift($trendStart, $i), 0, 7); $months[$k] = date('M y', strtotime($k . '-01')); }
$mOf = static fn (?string $d): string => substr((string) $d, 0, 7);

// One window covering the period, its comparison and the trend.
$winFrom = min($from, $pFrom, $trendStart);
$winTo   = max($to, $today);

// ---------------------------------------------------------------------------
// Data
// ---------------------------------------------------------------------------
$tbl = static fn (string $t): bool => ar_table_ready($pdo, $t);
$col = static fn (string $t, string $c): bool => pe_col_exists($pdo, $t, $c);
$placedIn = "'" . implode("','", os_placed_statuses()) . "'";

// --- Orders placed (by the date the order was created — there is no separate
//     "placed on" date). Trade accounts: what the invoice bills
//     (ar_placed_orders, the Invoices page's own figure). ar_placed_orders caps
//     at 500 rows, so it's read a month at a time.
$orders = [];   // id => [date, account_id, account, value, blinds, own]
for ($m = substr($winFrom, 0, 7) . '-01'; $m <= $winTo; $m = fd_shift($m, 1)) {
    foreach (ar_placed_orders($pdo, $factory, null, max($m, $winFrom), min(fd_month_end($m), $winTo)) as $r) {
        $orders[(int) $r['id']] = [
            'date' => (string) $r['created_at'], 'account_id' => (int) $r['account_id'],
            'account' => (string) $r['account_name'], 'value' => (float) $r['wholesale_total'],
            'blinds' => (int) $r['bev_qty'], 'own' => false,
        ];
    }
}
// Our own one-off sales (no trade account): billed from the quote builder, at the
// quote's net subtotal.
try {
    $acctE = $col('quotes', 'account_client_id') ? 'COALESCE(NULLIF(q.account_client_id,0), q.client_id)' : 'q.client_id';
    $st = $pdo->prepare(
        "SELECT q.id, q.created_at, q.subtotal,
                (SELECT COALESCE(SUM(qi.quantity),0) FROM quote_items qi JOIN products p ON p.id = qi.product_id
                  WHERE qi.quote_id = q.id AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?) AS blinds
           FROM quotes q
          WHERE q.client_id = ? AND $acctE = ? AND q.status IN ($placedIn)
            AND q.created_at >= ? AND q.created_at < DATE_ADD(?, INTERVAL 1 DAY)"
    );
    $st->execute([$factory, $factory, $factory, $winFrom, $winTo]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ((int) $r['blinds'] <= 0) continue;
        $orders[(int) $r['id']] = ['date' => (string) $r['created_at'], 'account_id' => 0, 'account' => 'One-off sales',
                                   'value' => (float) $r['subtotal'], 'blinds' => (int) $r['blinds'], 'own' => true];
    }
} catch (Throwable $e) { /* pre-account schema */ }
// Remake orders are putting a fault right, not a sale — out of the order counts.
$remakeIds = [];
foreach (array_chunk(array_keys($orders), 500) as $chunk) $remakeIds += rm_remake_orders($pdo, $chunk);
foreach ($remakeIds as $id => $_) unset($orders[$id]);

// --- Costed lines (The Numbers' logic) ---------------------------------------
$lines = fcost_lines($pdo, $factory, $winFrom . ' 00:00:00', $winTo . ' 23:59:59');

// --- Invoices, credit notes, payments ---------------------------------------
$invoices = []; $credits = []; $payments = []; $carriage = []; $paidInv = []; $openInv = [];
if ($tbl('factory_ar_invoices')) {
    try {
        $st = $pdo->prepare("SELECT i.id, i.account_client_id, i.issue_date, i.subtotal, i.total, c.company_name
                               FROM factory_ar_invoices i LEFT JOIN clients c ON c.id = i.account_client_id
                              WHERE i.factory_client_id = ? AND i.status NOT IN ('draft','void')
                                AND i.issue_date BETWEEN ? AND ?");
        $st->execute([$factory, $winFrom, $winTo]);
        $invoices = $st->fetchAll(PDO::FETCH_ASSOC);

        $st = $pdo->prepare("SELECT i.issue_date, SUM(l.line_net) AS net
                               FROM factory_ar_invoice_lines l JOIN factory_ar_invoices i ON i.id = l.invoice_id
                              WHERE i.factory_client_id = ? AND i.status NOT IN ('draft','void') AND l.line_type = 'carriage'
                                AND i.issue_date BETWEEN ? AND ?
                           GROUP BY i.id, i.issue_date");
        $st->execute([$factory, $winFrom, $winTo]);
        $carriage = $st->fetchAll(PDO::FETCH_ASSOC);

        // Open invoices now: what's still owed, net of payments AND credit notes
        // (the same sum as ar_open_invoices()), aged on the due date.
        $cnSub = $tbl('factory_ar_credit_notes')
            ? "COALESCE((SELECT SUM(cn.total) FROM factory_ar_credit_notes cn WHERE cn.against_invoice_id = i.id AND cn.status <> 'void'), 0)"
            : '0';
        $st = $pdo->prepare("SELECT i.id, i.account_client_id, c.company_name, i.issue_date, i.due_date,
                                    i.total - i.amount_paid - $cnSub AS outstanding
                               FROM factory_ar_invoices i LEFT JOIN clients c ON c.id = i.account_client_id
                              WHERE i.factory_client_id = ? AND i.status IN ('raised','sent','part_paid')");
        $st->execute([$factory]);
        $openInv = array_values(array_filter($st->fetchAll(PDO::FETCH_ASSOC), static fn ($r) => (float) $r['outstanding'] > 0.004));
    } catch (Throwable $e) { /* leave empty */ }
}
if ($tbl('factory_ar_credit_notes')) {
    try {
        $st = $pdo->prepare("SELECT issue_date, subtotal FROM factory_ar_credit_notes
                              WHERE factory_client_id = ? AND status <> 'void' AND issue_date BETWEEN ? AND ?");
        $st->execute([$factory, $winFrom, $winTo]);
        $credits = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}
if ($tbl('factory_ar_payments')) {
    try {
        $st = $pdo->prepare("SELECT payment_date, amount FROM factory_ar_payments
                              WHERE factory_client_id = ? AND voided_at IS NULL AND payment_date BETWEEN ? AND ?");
        $st->execute([$factory, $winFrom, $winTo]);
        $payments = $st->fetchAll(PDO::FETCH_ASSOC);
        // Paid invoices and the day the last payment landed on them.
        $st = $pdo->prepare("SELECT i.id, i.issue_date, i.due_date, MAX(p.payment_date) AS paid_on
                               FROM factory_ar_invoices i
                               JOIN factory_ar_payment_allocations a ON a.invoice_id = i.id
                               JOIN factory_ar_payments p ON p.id = a.payment_id AND p.voided_at IS NULL
                              WHERE i.factory_client_id = ? AND i.status = 'paid'
                           GROUP BY i.id, i.issue_date, i.due_date
                             HAVING paid_on BETWEEN ? AND ?");
        $st->execute([$factory, $winFrom, $winTo]);
        $paidInv = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

// --- Remakes raised in the period -------------------------------------------
$remakes = ['raised' => 0, 'approved' => 0, 'declined' => 0, 'charged' => 0.0, 'reasons' => [], 'modes' => []];
if (rm_ready($pdo)) {
    try {
        $st = $pdo->prepare("SELECT status, reason_label, charge_mode, charge_amount FROM factory_remakes
                              WHERE factory_client_id = ? AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)");
        $st->execute([$factory, $from, $to]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $remakes['raised']++;
            if ($r['status'] === 'approved') {
                $remakes['approved']++;
                $remakes['charged'] += (float) $r['charge_amount'];
                $mode = (string) ($r['charge_mode'] ?: 'free');
                $remakes['modes'][$mode] = ($remakes['modes'][$mode] ?? 0) + 1;
            } elseif ($r['status'] === 'declined') {
                $remakes['declined']++;
            }
            $lbl = trim((string) $r['reason_label']) ?: 'No reason given';
            $remakes['reasons'][$lbl] = ($remakes['reasons'][$lbl] ?? 0) + 1;
        }
        arsort($remakes['reasons']);
    } catch (Throwable $e) {}
}

// --- Work in progress, right now (not period-bound) --------------------------
$wip = ['new' => [0, 0.0], 'in_production' => [0, 0.0], 'awaiting_supplier' => [0, 0.0], 'ready' => [0, 0.0],
        'needs_invoicing' => [0, 0.0]];
$wipUnknown = 0;
foreach (fc_orders($pdo, $factory) as $o) {
    if ($o['remake_of'] !== '') continue;
    $k = isset($wip[$o['stage']]) && $o['stage'] !== 'needs_invoicing' ? $o['stage']
       : ($o['money'] === 'needs_invoicing' ? 'needs_invoicing' : null);
    if ($k === null) continue;
    $wip[$k][0]++;
    if ($o['value'] === null) $wipUnknown++; else $wip[$k][1] += (float) $o['value'];
}

// ---------------------------------------------------------------------------
// Aggregate
// ---------------------------------------------------------------------------
$sum = static function (array $rows, string $dateKey, string $valKey, callable $pick): float {
    $t = 0.0;
    foreach ($rows as $r) if ($pick($r[$dateKey] ?? null)) $t += (float) $r[$valKey];
    return $t;
};
$kpi = static function (callable $pick) use ($orders, $lines, $invoices, $credits, $payments, $carriage, $paidInv, $sum): array {
    $o = array_filter($orders, static fn ($r) => $pick($r['date']));
    $value = array_sum(array_column($o, 'value'));
    $rc = 0.0; $c = 0.0; $ru = 0.0; $bl = 0; $mk = 0.0; $bought = 0.0; $rmCost = 0.0; $rmRev = 0.0; $ex = 0.0;
    foreach ($lines as $l) {
        if (!$pick($l['created_at'])) continue;
        if (!$l['is_remake']) $bl += $l['qty'];
        $ex += $l['extras'];
        if ($l['cost'] === null) { $ru += $l['rev']; continue; }
        $rc += $l['rev']; $c += $l['cost'];
        if ($l['is_remake']) { $rmCost += $l['cost']; $rmRev += $l['rev']; }
        if ($l['source'] === 'supplier') $bought += $l['cost']; else $mk += $l['cost'];
    }
    $inv = $sum($invoices, 'issue_date', 'subtotal', $pick);
    $cn  = $sum($credits, 'issue_date', 'subtotal', $pick);
    $days = []; $late = [];
    foreach ($paidInv as $p) {
        if (!$pick($p['paid_on'])) continue;
        $days[] = (strtotime($p['paid_on']) - strtotime((string) $p['issue_date'])) / 86400;
        if (!empty($p['due_date'])) $late[] = max(0, (strtotime($p['paid_on']) - strtotime((string) $p['due_date'])) / 86400);
    }
    return [
        'orders' => count($o), 'value' => $value, 'aov' => $o ? $value / count($o) : null, 'blinds' => $bl,
        'invoiced' => $inv, 'credited' => $cn, 'net_sales' => $inv - $cn,
        'cash' => $sum($payments, 'payment_date', 'amount', $pick),
        'carriage' => $sum($carriage, 'issue_date', 'net', $pick),
        'rev_costed' => $rc, 'cost' => $c, 'rev_uncosted' => $ru, 'extras' => $ex,
        'profit' => $rc - $c, 'margin' => $rc > 0 ? ($rc - $c) / $rc * 100 : null,
        'cost_made' => $mk, 'cost_bought' => $bought, 'remake_cost' => $rmCost, 'remake_rev' => $rmRev,
        'pay_days' => $days ? array_sum($days) / count($days) : null, 'late_days' => $late ? array_sum($late) / count($late) : null,
        'paid_n' => count($days),
    ];
};
$K = $kpi($inP);
$P = $kpi($inPP);

// Monthly trend
$trend = [];
foreach ($months as $mk => $lbl) {
    $t = $kpi(static fn (?string $d) => $mOf($d) === $mk);
    $trend[$mk] = $t;
}

// By account (period): orders value from the orders, margin from the costed lines.
$byAcct = [];
foreach ($orders as $o) {
    if (!$inP($o['date'])) continue;
    $a = &$byAcct[$o['account_id']];
    $a ??= ['name' => $o['account'], 'orders' => 0, 'value' => 0.0, 'blinds' => 0, 'rc' => 0.0, 'cost' => 0.0];
    $a['orders']++; $a['value'] += $o['value']; $a['blinds'] += $o['blinds'];
    unset($a);
}
foreach ($lines as $l) {
    if (!$inP($l['created_at']) || $l['cost'] === null || $l['is_remake']) continue;
    $a = &$byAcct[$l['account_id']];
    $a ??= ['name' => $l['account_name'], 'orders' => 0, 'value' => 0.0, 'blinds' => 0, 'rc' => 0.0, 'cost' => 0.0];
    $a['rc'] += $l['rev']; $a['cost'] += $l['cost'];
    unset($a);
}
uasort($byAcct, static fn ($a, $b) => $b['value'] <=> $a['value']);

// By product + made vs bought-in + bought-in by supplier (period)
$byProd = []; $mix = ['own' => ['rev' => 0.0, 'rc' => 0.0, 'cost' => 0.0, 'blinds' => 0], 'supplier' => ['rev' => 0.0, 'rc' => 0.0, 'cost' => 0.0, 'blinds' => 0]];
$bySupplier = [];
foreach ($lines as $l) {
    if (!$inP($l['created_at']) || $l['is_remake']) continue;
    $p = &$byProd[$l['product']];
    $p ??= ['rev' => 0.0, 'rc' => 0.0, 'cost' => 0.0, 'blinds' => 0, 'costed' => 0, 'source' => $l['source']];
    $p['rev'] += $l['rev']; $p['blinds'] += $l['qty'];
    $x = &$mix[$l['source'] === 'supplier' ? 'supplier' : 'own'];
    $x['rev'] += $l['rev']; $x['blinds'] += $l['qty'];
    if ($l['cost'] !== null) {
        $p['rc'] += $l['rev']; $p['cost'] += $l['cost']; $p['costed'] += $l['qty'];
        $x['rc'] += $l['rev']; $x['cost'] += $l['cost'];
        if ($l['source'] === 'supplier') {
            $s = $l['supplier'] !== '' ? $l['supplier'] : 'Supplier not named';
            $bySupplier[$s] = ($bySupplier[$s] ?? 0) + $l['cost'];
        }
    }
    unset($p, $x);
}
uasort($byProd, static fn ($a, $b) => $b['rev'] <=> $a['rev']);
arsort($bySupplier);

// Debtors ageing (now)
$age = ['Not due yet' => 0.0, '1–30 days late' => 0.0, '31–60 days late' => 0.0, 'Over 60 days late' => 0.0];
$debtorsBy = [];
foreach ($openInv as $r) {
    $due = !empty($r['due_date']) ? (string) $r['due_date'] : (!empty($r['issue_date']) ? date('Y-m-d', strtotime($r['issue_date'] . ' +30 days')) : $today);
    $late = (int) floor((strtotime($today) - strtotime($due)) / 86400);
    $amt = (float) $r['outstanding'];
    $age[$late <= 0 ? 'Not due yet' : ($late <= 30 ? '1–30 days late' : ($late <= 60 ? '31–60 days late' : 'Over 60 days late'))] += $amt;
    $d = &$debtorsBy[(int) $r['account_client_id']];
    $d ??= ['name' => (string) ($r['company_name'] ?? 'Account'), 'owed' => 0.0, 'late' => 0.0, 'n' => 0];
    $d['owed'] += $amt; $d['n']++; if ($late > 0) $d['late'] += $amt;
    unset($d);
}
uasort($debtorsBy, static fn ($a, $b) => $b['owed'] <=> $a['owed']);
$owed = array_sum($age);
$overdue = $owed - $age['Not due yet'];

// ---------------------------------------------------------------------------
// Formatting + chart helpers (inline SVG, themed by the CSS variables below)
// ---------------------------------------------------------------------------
function fd_gbp(?float $v, int $dp = 0): string
{
    if ($v === null) return '—';
    return ($v < -0.004 ? '−' : '') . '£' . number_format(abs($v), $dp);
}
function fd_pct(?float $v, int $dp = 1): string { return $v === null ? '—' : number_format($v, $dp) . '%'; }

/** "▲ 12% vs …" — green when up is good. Percentage figures compare in points. */
function fd_delta(?float $cur, ?float $prev, bool $points = false, bool $upIsGood = true): string
{
    if ($cur === null || $prev === null) return '<span class="fd-d">no comparison</span>';
    if ($points) {
        $diff = $cur - $prev;
        $txt = number_format(abs($diff), 1) . ' pts';
    } else {
        if (abs($prev) < 0.005) return abs($cur) < 0.005 ? '<span class="fd-d">same</span>' : '<span class="fd-d">new — nothing before</span>';
        $diff = ($cur - $prev) / abs($prev) * 100;
        $txt = number_format(abs($diff), 0) . '%';
    }
    if (abs($diff) < 0.05) return '<span class="fd-d">same</span>';
    $good = ($diff > 0) === $upIsGood;
    return '<span class="fd-d ' . ($good ? 'up' : 'down') . '">' . ($diff > 0 ? '▲' : '▼') . ' ' . $txt . '</span>';
}

/** A tidy axis step for a span split into ~4. */
function fd_nice(float $span): float
{
    if ($span <= 0) return 1;
    $raw = $span / 4; $mag = 10 ** floor(log10($raw)); $n = $raw / $mag;
    return ($n <= 1 ? 1 : ($n <= 2 ? 2 : ($n <= 2.5 ? 2.5 : ($n <= 5 ? 5 : 10)))) * $mag;
}
function fd_axis_label(float $v, bool $pct): string
{
    if ($pct) return round($v) . '%';
    $a = abs($v); $s = $v < 0 ? '−' : '';
    return $s . '£' . ($a >= 1000000 ? round($a / 1000000, 1) . 'm' : ($a >= 1000 ? round($a / 1000, $a >= 10000 ? 0 : 1) . 'k' : (string) round($a)));
}

/**
 * Vertical bars, grouped when there's more than one series.
 * $series = [[name, cssVar, values[]], …]; values align with $labels.
 */
function fd_bars(array $labels, array $series, bool $pct = false): string
{
    $W = 720; $H = 250; $L = 56; $R = 8; $T = 14; $B = 30;
    $all = []; foreach ($series as $s) foreach ($s[2] as $v) if ($v !== null) $all[] = (float) $v;
    $max = $all ? max(0, max($all)) : 0; $min = $all ? min(0, min($all)) : 0;
    if ($max - $min < 0.01) $max = $min + 1;
    $step = fd_nice($max - $min);
    $top = ceil($max / $step) * $step; $bot = floor($min / $step) * $step;
    $y = static fn (float $v) => $T + ($top - $v) / ($top - $bot) * ($H - $T - $B);
    $n = max(1, count($labels)); $gw = ($W - $L - $R) / $n; $k = count($series);
    $bw = min(26, ($gw * 0.72 - 2 * ($k - 1)) / max(1, $k));
    $o = '<svg class="fd-chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img">';
    for ($v = $bot; $v <= $top + $step / 2; $v += $step) {
        $yy = round($y($v), 1);
        $o .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . $yy . '" y2="' . $yy . '" class="' . (abs($v) < 1e-9 ? 'fd-base' : 'fd-grid') . '"/>';
        $o .= '<text x="' . ($L - 6) . '" y="' . ($yy + 4) . '" class="fd-ax" text-anchor="end">' . e(fd_axis_label($v, $pct)) . '</text>';
    }
    $i = 0;
    foreach ($labels as $lbl) {
        $gx = $L + $i * $gw + ($gw - ($bw * $k + 2 * ($k - 1))) / 2;
        foreach ($series as $j => [$name, $var, $vals]) {
            $v = $vals[$i] ?? null;
            if ($v === null) continue;
            $x = $gx + $j * ($bw + 2); $y0 = $y(0); $y1 = $y((float) $v);
            $h = abs($y1 - $y0); $r = min(4, $bw / 2, $h);
            $tip = e($lbl . ' · ' . $name . ': ' . ($pct ? fd_pct((float) $v) : fd_gbp((float) $v)));
            if ($v >= 0) {
                $d = sprintf('M%.1f %.1f V%.1f Q%.1f %.1f %.1f %.1f H%.1f Q%.1f %.1f %.1f %.1f V%.1f Z',
                    $x, $y0, $y1 + $r, $x, $y1, $x + $r, $y1, $x + $bw - $r, $x + $bw, $y1, $x + $bw, $y1 + $r, $y0);
            } else {
                $d = sprintf('M%.1f %.1f V%.1f Q%.1f %.1f %.1f %.1f H%.1f Q%.1f %.1f %.1f %.1f V%.1f Z',
                    $x, $y0, $y1 - $r, $x, $y1, $x + $r, $y1, $x + $bw - $r, $x + $bw, $y1, $x + $bw, $y1 - $r, $y0);
            }
            $o .= '<path d="' . $d . '" style="fill:var(' . $var . ')" class="fd-mark"><title>' . $tip . '</title></path>';
        }
        $o .= '<text x="' . round($L + $i * $gw + $gw / 2, 1) . '" y="' . ($H - 10) . '" class="fd-ax" text-anchor="middle">' . e($lbl) . '</text>';
        $i++;
    }
    return $o . '</svg>';
}

/** One line (e.g. margin % by month). Nulls leave a gap. */
function fd_line(array $labels, array $vals, string $name, string $var, bool $pct = true): string
{
    $W = 720; $H = 200; $L = 56; $R = 12; $T = 14; $B = 30;
    $ok = array_values(array_filter($vals, static fn ($v) => $v !== null));
    $max = $ok ? max(0, max($ok)) : 1; $min = $ok ? min(0, min($ok)) : 0;
    if ($max - $min < 0.01) $max = $min + 1;
    $step = fd_nice($max - $min); $top = ceil($max / $step) * $step; $bot = floor($min / $step) * $step;
    $y = static fn (float $v) => $T + ($top - $v) / ($top - $bot) * ($H - $T - $B);
    $n = max(1, count($labels)); $gw = ($W - $L - $R) / $n;
    $o = '<svg class="fd-chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img">';
    for ($v = $bot; $v <= $top + $step / 2; $v += $step) {
        $yy = round($y($v), 1);
        $o .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . $yy . '" y2="' . $yy . '" class="' . (abs($v) < 1e-9 ? 'fd-base' : 'fd-grid') . '"/>';
        $o .= '<text x="' . ($L - 6) . '" y="' . ($yy + 4) . '" class="fd-ax" text-anchor="end">' . e(fd_axis_label($v, $pct)) . '</text>';
    }
    $pts = []; $path = ''; $i = 0; $pen = false;
    foreach ($labels as $lbl) {
        $x = round($L + $i * $gw + $gw / 2, 1);
        $v = $vals[$i] ?? null;
        if ($v !== null) {
            $yy = round($y((float) $v), 1);
            $path .= ($pen ? ' L' : ' M') . $x . ' ' . $yy; $pen = true;
            $pts[] = [$x, $yy, $lbl . ' · ' . $name . ': ' . ($pct ? fd_pct((float) $v) : fd_gbp((float) $v))];
        } else {
            $pen = false;
        }
        $o .= '<text x="' . $x . '" y="' . ($H - 10) . '" class="fd-ax" text-anchor="middle">' . e($lbl) . '</text>';
        $i++;
    }
    if ($path !== '') $o .= '<path d="' . trim($path) . '" fill="none" style="stroke:var(' . $var . ')" stroke-width="2" stroke-linejoin="round"/>';
    foreach ($pts as [$x, $yy, $tip]) {
        $o .= '<circle cx="' . $x . '" cy="' . $yy . '" r="4" style="fill:var(' . $var . ')" class="fd-dot"><title>' . e($tip) . '</title></circle>';
        $o .= '<circle cx="' . $x . '" cy="' . $yy . '" r="12" fill="transparent"><title>' . e($tip) . '</title></circle>';
    }
    return $o . '</svg>';
}

/**
 * Donut + legend. $items = [label => value]; more than 7 fold into "Other" so
 * the eight colours are never cycled.
 */
function fd_donut(array $items, bool $money = true): string
{
    $items = array_filter($items, static fn ($v) => $v > 0.004);
    arsort($items);
    if (count($items) > 8) {
        $top = array_slice($items, 0, 7, true);
        $top['Other'] = array_sum(array_slice($items, 7));
        $items = $top;
    }
    $total = array_sum($items);
    if ($total <= 0) return '<p class="fd-empty">Nothing in this period.</p>';
    $r = 60; $c = 2 * M_PI * $r; $off = 0.0; $i = 0;
    $svg = '<svg class="fd-donut" viewBox="0 0 160 160" role="img"><g transform="rotate(-90 80 80)">';
    $leg = '<ul class="fd-legend">';
    foreach ($items as $label => $v) {
        $var = '--s' . ($label === 'Other' ? 'o' : ($i + 1));
        $len = $v / $total * $c;
        $gap = count($items) > 1 ? min(2, $len / 2) : 0;
        $tip = e($label . ': ' . ($money ? fd_gbp($v) : number_format($v)) . ' (' . number_format($v / $total * 100, 1) . '%)');
        $svg .= '<circle cx="80" cy="80" r="' . $r . '" fill="none" stroke-width="26" style="stroke:var(' . $var . ')" class="fd-mark"'
              . ' stroke-dasharray="' . round(max(0.1, $len - $gap), 2) . ' ' . round($c, 2) . '" stroke-dashoffset="' . round(-$off, 2) . '"><title>' . $tip . '</title></circle>';
        $leg .= '<li><span class="fd-sw" style="background:var(' . $var . ')"></span><span class="fd-ll">' . e((string) $label) . '</span>'
              . '<span class="fd-lv">' . ($money ? fd_gbp($v) : number_format($v)) . ' · ' . number_format($v / $total * 100, 0) . '%</span></li>';
        $off += $len; $i++;
    }
    $svg .= '</g><text x="80" y="76" text-anchor="middle" class="fd-dt">' . ($money ? e(fd_axis_label($total, false)) : number_format($total)) . '</text>'
          . '<text x="80" y="94" text-anchor="middle" class="fd-ax">total</text></svg>';
    return '<div class="fd-donutwrap">' . $svg . $leg . '</ul></div>';
}

/** A legend row for multi-series charts. */
function fd_legend(array $series): string
{
    $o = '<ul class="fd-legend fd-legend-row">';
    foreach ($series as [$name, $var]) $o .= '<li><span class="fd-sw" style="background:var(' . $var . ')"></span>' . e($name) . '</li>';
    return $o . '</ul>';
}

$labels = array_values($months);
$tv = static fn (string $k) => array_values(array_map(static fn ($t) => $t[$k], $trend));
$periodLabel = date('j M Y', strtotime($from)) . ' – ' . date('j M Y', strtotime($to));
$prevLabel   = date('j M Y', strtotime($pFrom)) . ' – ' . date('j M Y', strtotime($pTo));
$numbersUrl  = '/factory/profit.php?' . http_build_query(['period' => 'custom', 'from' => $from, 'to' => min($to, $today), 'why' => 1]);

$activeNav = 'factory-dashboard';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard &middot; Factory Console &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
      /* Chart colours: the validated categorical order, its own steps in dark. */
      :root { --s1:#2a78d6; --s2:#eb6834; --s3:#1baf7a; --s4:#eda100; --s5:#e87ba4; --s6:#008300; --s7:#4a3aa7; --s8:#e34948; --so:#9ca3af;
              --fd-good:#15803d; --fd-bad:#b91c1c; }
      [data-theme="dark"] { --s1:#3987e5; --s2:#d95926; --s3:#199e70; --s4:#c98500; --s5:#d55181; --s6:#008300; --s7:#9085e9; --s8:#e66767; --so:#6b7280;
              --fd-good:#4ade80; --fd-bad:#f87171; }
      .fd-sec { margin:0 0 1.6rem; }
      .fd-sec > h2 { font-size:1.1rem; margin:0 0 .25rem; color:var(--text-primary); }
      .fd-sec > p.fd-hint { margin:0 0 .8rem; color:var(--text-faint); font-size:.85rem; max-width:62rem; }
      .fd-periods { display:flex; flex-wrap:wrap; gap:.35rem; align-items:center; margin:0 0 .5rem; }
      .fd-periods a, .fd-periods button { text-decoration:none; font:inherit; font-size:.85rem; font-weight:600; padding:.32rem .75rem; border-radius:8px;
              background:var(--bg-subtle-2); color:var(--text-body); border:0; cursor:pointer; }
      .fd-periods a.on, .fd-periods.on button { background:var(--bg-sidebar); color:#fff; }
      .fd-periods input[type=date] { font:inherit; font-size:.85rem; padding:.28rem .45rem; border:1px solid var(--border); border-radius:8px; background:var(--bg-input); color:inherit; }
      .fd-periods label { font-size:.8rem; color:var(--text-faint); font-weight:600; }
      .fd-when { color:var(--text-muted); font-size:.85rem; margin:.2rem 0 1.2rem; }
      .fd-note { background:var(--bg-subtle); border:1px solid var(--border); padding:.5rem .9rem; border-radius:10px; margin:0 0 1rem; font-size:.85rem; }
      .fd-warn { background:#fef3c7; border:1px solid #fde68a; color:#92600a; padding:.6rem .9rem; border-radius:10px; margin:0 0 1rem; font-size:.875rem; }
      [data-theme="dark"] .fd-warn { background:#3b2f0b; border-color:#6b5310; color:#fcd34d; }
      .fd-kpis { display:grid; gap:.8rem; grid-template-columns:repeat(auto-fill, minmax(190px, 1fr)); }
      .fd-kpi { background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:.85rem 1rem; min-width:0; }
      .fd-kpi .k { font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-faint); font-weight:700; }
      .fd-kpi .v { font-size:1.6rem; font-weight:800; color:var(--text-primary); line-height:1.15; margin-top:.2rem; font-variant-numeric:tabular-nums; }
      .fd-kpi .s { font-size:.78rem; color:var(--text-faint); margin-top:.2rem; }
      .fd-kpi.hero { border-top:4px solid var(--s1); }
      .fd-kpi.profit .v { color:var(--fd-good); }
      .fd-d { font-size:.75rem; font-weight:700; color:var(--text-faint); white-space:nowrap; }
      .fd-d.up { color:var(--fd-good); } .fd-d.down { color:var(--fd-bad); }
      .fd-row { display:grid; gap:1rem; grid-template-columns:repeat(auto-fit, minmax(340px, 1fr)); }
      .fd-panel { background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:1rem 1.1rem; min-width:0; }
      .fd-panel h3 { font-size:.95rem; margin:0 0 .15rem; color:var(--text-primary); }
      .fd-panel .fd-sub { font-size:.8rem; color:var(--text-faint); margin:0 0 .6rem; }
      .fd-chart { width:100%; height:auto; display:block; }
      .fd-chart .fd-grid { stroke:var(--border); stroke-width:1; }
      .fd-chart .fd-base { stroke:var(--border-strong); stroke-width:1; }
      .fd-ax { fill:var(--text-faint); font-size:11px; font-family:inherit; }
      .fd-dt { fill:var(--text-primary); font-size:17px; font-weight:800; font-family:inherit; }
      .fd-mark { transition:opacity .12s; } .fd-mark:hover { opacity:.75; }
      .fd-donutwrap { display:flex; gap:1rem; align-items:center; flex-wrap:wrap; }
      .fd-donut { width:170px; height:170px; flex:0 0 auto; }
      .fd-legend { list-style:none; margin:0; padding:0; font-size:.83rem; flex:1 1 200px; min-width:0; }
      .fd-legend li { display:flex; align-items:center; gap:.45rem; padding:.18rem 0; color:var(--text-body); }
      .fd-legend .fd-ll { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
      .fd-legend .fd-lv { color:var(--text-muted); font-variant-numeric:tabular-nums; white-space:nowrap; }
      .fd-legend-row { display:flex; flex-wrap:wrap; gap:.2rem 1rem; margin:0 0 .3rem; }
      .fd-sw { width:10px; height:10px; border-radius:3px; flex:0 0 auto; display:inline-block; }
      .fd-hbar { display:grid; grid-template-columns:minmax(0, 11rem) 1fr auto; gap:.3rem .6rem; align-items:center; font-size:.85rem; }
      .fd-hbar .lbl { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--text-body); }
      .fd-hbar .track { height:12px; background:var(--bg-subtle-2); border-radius:4px; overflow:hidden; }
      .fd-hbar .fill { height:100%; border-radius:0 4px 4px 0; }
      .fd-hbar .val { font-variant-numeric:tabular-nums; color:var(--text-muted); white-space:nowrap; }
      table.fd-t { width:100%; border-collapse:collapse; font-size:.86rem; }
      .fd-t th { text-align:left; font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-faint); font-weight:700; padding:.4rem .5rem; border-bottom:1px solid var(--border); }
      .fd-t td { padding:.4rem .5rem; border-bottom:1px solid var(--border-faint); font-variant-numeric:tabular-nums; color:var(--text-body); }
      .fd-t .r { text-align:right; }
      .fd-t tr:last-child td { border-bottom:0; }
      .fd-t tfoot td { font-weight:700; border-top:1px solid var(--border); }
      .fd-muted { color:var(--text-faint); }
      .fd-empty { color:var(--text-faint); font-size:.9rem; }
      details.fd-tbl { margin-top:.5rem; font-size:.85rem; } details.fd-tbl summary { cursor:pointer; color:var(--link); font-weight:600; }
      .fd-defs { font-size:.85rem; color:var(--text-muted); line-height:1.55; }
      .fd-defs dt { font-weight:700; color:var(--text-body); margin-top:.5rem; }
      .fd-defs dd { margin:0; }
      .fd-strip { display:flex; height:22px; border-radius:6px; overflow:hidden; gap:2px; margin:.4rem 0 .6rem; background:var(--bg-card); }
      .fd-strip span { display:block; height:100%; }
    </style>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/../_partials/sidebar.php'; ?>
<main class="app-main">
  <div class="page-header">
    <div>
      <h1 class="page-title">Dashboard</h1>
      <p class="page-subtitle">The money side of the business &mdash; your eyes only. Everything is <b>ex VAT</b> unless it says otherwise.</p>
    </div>
    <a href="/factory/pipeline.php" class="btn">Pipeline</a>
  </div>

  <?php
    // Order checks — counted LIVE, the same way the Order checks page does. It
    // used to show the 7am email's count (cron_messages.php), so once those
    // orders were put right the line still said "3 orders didn't add up" while
    // Order checks said everything was fine (John, 2026-10-13). A line only
    // when something disagrees right now, so a quiet day shows nothing.
    $ocNow = 0;
    if ((int) $factory === (int) factory_client_id()) {
        require_once __DIR__ . '/../_partials/order_checks.php';
        try { $ocNow = count(oc_findings($pdo, (int) $factory)); } catch (Throwable $e) { $ocNow = 0; }
    }
    if ($ocNow > 0):
  ?>
    <div class="alert alert-error" role="status" style="margin-bottom:.8rem">
      ⚠ <?= $ocNow ?> order<?= $ocNow === 1 ? '' : 's' ?> <?= $ocNow === 1 ? 'doesn&rsquo;t' : 'don&rsquo;t' ?> add up right now &mdash;
      <a href="/factory/checks.php">see Order checks</a>.
    </div>
  <?php endif; ?>

  <div class="fd-periods">
    <?php foreach ($periods as $k => $lbl): ?>
      <a class="<?= $period === $k ? 'on' : '' ?>" href="?period=<?= e($k) ?>"><?= e($lbl) ?></a>
    <?php endforeach; ?>
  </div>
  <form class="fd-periods <?= $period === 'custom' ? 'on' : '' ?>" method="get" action="">
    <input type="hidden" name="period" value="custom">
    <label for="fdFrom">From</label><input type="date" id="fdFrom" name="from" value="<?= e($period === 'custom' ? $from : '') ?>" required>
    <label for="fdTo">to</label><input type="date" id="fdTo" name="to" value="<?= e($period === 'custom' ? $to : '') ?>" required>
    <button type="submit">Show</button>
  </form>
  <p class="fd-when"><b><?= e($periodLabel) ?></b> &middot; arrows compare with <?= e($cmp) ?> (<?= e($prevLabel) ?>)</p>
  <?php if ($rangeNote !== null): ?><div class="fd-note"><?= e($rangeNote) ?></div><?php endif; ?>

  <!-- ===== Headline ===== -->
  <section class="fd-sec">
    <h2>Headline</h2>
    <div class="fd-kpis">
      <div class="fd-kpi hero"><div class="k">Sales invoiced</div><div class="v"><?= fd_gbp($K['net_sales']) ?></div>
        <div class="s"><?= fd_delta($K['net_sales'], $P['net_sales']) ?> &middot; <?= fd_gbp($K['invoiced']) ?> invoiced<?= $K['credited'] > 0 ? ' − ' . fd_gbp($K['credited']) . ' credited' : '' ?></div></div>
      <div class="fd-kpi hero"><div class="k">Orders taken</div><div class="v"><?= fd_gbp($K['value']) ?></div>
        <div class="s"><?= fd_delta($K['value'], $P['value']) ?> &middot; <?= (int) $K['orders'] ?> order<?= $K['orders'] === 1 ? '' : 's' ?></div></div>
      <div class="fd-kpi profit"><div class="k">Gross profit</div><div class="v"><?= fd_gbp($K['profit']) ?></div>
        <div class="s"><?= fd_delta($K['profit'], $P['profit']) ?> &middot; on <?= fd_gbp($K['rev_costed']) ?> costed</div></div>
      <div class="fd-kpi"><div class="k">Gross margin</div><div class="v"><?= fd_pct($K['margin']) ?></div>
        <div class="s"><?= fd_delta($K['margin'], $P['margin'], true) ?> &middot; was <?= fd_pct($P['margin']) ?></div></div>
      <div class="fd-kpi"><div class="k">Cash received</div><div class="v"><?= fd_gbp($K['cash']) ?></div>
        <div class="s"><?= fd_delta($K['cash'], $P['cash']) ?> &middot; inc VAT, into the bank</div></div>
      <div class="fd-kpi"><div class="k">Average order</div><div class="v"><?= fd_gbp($K['aov'], 2) ?></div>
        <div class="s"><?= fd_delta($K['aov'], $P['aov']) ?> &middot; was <?= fd_gbp($P['aov'], 2) ?></div></div>
      <div class="fd-kpi"><div class="k">Blinds ordered</div><div class="v"><?= number_format($K['blinds']) ?></div>
        <div class="s"><?= fd_delta((float) $K['blinds'], (float) $P['blinds']) ?> &middot; <?= $K['blinds'] > 0 && $K['rev_costed'] > 0 ? fd_gbp(($K['rev_costed'] + $K['rev_uncosted']) / $K['blinds'], 2) . ' a blind' : '' ?></div></div>
      <div class="fd-kpi"><div class="k">Owed to you now</div><div class="v"><?= fd_gbp($owed) ?></div>
        <div class="s"><?= $overdue > 0.004 ? '<b style="color:var(--fd-bad)">' . fd_gbp($overdue) . ' overdue</b>' : 'nothing overdue' ?> &middot; inc VAT</div></div>
      <div class="fd-kpi"><div class="k">Days to get paid</div><div class="v"><?= $K['pay_days'] === null ? '—' : number_format($K['pay_days'], 0) ?></div>
        <div class="s"><?= fd_delta($K['pay_days'], $P['pay_days'], false, false) ?> &middot; <?= $K['paid_n'] ?> invoice<?= $K['paid_n'] === 1 ? '' : 's' ?> paid<?= $K['late_days'] !== null ? ', avg ' . number_format($K['late_days'], 0) . ' days late' : '' ?></div></div>
    </div>
    <?php if ($K['rev_uncosted'] > 0.004): ?>
      <div class="fd-warn" style="margin-top:.9rem"><b><?= fd_gbp($K['rev_uncosted']) ?></b> of blinds this period are on products we can't cost yet, so they're left out of profit and margin rather than guessed. <a href="<?= e($numbersUrl) ?>">See which lines</a>.</div>
    <?php endif; ?>
  </section>

  <!-- ===== Work in progress ===== -->
  <section class="fd-sec">
    <h2>Work in progress &mdash; right now</h2>
    <p class="fd-hint">Orders placed but not finished with, valued at what they'll be invoiced at. Not tied to the period above.</p>
    <?php
      $wipMeta = [
          'new'               => ['Not started',        '--s4', '/factory/pipeline.php#col-new'],
          'in_production'     => ['Being made',         '--s1', '/factory/pipeline.php#col-in_production'],
          'awaiting_supplier' => ['Awaiting supplier',  '--s7', '/factory/pipeline.php#col-awaiting_supplier'],
          'ready'             => ['Ready to dispatch',  '--s3', '/factory/pipeline.php#col-ready'],
          'needs_invoicing'   => ['Dispatched, not invoiced', '--s2', '/factory/orders.php?stage=needs_invoicing'],
      ];
      $wipMaking = $wip['new'][1] + $wip['in_production'][1] + $wip['awaiting_supplier'][1] + $wip['ready'][1];
      $wipAll = $wipMaking + $wip['needs_invoicing'][1];
    ?>
    <div class="fd-kpis">
      <div class="fd-kpi hero"><div class="k">Work in progress</div><div class="v"><?= fd_gbp($wipMaking) ?></div>
        <div class="s">placed, not yet dispatched</div></div>
      <?php foreach ($wipMeta as $k => [$lbl, $var, $href]): ?>
        <a class="fd-kpi" href="<?= e($href) ?>" style="text-decoration:none;border-top:4px solid var(<?= $var ?>)"><div class="k"><?= e($lbl) ?></div><div class="v"><?= fd_gbp($wip[$k][1]) ?></div>
          <div class="s"><?= (int) $wip[$k][0] ?> order<?= $wip[$k][0] === 1 ? '' : 's' ?></div></a>
      <?php endforeach; ?>
    </div>
    <?php if ($wipAll > 0): ?>
      <div class="fd-strip" title="Work in progress by stage">
        <?php foreach ($wipMeta as $k => [$lbl, $var]): if ($wip[$k][1] <= 0) continue; ?>
          <span style="flex:<?= round($wip[$k][1], 2) ?>;background:var(<?= $var ?>)" title="<?= e($lbl . ': ' . fd_gbp($wip[$k][1])) ?>"></span>
        <?php endforeach; ?>
      </div>
      <?= fd_legend(array_map(static fn ($m) => [$m[0], $m[1]], array_filter($wipMeta, static fn ($k) => $wip[$k][1] > 0, ARRAY_FILTER_USE_KEY))) ?>
    <?php endif; ?>
    <?php if ($wipUnknown > 0): ?><p class="fd-hint"><?= $wipUnknown ?> order<?= $wipUnknown === 1 ? ' has' : 's have' ?> no value yet, so <?= $wipUnknown === 1 ? 'it isn\'t' : 'they aren\'t' ?> in the totals.</p><?php endif; ?>
  </section>

  <!-- ===== Trend ===== -->
  <section class="fd-sec">
    <h2>The last 12 months</h2>
    <p class="fd-hint">Month by month. Hover a bar for its figure.</p>
    <div class="fd-row">
      <div class="fd-panel">
        <h3>Orders taken, invoiced and cash in</h3>
        <?php $s3 = [['Orders taken', '--s1', $tv('value')], ['Sales invoiced', '--s2', $tv('net_sales')], ['Cash received (inc VAT)', '--s3', $tv('cash')]]; ?>
        <?= fd_legend($s3) ?>
        <?= fd_bars($labels, $s3) ?>
      </div>
      <div class="fd-panel">
        <h3>Gross profit</h3>
        <p class="fd-sub">On costed blinds, by the month the order came in.</p>
        <?= fd_bars($labels, [['Gross profit', '--s3', $tv('profit')]]) ?>
      </div>
    </div>
    <div class="fd-row" style="margin-top:1rem">
      <div class="fd-panel">
        <h3>Gross margin %</h3>
        <?= fd_line($labels, $tv('margin'), 'Gross margin', '--s1') ?>
      </div>
      <div class="fd-panel">
        <h3>Cost to make vs buy in</h3>
        <p class="fd-sub">What the costed blinds cost you, split by where they come from.</p>
        <?php $s2 = [['Made here', '--s1', $tv('cost_made')], ['Bought in', '--s7', $tv('cost_bought')]]; ?>
        <?= fd_legend($s2) ?>
        <?= fd_bars($labels, $s2) ?>
      </div>
    </div>
    <details class="fd-tbl"><summary>Show the 12 months as a table</summary>
      <div class="table-wrap"><table class="fd-t">
        <thead><tr><th>Month</th><th class="r">Orders</th><th class="r">Orders taken</th><th class="r">Avg order</th><th class="r">Invoiced</th><th class="r">Cash in</th><th class="r">Gross profit</th><th class="r">Margin</th><th class="r">Blinds</th></tr></thead>
        <tbody>
        <?php foreach ($trend as $mk => $t): ?>
          <tr><td><?= e($months[$mk]) ?></td><td class="r"><?= $t['orders'] ?></td><td class="r"><?= fd_gbp($t['value']) ?></td><td class="r"><?= fd_gbp($t['aov']) ?></td>
              <td class="r"><?= fd_gbp($t['net_sales']) ?></td><td class="r"><?= fd_gbp($t['cash']) ?></td><td class="r"><?= fd_gbp($t['profit']) ?></td><td class="r"><?= fd_pct($t['margin']) ?></td><td class="r"><?= number_format($t['blinds']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </details>
  </section>

  <!-- ===== Accounts ===== -->
  <section class="fd-sec">
    <h2>Who's buying</h2>
    <p class="fd-hint">Trade accounts by the value of orders taken in the period. Profit is on their costed blinds only.</p>
    <?php if (!$byAcct): ?>
      <p class="fd-empty">No orders in this period.</p>
    <?php else: ?>
    <div class="fd-row">
      <div class="fd-panel">
        <h3>Share of orders taken</h3>
        <?php $acctPie = []; foreach ($byAcct as $a) $acctPie[$a['name']] = ($acctPie[$a['name']] ?? 0) + $a['value']; ?>
        <?= fd_donut($acctPie) ?>
      </div>
      <div class="fd-panel">
        <h3>Top 10 accounts</h3>
        <div class="table-wrap"><table class="fd-t">
          <thead><tr><th>Account</th><th class="r">Orders</th><th class="r">Blinds</th><th class="r">Value</th><th class="r">Avg</th><th class="r">Profit</th><th class="r">Margin</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($byAcct, 0, 10, true) as $a): ?>
            <tr><td><?= e($a['name']) ?></td><td class="r"><?= $a['orders'] ?></td><td class="r"><?= $a['blinds'] ?></td><td class="r"><?= fd_gbp($a['value']) ?></td>
                <td class="r"><?= $a['orders'] ? fd_gbp($a['value'] / $a['orders']) : '—' ?></td>
                <td class="r"><?= $a['rc'] > 0 ? fd_gbp($a['rc'] - $a['cost']) : '<span class="fd-muted">—</span>' ?></td>
                <td class="r"><?= $a['rc'] > 0 ? fd_pct(($a['rc'] - $a['cost']) / $a['rc'] * 100, 0) : '<span class="fd-muted">—</span>' ?></td></tr>
          <?php endforeach; ?>
          </tbody>
          <?php if (count($byAcct) > 10): ?><tfoot><tr><td colspan="7" class="fd-muted" style="font-weight:400">+ <?= count($byAcct) - 10 ?> more account<?= count($byAcct) - 10 === 1 ? '' : 's' ?></td></tr></tfoot><?php endif; ?>
        </table></div>
      </div>
    </div>
    <?php endif; ?>
  </section>

  <!-- ===== Products ===== -->
  <section class="fd-sec">
    <h2>What's selling, and what it makes</h2>
    <p class="fd-hint">Blind revenue by product (the trade price of the blind, before options), its cost to make or buy in, and the margin on it. Remakes are left out here.</p>
    <?php if (!$byProd): ?>
      <p class="fd-empty">No blinds ordered in this period.</p>
    <?php else: ?>
    <div class="fd-row">
      <div class="fd-panel">
        <h3>Revenue by product</h3>
        <?= fd_donut(array_map(static fn ($p) => $p['rev'], $byProd)) ?>
      </div>
      <div class="fd-panel">
        <h3>Made here vs bought in</h3>
        <p class="fd-sub">Revenue split by where the blind comes from, and the margin on each.</p>
        <?= fd_donut(['Made here' => $mix['own']['rev'], 'Bought in' => $mix['supplier']['rev']]) ?>
        <table class="fd-t" style="margin-top:.6rem">
          <thead><tr><th></th><th class="r">Blinds</th><th class="r">Revenue</th><th class="r">Cost</th><th class="r">Profit</th><th class="r">Margin</th></tr></thead>
          <tbody>
          <?php foreach (['own' => 'Made here', 'supplier' => 'Bought in'] as $k => $lbl): $x = $mix[$k]; ?>
            <tr><td><?= e($lbl) ?></td><td class="r"><?= $x['blinds'] ?></td><td class="r"><?= fd_gbp($x['rev']) ?></td><td class="r"><?= $x['rc'] > 0 ? fd_gbp($x['cost']) : '—' ?></td>
                <td class="r"><?= $x['rc'] > 0 ? fd_gbp($x['rc'] - $x['cost']) : '—' ?></td><td class="r"><?= $x['rc'] > 0 ? fd_pct(($x['rc'] - $x['cost']) / $x['rc'] * 100, 0) : '—' ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="fd-panel" style="margin-top:1rem">
      <h3>Every product</h3>
      <div class="table-wrap"><table class="fd-t">
        <thead><tr><th>Product</th><th>Source</th><th class="r">Blinds</th><th class="r">Revenue</th><th class="r">Avg a blind</th><th class="r">Cost</th><th class="r">Profit</th><th class="r">Margin</th></tr></thead>
        <tbody>
        <?php foreach ($byProd as $name => $p): $part = $p['costed'] > 0 && $p['costed'] < $p['blinds']; ?>
          <tr><td><?= e((string) $name) ?><?= $part ? ' <span class="fd-muted">(' . $p['costed'] . '/' . $p['blinds'] . ' costed)</span>' : '' ?></td>
              <td><?= $p['source'] === 'supplier' ? 'Bought in' : 'Made here' ?></td>
              <td class="r"><?= $p['blinds'] ?></td><td class="r"><?= fd_gbp($p['rev']) ?></td><td class="r"><?= $p['blinds'] ? fd_gbp($p['rev'] / $p['blinds'], 2) : '—' ?></td>
              <td class="r"><?= $p['costed'] ? fd_gbp($p['cost']) : '<span class="fd-muted">no cost</span>' ?></td>
              <td class="r"><?= $p['costed'] ? fd_gbp($p['rc'] - $p['cost']) : '<span class="fd-muted">—</span>' ?></td>
              <td class="r"><?= $p['costed'] && $p['rc'] > 0 ? fd_pct(($p['rc'] - $p['cost']) / $p['rc'] * 100, 0) : '<span class="fd-muted">—</span>' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
    <?php if ($bySupplier): $maxS = max($bySupplier); ?>
    <div class="fd-panel" style="margin-top:1rem">
      <h3>Bought-in spend by supplier</h3>
      <p class="fd-sub">What the bought-in blinds ordered in the period cost at the supplier's list less your buying discount &mdash; an estimate, not their invoices.</p>
      <div class="fd-hbar">
        <?php foreach ($bySupplier as $s => $v): ?>
          <span class="lbl" title="<?= e($s) ?>"><?= e($s) ?></span><span class="track"><span class="fill" style="display:block;width:<?= round($v / $maxS * 100, 1) ?>%;background:var(--s7)"></span></span><span class="val"><?= fd_gbp($v) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </section>

  <!-- ===== Debtors ===== -->
  <section class="fd-sec">
    <h2>Who owes you &mdash; right now</h2>
    <p class="fd-hint">Open invoices less payments and credit notes, inc VAT, aged on the due date (30 days from the invoice if it has none). Money paid on account but not yet matched to an invoice isn't taken off here.</p>
    <?php if ($owed <= 0.004): ?>
      <p class="fd-empty">Nothing owed. Every invoice is paid.</p>
    <?php else: ?>
    <div class="fd-row">
      <div class="fd-panel">
        <h3>Ageing</h3>
        <?php $ageVars = ['--s3', '--s4', '--s2', '--s8']; $i = 0; $maxA = max($age) ?: 1; ?>
        <div class="fd-hbar">
          <?php foreach ($age as $lbl => $v): ?>
            <span class="lbl"><?= e($lbl) ?></span><span class="track"><span class="fill" style="display:block;width:<?= round($v / $maxA * 100, 1) ?>%;background:var(<?= $ageVars[$i++] ?>)"></span></span><span class="val"><?= fd_gbp($v) ?></span>
          <?php endforeach; ?>
        </div>
        <p class="fd-sub" style="margin-top:.7rem">Total <b><?= fd_gbp($owed) ?></b> across <?= count($openInv) ?> invoice<?= count($openInv) === 1 ? '' : 's' ?>. <a href="/master-admin/statement-run.php">Statements</a></p>
      </div>
      <div class="fd-panel">
        <h3>Biggest balances</h3>
        <div class="table-wrap"><table class="fd-t">
          <thead><tr><th>Account</th><th class="r">Invoices</th><th class="r">Owed</th><th class="r">Of which late</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($debtorsBy, 0, 10, true) as $d): ?>
            <tr><td><?= e($d['name']) ?></td><td class="r"><?= $d['n'] ?></td><td class="r"><?= fd_gbp($d['owed']) ?></td><td class="r"><?= $d['late'] > 0.004 ? '<b style="color:var(--fd-bad)">' . fd_gbp($d['late']) . '</b>' : '—' ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      </div>
    </div>
    <?php endif; ?>
  </section>

  <!-- ===== Remakes, delivery, quotes ===== -->
  <section class="fd-sec">
    <h2>Remakes, delivery and credits</h2>
    <p class="fd-hint">For the period above.</p>
    <div class="fd-kpis">
      <div class="fd-kpi"><div class="k">Remakes raised</div><div class="v"><?= $remakes['raised'] ?></div>
        <div class="s"><?= $remakes['approved'] ?> approved<?= $remakes['declined'] ? ', ' . $remakes['declined'] . ' declined' : '' ?><?= $K['orders'] > 0 ? ' &middot; ' . fd_pct($remakes['approved'] / $K['orders'] * 100) . ' of orders' : '' ?></div></div>
      <div class="fd-kpi"><div class="k">Cost of remakes</div><div class="v"><?= fd_gbp($K['remake_cost']) ?></div>
        <div class="s"><?= fd_delta($K['remake_cost'], $P['remake_cost'], false, false) ?> &middot; <?= fd_gbp($K['remake_rev']) ?> charged back</div></div>
      <div class="fd-kpi"><div class="k">Delivery charged</div><div class="v"><?= fd_gbp($K['carriage']) ?></div>
        <div class="s"><?= fd_delta($K['carriage'], $P['carriage']) ?> &middot; carriage on invoices</div></div>
      <div class="fd-kpi"><div class="k">Options revenue</div><div class="v"><?= fd_gbp($K['extras']) ?></div>
        <div class="s">add-ons on the blinds &middot; not costed</div></div>
      <div class="fd-kpi"><div class="k">Credit notes</div><div class="v"><?= fd_gbp($K['credited']) ?></div>
        <div class="s"><?= fd_delta($K['credited'], $P['credited'], false, false) ?></div></div>
    </div>
    <?php if ($remakes['reasons']): ?>
      <div class="fd-row" style="margin-top:1rem">
        <div class="fd-panel">
          <h3>Why remakes happened</h3>
          <?= fd_donut($remakes['reasons'], false) ?>
        </div>
        <?php if ($remakes['modes']): ?>
        <div class="fd-panel">
          <h3>Who paid for them</h3>
          <?= fd_donut(array_combine(array_map(static fn ($m) => ['free' => 'Free (on us)', 'charge' => 'Charged to the account', 'supplier' => 'Claimed from the supplier'][$m] ?? ucfirst((string) $m), array_keys($remakes['modes'])), array_values($remakes['modes'])) ?: [], false) ?>
        </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- ===== Definitions ===== -->
  <section class="fd-sec fd-panel">
    <h2 style="font-size:1rem;margin:0 0 .2rem">How these are worked out</h2>
    <dl class="fd-defs">
      <dt>Orders taken</dt><dd>Orders with our blinds on them, placed by a trade account (or our own one-off sales), dated by the day the order was created &mdash; there's no separate "placed on" date. Valued at what the invoice bills (the Invoices page's figure), ex VAT. Remake orders are left out.</dd>
      <dt>Average order</dt><dd>Orders taken ÷ number of orders.</dd>
      <dt>Sales invoiced</dt><dd>Net of every invoice raised in the period (not drafts or voided ones), less the net of credit notes issued in the period. One-off retail sales billed from the quote builder aren't factory invoices, so they aren't in here.</dd>
      <dt>Cash received</dt><dd>Payments recorded against trade accounts in the period, as they hit the bank &mdash; so inc VAT.</dd>
      <dt>Gross profit and margin</dt><dd>The same sums as The Numbers: for each blind, its trade price (after your buying discount, before the account's own markup) minus what it costs you &mdash; your cost grid for what you make, the supplier's list less your buying discount for what you buy in. Blinds we can't cost are left out of both sides, never guessed. Options aren't costed, so they aren't in it. Remakes count at what was charged against their full cost.</dd>
      <dt>Work in progress</dt><dd>Open orders now, by stage, at the value they'll be invoiced at. "Dispatched, not invoiced" is gone out the door with no invoice yet.</dd>
      <dt>Days to get paid</dt><dd>For invoices fully paid in the period: days from the invoice date to the last payment on it. "Late" is days past the due date.</dd>
      <dt>Net profit</dt><dd>Not shown &mdash; the system doesn't hold overheads (wages, rent, vans), so anything past gross margin would be a guess.</dd>
    </dl>
    <p class="fd-defs" style="margin-top:.6rem">Line-by-line costing and the lines we can't cost: <a href="<?= e($numbersUrl) ?>">The Numbers</a>.</p>
  </section>
</main>
</div>
<?php require_once __DIR__ . '/../_partials/factory_poll_banner.php'; fx_poll_banner(db(), 'pipeline', current_factory_id()); ?>
</body>
</html>
