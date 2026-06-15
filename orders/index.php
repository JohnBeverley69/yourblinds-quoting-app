<?php
declare(strict_types=1);

/**
 * Order history — unified quotes + orders list view.
 *
 * Per Tyler's review (Quotes #3): the separate "New Quote" / "Quote
 * History" / "Orders" sidebar links were confusing. They all looked
 * at the same `quotes` table from slightly different angles. This
 * page is the merged result: every quote in the funnel, with status
 * chips that pivot the view between "all" / "draft" / "sent" /
 * "accepted+" etc.
 *
 * Capabilities (replaces both old pages):
 *   - All 8 lifecycle statuses surfaced as filter chips
 *   - Search by quote # / customer name / postcode
 *   - Bulk delete (from old quote-history) with the payments-attached
 *     guard intact
 *   - Order-specific deposit + outstanding columns (from old orders),
 *     shown when the row is in 'accepted+' (pre-order rows just dash)
 *   - "+ New quote" prominent button in the page header
 *
 * Backward compat: /quote-history/index.php redirects to this URL,
 * preserving status and q querystring.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/job_status_colours.php';
require_once __DIR__ . '/../_partials/payments_ledger.php';

requireLogin();

$user     = current_user();
$clientId = (int) $user['client_id'];
$isAdmin  = ($user['role'] ?? '') === 'admin';

// Traffic-light palette for this tenant — same colours as the calendar.
$statusPalette = job_client_palette($clientId);
$_perms   = current_user_permissions();

// Row filter: non-admin users without view-all see only quotes
// linked to appointments they're assigned to. Same shape as the
// old orders page — fitters can still see their installs.
$canViewAll     = $isAdmin || $_perms['can_view_all_customer_jobs'];
$restrictToMine = !$canViewAll;
$canCreateQuotes = $isAdmin || $_perms['can_create_quotes'];

// Paid Accounts add-on toggles the Outstanding column on/off.
$accountsEnabled = false;
try {
    $accSt = db()->prepare(
        'SELECT COALESCE(feature_accounts, 0)
           FROM client_settings WHERE client_id = ? LIMIT 1'
    );
    $accSt->execute([$clientId]);
    $accountsEnabled = ((int) $accSt->fetchColumn()) === 1;
} catch (Throwable $e) { /* feature off */ }

// Full lifecycle. The chip rendering hides rows whose count is zero
// so a tenant who's never had a declined quote doesn't see a
// 'declined (0)' chip clogging the bar.
$allStatuses   = ['draft', 'sent', 'accepted', 'declined', 'ordered', 'fitted', 'invoiced', 'paid'];
$orderStatuses = ['accepted', 'ordered', 'fitted', 'invoiced', 'paid'];
$quoteStatuses = ['draft', 'sent', 'declined'];

// This page doubles as Quote history (pre-acceptance) and Order history
// (accepted onward) — the two sidebar links pass ?scope=. Default is orders.
$scope = (($_GET['scope'] ?? '') === 'quotes') ? 'quotes' : 'orders';
$scopeStatuses = $scope === 'quotes' ? $quoteStatuses : $orderStatuses;

// Soft-archive (migrate_quote_archive.php). Default view hides archived rows;
// ?view=archived shows only archived ones (to review / restore).
$hasArchive = false;
try { db()->query('SELECT archived_at FROM quotes LIMIT 0'); $hasArchive = true; } catch (Throwable $e) {}
$view = ($hasArchive && ($_GET['view'] ?? '') === 'archived') ? 'archived' : 'active';

// Filter chips per scope. Quotes scope mirrors the pipeline: draft + sent are
// one "Quote" group (a draft is just a quote that's not sent yet). Each chip is
// key => [label, statuses[]].
$chipDefs = $scope === 'quotes'
    ? [
        'quote'    => ['Quote',    ['draft', 'sent']],
        'declined' => ['Declined', ['declined']],
      ]
    : [
        'accepted' => ['Accepted', ['accepted']],
        'ordered'  => ['Ordered',  ['ordered']],
        'fitted'   => ['Fitted',   ['fitted']],
        'invoiced' => ['Invoiced', ['invoiced']],
        'paid'     => ['Paid',     ['paid']],
      ];

