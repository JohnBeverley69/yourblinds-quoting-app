<?php
declare(strict_types=1);

/**
 * Wholesale A/R — raise a PART credit note against an invoice. ?inv_id=<id>
 *
 * Tick the invoice lines being credited (and how many of each), and/or add one
 * adjustment line (net amount + description) — e.g. a goodwill credit or a price
 * correction. VAT is at the invoice's own rate; the running credited total can
 * never exceed the invoice. Settle as CREDIT (sits on the account) or REFUND
 * (recorded as refunded to the customer — nothing is actually paid out by the
 * system). The full-credit shortcut stays on the Wholesale hub.
 * Super-admin (factory) only, like the rest of Wholesale.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_ar.php';

requireFactoryOffice();

$pdo     = db();
$factory = ar_factory_id();
$user    = current_user();
$invId   = (int) ($_GET['inv_id'] ?? $_POST['inv_id'] ?? 0);
$back    = '/master-admin/credit-note.php?inv_id=' . $invId;

if (!ar_table_ready($pdo, 'factory_ar_credit_notes')) {
    $_SESSION['flash_error'] = 'Run /migrate_ar_credit_notes.php first.';
    header('Location: /master-admin/wholesale.php');
    exit;
}

$inv = null;
try {
    $st = $pdo->prepare(
        'SELECT i.*, c.company_name AS account_name FROM factory_ar_invoices i
           JOIN clients c ON c.id = i.account_client_id
          WHERE i.id = ? AND i.factory_client_id = ? LIMIT 1'
    );
    $st->execute([$invId, $factory]);
    $inv = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) { $inv = null; }
if (!$inv || $inv['status'] === 'void') {
    $_SESSION['flash_error'] = $inv ? 'That invoice is void — nothing to credit.' : 'Invoice not found.';
    header('Location: /master-admin/wholesale.php');
    exit;
}

$lines = [];
try {
    $ls = $pdo->prepare('SELECT * FROM factory_ar_invoice_lines WHERE invoice_id = ? ORDER BY sort_order, id');
    $ls->execute([$invId]);
    $lines = $ls->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* none */ }
$lineById = [];
foreach ($lines as $l) $lineById[(int) $l['id']] = $l;

$cr = $pdo->prepare("SELECT COALESCE(SUM(subtotal),0) sub, COALESCE(SUM(total),0) tot FROM factory_ar_credit_notes WHERE against_invoice_id = ? AND factory_client_id = ? AND status <> 'void'");
$cr->execute([$invId, $factory]);
$credited = $cr->fetch(PDO::FETCH_ASSOC) ?: ['sub' => 0, 'tot' => 0];
$leftSub  = round((float) $inv['subtotal'] - (float) $credited['sub'], 2);
$leftTot  = round((float) $inv['total'] - (float) $credited['tot'], 2);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $cnLines = [];
        foreach ((array) ($_POST['credit_qty'] ?? []) as $lineId => $q) {
            $lineId = (int) $lineId;
            $q      = (int) $q;
            if ($q <= 0 || !isset($lineById[$lineId]) || empty($_POST['credit_line'][$lineId])) continue;
            $l     = $lineById[$lineId];
            $maxQ  = max(1, (int) $l['quantity']);
            $q     = min($q, $maxQ);
            // Whole line → its exact net (keeps any flat per-line charge);
            // part of a line → unit × qty.
            $net   = $q === $maxQ ? round((float) $l['line_net'], 2) : round((float) $l['unit_net'] * $q, 2);
            if ($net <= 0.004) continue;
            $cnLines[] = [
                'source_invoice_line_id' => $lineId,
                'description'            => (string) $l['description'],
                'width_mm'               => $l['width_mm'], 'drop_mm' => $l['drop_mm'],
                'quantity'               => $q,
                'unit_net'               => round($net / $q, 2),
                'line_net'               => $net,
            ];
        }
        $adjAmt  = round((float) str_replace([',', '£'], '', (string) ($_POST['adj_amount'] ?? '0')), 2);
        $adjDesc = trim((string) ($_POST['adj_description'] ?? ''));
        if ($adjAmt < 0) throw new RuntimeException('Enter the adjustment as a positive amount to credit.');
        if ($adjAmt > 0.004) {
            $cnLines[] = [
                'source_invoice_line_id' => null,
                'description'            => $adjDesc !== '' ? $adjDesc : 'Credit adjustment',
                'quantity'               => 1, 'unit_net' => $adjAmt, 'line_net' => $adjAmt,
            ];
        }
        if (!$cnLines) throw new RuntimeException('Tick at least one line or enter an adjustment amount.');

        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '') throw new RuntimeException('Give a reason for the credit (it prints on the credit note).');
        $mode   = (string) ($_POST['settle_mode'] ?? 'credit') === 'refund' ? 'refund' : 'credit';

        $cn = ar_create_credit_note($pdo, $factory, $invId, $cnLines, $reason, $mode, (int) ($user['user_id'] ?? 0));
        $_SESSION['flash_success'] = 'Credit note ' . $cn['number'] . ' raised against ' . $inv['inv_number']
            . ' (£' . number_format($cn['total'], 2) . ($mode === 'refund' ? ', recorded as refunded' : ', on the account') . '). Email it from the order row.';
        header('Location: /master-admin/wholesale.php');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['flash_error'] = 'Could not raise credit note: ' . $e->getMessage();
        header('Location: ' . $back);
        exit;
    }
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$money  = static fn ($n) => '&pound;' . number_format((float) $n, 2);
$vatPct = rtrim(rtrim(number_format((float) $inv['vat_percent'], 2), '0'), '.');

