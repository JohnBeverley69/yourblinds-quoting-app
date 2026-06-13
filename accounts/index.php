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

// Paid add-on — gate the whole module behind the per-tenant flag.
acct_require_feature($clientId);

// Permission gate: non-admin users without can_view_all_customer_jobs
// only see payments + outstanding for orders they're assigned to.
$isAdmin    = ($user['role'] ?? '') === 'admin';
$canViewAll = $isAdmin;
if (!$canViewAll) {
    $permSt = db()->prepare(
        'SELECT COALESCE(can_view_all_customer_jobs, 0)
           FROM client_users WHERE id = ? AND client_id = ? LIMIT 1'
    );
    $permSt->execute([(int) $user['user_id'], $clientId]);
    $canViewAll = ((int) $permSt->fetchColumn()) === 1;
}
$restrictToMine = !$canViewAll;
$myUserId       = (int) $user['user_id'];

$q       = trim((string) ($_GET['q']      ?? ''));
$from    = trim((string) ($_GET['from']   ?? ''));
$to      = trim((string) ($_GET['to']     ?? ''));
$method  = trim((string) ($_GET['method'] ?? ''));

$where  = ['p.client_id = ?'];
$params = [$clientId];

if ($restrictToMine) {
    $where[]  = 'p.quote_id IN (SELECT quote_id FROM appointments WHERE client_user_id = ?)';
    $params[] = $myUserId;
}

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

$payerSel = acct_has_payer_column() ? 'p.payer_name' : 'NULL AS payer_name';
$depSel   = payments_has_is_deposit() ? 'p.is_deposit' : '0 AS is_deposit';
$sql = 'SELECT p.id, p.amount, p.received_at, p.method, p.reference, p.notes,
               p.quote_id, p.customer_id, ' . $payerSel . ', ' . $depSel . ',
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
// Restricted to the user's own assigned orders if they don't have
// the view-all permission.
$pdo = db();
$monthStart = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');

if ($restrictToMine) {
    $summary = $pdo->prepare(
        'SELECT
           IFNULL(SUM(amount), 0) AS all_time,
           IFNULL(SUM(CASE WHEN received_at >= ? THEN amount ELSE 0 END), 0)
                                  AS this_month
           FROM payments
          WHERE client_id = ?
            AND quote_id IN (SELECT quote_id FROM appointments WHERE client_user_id = ?)'
    );
    $summary->execute([$monthStart, $clientId, $myUserId]);
} else {
    $summary = $pdo->prepare(
        'SELECT
           IFNULL(SUM(amount), 0) AS all_time,
           IFNULL(SUM(CASE WHEN received_at >= ? THEN amount ELSE 0 END), 0)
                                  AS this_month
           FROM payments WHERE client_id = ?'
    );
    $summary->execute([$monthStart, $clientId]);
}
$summaryRow = $summary->fetch() ?: ['all_time' => 0, 'this_month' => 0];

// Outstanding across all accepted-or-beyond quotes. Same formula as
// acct_outstanding_for_quote(), but folded into a single SQL so we
// don't have to walk every quote in PHP. Restricted to user-assigned
// orders when the permission requires it.
// The deposit subtraction is dropped once the deposit is its own payment row
// (it's then already inside the payments SUM); kept pre-migration.
$depTermSql = payments_has_is_deposit() ? '' : "
         - CASE WHEN q.deposit_paid_at IS NOT NULL
                THEN IFNULL(q.deposit_amount, 0)
                ELSE 0 END";
$outSql = "SELECT
       IFNULL(SUM(
         q.total
         - IFNULL((SELECT SUM(amount) FROM payments WHERE quote_id = q.id), 0)$depTermSql
       ), 0) AS outstanding
       FROM quotes q
      WHERE q.client_id = ?
        AND q.status IN ('accepted','ordered','fitted','invoiced','paid')";
$outParams = [$clientId];
if ($restrictToMine) {
    $outSql      .= ' AND q.id IN (SELECT quote_id FROM appointments WHERE client_user_id = ?)';
    $outParams[]  = $myUserId;
}
$outSt = $pdo->prepare($outSql);
$outSt->execute($outParams);
$outstandingTotal = (float) $outSt->fetchColumn();

