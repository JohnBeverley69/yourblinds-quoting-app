<?php
declare(strict_types=1);

/**
 * Pipeline — Kanban view of every quote/order in the funnel.
 *
 * Columns left → right map to the quote-status enum so you can see
 * "where is everything?" at a glance, plus column totals so you
 * know the £ value sitting in each stage. Cards click through to
 * /quote-builder/edit.php for the underlying quote.
 *
 *   Draft → Sent → Accepted → Ordered → Invoiced → Paid
 *
 * V1 is read-only — no drag-drop. Status transitions have real
 * side-effects (sent_at timestamps, accepted_at, payment recording,
 * etc.) and we don't want a mistaken drag to fire those. Drag-drop
 * is a V2 with explicit confirms per transition.
 *
 * Permission scope: matches the rest of the orders module. A user
 * with can_view_all_customer_jobs (or role=admin) sees everything;
 * otherwise only quotes they're the assigned salesperson on, or
 * have a fitting assignment to.
 *
 * Scaling: caps per column at 50 cards (newest by updated_at). A
 * "more" footer tells the operator a column has been trimmed —
 * they'd refine via the date filter for the rest.
 *
 * Phase-2-rebuild safety: the quotes table may be missing on very
 * old schemas. Page falls back to a "Pipeline is unavailable —
 * Phase 3 hasn't shipped yet" message instead of 500ing.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$user        = current_user();
$clientId    = (int) $user['client_id'];
$myUserId    = (int) $user['user_id'];
$isAdmin     = ($user['role'] ?? '') === 'admin';

// Permission scope — same shape as /orders/index.php and the
// calendar. Restricted users only see jobs they're on.
$perms = function_exists('current_user_permissions')
    ? current_user_permissions()
    : ['can_view_all_customer_jobs' => false, 'can_view_costs' => false];
$canViewAll = $isAdmin || !empty($perms['can_view_all_customer_jobs']);
$canViewCosts = $isAdmin || !empty($perms['can_view_costs']);

$pdo = db();

// Schema check — quotes table may be absent on Phase 2 builds.
$hasQuotes = false;
try {
    $hasQuotes = (bool) $pdo->query("SHOW TABLES LIKE 'quotes'")->fetchColumn();
} catch (Throwable $e) { /* ignore */ }

// ── Filters ──────────────────────────────────────────────────────────
$q       = trim((string) ($_GET['q']    ?? ''));
$days    = (int) ($_GET['days'] ?? 90);
if ($days <= 0 || $days > 730) $days = 90;
$showAll = !empty($_GET['all']);   // override → ignore the date window

// "Mine only" toggle, only honoured when the user CAN see everyone.
// For restricted users it's forced on anyway via the WHERE below.
$mineOnly = !empty($_GET['mine']);

$columns = [
    'draft'    => ['Draft',     '#fef3c7', '#92400e'],
    'sent'     => ['Sent',      '#fed7aa', '#9a3412'],
    'accepted' => ['Accepted',  '#bbf7d0', '#166534'],
    'ordered'  => ['Ordered',   '#bfdbfe', '#1e3a8a'],
    // 'fitted' = materials installed, awaiting invoice. Critical
    // stage that was missing — without it, "ordered" jobs that
    // had been installed but not yet invoiced were
    // indistinguishable from ones still awaiting materials.
    'fitted'   => ['Fitted',    '#a7f3d0', '#065f46'],
    'invoiced' => ['Invoiced',  '#ddd6fe', '#5b21b6'],
    'paid'     => ['Paid',      '#d1fae5', '#065f46'],
];
// 'declined' is a terminal state — surfaced via a filter chip, not
// a column. Keeping it out of the kanban stops dead leads from
// cluttering the view.

$byStatus = array_fill_keys(array_keys($columns), []);
$totals   = array_fill_keys(array_keys($columns), 0.0);
$counts   = array_fill_keys(array_keys($columns), 0);
$truncated = array_fill_keys(array_keys($columns), false);

$CARD_CAP_PER_COL = 50;