$activeNav = 'wholesale';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Credit note &middot; <?= e((string) $inv['inv_number']) ?> &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .cn-num { font-variant-numeric:tabular-nums; text-align:right; white-space:nowrap }
        .cn-qty { width:4.5rem; text-align:right; padding:0.3rem 0.4rem; border:1px solid var(--border-strong); border-radius:6px; font:inherit }
        .cn-row { display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end; margin:0.75rem 0 }
        .cn-row label { display:flex; flex-direction:column; gap:0.2rem; font-size:0.85rem }
        .cn-row input[type=text], .cn-row input[type=number] { padding:0.4rem 0.5rem; border:1px solid var(--border-strong); border-radius:6px; font:inherit; background:var(--bg-input) }
        .cn-muted { color:var(--text-faint); font-size:0.85rem }
        .cn-sum { font-variant-numeric:tabular-nums; font-weight:700 }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <p style="margin:0 0 .3rem"><a href="/master-admin/wholesale.php">&larr; Wholesale</a></p>
                <h1 class="page-title">Credit note against <?= e((string) $inv['inv_number']) ?></h1>
                <p class="page-subtitle">
                    <?= e((string) $inv['account_name']) ?> &middot; invoice total <?= $money($inv['total']) ?>
                    <?php if ((float) $credited['tot'] > 0.005): ?> &middot; already credited <?= $money($credited['tot']) ?><?php endif; ?>
                    &middot; <strong><?= $money(max(0, $leftTot)) ?></strong> left to credit (<?= $money(max(0, $leftSub)) ?> net).
                </p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?><div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div><?php endif; ?>
        <?php if ($flashErr !== null): ?><div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div><?php endif; ?>

        <?php if ($leftTot <= 0.005): ?>
            <div class="alert alert-error" role="alert">This invoice is already credited in full.</div>
        <?php else: ?>
        <form method="post" action="/master-admin/credit-note.php" id="cn-form">
            <?= csrf_field() ?>
            <input type="hidden" name="inv_id" value="<?= (int) $invId ?>">

            <section class="section">
                <h2 class="section-title" style="margin:0 0 0.25rem">1. Lines to credit <span class="cn-muted">(optional)</span></h2>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th style="width:2rem"></th><th>Description</th><th class="cn-num">Invoiced qty</th><th class="cn-num">Unit (net)</th><th class="cn-num">Line net</th><th class="cn-num">Qty to credit</th></tr></thead>
                        <tbody>
                            <?php foreach ($lines as $l): $lid = (int) $l['id']; $q = max(1, (int) $l['quantity']); ?>
                                <tr>
                                    <td><input type="checkbox" class="cn-tick" name="credit_line[<?= $lid ?>]" value="1"
                                               data-unit="<?= e(number_format((float) $l['unit_net'], 2, '.', '')) ?>"
                                               data-line="<?= e(number_format((float) $l['line_net'], 2, '.', '')) ?>"
                                               data-qty="<?= $q ?>"></td>
                                    <td><?= e((string) $l['description']) ?></td>
                                    <td class="cn-num"><?= $q ?></td>
                                    <td class="cn-num"><?= $money($l['unit_net']) ?></td>
                                    <td class="cn-num"><?= $money($l['line_net']) ?></td>
                                    <td class="cn-num"><input type="number" class="cn-qty" name="credit_qty[<?= $lid ?>]" min="1" max="<?= $q ?>" step="1" value="<?= $q ?>"></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="section">
                <h2 class="section-title" style="margin:0 0 0.25rem">2. Adjustment <span class="cn-muted">(optional &mdash; a single amount, e.g. goodwill or a price correction)</span></h2>
                <div class="cn-row">
                    <label style="flex:1;min-width:14rem">Description
                        <input type="text" name="adj_description" maxlength="255" placeholder="e.g. Goodwill — late delivery">
                    </label>
                    <label>Amount (net, ex VAT) &pound;
                        <input type="number" name="adj_amount" id="cn-adj" step="0.01" min="0" style="width:8rem;text-align:right">
                    </label>
                </div>
            </section>

            <section class="section">
                <h2 class="section-title" style="margin:0 0 0.25rem">3. Reason &amp; settlement</h2>
                <div class="cn-row">
                    <label style="flex:1;min-width:16rem">Reason (prints on the credit note)
                        <input type="text" name="reason" maxlength="255" required placeholder="e.g. Blind 3 remade — wrong colour">
                    </label>
                </div>
                <div class="cn-row" style="gap:1.25rem">
                    <label style="flex-direction:row;align-items:center;gap:0.4rem"><input type="radio" name="settle_mode" value="credit" checked> Leave as credit on the account</label>
                    <label style="flex-direction:row;align-items:center;gap:0.4rem"><input type="radio" name="settle_mode" value="refund"> Refund to the customer <span class="cn-muted">(recorded only &mdash; pay it yourself)</span></label>
                </div>
                <p class="cn-muted" style="margin:0.5rem 0">
                    Credit (net) <span class="cn-sum" id="cn-net">&pound;0.00</span>
                    + VAT @ <?= e($vatPct) ?>% &asymp; <span class="cn-sum" id="cn-gross">&pound;0.00</span>
                    &middot; up to <?= $money(max(0, $leftSub)) ?> net can be credited.
                </p>
                <button type="submit" class="btn btn-primary">Raise credit note</button>
            </section>
        </form>
        <?php endif; ?>
    </main>