$status = trim((string) ($_GET['status'] ?? ''));   // now a CHIP key (may be a group)
$q      = trim((string) ($_GET['q'] ?? ''));

$where  = ['q.client_id = ?'];
$params = [$clientId];

// Restrict to the current scope's statuses.
$scopePlace = implode(',', array_fill(0, count($scopeStatuses), '?'));
$where[]    = "q.status IN ($scopePlace)";
foreach ($scopeStatuses as $ss) { $params[] = $ss; }

// Active vs archived.
if ($hasArchive) {
    $where[] = $view === 'archived' ? 'q.archived_at IS NOT NULL' : 'q.archived_at IS NULL';
}

// A chip narrows further to its status group, within this scope.
if ($status !== '' && isset($chipDefs[$status])) {
    $chipStatuses = $chipDefs[$status][1];
    $cPlace = implode(',', array_fill(0, count($chipStatuses), '?'));
    $where[] = "q.status IN ($cPlace)";
    foreach ($chipStatuses as $cs) { $params[] = $cs; }
}
if ($q !== '') {
    $where[]  = '(q.quote_number LIKE ? OR q.end_customer_name LIKE ? OR q.end_customer_postcode LIKE ?)';
    $like     = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($restrictToMine) {
    $where[]  = 'q.id IN (SELECT quote_id FROM appointments WHERE client_user_id = ?)';
    $params[] = (int) $user['user_id'];
}

// Single SELECT covers both old views — pulls the order-side columns
// (deposit, payments) too. NULL-safe for draft rows that won't have
// accepted_at yet.
$sql = "SELECT q.id, q.quote_number, q.end_customer_name, q.end_customer_postcode,
               q.status, q.total, q.accepted_at, q.created_at, q.updated_at,
               q.deposit_amount, q.deposit_paid_at,
               IFNULL((SELECT SUM(amount) FROM payments WHERE quote_id = q.id), 0)
                 AS payments_total
          FROM quotes q
         WHERE " . implode(' AND ', $where) . "
         ORDER BY COALESCE(q.accepted_at, q.created_at) DESC, q.id DESC
         LIMIT 200";
try {
    $st = db()->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();
} catch (Throwable $e) {
    // payments table absent on very old schemas — re-run without it.
    $sql = "SELECT q.id, q.quote_number, q.end_customer_name, q.end_customer_postcode,
                   q.status, q.total, q.accepted_at, q.created_at, q.updated_at,
                   q.deposit_amount, q.deposit_paid_at,
                   0 AS payments_total
              FROM quotes q
             WHERE " . implode(' AND ', $where) . "
             ORDER BY COALESCE(q.accepted_at, q.created_at) DESC, q.id DESC
             LIMIT 200";
    $st = db()->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();
}

// Per-status counts for the chip bar. Mirrors the row filter so
// counts and the visible rows agree.
$countWhere   = ['client_id = ?'];
$countParams  = [$clientId];
if ($restrictToMine) {
    $countWhere[]   = 'id IN (SELECT quote_id FROM appointments WHERE client_user_id = ?)';
    $countParams[]  = (int) $user['user_id'];
}
// Chip counts reflect the current (active/archived) view.
if ($hasArchive) {
    $countWhere[] = $view === 'archived' ? 'archived_at IS NOT NULL' : 'archived_at IS NULL';
}
$countSt = db()->prepare(
    'SELECT status, COUNT(*) AS n
       FROM quotes
      WHERE ' . implode(' AND ', $countWhere) . '
   GROUP BY status'
);
$countSt->execute($countParams);
$counts     = [];
$grandTotal = 0;
foreach ($countSt->fetchAll() as $r) {
    $counts[$r['status']] = (int) $r['n'];
    $grandTotal += (int) $r['n'];
}
// Total within the current scope (for the "All" chip).
$scopeTotal = 0;
foreach ($scopeStatuses as $ss) { $scopeTotal += $counts[$ss] ?? 0; }

// Count of archived jobs in this scope (for the Archived toggle label).
$archivedCount = 0;
if ($hasArchive) {
    $azWhere  = ['client_id = ?', 'archived_at IS NOT NULL', "status IN ($scopePlace)"];
    $azParams = array_merge([$clientId], $scopeStatuses);
    if ($restrictToMine) {
        $azWhere[]  = 'id IN (SELECT quote_id FROM appointments WHERE client_user_id = ?)';
        $azParams[] = (int) $user['user_id'];
    }
    $azSt = db()->prepare('SELECT COUNT(*) FROM quotes WHERE ' . implode(' AND ', $azWhere));
    $azSt->execute($azParams);
    $archivedCount = (int) $azSt->fetchColumn();
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$money   = static fn ($n) => '£' . number_format((float) $n, 2);
$fmtDate = static function (?string $dt): string {
    if (!$dt) return '—';
    $ts = strtotime((string) $dt);
    return $ts ? date('j M Y', $ts) : '—';
};

// Deposit/Outstanding columns only make sense for orders (accepted onward),
// so they're shown in the orders scope and hidden in the quotes scope.
$isOrderView = $scope === 'orders';

$pageTitle = $scope === 'quotes' ? 'Quote history' : 'Order history';
$pageSub   = $scope === 'quotes'
    ? 'Quotes still in the pipeline — drafts, sent, and declined.'
    : 'Accepted onward — orders, invoices and paid jobs.';
$activeNav = $scope === 'quotes' ? 'quote-history' : 'order-history';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .filter-chips { display: flex; gap: 0.5rem; flex-wrap: wrap; margin: 0 0 0.75rem; }
        .filter-chips a {
            display: inline-block; padding: 0.25rem 0.625rem;
            font-size: 0.8125rem; border-radius: 999px; text-decoration: none;
            background: var(--bg-subtle-2); color: var(--text-muted); border: 1px solid transparent;
        }
        .filter-chips a.active { background: var(--brand); color: #fff; }
        .filter-chips a:hover  { border-color: var(--border-strong); }
        /* Status pills are coloured inline from the tenant's traffic-light
           palette (the same colours as the calendar) — a job reads the same
           colour everywhere. Editable per client in Settings. */
        .status-pill {
            display: inline-block; padding: 0.0625rem 0.5rem; font-size: 0.6875rem;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
            border-radius: 999px;
        }
        a.q-link { font-weight: 600; color: var(--text-primary); text-decoration: none; }
        a.q-link:hover { color: var(--link); text-decoration: underline; }
        .search-form { display: flex; gap: 0.5rem; margin-bottom: 0.75rem; }
        .search-form input {
            flex: 1; padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-strong); border-radius: 8px; font: inherit;
            background: var(--bg-input); color: var(--text-body);
        }
        .empty-state {
            padding: 2rem 1rem; text-align: center;
            color: var(--text-faint); font-size: 0.9375rem;
        }
        /* Pipeline shortcut — same row as the filter chips so it
           doesn't take extra vertical space. */
        .view-toggle {
            display: inline-flex; gap: 0.375rem;
            margin-left: auto;
        }
        .view-toggle a {
            font-size: 0.8125rem; padding: 0.25rem 0.625rem;
            background: var(--bg-card); border: 1px solid var(--border-strong); border-radius: 6px;
            color: var(--link); text-decoration: none;
        }
        .view-toggle a:hover { border-color: var(--link); }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title"><?= e($pageTitle) ?></h1>
                <p class="page-subtitle"><?= e($pageSub) ?></p>
                <div style="display:inline-flex;background:var(--bg-subtle-2);border-radius:8px;padding:0.125rem;margin-top:0.5rem">
                    <a href="/orders/index.php?scope=<?= e($scope) ?>"
                       style="padding:0.3125rem 0.875rem;border-radius:6px;text-decoration:none;font-weight:600;font-size:0.875rem;background:var(--bg-card);color:var(--text-primary);box-shadow:0 1px 2px rgba(0,0,0,0.06)">List</a>
                    <a href="/orders/pipeline.php"
                       style="padding:0.3125rem 0.875rem;border-radius:6px;text-decoration:none;font-weight:600;font-size:0.875rem;color:var(--text-faint)">Pipeline</a>
                </div>
            </div>
            <?php if ($canCreateQuotes): ?>
                <a href="/quote-builder/new.php" class="btn btn-primary">
                    + New quote
                </a>
            <?php endif; ?>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <section class="section">
            <div class="filter-chips">
                <a href="/orders/index.php?scope=<?= e($scope) ?>" class="<?= $status === '' ? 'active' : '' ?>">
                    All (<?= $scopeTotal ?>)
                </a>
                <?php foreach ($chipDefs as $chipKey => [$chipLabel, $chipStatuses]):
                    $chipCount = 0;
                    foreach ($chipStatuses as $cs) { $chipCount += $counts[$cs] ?? 0; }
                    if ($chipCount === 0) continue;
                ?>
                    <a href="/orders/index.php?scope=<?= e($scope) ?>&status=<?= e($chipKey) ?>"
                       class="<?= $status === $chipKey ? 'active' : '' ?>">
                        <?= e($chipLabel) ?> (<?= $chipCount ?>)
                    </a>
                <?php endforeach; ?>
                <?php if ($hasArchive): ?>
                    <?php if ($view === 'archived'): ?>
                        <a href="/orders/index.php?scope=<?= e($scope) ?>" style="margin-left:auto" class="active">&larr; Back to active</a>
                    <?php elseif ($archivedCount > 0): ?>
                        <a href="/orders/index.php?scope=<?= e($scope) ?>&view=archived" style="margin-left:auto">🗄 Archived (<?= $archivedCount ?>)</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <form method="get" action="/orders/index.php" class="search-form">
                <input type="hidden" name="scope" value="<?= e($scope) ?>">
                <?php if ($status !== ''): ?>
                    <input type="hidden" name="status" value="<?= e($status) ?>">
                <?php endif; ?>
                <input type="search" name="q" value="<?= e($q) ?>"
                       placeholder="Search by quote #, customer name, or postcode...">
                <button type="submit" class="btn btn-secondary">Search</button>
                <?php if ($q !== ''): ?>
                    <a href="/orders/index.php?scope=<?= e($scope) ?><?= $status !== '' ? '&status=' . e($status) : '' ?>"
                       class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </form>

            <?php if (!$rows): ?>
                <div class="empty-state">
                    <?php if ($q !== '' || $status !== ''): ?>
                        Nothing matches your filter. <a href="/orders/index.php?scope=<?= e($scope) ?>">Clear filters</a>.
                    <?php elseif ($canCreateQuotes): ?>
                        No quotes yet.
                        <a href="/quote-builder/new.php">Start a new quote &rarr;</a>
                    <?php else: ?>
                        Quotes you're assigned to fit will appear here.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <form method="post" action="/quote-history/bulk_delete.php"
                      id="bulk-delete-form"
                      data-confirm-submit="Delete the selected quotes? This is permanent — all blinds, items and appointments go too.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return_status" value="<?= e($status) ?>">
                    <input type="hidden" name="return_q"      value="<?= e($q) ?>">
                    <input type="hidden" name="return_scope"  value="<?= e($scope) ?>">
                    <input type="hidden" name="return_view"   value="<?= e($view) ?>">

                    <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;margin:0 0 0.625rem;">
                        <?php if ($hasArchive && $view === 'archived'): ?>
                            <button type="submit" class="btn btn-secondary" id="bulk-archive-btn"
                                    formaction="/orders/archive.php" name="do" value="unarchive"
                                    style="padding:0.3125rem 0.875rem;font-size:0.875rem" disabled>
                                Restore selected
                            </button>
                        <?php elseif ($hasArchive): ?>
                            <button type="submit" class="btn btn-secondary" id="bulk-archive-btn"
                                    formaction="/orders/archive.php" name="do" value="archive"
                                    style="padding:0.3125rem 0.875rem;font-size:0.875rem" disabled>
                                🗄 Archive selected
                            </button>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-danger"
                                id="bulk-delete-btn"
                                style="padding:0.3125rem 0.875rem;font-size:0.875rem"
                                disabled>
                            Delete selected
                        </button>
                        <span id="bulk-count" style="color:var(--text-faint);font-size:0.8125rem">
                            (none selected)
                        </span>
                    </div>

                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:1.75rem;text-align:center">
                                        <input type="checkbox" id="bulk-all"
                                               aria-label="Select all visible quotes">
                                    </th>
                                    <th>Quote #</th>
                                    <th>Customer</th>
                                    <th>Postcode</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th class="num">Total</th>
                                    <?php if ($isOrderView): ?>
                                        <th>Deposit</th>
                                    <?php endif; ?>
                                    <?php if ($isOrderView && $accountsEnabled): ?>
                                        <th class="num">Outstanding</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r):
                                    $isOrderRow = in_array((string) $r['status'], $orderStatuses, true);
                                    $dep    = $r['deposit_amount'];
                                    $depAt  = $r['deposit_paid_at'];
                                    // 0 once the deposit is its own payment row (already in payments_total).
                                    $depCounted = deposit_extra_for($depAt, $dep);
                                    $outstanding = round(
                                        (float) $r['total']
                                        - (float) ($r['payments_total'] ?? 0)
                                        - $depCounted, 2
                                    );
                                ?>
                                    <tr>
                                        <td style="text-align:center">
                                            <input type="checkbox"
                                                   class="bulk-row"
                                                   name="quote_ids[]"
                                                   value="<?= (int) $r['id'] ?>"
                                                   aria-label="Select quote <?= e((string) $r['quote_number']) ?>">
                                        </td>
                                        <td>
                                            <a href="/quote-builder/edit.php?id=<?= (int) $r['id'] ?>" class="q-link">
                                                <?= e((string) $r['quote_number']) ?>
                                            </a>
                                            <?php if ($isOrderRow && ($isAdmin || !empty($_perms['can_create_orders']))): ?>
                                                <div style="margin-top:0.1875rem">
                                                    <a href="/quote-builder/order_suppliers.php?id=<?= (int) $r['id'] ?>"
                                                       title="Send this order to its suppliers"
                                                       style="font-size:0.6875rem;color:var(--link);text-decoration:none;white-space:nowrap">
                                                        &#128230; Send to suppliers
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e((string) ($r['end_customer_name'] ?? '')) ?></td>
                                        <td><?= e((string) ($r['end_customer_postcode'] ?? '')) ?></td>
                                        <td>
                                            <?php
                                                $rawStatus    = (string) $r['status'];
                                                // In the quotes scope, draft + sent both read as "Quote"
                                                // (a draft is just an un-sent quote) — mirrors the pipeline.
                                                $isQuoteStage = $scope === 'quotes' && in_array($rawStatus, ['draft', 'sent'], true);
                                                $pillLabel    = $isQuoteStage ? 'Quote' : $rawStatus;
                                                $pillBg       = job_status_colour($isQuoteStage ? 'sent' : $rawStatus, $statusPalette);
                                            ?>
                                            <span class="status-pill status-<?= e($rawStatus) ?>"
                                                  style="background:<?= e($pillBg) ?>;color:<?= e(job_status_text_colour($pillBg)) ?>">
                                                <?= e($pillLabel) ?>
                                            </span>
                                            <?php if ($rawStatus === 'draft'): ?>
                                                <span title="This quote hasn't been sent to the customer yet"
                                                      style="display:inline-block;margin-left:0.25rem;font-size:0.625rem;font-weight:700;text-transform:uppercase;letter-spacing:0.03em;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:999px;padding:0.0625rem 0.4375rem">Not sent</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:0.8125rem;color:var(--text-faint);white-space:nowrap">
                                            <?= e(date('j M Y', strtotime((string) $r['created_at']))) ?>
                                        </td>
                                        <td class="num"><?= e($money($r['total'])) ?></td>
                                        <?php if ($isOrderView): ?>
                                        <td>
                                            <?php if (!$isOrderRow || $dep === null): ?>
                                                <span style="color:var(--text-faint);font-size:0.8125rem">—</span>
                                            <?php elseif ($depAt): ?>
                                                <span style="color:#065f46;font-weight:600;font-size:0.8125rem">
                                                    &check; <?= e($money($dep)) ?> paid
                                                </span>
                                            <?php else: ?>
                                                <span style="color:#92400e;font-weight:600;font-size:0.8125rem">
                                                    <?= e($money($dep)) ?> due
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                        <?php if ($isOrderView && $accountsEnabled): ?>
                                            <td class="num">
                                                <?php if (!$isOrderRow): ?>
                                                    <span style="color:var(--text-faint);font-size:0.8125rem">—</span>
                                                <?php elseif ($outstanding > 0.0049): ?>
                                                    <a href="/accounts/index.php?prefill_quote=<?= (int) $r['id'] ?>"
                                                       title="Click to take a payment against this order"
                                                       style="color:#92400e;font-weight:700;text-decoration:none;
                                                              border-bottom:1px dashed #92400e">
                                                        <?= e($money($outstanding)) ?>
                                                    </a>
                                                <?php elseif ($outstanding < -0.0049): ?>
                                                    <span style="color:#1e40af;font-weight:700"
                                                          title="Overpaid">
                                                        +<?= e($money(-$outstanding)) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color:#065f46;font-weight:700">&check; paid</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <script>
                (function () {
                    var form    = document.getElementById('bulk-delete-form');
                    var allBox  = document.getElementById('bulk-all');
                    var btn     = document.getElementById('bulk-delete-btn');
                    var archBtn = document.getElementById('bulk-archive-btn');
                    var counter = document.getElementById('bulk-count');
                    if (!form || !allBox || !btn || !counter) return;

                    function rows() {
                        return form.querySelectorAll('input.bulk-row');
                    }
                    function refresh() {
                        var checked = form.querySelectorAll('input.bulk-row:checked').length;
                        var total   = rows().length;
                        btn.disabled = checked === 0;
                        if (archBtn) archBtn.disabled = checked === 0;
                        counter.textContent = checked === 0
                            ? '(none selected)'
                            : '(' + checked + ' of ' + total + ' selected)';
                        allBox.checked       = checked > 0 && checked === total;
                        allBox.indeterminate = checked > 0 && checked < total;
                    }
                    allBox.addEventListener('change', function () {
                        rows().forEach(function (cb) { cb.checked = allBox.checked; });
                        refresh();
                    });
                    form.addEventListener('change', function (e) {
                        if (e.target && e.target.classList.contains('bulk-row')) refresh();
                    });
                    form.addEventListener('submit', function (e) {
                        // Archive/Restore don't need the destructive delete confirm.
                        if (e.submitter && e.submitter.id === 'bulk-archive-btn') return;
                        var msg = form.getAttribute('data-confirm-submit');
                        if (msg && !window.confirm(msg)) {
                            e.preventDefault();
                        }
                    });
                    refresh();
                })();
                </script>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
