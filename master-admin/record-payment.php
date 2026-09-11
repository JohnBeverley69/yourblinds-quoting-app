<?php
declare(strict_types=1);

/**
 * Wholesale A/R 2D — Payments & balance for one trade account.
 *
 * ?account_id=<id>. Super-admin (factory) only. Shows the account's A/R summary
 * (invoiced / credited / paid / outstanding), records a payment allocated across
 * its open invoices (auto-suggested oldest-first, editable), and lists payment
 * history with a void. Allocations update each invoice's amount_paid + status via
 * ar_recompute_invoice_paid().
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_ar.php';

requireSuperAdmin();

$pdo      = db();
$factory  = ar_factory_id();
$accountId = (int) ($_GET['account_id'] ?? $_POST['account_id'] ?? 0);

$acStmt = $pdo->prepare('SELECT id, company_name FROM clients WHERE id = ? LIMIT 1');
$acStmt->execute([$accountId]);
$account = $acStmt->fetch(PDO::FETCH_ASSOC);
if (!$account || $accountId === $factory) {
    $_SESSION['flash_error'] = 'Choose a trade account to record a payment against.';
    header('Location: /master-admin/wholesale.php');
    exit;
}

$back = '/master-admin/record-payment.php?account_id=' . $accountId;

if (!ar_payments_ready($pdo)) {
    $_SESSION['flash_error'] = 'Payments are not set up yet — run migrate_ar_payments.php.';
}

// ── Record / void ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ar_payments_ready($pdo)) {
    csrf_check();
    $action = (string) ($_POST['action'] ?? 'record');
    $uid    = (int) (current_user()['user_id'] ?? 0);

    if ($action === 'void') {
        $payId = (int) ($_POST['payment_id'] ?? 0);
        try {
            ar_void_payment($pdo, $factory, $payId, (string) ($_POST['void_reason'] ?? ''));
            $_SESSION['flash_success'] = 'Payment voided — the invoices it covered are open again.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not void: ' . $e->getMessage();
        }
        header('Location: ' . $back);
        exit;
    }

    // Record a payment.
    $amount = round((float) ($_POST['amount'] ?? 0), 2);
    $date   = trim((string) ($_POST['payment_date'] ?? ''));
    $method = (string) ($_POST['method'] ?? 'bank');
    $ref    = trim((string) ($_POST['reference'] ?? ''));
    $notes  = trim((string) ($_POST['notes'] ?? ''));
    if (!in_array($method, ['bank', 'cash', 'card', 'cheque', 'other'], true)) $method = 'bank';
    if ($date === '' || strtotime($date) === false) $date = date('Y-m-d');

    // Clamp each submitted allocation to that invoice's real open balance.
    $open = [];
    foreach (ar_open_invoices($pdo, $factory, $accountId) as $o) $open[(int) $o['id']] = (float) $o['balance'];
    $alloc = [];
    $allocSum = 0.0;
    foreach ((array) ($_POST['alloc'] ?? []) as $invId => $amt) {
        $invId = (int) $invId;
        $amt   = round((float) $amt, 2);
        if ($invId <= 0 || $amt <= 0.004 || !isset($open[$invId])) continue;
        $amt = min($amt, $open[$invId]);           // never over-pay an invoice
        if ($amt <= 0.004) continue;
        $alloc[$invId] = $amt;
        $allocSum += $amt;
    }

    if ($amount <= 0.004) {
        $_SESSION['flash_error'] = 'Enter the payment amount.';
        header('Location: ' . $back);
        exit;
    }
    if ($allocSum > $amount + 0.01) {
        $_SESSION['flash_error'] = 'Allocated (' . number_format($allocSum, 2)
            . ') is more than the payment (' . number_format($amount, 2) . '). Adjust the allocation.';
        header('Location: ' . $back);
        exit;
    }

    try {
        $res       = ar_create_payment($pdo, $factory, $accountId, $date, $method, $amount, $ref, $notes, $alloc, $uid);
        $unallocated = round($amount - $res['allocated'], 2);
        $msg = 'Recorded ' . $res['number'] . ' — £' . number_format($amount, 2)
             . ', £' . number_format($res['allocated'], 2) . ' allocated';
        $msg .= $unallocated > 0.004 ? ', £' . number_format($unallocated, 2) . ' left as credit on account.' : '.';
        $_SESSION['flash_success'] = $msg;
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not record the payment: ' . $e->getMessage();
    }
    header('Location: ' . $back);
    exit;
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$bal      = ar_account_balance($pdo, $factory, $accountId);
$openInv  = ar_payments_ready($pdo) ? ar_open_invoices($pdo, $factory, $accountId) : [];
$payments = ar_payments_ready($pdo) ? ar_account_payments($pdo, $factory, $accountId) : [];

$money  = static fn ($n) => '&pound;' . number_format((float) $n, 2);
$fmtD   = static function ($d): string { $t = $d ? strtotime((string) $d) : false; return $t ? date('j M Y', $t) : '&mdash;'; };
$methodLabel = ['bank' => 'Bank transfer', 'cash' => 'Cash', 'card' => 'Card', 'cheque' => 'Cheque', 'other' => 'Other'];

$activeNav = 'wholesale';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payments &middot; <?= e((string) $account['company_name']) ?> &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .pm-cards { display:flex; gap:1rem; flex-wrap:wrap; margin:0 0 1.5rem }
        .pm-card { flex:1; min-width:9rem; background:var(--bg-subtle-2,#f8fafc); border:1px solid var(--border); border-radius:12px; padding:0.75rem 1rem }
        .pm-card .lbl { font-size:0.7rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-faint) }
        .pm-card .val { font-size:1.35rem; font-weight:700; font-variant-numeric:tabular-nums; margin-top:0.15rem }
        .pm-card.out .val { color:#b91c1c }
        .pm-num { font-variant-numeric:tabular-nums; text-align:right; white-space:nowrap }
        .pm-alloc { width:6.5rem; text-align:right; padding:0.3rem 0.4rem; border:1px solid var(--border-strong); border-radius:6px; font:inherit }
        .pm-tot { font-variant-numeric:tabular-nums; font-weight:700 }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <p style="margin:0 0 .3rem"><a href="/master-admin/wholesale.php">&larr; Wholesale</a></p>
                <h1 class="page-title">Payments &mdash; <?= e((string) $account['company_name']) ?></h1>
                <p class="page-subtitle">Record payments received and allocate them across this account's invoices.</p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?><div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div><?php endif; ?>
        <?php if ($flashErr !== null): ?><div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div><?php endif; ?>

        <div class="pm-cards">
            <div class="pm-card"><div class="lbl">Invoiced</div><div class="val"><?= $money($bal['invoiced']) ?></div></div>
            <div class="pm-card"><div class="lbl">Credited</div><div class="val"><?= $money($bal['credited']) ?></div></div>
            <div class="pm-card"><div class="lbl">Paid</div><div class="val"><?= $money($bal['paid']) ?></div></div>
            <div class="pm-card out"><div class="lbl">Outstanding</div><div class="val"><?= $money($bal['outstanding']) ?></div></div>
        </div>

        <?php if (!ar_payments_ready($pdo)): ?>
            <section class="section"><p style="color:var(--text-faint);margin:0">Run <code>migrate_ar_payments.php</code> to enable payments.</p></section>
        <?php else: ?>
        <section class="section">
            <h2 class="section-title">Record a payment</h2>
            <form method="post" action="/master-admin/record-payment.php" id="pm-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="record">
                <input type="hidden" name="account_id" value="<?= (int) $accountId ?>">
                <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end;margin:0 0 1rem">
                    <label style="display:flex;flex-direction:column;gap:0.2rem;font-size:0.85rem">Amount
                        <span style="display:inline-flex;align-items:center;gap:0.25rem">&pound;
                            <input type="number" step="0.01" min="0" name="amount" id="pm-amount" required
                                   style="width:8rem;padding:0.4rem 0.5rem;border:1px solid var(--border-strong);border-radius:6px;font:inherit;text-align:right">
                        </span>
                    </label>
                    <label style="display:flex;flex-direction:column;gap:0.2rem;font-size:0.85rem">Date
                        <input type="date" name="payment_date" value="<?= e(date('Y-m-d')) ?>"
                               style="padding:0.4rem 0.5rem;border:1px solid var(--border-strong);border-radius:6px;font:inherit">
                    </label>
                    <label style="display:flex;flex-direction:column;gap:0.2rem;font-size:0.85rem">Method
                        <select name="method" style="padding:0.4rem 0.5rem;border:1px solid var(--border-strong);border-radius:6px;font:inherit">
                            <?php foreach ($methodLabel as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label style="display:flex;flex-direction:column;gap:0.2rem;font-size:0.85rem;flex:1;min-width:10rem">Reference
                        <input type="text" name="reference" maxlength="120" placeholder="e.g. bank ref / cheque no."
                               style="padding:0.4rem 0.5rem;border:1px solid var(--border-strong);border-radius:6px;font:inherit">
                    </label>
                </div>

                <?php if (!$openInv): ?>
                    <p style="color:var(--text-faint)">No open invoices — any payment recorded will sit as credit on the account.</p>
                <?php else: ?>
                    <p style="font-size:0.85rem;color:var(--text-secondary);margin:0 0 0.4rem">Allocation is filled oldest-first as you type the amount — adjust any line if the customer is paying a specific invoice.</p>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Invoice</th><th>Issued</th><th>Due</th><th class="pm-num">Balance</th><th class="pm-num">Allocate</th></tr></thead>
                            <tbody>
                                <?php foreach ($openInv as $o): ?>
                                    <tr>
                                        <td class="pm-num" style="text-align:left;font-weight:600"><?= e((string) $o['inv_number']) ?></td>
                                        <td><?= $fmtD($o['issue_date']) ?></td>
                                        <td><?= $fmtD($o['due_date']) ?></td>
                                        <td class="pm-num"><?= $money($o['balance']) ?></td>
                                        <td class="pm-num">
                                            <input type="number" step="0.01" min="0" max="<?= e(number_format((float) $o['balance'], 2, '.', '')) ?>"
                                                   class="pm-alloc" name="alloc[<?= (int) $o['id'] ?>]"
                                                   data-balance="<?= e(number_format((float) $o['balance'], 2, '.', '')) ?>" value="">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" style="text-align:right">Allocated</td>
                                    <td class="pm-num pm-tot" id="pm-allocated">&pound;0.00</td>
                                    <td class="pm-num" id="pm-unalloc" style="font-size:0.8rem;color:var(--text-faint)"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>

                <label style="display:block;font-size:0.85rem;margin:0.75rem 0">Notes
                    <textarea name="notes" rows="2" style="width:100%;max-width:32rem;display:block;padding:0.4rem 0.5rem;border:1px solid var(--border-strong);border-radius:6px;font:inherit"></textarea>
                </label>
                <button type="submit" class="btn btn-primary">Record payment</button>
            </form>
        </section>

        <section class="section">
            <h2 class="section-title">Payment history</h2>
            <?php if (!$payments): ?>
                <p style="color:var(--text-faint);margin:0">No payments recorded yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Payment</th><th>Date</th><th>Method</th><th>Reference</th><th class="pm-num">Amount</th><th class="pm-num">Allocated</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($payments as $p): $void = !empty($p['voided_at']); ?>
                                <tr<?= $void ? ' style="opacity:.55"' : '' ?>>
                                    <td style="font-weight:600"><?= e((string) $p['pay_number']) ?></td>
                                    <td style="white-space:nowrap"><?= $fmtD($p['payment_date']) ?></td>
                                    <td><?= e($methodLabel[$p['method']] ?? ucfirst((string) $p['method'])) ?></td>
                                    <td><?= e((string) ($p['reference'] ?? '')) ?></td>
                                    <td class="pm-num"><?= $money($p['amount']) ?></td>
                                    <td class="pm-num"><?= $money($p['allocated']) ?></td>
                                    <td><?= $void ? 'Voided' : 'Recorded' ?></td>
                                    <td style="text-align:right">
                                        <?php if (!$void): ?>
                                            <form method="post" action="/master-admin/record-payment.php" style="display:inline;margin:0"
                                                  data-confirm="Void payment <?= e((string) $p['pay_number']) ?>? The invoices it covered will show as open again.">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="void">
                                                <input type="hidden" name="account_id" value="<?= (int) $accountId ?>">
                                                <input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm">Void</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
<script>
(function () {
    var amount = document.getElementById('pm-amount');
    if (!amount) return;
    var allocs = Array.prototype.slice.call(document.querySelectorAll('.pm-alloc'));
    var allocatedEl = document.getElementById('pm-allocated');
    var unallocEl   = document.getElementById('pm-unalloc');
    var manual = false;   // once the user edits a line, stop auto-redistributing

    function round2(n) { return Math.round((n + Number.EPSILON) * 100) / 100; }

    function refreshTotals() {
        var sum = 0;
        allocs.forEach(function (i) { sum += parseFloat(i.value) || 0; });
        sum = round2(sum);
        if (allocatedEl) allocatedEl.innerHTML = '&pound;' + sum.toFixed(2);
        var amt = round2(parseFloat(amount.value) || 0);
        var un = round2(amt - sum);
        if (unallocEl) unallocEl.textContent = un > 0.004 ? ('£' + un.toFixed(2) + ' on account') : (un < -0.004 ? 'over-allocated' : '');
    }

    function autoAllocate() {
        if (manual) { refreshTotals(); return; }
        var remaining = round2(parseFloat(amount.value) || 0);
        allocs.forEach(function (i) {
            var bal = parseFloat(i.getAttribute('data-balance')) || 0;
            var give = Math.max(0, Math.min(bal, remaining));
            give = round2(give);
            i.value = give > 0 ? give.toFixed(2) : '';
            remaining = round2(remaining - give);
        });
        refreshTotals();
    }

    amount.addEventListener('input', autoAllocate);
    allocs.forEach(function (i) {
        i.addEventListener('input', function () { manual = true; refreshTotals(); });
    });
})();
</script>
</body>
</html>