if ($hasQuotes) {
    // Build WHERE clauses + params. Order matters for prepared params.
    $where  = ['q.client_id = ?'];
    $params = [$clientId];

    if (!$showAll) {
        $where[]  = '(q.updated_at >= ? OR q.created_at >= ?)';
        $cutoff   = (new DateTimeImmutable('-' . $days . ' days'))->format('Y-m-d 00:00:00');
        $params[] = $cutoff;
        $params[] = $cutoff;
    }

    if ($q !== '') {
        $like     = '%' . $q . '%';
        $where[]  = '(q.quote_number LIKE ? OR q.end_customer_name LIKE ? OR q.end_customer_postcode LIKE ?)';
        $params[] = $like; $params[] = $like; $params[] = $like;
    }

    // Permission filter. Restricted users only see quotes where
    // EITHER they're the recorded salesperson OR they're assigned
    // to a fitting on the quote. The EXISTS sub-handles the latter
    // since salesperson_id isn't always set on older rows.
    if (!$canViewAll || $mineOnly) {
        $where[] = '(q.salesperson_id = ? OR EXISTS (
                       SELECT 1 FROM appointments a
                        WHERE a.quote_id = q.id AND a.client_user_id = ?
                    ))';
        $params[] = $myUserId; $params[] = $myUserId;
    }

    // Pull the lot. We sort by updated_at so the most-recently-touched
    // quotes rise to the top of each column.
    $sql = "SELECT q.id, q.quote_number, q.end_customer_name, q.end_customer_postcode,
                   q.status, q.total, q.deposit_amount, q.deposit_paid_at,
                   q.created_at, q.updated_at, q.accepted_at,
                   IFNULL((SELECT SUM(amount) FROM payments
                            WHERE quote_id = q.id), 0) AS paid_total
              FROM quotes q
             WHERE " . implode(' AND ', $where) . "
          ORDER BY q.updated_at DESC, q.id DESC";

    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        foreach ($st->fetchAll() as $row) {
            $status = (string) ($row['status'] ?? '');
            if (!isset($byStatus[$status])) continue;   // skip 'declined' etc.

            $counts[$status]++;
            $totals[$status] += (float) ($row['total'] ?? 0);

            if (count($byStatus[$status]) < $CARD_CAP_PER_COL) {
                $byStatus[$status][] = $row;
            } else {
                $truncated[$status] = true;
            }
        }
    } catch (Throwable $e) {
        // Failed query — log and degrade gracefully. The UI shows
        // empty columns; the operator can refresh / refine filters.
        error_log('orders/pipeline.php query failed: ' . $e->getMessage());
    }
}

$grandValue = array_sum($totals);
$grandCount = array_sum($counts);

$activeNav = 'pipeline';
$dashTag   = $isAdmin ? 'Admin Console' : 'Trade Portal';

