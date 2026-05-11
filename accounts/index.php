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

// Quotes available to attach a new payment to — anything accepted or
// beyond, with their per-quote outstanding pre-computed so the picker
// can offer a sensible default amount when one is chosen.
$pickSt = db()->prepare(
    "SELECT q.id, q.quote_number, q.end_customer_name, q.total,
            q.deposit_amount, q.deposit_paid_at,
            IFNULL((SELECT SUM(amount) FROM payments WHERE quote_id = q.id), 0)
              AS payments_total
       FROM quotes q
      WHERE q.client_id = ?
        AND q.status IN ('accepted','ordered','invoiced','paid')
   ORDER BY COALESCE(q.accepted_at, q.created_at) DESC
      LIMIT 200"
);
$pickSt->execute([$clientId]);
$payableQuotes = $pickSt->fetchAll();

// ?prefill_quote=N — clicked through from the Orders page's
// Outstanding column. Pre-select that quote in the new-payment
// dropdown, pre-fill the amount with its outstanding balance,
// and auto-open the panel so the user lands directly on the form.
$prefillQuoteId    = (int) ($_GET['prefill_quote'] ?? 0);
$prefillAmount     = '';
$prefillFound      = false;
if ($prefillQuoteId > 0) {
    foreach ($payableQuotes as $pq) {
        if ((int) $pq['id'] !== $prefillQuoteId) continue;
        $depCounted = $pq['deposit_paid_at']
            ? (float) ($pq['deposit_amount'] ?? 0) : 0.0;
        $out = round(
            (float) $pq['total']
            - (float) $pq['payments_total']
            - $depCounted, 2
        );
        if ($out > 0.0049) {
            $prefillAmount = number_format($out, 2, '.', '');
            $prefillFound  = true;
        }
        break;
    }
}

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

        .filter-fieldset {
            border: 1px dashed #d1d5db; border-radius: 10px;
            padding: 0.5rem 0.875rem 0.625rem;
            margin: 0 0 1rem; background: #fafafa;
        }
        .filter-fieldset legend {
            padding: 0 0.4375rem; font-size: 0.75rem;
            color: #6b7280; text-transform: uppercase;
            letter-spacing: 0.05em; font-weight: 600;
        }
        .filter-bar {
            display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: end;
        }
        .filter-bar input, .filter-bar select {
            padding: 0.4375rem 0.625rem;
            border: 1px solid #d1d5db; border-radius: 6px; font: inherit;
            background: #fff;
        }
        .filter-bar input[type="search"] { flex: 1 1 200px; }
        .filter-bar select               { flex: 0 0 9rem; }
        .filter-bar .filter-date {
            display: flex; flex-direction: column; gap: 0.125rem;
            font-size: 0.75rem; color: #6b7280;
        }
        .filter-bar .filter-date input { width: 9rem; }

        /* Record-new-payment panel */
        .np-panel {
            background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
            padding: 0 0.875rem; margin: 0 0 1rem;
        }
        .np-panel summary {
            list-style: none; cursor: pointer;
            padding: 0.75rem 0; font-weight: 600; color: #1f3b5b;
            font-size: 0.9375rem;
        }
        .np-panel summary::-webkit-details-marker { display: none; }
        .np-panel[open] summary {
            border-bottom: 1px solid #e5e7eb; margin-bottom: 0.75rem;
        }
        .np-panel summary::before {
            content: '▸ '; color: #9ca3af; margin-right: 0.25rem;
        }
        .np-panel[open] summary::before { content: '▾ '; }
        .np-form { padding-bottom: 0.875rem; }
        .np-grid {
            display: flex; flex-wrap: wrap; gap: 0.5rem 0.75rem;
            align-items: end;
        }
        .np-field { display: flex; flex-direction: column; gap: 0.1875rem; }
        .np-field label {
            font-size: 0.75rem; color: #6b7280; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.04em;
        }
        .np-field input, .np-field select {
            padding: 0.4375rem 0.625rem;
            border: 1px solid #d1d5db; border-radius: 6px;
            font: inherit; background: #fff;
        }
        .np-actions {
            display: flex; gap: 0.5rem; margin-top: 0.75rem;
        }

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
            <button type="button"
                    onclick="document.getElementById('new-payment-panel').open = true;
                             document.getElementById('np-amount').focus();"
                    class="btn btn-primary">
                + Record payment
            </button>
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

        <!-- Record-new-payment panel. <details> + open when the user
             clicks the header button above. Stays open if there was a
             validation error so the typed values aren't lost, OR if
             we got here via ?prefill_quote= from the Orders page. -->
        <details id="new-payment-panel" class="np-panel"
                 <?= ($flashErr || $prefillFound) ? 'open' : '' ?>>
            <summary>+ Record payment</summary>
            <form method="post" action="/accounts/payment_save.php" class="np-form">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="/accounts/index.php">

                <div class="np-grid">
                    <div class="np-field" style="flex:2 1 14rem">
                        <label for="np-quote">Order (optional)</label>
                        <select id="np-quote" name="quote_id" data-outstanding-map>
                            <option value=""
                                    <?= !$prefillFound ? 'selected' : '' ?>>
                                — Standalone payment (no order linked) —
                            </option>
                            <?php foreach ($payableQuotes as $pq):
                                $depCounted = $pq['deposit_paid_at']
                                    ? (float) ($pq['deposit_amount'] ?? 0) : 0.0;
                                $out = round(
                                    (float) $pq['total']
                                    - (float) $pq['payments_total']
                                    - $depCounted, 2
                                );
                                if ($out <= 0.0049) continue;   // already paid
                                $sel = ($prefillFound && (int) $pq['id'] === $prefillQuoteId);
                            ?>
                                <option value="<?= (int) $pq['id'] ?>"
                                        data-outstanding="<?= e(number_format($out, 2, '.', '')) ?>"
                                        <?= $sel ? 'selected' : '' ?>>
                                    <?= e((string) $pq['quote_number']) ?>
                                    — <?= e((string) ($pq['end_customer_name'] ?? '?')) ?>
                                    (<?= e(acct_fmt_money($out)) ?> outstanding)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="np-field" style="flex:0 0 7rem">
                        <label for="np-amount">Amount £</label>
                        <input id="np-amount" name="amount"
                               type="number" step="0.01" required
                               value="<?= e($prefillAmount) ?>"
                               placeholder="0.00">
                    </div>

                    <div class="np-field" style="flex:0 0 9rem">
                        <label for="np-date">Received on</label>
                        <input id="np-date" name="received_at" type="date" required
                               value="<?= e(date('Y-m-d')) ?>">
                    </div>

                    <div class="np-field" style="flex:0 0 9rem">
                        <label for="np-method">Method</label>
                        <select id="np-method" name="method">
                            <?php foreach (acct_methods() as $k => $lbl): ?>
                                <option value="<?= e($k) ?>"
                                        <?= $k === 'bank_transfer' ? 'selected' : '' ?>>
                                    <?= e($lbl) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="np-field" style="flex:1 1 12rem">
                        <label for="np-reference">Reference (optional)</label>
                        <input id="np-reference" name="reference" type="text"
                               maxlength="200"
                               placeholder="e.g. cheque #, Stripe id...">
                    </div>
                </div>

                <div class="np-actions">
                    <button type="submit" class="btn btn-primary">Save payment</button>
                    <button type="button" class="btn btn-secondary"
                            onclick="document.getElementById('new-payment-panel').open = false;">
                        Cancel
                    </button>
                </div>
            </form>
        </details>
        <script>
            // When a quote is picked, pre-fill amount with the quote's
            // outstanding balance — the typical case is "customer paid
            // the rest." User can still type a different figure for
            // part-payments.
            (function () {
                var sel = document.getElementById('np-quote');
                var amt = document.getElementById('np-amount');
                if (!sel || !amt) return;
                sel.addEventListener('change', function () {
                    var opt = sel.options[sel.selectedIndex];
                    var out = opt ? opt.getAttribute('data-outstanding') : '';
                    if (out && (!amt.value || parseFloat(amt.value) === 0)) {
                        amt.value = out;
                    }
                });
            })();
            // If we landed here from the Orders page's outstanding link,
            // scroll the open panel into view and focus the amount so
            // the user can verify-and-Enter to save.
            <?php if ($prefillFound): ?>
            (function () {
                var panel = document.getElementById('new-payment-panel');
                var amt   = document.getElementById('np-amount');
                if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                if (amt) {
                    amt.focus();
                    amt.select();
                }
            })();
            <?php endif; ?>
        </script>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Payment history</h2>
            </div>

            <fieldset class="filter-fieldset">
                <legend>Filter the list</legend>
                <form method="get" action="/accounts/index.php" class="filter-bar">
                    <input type="search" name="q" value="<?= e($q) ?>"
                           placeholder="Customer, quote #, reference...">
                    <label class="filter-date">
                        <span>From</span>
                        <input type="date" name="from" value="<?= e($from) ?>">
                    </label>
                    <label class="filter-date">
                        <span>To</span>
                        <input type="date" name="to" value="<?= e($to) ?>">
                    </label>
                    <select name="method">
                        <option value="">All methods</option>
                        <?php foreach (acct_methods() as $k => $lbl): ?>
                            <option value="<?= e($k) ?>" <?= $method === $k ? 'selected' : '' ?>>
                                <?= e($lbl) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-secondary">Apply filter</button>
                    <?php if ($q || $from || $to || $method): ?>
                        <a href="/accounts/index.php" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </form>
            </fieldset>

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
