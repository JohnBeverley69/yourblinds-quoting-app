<?php
declare(strict_types=1);

/**
 * Wholesale A/R — one trade account, whole picture (Phase 2b).
 *
 * ?id=<accountId>. Super-admin (factory) only. The single place to see where a
 * client stands: balance + aged debt, their orders (with fulfilment stage), the
 * open invoices, and payment history — with drill-in to record a payment or run
 * a statement. Read-only overview; the actions live on their own pages.
 *
 * Composes existing helpers: ar_account_balance / ar_aging / ar_placed_orders /
 * ar_statement_invoices / ar_account_payments (no new A/R logic).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_ar.php';
require_once __DIR__ . '/../_partials/order_stage.php';

requireSuperAdmin();

$pdo       = db();
$factory   = ar_factory_id();
$accountId = (int) ($_GET['id'] ?? $_GET['account_id'] ?? 0);

$acStmt = $pdo->prepare('SELECT id, company_name, contact_name, email FROM clients WHERE id = ? LIMIT 1');
$acStmt->execute([$accountId]);
$account = $acStmt->fetch(PDO::FETCH_ASSOC);
if (!$account || $accountId === $factory) {
    $_SESSION['flash_error'] = 'Choose a trade account.';
    header('Location: /master-admin/wholesale.php');
    exit;
}

$bal    = ar_account_balance($pdo, $factory, $accountId);
$aging  = ar_aging($pdo, $factory, $accountId);
$orders = ar_placed_orders($pdo, $factory, $accountId);
$invRes = ar_statement_invoices($pdo, $factory, $accountId);
$openInvoices = $invRes['rows'];
$payments = ar_payments_ready($pdo) ? ar_account_payments($pdo, $factory, $accountId) : [];

// Fulfilment stage + "billed?" flag per order (Phase 0/2 — derived stage).
$oids = array_map(static fn ($o) => (int) $o['id'], $orders);
$stageBy = [];
$billedIds = [];
if ($oids) {
    $ph = implode(',', array_fill(0, count($oids), '?'));
    try {
        $s = $pdo->prepare("SELECT id, fulfilment_stage FROM quotes WHERE id IN ($ph)");
        $s->execute($oids);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) $stageBy[(int) $r['id']] = $r['fulfilment_stage'];
    } catch (Throwable $e) { /* pre-migration — no stage */ }
    try {
        $bi = $pdo->prepare(
            "SELECT DISTINCT io.quote_id FROM factory_ar_invoice_orders io
               JOIN factory_ar_invoices i ON i.id = io.invoice_id
              WHERE i.account_client_id = ? AND i.status <> 'void' AND io.quote_id IN ($ph)"
        );
        $bi->execute(array_merge([$accountId], $oids));
        foreach ($bi->fetchAll(PDO::FETCH_COLUMN) as $qid) $billedIds[(int) $qid] = true;
    } catch (Throwable $e) { /* no invoices table — none billed */ }
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$money = static fn ($n) => '&pound;' . number_format((float) $n, 2);
$fmtD  = static function ($d): string { $t = $d ? strtotime((string) $d) : false; return $t ? date('j M Y', $t) : '&mdash;'; };
$stageColour = [
    'confirmed'    => ['#5b6b7f', '#e6ebf1'],
    'in_production'=> ['#b5730f', '#f7ecd6'],
    'ready'        => ['#245ea3', '#dde8f6'],
    'dispatched'   => ['#0d7a67', '#d6ece6'],
];

