<?php
declare(strict_types=1);

/**
 * Wholesale A/R 2D — account statement (screen). ?account_id=&from=&to=
 * Super-admin (factory) only. A ledger of the account's invoices, credit notes
 * and payments with a running balance, opening + closing, over a period. PDF via
 * statement-pdf.php with the same params.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_ar.php';

requireSuperAdmin();

$pdo       = db();
$factory   = ar_factory_id();
$accountId = (int) ($_GET['account_id'] ?? 0);

$acStmt = $pdo->prepare('SELECT id, company_name FROM clients WHERE id = ? LIMIT 1');
$acStmt->execute([$accountId]);
$account = $acStmt->fetch(PDO::FETCH_ASSOC);
if (!$account || $accountId === $factory) {
    $_SESSION['flash_error'] = 'Choose a trade account to statement.';
    header('Location: /master-admin/wholesale.php');
    exit;
}

$from = trim((string) ($_GET['from'] ?? ''));
$to   = trim((string) ($_GET['to'] ?? ''));

$data = ar_statement($pdo, $factory, $accountId, $from, $to);
$bal  = ar_account_balance($pdo, $factory, $accountId);

$money = static fn ($n) => '&pound;' . number_format((float) $n, 2);
$fmtD  = static function ($d): string { $t = $d ? strtotime((string) $d) : false; return $t ? date('j M Y', $t) : '&mdash;'; };
$pdfQs = 'account_id=' . $accountId . ($from !== '' ? '&from=' . urlencode($from) : '') . ($to !== '' ? '&to=' . urlencode($to) : '');

$activeNav = 'wholesale';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statement &middot; <?= e((string) $account['company_name']) ?> &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .st-num { font-variant-numeric:tabular-nums; text-align:right; white-space:nowrap }
        .st-open td { background:var(--bg-subtle-2,#f8fafc); font-style:italic }
        .st-close td { font-weight:700; border-top:2px solid var(--border-strong) }
        .st-filter { display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end; margin:0 0 1.25rem }
        .st-filter label { display:flex; flex-direction:column; gap:0.2rem; font-size:0.85rem }
        .st-filter input { padding:0.4rem 0.5rem; border:1px solid var(--border-strong); border-radius:6px; font:inherit }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <p style="margin:0 0 .3rem">
                    <a href="/master-admin/record-payment.php?account_id=<?= (int) $accountId ?>">&larr; Payments &amp; balance</a>
                </p>
                <h1 class="page-title">Statement &mdash; <?= e((string) $account['company_name']) ?></h1>
                <p class="page-subtitle">Outstanding <strong><?= $money($bal['outstanding']) ?></strong> as of today.</p>
            </div>
            <div>
                <a href="/master-admin/statement-pdf.php?<?= e($pdfQs) ?>&amp;download=1" class="btn btn-primary">Download PDF</a>
            </div>
        </div>

        <form method="get" action="/master-admin/statement.php" class="st-filter">
            <input type="hidden" name="account_id" value="<?= (int) $accountId ?>">
            <label>From <span style="color:var(--text-faint);font-weight:400">(blank = start)</span>
                <input type="date" name="from" value="<?= e($from) ?>">
            </label>
            <label>To
                <input type="date" name="to" value="<?= e($to !== '' ? $to : date('Y-m-d')) ?>">
            </label>
            <button type="submit" class="btn btn-secondary">Apply</button>
        </form>

        <section class="section">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Date</th><th>Reference</th><th>Type</th><th class="st-num">Charges</th><th class="st-num">Payments / credits</th><th class="st-num">Balance</th></tr>
                    </thead>
                    <tbody>
                        <tr class="st-open">
                            <td><?= $data['from'] ? $fmtD($data['from']) : '&mdash;' ?></td>
                            <td></td><td>Opening balance</td>
                            <td class="st-num"></td><td class="st-num"></td>
                            <td class="st-num"><?= $money($data['opening']) ?></td>
                        </tr>
                        <?php if (!$data['rows']): ?>
                            <tr><td colspan="6" style="color:var(--text-faint)">No transactions in this period.</td></tr>
                        <?php else: foreach ($data['rows'] as $r): ?>
                            <tr>
                                <td style="white-space:nowrap"><?= $fmtD($r['date']) ?></td>
                                <td style="font-weight:600"><?= e((string) $r['ref']) ?></td>
                                <td><?= e((string) $r['type']) ?></td>
                                <td class="st-num"><?= $r['charge'] > 0 ? $money($r['charge']) : '' ?></td>
                                <td class="st-num"><?= $r['credit'] > 0 ? $money($r['credit']) : '' ?></td>
                                <td class="st-num"><?= $money($r['balance']) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        <tr class="st-close">
                            <td colspan="3" style="text-align:right">Balance due</td>
                            <td class="st-num"></td><td class="st-num"></td>
                            <td class="st-num"><?= $money($data['closing']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
