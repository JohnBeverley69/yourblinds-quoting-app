<?php
declare(strict_types=1);

/**
 * Wholesale A/R 2E — commission statement (screen). Super-admin (factory) only.
 * ?consultant_id=&from=&to=. Applies each consultant's trade_commissions rates to
 * the accounts' invoiced net turnover in the period. PDF via commission-pdf.php.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_ar.php';

requireSuperAdmin();

$pdo     = db();
$factory = ar_factory_id();

$consultants = [];
try {
    if (ar_table_ready($pdo, 'sales_consultants')) {
        $consultants = $pdo->query('SELECT id, name FROM sales_consultants WHERE active = 1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) { $consultants = []; }

$consultantId = (int) ($_GET['consultant_id'] ?? 0);
if ($consultantId === 0 && $consultants) $consultantId = (int) $consultants[0]['id'];
$from = trim((string) ($_GET['from'] ?? ''));
$to   = trim((string) ($_GET['to'] ?? ''));

$consultantName = '';
foreach ($consultants as $c) if ((int) $c['id'] === $consultantId) $consultantName = (string) $c['name'];

$data  = $consultantId > 0 ? ar_commission_statement($pdo, $factory, $consultantId, $from, $to) : ['from' => null, 'to' => date('Y-m-d'), 'rows' => [], 'total' => 0.0];
$money = static fn ($n) => '&pound;' . number_format((float) $n, 2);
$pctF  = static fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.') . '%';
$pdfQs = 'consultant_id=' . $consultantId . ($from !== '' ? '&from=' . urlencode($from) : '') . ($to !== '' ? '&to=' . urlencode($to) : '');

$activeNav = 'commissions';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Commission statement &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .cm-num { font-variant-numeric:tabular-nums; text-align:right; white-space:nowrap }
        .cm-total td { font-weight:700; border-top:2px solid var(--border-strong) }
        .cm-filter { display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end; margin:0 0 1.25rem }
        .cm-filter label { display:flex; flex-direction:column; gap:0.2rem; font-size:0.85rem }
        .cm-filter input, .cm-filter select { padding:0.4rem 0.5rem; border:1px solid var(--border-strong); border-radius:6px; font:inherit }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Commission statement</h1>
                <p class="page-subtitle">Commission on each account's <strong>invoiced net turnover</strong> (ex VAT) in the period, at the rates set on the account's Commission tab.</p>
            </div>
            <?php if ($consultantId > 0): ?>
                <div><a href="/master-admin/commission-pdf.php?<?= e($pdfQs) ?>&amp;download=1" class="btn btn-primary">Download PDF</a></div>
            <?php endif; ?>
        </div>

        <?php if (!$consultants): ?>
            <section class="section">
                <p style="color:var(--text-faint);margin:0">No sales consultants yet. Add one and set rates on a trade account's <strong>Commission</strong> tab first<?= ar_table_ready($pdo, 'sales_consultants') ? '' : ' (run migrate_commissions.php)' ?>.</p>
            </section>
        <?php else: ?>
            <form method="get" action="/master-admin/commissions.php" class="cm-filter">
                <label>Consultant
                    <select name="consultant_id">
                        <?php foreach ($consultants as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"<?= (int) $c['id'] === $consultantId ? ' selected' : '' ?>><?= e((string) $c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
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
                        <thead><tr><th>Account</th><th>Product</th><th class="cm-num">Turnover (net)</th><th class="cm-num">Rate</th><th class="cm-num">Commission</th></tr></thead>
                        <tbody>
                            <?php if (!$data['rows']): ?>
                                <tr><td colspan="5" style="color:var(--text-faint)">No commission rules or turnover for <?= e($consultantName) ?> in this period.</td></tr>
                            <?php else: foreach ($data['rows'] as $r): ?>
                                <tr>
                                    <td style="font-weight:600"><?= e((string) $r['account_name']) ?></td>
                                    <td><?= e((string) $r['product_name']) ?></td>
                                    <td class="cm-num"><?= $money($r['turnover']) ?></td>
                                    <td class="cm-num"><?= $pctF($r['percent']) ?></td>
                                    <td class="cm-num"><?= $money($r['commission']) ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            <tr class="cm-total">
                                <td colspan="4" style="text-align:right">Total commission</td>
                                <td class="cm-num"><?= $money($data['total']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