</div>
<script>
(function () {
    var vat = <?= json_encode((float) $inv['vat_percent']) ?>;
    var ticks = Array.prototype.slice.call(document.querySelectorAll('.cn-tick'));
    var adj = document.getElementById('cn-adj');
    function r2(n) { return Math.round((n + Number.EPSILON) * 100) / 100; }
    function sum() {
        var net = 0;
        ticks.forEach(function (t) {
            if (!t.checked) return;
            var row = t.closest('tr');
            var q = parseInt(row.querySelector('.cn-qty').value, 10) || 0;
            var max = parseInt(t.getAttribute('data-qty'), 10) || 1;
            q = Math.min(Math.max(q, 0), max);
            net += q === max ? parseFloat(t.getAttribute('data-line')) : r2(parseFloat(t.getAttribute('data-unit')) * q);
        });
        net = r2(net + (parseFloat(adj && adj.value) || 0));
        document.getElementById('cn-net').innerHTML = '&pound;' + net.toFixed(2);
        document.getElementById('cn-gross').innerHTML = '&pound;' + r2(net + r2(net * vat / 100)).toFixed(2);
    }
    document.querySelectorAll('.cn-tick, .cn-qty, #cn-adj').forEach(function (el) {
        el.addEventListener('input', sum); el.addEventListener('change', sum);
    });
    sum();
})();
</script>
</body>
</html>