// Helper — humanise an "X days ago" age from a timestamp.
$ageOf = static function (?string $ts): string {
    if (!$ts) return '';
    $diff = time() - strtotime($ts);
    if ($diff < 60)       return 'just now';
    if ($diff < 3600)     return floor($diff / 60) . 'm ago';
    if ($diff < 86400)    return floor($diff / 3600) . 'h ago';
    if ($diff < 86400*7)  return floor($diff / 86400) . 'd ago';
    if ($diff < 86400*30) return floor($diff / 86400 / 7) . 'w ago';
    return date('j M', strtotime($ts));
};
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pipeline &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        /* Pipeline = horizontal-scrolling Kanban. On desktop the
           columns fit; on mobile you swipe sideways. Each column is
           independently vertically scrollable. */
        .pl-filters {
            display: flex; flex-wrap: wrap; gap: 0.5rem 0.75rem;
            align-items: center; margin-bottom: 0.875rem;
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 10px; padding: 0.625rem 0.875rem;
        }
        .pl-filters input[type="search"] {
            padding: 0.375rem 0.625rem; border: 1px solid var(--border-strong);
            border-radius: 6px; font: inherit; font-size: 0.875rem;
            flex: 0 1 16rem;
            background: var(--bg-input); color: var(--text-body);
        }
        .pl-filters select, .pl-filters .pl-chip {
            padding: 0.3125rem 0.625rem; border: 1px solid var(--border-strong);
            border-radius: 6px; font-size: 0.8125rem; background: var(--bg-card);
            text-decoration: none; color: var(--link);
        }
        .pl-filters .pl-chip.is-active {
            background: var(--brand); color: #fff; border-color: var(--brand);
        }
        .pl-filters label { font-size: 0.8125rem; color: var(--text-faint); }
        .pl-filters .pl-summary {
            margin-left: auto; font-size: 0.875rem; color: var(--text-secondary);
        }
        .pl-board {
            display: grid;
            grid-auto-flow: column;
            grid-auto-columns: minmax(16rem, 1fr);
            gap: 0.625rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }
        .pl-col {
            background: var(--bg-subtle); border: 1px solid var(--border);
            border-radius: 10px;
            display: flex; flex-direction: column;
            min-height: 24rem; max-height: calc(100vh - 16rem);
        }
        .pl-col-head {
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid var(--border);
            position: sticky; top: 0; background: var(--bg-subtle);
            border-radius: 10px 10px 0 0;
        }
        .pl-col-head .pl-col-name {
            display: inline-block; padding: 0.0625rem 0.5rem;
            border-radius: 999px; font-size: 0.6875rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .pl-col-head .pl-col-meta {
            display: flex; align-items: baseline; gap: 0.5rem;
            margin-top: 0.25rem; font-size: 0.875rem;
        }
        .pl-col-head .pl-col-count { font-weight: 700; color: var(--text-primary); }
        .pl-col-head .pl-col-value { color: var(--text-faint); font-size: 0.8125rem; }
        .pl-col-body {
            flex: 1 1 auto; overflow-y: auto;
            padding: 0.5rem;
            display: flex; flex-direction: column; gap: 0.4375rem;
        }
        .pl-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 8px; padding: 0.5rem 0.625rem;
            text-decoration: none; color: inherit;
            display: block;
            font-size: 0.8125rem; line-height: 1.4;
            transition: border-color 100ms, box-shadow 100ms;
        }
        .pl-card:hover {
            border-color: var(--link);
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .pl-card .pl-card-name {
            font-weight: 600; color: var(--text-primary);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .pl-card .pl-card-num {
            color: var(--text-faint); font-size: 0.75rem;
            font-family: ui-monospace, Menlo, Consolas, monospace;
        }
        .pl-card .pl-card-row {
            display: flex; justify-content: space-between; gap: 0.5rem;
            margin-top: 0.1875rem;
        }
        .pl-card .pl-card-total {
            font-weight: 700; color: #16a34a;
        }
        .pl-card .pl-card-age { color: var(--text-faint); font-size: 0.6875rem; }
        .pl-card .pl-card-outstanding {
            color: #ef4444; font-size: 0.6875rem; font-weight: 600;
        }
        .pl-card .pl-card-place {
            color: var(--text-faint); font-size: 0.6875rem;
        }
        /* Payment chip — surfaced on every card, regardless of column.
           A paid-up-front quote sitting in 'accepted' was previously
           indistinguishable from one with no money in. The chip
           floats top-right so it's the first thing the eye lands on. */
        .pl-card { position: relative; }
        .pl-card-paid-chip {
            position: absolute; top: 0.4375rem; right: 0.4375rem;
            display: inline-block;
            padding: 0.0625rem 0.375rem;
            border-radius: 999px;
            font-size: 0.625rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.04em;
            line-height: 1.4;
        }
        .pl-card-paid-chip.is-full {
            background: #d1fae5; color: #065f46;
        }
        .pl-card-paid-chip.is-part {
            background: #fef3c7; color: #92400e;
        }
        /* Reserve room on the right of the name row so the chip
           doesn't sit on top of long customer names. */
        .pl-card.has-paid-chip .pl-card-name { padding-right: 4.5rem; }
        .pl-col-empty {
            color: var(--text-faint); font-style: italic; font-size: 0.8125rem;
            text-align: center; padding: 1rem 0.5rem;
        }
        .pl-col-truncated {
            font-size: 0.6875rem; color: var(--text-faint);
            text-align: center; padding: 0.375rem 0;
            border-top: 1px dashed var(--border);
            background: var(--bg-subtle-2);
            border-radius: 0 0 10px 10px;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Pipeline</h1>
                <p class="page-subtitle">
                    Where every job is in the funnel.
                    <a href="/orders/index.php">List view &rarr;</a>
                </p>
            </div>
        </div>

        <?php if (!$hasQuotes): ?>
            <div class="alert alert-error" role="alert">
                The pipeline isn't available yet — the <code>quotes</code> table
                is missing on this database. (Phase 3 schema rebuild hasn't
                shipped here.)
            </div>
        <?php else: ?>

            <!-- Filter bar -->
            <form method="get" action="/orders/pipeline.php" class="pl-filters">
                <input type="search" name="q" value="<?= e($q) ?>"
                       placeholder="Search customer / quote # / postcode…">
                <label>
                    Window:
                    <select name="days" onchange="this.form.submit()">
                        <?php foreach ([30, 60, 90, 180, 365] as $d): ?>
                            <option value="<?= $d ?>" <?= $days === $d ? 'selected' : '' ?>>
                                Last <?= $d ?> days
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if ($showAll): ?>
                    <a href="/orders/pipeline.php<?= $q !== '' ? '?q=' . urlencode($q) : '' ?>"
                       class="pl-chip is-active">All time &times;</a>
                <?php else: ?>
                    <a href="?<?= http_build_query(array_filter(['q' => $q, 'days' => $days, 'mine' => $mineOnly ? 1 : null, 'all' => 1])) ?>"
                       class="pl-chip">All time</a>
                <?php endif; ?>

                <?php if ($canViewAll): ?>
                    <?php if ($mineOnly): ?>
                        <a href="?<?= http_build_query(array_filter(['q' => $q, 'days' => $days, 'all' => $showAll ? 1 : null])) ?>"
                           class="pl-chip is-active">Mine only &times;</a>
                    <?php else: ?>
                        <a href="?<?= http_build_query(array_filter(['q' => $q, 'days' => $days, 'mine' => 1, 'all' => $showAll ? 1 : null])) ?>"
                           class="pl-chip">Mine only</a>
                    <?php endif; ?>
                <?php endif; ?>

                <button type="submit" class="btn btn-sm btn-secondary"
                        style="font-size:0.8125rem">Apply</button>

                <span class="pl-summary">
                    <strong><?= (int) $grandCount ?></strong>
                    job<?= $grandCount === 1 ? '' : 's' ?>
                    <?php if ($canViewCosts): ?>
                        &middot;
                        <strong>&pound;<?= number_format($grandValue, 0) ?></strong>
                        in pipeline
                    <?php endif; ?>
                </span>
            </form>

            <!-- The board -->
            <div class="pl-board">
                <?php foreach ($columns as $statusKey => $meta):
                    [$label, $bg, $fg] = $meta;
                    $cards = $byStatus[$statusKey];
                ?>
                    <div class="pl-col">
                        <div class="pl-col-head">
                            <span class="pl-col-name"
                                  style="background:<?= $bg ?>;color:<?= $fg ?>">
                                <?= e($label) ?>
                            </span>
                            <div class="pl-col-meta">
                                <span class="pl-col-count"><?= (int) $counts[$statusKey] ?></span>
                                <?php if ($canViewCosts): ?>
                                    <span class="pl-col-value">
                                        &pound;<?= number_format($totals[$statusKey], 0) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="pl-col-body">
                            <?php if (!$cards): ?>
                                <div class="pl-col-empty">No jobs</div>
                            <?php else: ?>
                                <?php foreach ($cards as $c):
                                    $total       = (float) ($c['total']       ?? 0);
                                    $paid        = (float) ($c['paid_total']  ?? 0);
                                    $outstanding = max(0, $total - $paid);
                                    $age         = $ageOf((string) ($c['updated_at'] ?? $c['created_at'] ?? ''));

                                    // Payment chip — independent of column so an
                                    // upfront-paid job in 'accepted' is just as
                                    // obvious as one in 'paid'. £0.005 fudge for
                                    // floating-point round-off on penny totals.
                                    $paidChip = '';
                                    if ($total > 0 && $paid > 0) {
                                        if ($paid >= $total - 0.005) {
                                            $paidChip = 'full';
                                        } else {
                                            $paidChip = 'part';
                                        }
                                    }
                                ?>
                                    <a class="pl-card<?= $paidChip ? ' has-paid-chip' : '' ?>"
                                       href="/quote-builder/edit.php?id=<?= (int) $c['id'] ?>">
                                        <?php if ($paidChip === 'full'): ?>
                                            <span class="pl-card-paid-chip is-full"
                                                  title="Paid in full">Paid</span>
                                        <?php elseif ($paidChip === 'part' && $canViewCosts): ?>
                                            <span class="pl-card-paid-chip is-part"
                                                  title="&pound;<?= number_format($paid, 2) ?> received of &pound;<?= number_format($total, 2) ?>">
                                                Part paid
                                            </span>
                                        <?php endif; ?>
                                        <div class="pl-card-name">
                                            <?= e((string) ($c['end_customer_name'] ?? 'No name')) ?>
                                        </div>
                                        <div class="pl-card-num">
                                            <?= e((string) ($c['quote_number'] ?? '#' . $c['id'])) ?>
                                            <?php if (!empty($c['end_customer_postcode'])): ?>
                                                <span class="pl-card-place">
                                                    &middot; <?= e((string) $c['end_customer_postcode']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="pl-card-row">
                                            <?php if ($canViewCosts): ?>
                                                <span class="pl-card-total">
                                                    &pound;<?= number_format($total, 2) ?>
                                                </span>
                                            <?php else: ?>
                                                <span></span>
                                            <?php endif; ?>
                                            <span class="pl-card-age"
                                                  title="Last touched: <?= e((string) ($c['updated_at'] ?? '')) ?>">
                                                <?= e($age) ?>
                                            </span>
                                        </div>
                                        <?php
                                            // For 'invoiced' status: surface outstanding balance.
                                            // Catches "we sent the invoice but never got paid" jobs.
                                            // Hidden when the paid chip is already there — no
                                            // need to say both "Paid" and "£0 outstanding".
                                            if ($statusKey === 'invoiced' && $outstanding > 0.005 && $canViewCosts):
                                        ?>
                                            <div class="pl-card-outstanding">
                                                &pound;<?= number_format($outstanding, 2) ?> outstanding
                                            </div>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($truncated[$statusKey]): ?>
                            <div class="pl-col-truncated">
                                Showing <?= $CARD_CAP_PER_COL ?> of <?= (int) $counts[$statusKey] ?>
                                &middot; refine the window or search
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </main>
</div>
</body>
</html>
