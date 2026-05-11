<?php
declare(strict_types=1);

/**
 * Accounts → all payments for this tenant, plus a summary header.
 *
 * Filters via querystring:
 *   q       free-text against customer name / quote # / reference
 *   from    YYYY-MM-DD lower bound on received_at
 *   to      YYYY-MM-DD upper bound on received_at
 *   method  one of acct_methods() keys
 *
 * Tenant-scoped throughout.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';

requireLogin();

$user     = current_user();
$clientId = (int) $user['client_id'];

$q       = trim((string) ($_GET['q']      ?? ''));
$from    = trim((string) ($_GET['from']   ?? ''));
$to      = trim((string) ($_GET['to']     ?? ''));
$method  = trim((string) ($_GET['method'] ?? ''));

$where  = ['p.client_id = ?'];
$params = [$clientId];

if ($q !== '') {
    $where[]  = '(c.name LIKE ? OR qq.quote_number LIKE ? OR p.reference LIKE ?)';
    $like     = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($from !== '' && DateTimeImmutable::createFromFormat('!Y-m-d', $from)) {
    $where[]  = 'p.received_at >= ?';
    $params[] = $from;
}
if ($to !== '' && DateTimeImmutable::createFromFormat('!Y-m-d', $to)) {
    $where[]  = 'p.received_at <= ?';
    $params[] = $to;
}
if ($method !== '' && array_key_exists($method, acct_methods())) {
    $where[]  = 'p.method = ?';
    $params[] = $method;
}

$sql = 'SELECT p.id, p.amount, p.received_at, p.method, p.reference, p.notes,
               p.quote_id, p.customer_id,
               c.name AS customer_name,
               qq.quote_number
          FROM payments p
          LEFT JOIN customers c  ON c.id  = p.customer_id
          LEFT JOIN quotes    qq ON qq.id = p.quote_id
         WHERE ' . implode(' AND ', $where) . '
      ORDER BY p.received_at DESC, p.id DESC
         LIMIT 300';
$st = db()->prepare($sql);
$st->execute($params);
$payments = $st->fetchAll();

// Summary stats — all-time, this-month, outstanding receivables.
$pdo = db();
$summary = $pdo->prepare(
    'SELECT
       IFNULL(SUM(amount), 0)                 AS all_time,
       IFNULL(SUM(CASE WHEN received_at >= ? THEN amount ELSE 0 END), 0)
                                              AS this_month
       FROM payments WHERE client_id = ?'
);
$monthStart = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
$summary->execute([$monthStart, $clientId]);
$summaryRow = $summary->fetch() ?: ['all_time' => 0, 'this_month' => 0];

// Outstanding across all accepted-or-beyond quotes. Same formula as
// acct_outstanding_for_quote(), but folded into a single SQL so we
// don't have to walk every quote in PHP.
$outSt = db()->prepare(
    "SELECT
       IFNULL(SUM(
         q.total
         - IFNULL((SELECT SUM(amount) FROM payments WHERE quote_id = q.id), 0)
         - CASE WHEN q.deposit_paid_at IS NOT NULL
                THEN IFNULL(q.deposit_amount, 0)
                ELSE 0 END
       ), 0) AS outstanding
       FROM quotes q
      WHERE q.client_id = ?
        AND q.status IN ('accepted','ordered','invoiced','paid')"
);
$outSt->execute([$clientId]);
$outstandingTotal = (float) $outSt->fetchColumn();

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$activeNav = 'accounts';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accounts &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .summary-cards {
            display: grid; gap: 0.75rem;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            margin: 0 0 1.25rem;
        }
        .summary-card {
            background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
            padding: 0.875rem 1rem;
        }
        .summary-card .lbl {
            font-size: 0.75rem; color: #6b7280;
            text-transform: uppercase; letter-spacing: 0.05em;
            font-weight: 600;
        }
        .summary-card .val {
            font-size: 1.375rem; color: #111827; font-weight: 700;
            margin-top: 0.25rem;
        }
        .summary-card.outstanding .val { color: #b45309; }
        .summary-card.month       .val { color: #1f3b5b; }
        .summary-card.alltime     .val { color: #065f46; }

        .filter-bar {
            display: flex; gap: 0.5rem; flex-wrap: wrap;
            margin: 0 0 0.75rem;
        }
        .filter-bar input, .filter-bar select {
            padding: 0.4375rem 0.625rem;
            border: 1px solid #d1d5db; border-radius: 6px; font: inherit;
        }
        .filter-bar input[type="search"] { flex: 1 1 200px; }
        .filter-bar input[type="date"]   { flex: 0 0 9.5rem; }
        .filter-bar select               { flex: 0 0 8.5rem; }

        .method-pill {
            display: inline-block; padding: 0.0625rem 0.5rem;
            font-size: 0.6875rem; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.04em;
            border-radius: 999px;
            background: #e0e7ff; color: #3730a3;
        }
        .empty-state {
            padding: 2rem 1rem; text-align: center;
            color: #6b7280; font-size: 0.9375rem;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Accounts</h1>
                <p class="page-subtitle">Payments received against your orders.</p>
            </div>
        </div>

        <?php if ($flashMsg): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <div class="summary-cards">
            <div class="summary-card outstanding">
                <div class="lbl">Outstanding</div>
                <div class="val"><?= e(acct_fmt_money($outstandingTotal)) ?></div>
            </div>
            <div class="summary-card month">
                <div class="lbl">Received this month</div>
                <div class="val"><?= e(acct_fmt_money((float) $summaryRow['this_month'])) ?></div>
            </div>
            <div class="summary-card alltime">
                <div class="lbl">All-time received</div>
                <div class="val"><?= e(acct_fmt_money((float) $summaryRow['all_time'])) ?></div>
            </div>
        </div>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Payments</h2>
            </div>

            <form method="get" action="/accounts/index.php" class="filter-bar">
                <input type="search" name="q" value="<?= e($q) ?>"
                       placeholder="Customer, quote #, reference...">
                <input type="date" name="from" value="<?= e($from) ?>" title="From">
                <input type="date" name="to"   value="<?= e($to)   ?>" title="To">
                <select name="method">
                    <option value="">All methods</option>
                    <?php foreach (acct_methods() as $k => $lbl): ?>
                        <option value="<?= e($k) ?>" <?= $method === $k ? 'selected' : '' ?>>
                            <?= e($lbl) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-secondary">Filter</button>
                <?php if ($q || $from || $to || $method): ?>
                    <a href="/accounts/index.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </form>

            <?php if (!$payments): ?>
                <div class="empty-state">
                    <?php if ($q || $from || $to || $method): ?>
                        Nothing matches your filter.
                        <a href="/accounts/index.php">Clear filters</a>.
                    <?php else: ?>
                        No payments recorded yet. Open an order from the Orders
                        page and use the Payments section to log the first one.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Quote #</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th class="num">Amount</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td><?= e(date('j M Y', strtotime((string) $p['received_at']))) ?></td>
                                    <td><?= e((string) ($p['customer_name'] ?? '—')) ?></td>
                                    <td>
                                        <?php if (!empty($p['quote_number'])): ?>
                                            <a href="/quote-builder/edit.php?id=<?= (int) $p['quote_id'] ?>"
                                               style="color:#1f3b5b;font-weight:600;text-decoration:none">
                                                <?= e((string) $p['quote_number']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color:#9ca3af">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="method-pill">
                                            <?= e(acct_method_label((string) $p['method'])) ?>
                                        </span>
                                    </td>
                                    <td><?= e((string) ($p['reference'] ?? '')) ?></td>
                                    <td class="num">
                                        <?= e(acct_fmt_money((float) $p['amount'])) ?>
                                    </td>
                                    <td style="white-space:nowrap">
                                        <form method="post" action="/accounts/payment_delete.php"
                                              style="display:inline;margin:0"
                                              data-confirm="Delete this payment? (Won't undo the bank entry — adjust on your bank reconciliation if needed.)">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <input type="hidden" name="return_to" value="/accounts/index.php">
                                            <button type="submit" class="btn btn-sm btn-danger"
                                                    style="padding:0.1875rem 0.5rem;font-size:0.75rem">
                                                ×
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