// Quotes available to attach a new payment to — anything accepted or
// beyond, with their per-quote outstanding pre-computed so the picker
// can offer a sensible default amount when one is chosen. Same
// restriction as the rest of the page.
$pickSql = "SELECT q.id, q.quote_number, q.end_customer_name, q.total,
            q.deposit_amount, q.deposit_paid_at,
            IFNULL((SELECT SUM(amount) FROM payments WHERE quote_id = q.id), 0)
              AS payments_total
       FROM quotes q
      WHERE q.client_id = ?
        AND q.status IN ('accepted','ordered','fitted','invoiced','paid')";
$pickParams = [$clientId];
if ($restrictToMine) {
    $pickSql      .= ' AND q.id IN (SELECT quote_id FROM appointments WHERE client_user_id = ?)';
    $pickParams[]  = $myUserId;
}
$pickSql .= ' ORDER BY COALESCE(q.accepted_at, q.created_at) DESC LIMIT 200';
$pickSt = db()->prepare($pickSql);
$pickSt->execute($pickParams);
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
        $depCounted = deposit_extra_for($pq['deposit_paid_at'] ?? null, $pq['deposit_amount'] ?? null);
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
    <title>Payments &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .summary-cards {
            display: grid; gap: 0.75rem;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            margin: 0 0 1.25rem;
        }
        .summary-card {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px;
            padding: 0.875rem 1rem;
        }
        .summary-card .lbl {
            font-size: 0.75rem; color: var(--text-faint);
            text-transform: uppercase; letter-spacing: 0.05em;
            font-weight: 600;
        }
        .summary-card .val {
            font-size: 1.375rem; color: var(--text-primary); font-weight: 700;
            margin-top: 0.25rem;
        }
        .summary-card.outstanding .val { color: #b45309; }
        .summary-card.month       .val { color: #1f3b5b; }
        .summary-card.alltime     .val { color: #065f46; }

        .filter-fieldset {
            border: 1px dashed var(--border-strong); border-radius: 10px;
            padding: 0.625rem 0.875rem 0.75rem;
            margin: 0 0 1rem; background: transparent;
        }
        /* Plain heading INSIDE the box. Was a <legend>, which floats on
           the fieldset border and read as stray text — and went nearly
           invisible in dark mode (faint colour over a dark gap). */
        .filter-fieldset-title {
            margin: 0 0 0.5rem; font-size: 0.75rem;
            color: var(--text-muted); text-transform: uppercase;
            letter-spacing: 0.05em; font-weight: 600;
        }
        .filter-bar {
            display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: end;
        }
        .filter-bar input, .filter-bar select {
            padding: 0.4375rem 0.625rem;
            border: 1px solid var(--border-strong); border-radius: 6px; font: inherit;
            background: var(--bg-input); color: var(--text-body);
        }
        .filter-bar input[type="search"] { flex: 1 1 200px; }
        .filter-bar select               { flex: 0 0 9rem; }
        .filter-bar .filter-date {
            display: flex; flex-direction: column; gap: 0.125rem;
            font-size: 0.75rem; color: var(--text-faint);
        }
        .filter-bar .filter-date input { width: 9rem; }

        /* Record-new-payment panel */
        .np-panel {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px;
            padding: 0 0.875rem; margin: 0 0 1rem;
        }
        .np-panel summary {
            list-style: none; cursor: pointer;
            padding: 0.75rem 0; font-weight: 600; color: var(--link);
            font-size: 0.9375rem;
        }
        .np-panel summary::-webkit-details-marker { display: none; }
        .np-panel[open] summary {
            border-bottom: 1px solid var(--border); margin-bottom: 0.75rem;
        }
        .np-panel summary::before {
            content: '▸ '; color: var(--text-faint); margin-right: 0.25rem;
        }
        .np-panel[open] summary::before { content: '▾ '; }
        .np-form { padding-bottom: 0.875rem; }
        .np-grid {
            display: flex; flex-wrap: wrap; gap: 0.5rem 0.75rem;
            align-items: end;
        }
        .np-field { display: flex; flex-direction: column; gap: 0.1875rem; }
        .np-field label {
            font-size: 0.75rem; color: var(--text-faint); font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.04em;
        }
        .np-field input, .np-field select {
            padding: 0.4375rem 0.625rem;
            border: 1px solid var(--border-strong); border-radius: 6px;
            font: inherit; background: var(--bg-input); color: var(--text-body);
        }
        .np-actions {
            display: flex; gap: 0.5rem; margin-top: 0.75rem;
        }

        .method-pill {
            display: inline-block; padding: 0.0625rem 0.5rem;
            font-size: 0.6875rem; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.04em;
            border-radius: 999px;
            background: var(--alert-info-bg); color: var(--alert-info-text);
        }
        .empty-state {
            padding: 2rem 1rem; text-align: center;
            color: var(--text-faint); font-size: 0.9375rem;
        }

        /* Payment history: grouped by order (or by customer for
           standalone payments). One collapsible card per group so a
           single order with 5 part-payments doesn't blow out the
           list. Click the summary row to expand and see individual
           payments + their delete buttons. */
        .payment-groups {
            display: flex; flex-direction: column; gap: 0.375rem;
        }
        .pg-card {
            border: 1px solid var(--border); border-radius: 8px;
            background: var(--bg-card); overflow: hidden;
        }
        .pg-card[open]    { border-color: var(--link); }
        .pg-card.is-paid  { background: var(--alert-success-bg); border-color: var(--alert-success-border); }

        .pg-summary {
            list-style: none;
            cursor: pointer;
            display: flex; align-items: center;
            gap: 0.875rem; flex-wrap: wrap;
            padding: 0.5rem 0.875rem;
            font-size: 0.9375rem;
            line-height: 1.3;
        }
        .pg-summary::-webkit-details-marker { display: none; }
        .pg-summary .pg-caret {
            color: var(--text-faint); font-weight: 700;
            width: 0.75rem; flex: 0 0 auto;
            font-size: 0.875rem; line-height: 1;
        }
        .pg-summary .pg-caret::before { content: '▸'; }
        .pg-card[open] .pg-summary .pg-caret         { color: var(--link); }
        .pg-card[open] .pg-summary .pg-caret::before { content: '▾'; }

        .pg-summary .pg-customer {
            font-weight: 600; color: var(--text-primary);
            flex: 0 0 auto;
        }
        .pg-summary .pg-quote-link {
            font-weight: 600; color: var(--link); text-decoration: none;
            font-size: 0.8125rem;
            padding: 0.125rem 0.5rem;
            background: var(--bg-subtle-2); border-radius: 4px;
            flex: 0 0 auto;
        }
        .pg-summary .pg-quote-link:hover { background: var(--border); }
        .pg-summary .pg-standalone {
            color: var(--text-faint); font-size: 0.8125rem; font-style: italic;
        }

        /* Money group — pushed to the right of the row by margin-left:
           auto, so the customer + quote sit on the left and the figures
           hug the right. */
        .pg-summary .pg-money {
            margin-left: auto;
            display: flex; gap: 0.75rem 1rem; flex-wrap: wrap;
            font-size: 0.875rem; color: var(--text-muted);
            align-items: baseline;
        }
        .pg-summary .pg-money-bit strong {
            font-variant-numeric: tabular-nums; color: var(--text-primary);
        }
        .pg-summary .pg-money-bit.is-owed     { color: #92400e; }
        .pg-summary .pg-money-bit.is-owed strong     { color: #92400e; }
        .pg-summary .pg-money-bit.is-paid     { color: #065f46; font-weight: 600; }
        .pg-summary .pg-money-bit.is-overpaid { color: #1e40af; }
        .pg-summary .pg-money-bit.is-overpaid strong { color: #1e40af; }
        .pg-summary .pg-money-sub {
            color: var(--text-faint); font-size: 0.75rem;
        }

        .pg-detail {
            border-top: 1px solid var(--border); background: var(--bg-subtle);
            padding: 0.375rem 0.875rem 0.625rem;
        }
        .pg-detail .table { font-size: 0.875rem; }

        /* Dark-mode status text. The amber / navy / green figures above
           are tuned for light cards; on dark cards they'd be near-black
           on near-black. Lighter shades keep them legible. (is-paid money
           uses the theme success colour so it reads on the green card.) */
        [data-theme="dark"] .summary-card.outstanding .val { color: #fbbf24; }
        [data-theme="dark"] .summary-card.month       .val { color: #93c5fd; }
        [data-theme="dark"] .summary-card.alltime     .val { color: #86efac; }
        [data-theme="dark"] .pg-summary .pg-money-bit.is-owed,
        [data-theme="dark"] .pg-summary .pg-money-bit.is-owed strong     { color: #fbbf24; }
        [data-theme="dark"] .pg-summary .pg-money-bit.is-paid            { color: var(--alert-success-text); }
        [data-theme="dark"] .pg-summary .pg-money-bit.is-overpaid,
        [data-theme="dark"] .pg-summary .pg-money-bit.is-overpaid strong { color: #93c5fd; }

        /* Narrow screens — money group breaks to a second line under
           the customer/quote, but each card stays one summary line tall
           on a typical phone. */
        @media (max-width: 640px) {
            .pg-summary { gap: 0.5rem; }
            .pg-summary .pg-money {
                margin-left: 0; width: 100%;
                gap: 0.5rem 0.875rem;
            }
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Payments</h1>
                <p class="page-subtitle">Payments received against your orders.</p>
            </div>
            <button type="button"
                    onclick="ybNewPayment()"
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
            <summary><span id="np-summary-text">+ Record payment</span></summary>
            <form method="post" action="/accounts/payment_save.php" class="np-form">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="/accounts/index.php">
                <!-- Present = UPDATE an existing payment; blank = INSERT a new
                     one. Set by the Edit buttons in the history below; cleared
                     by ybNewPayment(). -->
                <input type="hidden" name="id" id="np-id" value="">

                <div class="np-grid">
                    <div class="np-field" style="flex:2 1 14rem">
                        <label for="np-quote">Order (optional)</label>
                        <select id="np-quote" name="quote_id" data-outstanding-map>
                            <option value=""
                                    <?= !$prefillFound ? 'selected' : '' ?>>
                                — Standalone payment (no order linked) —
                            </option>
                            <?php foreach ($payableQuotes as $pq):
                                $depCounted = deposit_extra_for($pq['deposit_paid_at'] ?? null, $pq['deposit_amount'] ?? null);
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
                                        data-deposit="<?= $depCounted > 0 ? e(acct_fmt_money($depCounted)) : '' ?>"
                                        <?= $sel ? 'selected' : '' ?>>
                                    <?= e((string) $pq['quote_number']) ?>
                                    — <?= e((string) ($pq['end_customer_name'] ?? '?')) ?>
                                    (<?= e(acct_fmt_money($out)) ?> outstanding)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <!-- Warns that a paid deposit is already counted, so the
                             user doesn't re-enter it as a payment (double-count).
                             The Amount pre-fills to OUTSTANDING, which already
                             excludes the deposit. -->
                        <div id="np-deposit-note" hidden
                             style="margin-top:0.25rem;font-size:0.75rem;color:#92400e"></div>
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

                    <?php if (acct_has_payer_column()): ?>
                    <div class="np-field" style="flex:1 1 12rem">
                        <!-- Who paid. Most useful for standalone payments
                             (no linked order/customer to name) — keeps the
                             sender out of the Reference field (Tyler). -->
                        <label for="np-payer">Received from (optional)</label>
                        <input id="np-payer" name="payer_name" type="text"
                               maxlength="200"
                               placeholder="Who paid — e.g. customer name">
                    </div>
                    <?php endif; ?>

                    <div class="np-field" style="flex:1 1 12rem">
                        <label for="np-reference">Reference (optional)</label>
                        <input id="np-reference" name="reference" type="text"
                               maxlength="200"
                               placeholder="e.g. cheque #, Stripe id...">
                    </div>
                </div>

                <div class="np-actions">
                    <button type="submit" id="np-submit" class="btn btn-primary">Save payment</button>
                    <button type="button" class="btn btn-secondary"
                            onclick="ybResetPayment()">
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
                var depNote = document.getElementById('np-deposit-note');
                sel.addEventListener('change', function () {
                    var opt = sel.options[sel.selectedIndex];
                    var out = opt ? opt.getAttribute('data-outstanding') : '';
                    if (out && (!amt.value || parseFloat(amt.value) === 0)) {
                        amt.value = out;
                    }
                    // Flag an already-counted deposit so it isn't re-entered.
                    if (depNote) {
                        var dep = opt ? opt.getAttribute('data-deposit') : '';
                        if (dep) {
                            depNote.textContent = '✓ Deposit of ' + dep
                                + ' is already recorded on this order — the amount above is the'
                                + ' remaining balance, so don’t re-enter the deposit.';
                            depNote.hidden = false;
                        } else {
                            depNote.hidden = true;
                        }
                    }
                });
            })();
            // ---- New / Edit payment panel mode --------------------------
            // The same panel records a new payment AND edits an existing
            // one. ybEditPayment() fills it from a history row; ybNewPayment()
            // / ybResetPayment() clear it back to "record new".
            var TODAY_YMD = '<?= e(date('Y-m-d')) ?>';

            function ybSetPanelMode(isEdit) {
                var sumLabel = document.getElementById('np-summary-text');
                var submit   = document.getElementById('np-submit');
                if (sumLabel) sumLabel.textContent = isEdit ? '✎ Edit payment' : '+ Record payment';
                if (submit)   submit.textContent   = isEdit ? 'Update payment'  : 'Save payment';
            }

            // Reset to "record new" and close the panel (Cancel button).
            function ybResetPayment() {
                var panel = document.getElementById('new-payment-panel');
                var id    = document.getElementById('np-id');
                if (id) id.value = '';
                ybSetPanelMode(false);
                if (panel) panel.open = false;
            }

            // Small helper — set a field's value by element id, no-op if
            // the field isn't on the page (e.g. payer field absent before
            // the migration runs). Addressed by id, NOT form.<name>, since
            // names like "method" collide with HTMLFormElement IDL props.
            function ybSetField(elId, val) {
                var el = document.getElementById(elId);
                if (el) el.value = val;
            }

            // Open a blank "record new payment" form (header + button).
            function ybNewPayment() {
                var panel = document.getElementById('new-payment-panel');
                var id    = document.getElementById('np-id');
                if (id) id.value = '';
                ybSetPanelMode(false);
                // Clear edit-populated values back to sensible new-payment
                // defaults so a previous edit doesn't bleed through.
                ybSetField('np-quote', '');
                ybSetField('np-amount', '');
                ybSetField('np-date', TODAY_YMD);
                ybSetField('np-method', 'bank_transfer');
                ybSetField('np-reference', '');
                ybSetField('np-payer', '');
                if (panel) panel.open = true;
                var amt = document.getElementById('np-amount');
                if (amt) amt.focus();
            }

            // Populate the panel from a history row's Edit button.
            function ybEditPayment(btn) {
                var panel = document.getElementById('new-payment-panel');
                if (!panel) return;
                var d = btn.dataset;
                ybSetField('np-id', d.id || '');
                ybSetField('np-amount', d.amount || '');
                ybSetField('np-date', d.date || TODAY_YMD);
                ybSetField('np-method', d.method || 'bank_transfer');
                ybSetField('np-reference', d.reference || '');
                ybSetField('np-payer', d.payer || '');

                // Quote linkage. The picker only lists orders with an
                // outstanding balance, so a payment against a now-fully-paid
                // order won't have a matching option — inject one so editing
                // doesn't silently re-file the payment as standalone.
                var sel = document.getElementById('np-quote');
                if (sel) {
                    var qid = d.quote || '';
                    if (qid) {
                        var found = false, i;
                        for (i = 0; i < sel.options.length; i++) {
                            if (sel.options[i].value === qid) { found = true; break; }
                        }
                        if (!found) {
                            var opt = document.createElement('option');
                            opt.value = qid;
                            opt.textContent = d.quoteLabel || ('Order #' + qid);
                            sel.appendChild(opt);
                        }
                        sel.value = qid;
                    } else {
                        sel.value = '';
                    }
                }

                ybSetPanelMode(true);
                panel.open = true;
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var amt = document.getElementById('np-amount');
                if (amt) { amt.focus(); amt.select(); }
            }

            // Wire all Edit buttons in the history.
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.pg-edit-btn');
                if (!btn) return;
                e.preventDefault();
                ybEditPayment(btn);
            });

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

            <div class="filter-fieldset">
                <div class="filter-fieldset-title">Filter the list</div>
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
            </div>

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
                <?php
                    // Group payments so multiple part-payments on the
                    // same order roll up into a single collapsible
                    // card. Standalone payments (no quote_id) get their
                    // own group keyed by 'standalone:<customer_id>' so
                    // they don't all pile together.
                    $groups = [];
                    foreach ($payments as $p) {
                        $qid = (int) ($p['quote_id'] ?? 0);
                        // Standalone payments have no customer; group by the
                        // payer name so different senders don't pile into one
                        // anonymous "—" card. Falls back to customer_id (0).
                        $payer = trim((string) ($p['payer_name'] ?? ''));
                        $key = $qid > 0
                            ? 'q:' . $qid
                            : 's:' . (int) ($p['customer_id'] ?? 0) . ':' . strtolower($payer);
                        // Display name: real customer if linked, else the
                        // payer (sender) on standalone payments, else dash.
                        $displayName = $p['customer_name'] ?? null;
                        if ($qid <= 0 && ($displayName === null || $displayName === '') && $payer !== '') {
                            $displayName = $payer;
                        }
                        if (!isset($groups[$key])) {
                            $groups[$key] = [
                                'quote_id'      => $qid ?: null,
                                'quote_number'  => $p['quote_number'] ?? null,
                                'customer_name' => $displayName,
                                'payments'      => [],
                                'paid_total'    => 0.0,
                                'latest'        => '',
                                'order_total'   => null,   // filled below if quote-linked
                                'outstanding'   => null,   // filled below if quote-linked
                            ];
                        }
                        $groups[$key]['payments'][] = $p;
                        $groups[$key]['paid_total'] += (float) $p['amount'];
                        $d = (string) $p['received_at'];
                        if ($d > $groups[$key]['latest']) $groups[$key]['latest'] = $d;
                    }

                    // Decorate quote-linked groups with the order total
                    // and outstanding figure. One bulk SELECT covers
                    // them all so we don't N+1 per card.
                    $quoteIds = [];
                    foreach ($groups as $g) {
                        if ($g['quote_id']) $quoteIds[] = $g['quote_id'];
                    }
                    if ($quoteIds) {
                        $ph = implode(',', array_fill(0, count($quoteIds), '?'));
                        $qSt = $pdo->prepare(
                            "SELECT id, total, deposit_amount, deposit_paid_at
                               FROM quotes
                              WHERE id IN ($ph) AND client_id = ?"
                        );
                        $qSt->execute(array_merge($quoteIds, [$clientId]));
                        $quoteInfo = [];
                        foreach ($qSt->fetchAll() as $r) {
                            $quoteInfo[(int) $r['id']] = $r;
                        }
                        foreach ($groups as $key => &$g) {
                            if (!$g['quote_id']) continue;
                            $q = $quoteInfo[$g['quote_id']] ?? null;
                            if (!$q) continue;
                            // 0 once the deposit is its own payment row (it's then
                            // already inside this group's payment rows / paid_total).
                            $depCounted = deposit_extra_for($q['deposit_paid_at'] ?? null, $q['deposit_amount'] ?? null);
                            $g['order_total'] = (float) $q['total'];
                            $g['outstanding'] = round(
                                $g['order_total'] - $g['paid_total'] - $depCounted, 2
                            );
                            // Roll any legacy (pre-migration) deposit into the
                            // displayed "paid" figure so the user sees ONE total.
                            $g['paid_total'] = round($g['paid_total'] + $depCounted, 2);
                        }
                        unset($g);
                    }

                    // Sort groups by most-recent payment desc.
                    uasort($groups, static fn ($a, $b) => strcmp($b['latest'], $a['latest']));
                ?>
                <div class="payment-groups">
                    <?php foreach ($groups as $g):
                        $count       = count($g['payments']);
                        $hasOrder    = $g['order_total'] !== null;
                        $outstanding = $g['outstanding'];
                        $fullyPaid   = $hasOrder && $outstanding !== null
                                       && abs($outstanding) < 0.005;
                    ?>
                        <details class="pg-card<?= $hasOrder && $fullyPaid ? ' is-paid' : '' ?>">
                            <summary class="pg-summary">
                                <span class="pg-caret" aria-hidden="true"></span>
                                <span class="pg-customer">
                                    <?= e((string) ($g['customer_name'] ?? '—')) ?>
                                </span>
                                <?php if (!empty($g['quote_number'])): ?>
                                    <a class="pg-quote-link"
                                       href="/quote-builder/edit.php?id=<?= (int) $g['quote_id'] ?>"
                                       onclick="event.stopPropagation()">
                                        <?= e((string) $g['quote_number']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="pg-standalone">Standalone</span>
                                <?php endif; ?>
                                <span class="pg-money">
                                    <?php if ($hasOrder): ?>
                                        <span class="pg-money-bit">
                                            Total <strong><?= e(acct_fmt_money((float) $g['order_total'])) ?></strong>
                                        </span>
                                    <?php endif; ?>
                                    <span class="pg-money-bit">
                                        Paid <strong><?= e(acct_fmt_money($g['paid_total'])) ?></strong>
                                        <span class="pg-money-sub">(<?= $count ?>)</span>
                                    </span>
                                    <?php if ($hasOrder): ?>
                                        <?php if ($fullyPaid): ?>
                                            <span class="pg-money-bit is-paid">
                                                ✓ Fully paid
                                            </span>
                                        <?php elseif ($outstanding > 0): ?>
                                            <span class="pg-money-bit is-owed">
                                                Owed <strong><?= e(acct_fmt_money($outstanding)) ?></strong>
                                            </span>
                                        <?php else: ?>
                                            <span class="pg-money-bit is-overpaid">
                                                Overpaid <strong><?= e(acct_fmt_money(-$outstanding)) ?></strong>
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </span>
                            </summary>
                            <div class="pg-detail">
                                <table class="table" style="margin:0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Method</th>
                                            <th>Reference</th>
                                            <th class="num">Amount</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($g['payments'] as $p): ?>
                                            <tr<?= !empty($p['is_deposit']) ? ' style="background:var(--alert-success-bg)"' : '' ?>>
                                                <td><?= e(date('j M Y', strtotime((string) $p['received_at']))) ?></td>
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
                                                <?php if (!empty($p['is_deposit'])): ?>
                                                    <!-- The deposit lives in the payments ledger but is
                                                         managed on the order itself (deposit panel), so it
                                                         isn't edited/deleted here. -->
                                                    <span style="color:var(--text-faint);font-style:italic;font-size:0.75rem">managed on the order</span>
                                                <?php else:
                                                    // Edit pre-fills the panel above (no delete + re-add).
                                                    $editQuoteId    = (int) ($p['quote_id'] ?? 0);
                                                    $editQuoteLabel = '';
                                                    if ($editQuoteId > 0) {
                                                        $editQuoteLabel = trim((string) ($g['quote_number'] ?? ('Order #' . $editQuoteId)));
                                                        if (!empty($g['customer_name'])) {
                                                            $editQuoteLabel .= ' — ' . (string) $g['customer_name'];
                                                        }
                                                    }
                                                ?>
                                                    <button type="button" class="btn btn-sm btn-secondary pg-edit-btn"
                                                            style="padding:0.1875rem 0.5rem;font-size:0.75rem;margin-right:0.25rem"
                                                            data-id="<?= (int) $p['id'] ?>"
                                                            data-amount="<?= e(number_format((float) $p['amount'], 2, '.', '')) ?>"
                                                            data-date="<?= e(date('Y-m-d', strtotime((string) $p['received_at']))) ?>"
                                                            data-method="<?= e((string) $p['method']) ?>"
                                                            data-reference="<?= e((string) ($p['reference'] ?? '')) ?>"
                                                            data-payer="<?= e((string) ($p['payer_name'] ?? '')) ?>"
                                                            data-quote="<?= $editQuoteId > 0 ? (int) $editQuoteId : '' ?>"
                                                            data-quote-label="<?= e($editQuoteLabel) ?>">
                                                        Edit
                                                    </button>
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
                                                <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