$activeNav = 'wholesale';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account &middot; <?= e((string) $account['company_name']) ?> &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .ac-cards { display:flex; gap:1rem; flex-wrap:wrap; margin:0 0 1rem }
        .ac-card { flex:1; min-width:9rem; background:var(--bg-subtle-2,#f8fafc); border:1px solid var(--border); border-radius:12px; padding:0.75rem 1rem }
        .ac-card .lbl { font-size:0.7rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-faint) }
        .ac-card .val { font-size:1.35rem; font-weight:700; font-variant-numeric:tabular-nums; margin-top:0.15rem }
        .ac-card.out .val { color:#b91c1c }
        .ac-aging { display:flex; gap:0.5rem; flex-wrap:wrap; margin:0 0 1.5rem; font-size:0.85rem }
        .ac-age { border:1px solid var(--border); border-radius:9px; padding:0.4rem 0.7rem; background:var(--surface,#fff) }
        .ac-age .lbl { font-size:0.68rem; text-transform:uppercase; letter-spacing:0.04em; color:var(--text-faint) }
        .ac-age .val { font-variant-numeric:tabular-nums; font-weight:600 }
        .ac-age.late .val { color:#b91c1c }
        .ac-num { font-variant-numeric:tabular-nums; text-align:right; white-space:nowrap }
        .ac-chip { display:inline-block; font-size:0.72rem; font-weight:600; padding:0.15rem 0.5rem; border-radius:999px; white-space:nowrap }
        .ac-tick { color:#166534; font-weight:700 } .ac-dash { color:var(--text-faint) }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <p style="margin:0 0 .3rem"><a href="/master-admin/wholesale.php">&larr; Wholesale</a></p>
                <h1 class="page-title"><?= e((string) $account['company_name']) ?></h1>
                <p class="page-subtitle">
                    Everything for this account in one place &mdash; balance, orders, invoices and payments.
                    <?php if (($account['email'] ?? '') !== ''): ?> &middot; <?= e((string) $account['email']) ?><?php endif; ?>
                </p>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-start">
                <a href="/master-admin/record-payment.php?account_id=<?= (int) $accountId ?>" class="btn btn-primary">Record payment</a>
                <a href="/master-admin/statement.php?account_id=<?= (int) $accountId ?>" class="btn btn-secondary">Statement</a>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?><div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div><?php endif; ?>
        <?php if ($flashErr !== null): ?><div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div><?php endif; ?>

        <div class="ac-cards">
            <div class="ac-card out"><div class="lbl">Outstanding</div><div class="val"><?= $money($bal['outstanding']) ?></div></div>
            <div class="ac-card"><div class="lbl">Invoiced</div><div class="val"><?= $money($bal['invoiced']) ?></div></div>
            <div class="ac-card"><div class="lbl">Paid</div><div class="val"><?= $money($bal['paid']) ?></div></div>
            <div class="ac-card"><div class="lbl">Credited</div><div class="val"><?= $money($bal['credited']) ?></div></div>
        </div>

        <div class="ac-aging">
            <div class="ac-age"><div class="lbl">Current</div><div class="val"><?= $money($aging['current']) ?></div></div>
            <div class="ac-age <?= $aging['d30']>0?'late':'' ?>"><div class="lbl">1&ndash;30 days</div><div class="val"><?= $money($aging['d30']) ?></div></div>
            <div class="ac-age <?= $aging['d60']>0?'late':'' ?>"><div class="lbl">31&ndash;60</div><div class="val"><?= $money($aging['d60']) ?></div></div>
            <div class="ac-age <?= $aging['d90']>0?'late':'' ?>"><div class="lbl">61&ndash;90</div><div class="val"><?= $money($aging['d90']) ?></div></div>
            <div class="ac-age <?= $aging['d90plus']>0?'late':'' ?>"><div class="lbl">90+ days</div><div class="val"><?= $money($aging['d90plus']) ?></div></div>
        </div>

        <section class="section">
            <h2 class="section-title">Orders <span style="font-weight:400;color:var(--text-faint);font-size:0.85rem">(<?= count($orders) ?>)</span></h2>
            <?php if (!$orders): ?>
                <p style="color:var(--text-faint);margin:0">No placed orders for this account.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Order</th><th>Date</th><th class="ac-num">Blinds</th><th class="ac-num">Value</th><th>Stage</th><th style="text-align:center">Invoiced</th></tr></thead>
                        <tbody>
                            <?php foreach ($orders as $o): $oid = (int) $o['id']; $stg = $stageBy[$oid] ?? null; $col = $stageColour[$stg] ?? ['#5b6b7f','#eef1f4']; ?>
                                <tr>
                                    <td style="font-weight:600"><a href="/factory/edit-order.php?order=<?= $oid ?>"><?= e((string) $o['quote_number']) ?></a></td>
                                    <td style="white-space:nowrap"><?= $fmtD($o['created_at']) ?></td>
                                    <td class="ac-num"><?= (int) $o['bev_qty'] ?></td>
                                    <td class="ac-num"><?= $money($o['wholesale_total']) ?></td>
                                    <td><?php if ($stg): ?><span class="ac-chip" style="color:<?= $col[0] ?>;background:<?= $col[1] ?>"><?= e(os_stage_label($stg)) ?></span><?php else: ?><span class="ac-dash">&mdash;</span><?php endif; ?></td>
                                    <td style="text-align:center"><?= isset($billedIds[$oid]) ? '<span class="ac-tick">&check;</span>' : '<span class="ac-dash">&mdash;</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="section">
            <h2 class="section-title">Open invoices <span style="font-weight:400;color:var(--text-faint);font-size:0.85rem">(<?= $money($invRes['total_outstanding']) ?> outstanding)</span></h2>
            <?php if (!$openInvoices): ?>
                <p style="color:var(--text-faint);margin:0">Nothing outstanding &mdash; the account is square.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Invoice</th><th>Order</th><th>Issued</th><th>Due</th><th class="ac-num">Total</th><th class="ac-num">Paid / credited</th><th class="ac-num">Outstanding</th></tr></thead>
                        <tbody>
                            <?php foreach ($openInvoices as $iv): ?>
                                <tr>
                                    <td style="font-weight:600"><?= e((string) $iv['inv_number']) ?></td>
                                    <td><?= e((string) $iv['order_ref']) ?></td>
                                    <td style="white-space:nowrap"><?= $fmtD($iv['issue_date']) ?></td>
                                    <td style="white-space:nowrap"><?= $fmtD($iv['due_date']) ?></td>
                                    <td class="ac-num"><?= $money($iv['total']) ?></td>
                                    <td class="ac-num"><?= $money($iv['paid']) ?></td>
                                    <td class="ac-num" style="font-weight:600"><?= $money($iv['outstanding']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="section">
            <h2 class="section-title">Payments</h2>
            <?php if (!$payments): ?>
                <p style="color:var(--text-faint);margin:0">No payments recorded yet. <a href="/master-admin/record-payment.php?account_id=<?= (int) $accountId ?>">Record one &rarr;</a></p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Payment</th><th>Date</th><th>Method</th><th>Reference</th><th class="ac-num">Amount</th><th class="ac-num">Allocated</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($payments as $p): $void = !empty($p['voided_at']); ?>
                                <tr<?= $void ? ' style="opacity:.55"' : '' ?>>
                                    <td style="font-weight:600"><?= e((string) $p['pay_number']) ?></td>
                                    <td style="white-space:nowrap"><?= $fmtD($p['payment_date']) ?></td>
                                    <td><?= e(ucfirst((string) $p['method'])) ?></td>
                                    <td><?= e((string) ($p['reference'] ?? '')) ?></td>
                                    <td class="ac-num"><?= $money($p['amount']) ?></td>
                                    <td class="ac-num"><?= $money($p['allocated']) ?></td>
                                    <td><?= $void ? 'Voided' : 'Recorded' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
