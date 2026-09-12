<?php
declare(strict_types=1);

/**
 * Wholesale A/R — Statement run (Stage 1).
 *
 * One screen for the whole month-end: pick an "as at" date and see every trade
 * account that owes money, aged by days overdue (Current / 1-30 / 31-60 / 61-90 /
 * 90+) with a grand total — the owner's credit-control / chase list. From here:
 *   - open any account's open-item statement PDF, and
 *   - download ONE combined PDF of every account's statement (one per page) to
 *     print or archive — the batch that replaces sending 60 statements one by one.
 *
 * Emailing each account its own statement is Stage 2. Super-admin (factory) only.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_ar.php';

requireSuperAdmin();

$pdo     = db();
$factory = ar_factory_id();

// "As at" date — default today. (Month-end, e.g. last day of the previous month,
// is the usual statement date; the owner picks it here.)
$asAt = trim((string) ($_GET['to'] ?? ''));
if ($asAt === '' || !strtotime($asAt)) $asAt = date('Y-m-d');
$asAt = date('Y-m-d', strtotime($asAt));

$ready    = ar_table_ready($pdo, 'factory_ar_invoices');
$accounts = $ready ? ar_statement_accounts($pdo, $factory, $asAt) : [];

// Column totals for the footer.
$tot = ['current' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd90' => 0.0, 'd90plus' => 0.0, 'total' => 0.0];
foreach ($accounts as $a) {
    foreach ($tot as $k => $_) $tot[$k] = round($tot[$k] + (float) $a['aging'][$k], 2);
}
$overdueTotal = round($tot['d30'] + $tot['d60'] + $tot['d90'] + $tot['d90plus'], 2);
$noEmail = 0;
foreach ($accounts as $a) { if (trim((string) $a['email']) === '') $noEmail++; }

$money = static fn ($n) => '&pound;' . number_format((float) $n, 2);
$cell  = static function ($n) use ($money) {
    return (float) $n > 0.004
        ? '<td class="sr-num sr-od">' . $money($n) . '</td>'
        : '<td class="sr-num sr-zero">&mdash;</td>';
};
$fmtD  = static function ($d): string { $t = strtotime((string) $d); return $t ? date('j M Y', $t) : ''; };
$runQs = 'to=' . urlencode($asAt);

$activeNav = 'statement-run';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statement run &middot; Master admin</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .sr-cards { display:grid; gap:0.75rem; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); margin:0 0 1.25rem; }
        .sr-card { background:var(--bg-card); border:1px solid var(--border); border-radius:10px; padding:0.75rem 1rem; }
        .sr-card .v { font-size:1.4rem; font-weight:800; color:var(--text-primary); line-height:1.1; }
        .sr-card .v.warn { color:#b91c1c; }
        .sr-card .l { color:var(--text-faint); font-size:0.8125rem; margin-top:0.2rem; }
        .sr-filter { display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end; margin:0 0 1.25rem; }
        .sr-filter label { display:flex; flex-direction:column; gap:0.2rem; font-size:0.85rem; }
        .sr-filter input { padding:0.4rem 0.5rem; border:1px solid var(--border-strong); border-radius:6px; font:inherit; }
        .sr-num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
        .sr-od { color:#b91c1c; font-weight:600; }
        .sr-zero { color:var(--text-faint); }
        .sr-tot td { font-weight:800; border-top:2px solid var(--border-strong); background:var(--bg-subtle,#f8fafc); }
        .sr-name a { font-weight:700; color:var(--text-primary); text-decoration:none; }
        .sr-name a:hover { text-decoration:underline; }
        .sr-pill { display:inline-block; margin-left:0.4rem; padding:0.05rem 0.45rem; font-size:0.65rem; font-weight:700; border-radius:999px; text-transform:uppercase; letter-spacing:0.03em; background:#fef3c7; color:#92400e; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Statement run</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/wholesale.php">&larr; Wholesale</a>
                    &middot; every account that owes you, aged by days overdue &mdash; as at <strong><?= e($fmtD($asAt)) ?></strong>.
                </p>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
                <a href="/master-admin/statement-run-pdf.php?<?= e($runQs) ?>" class="btn btn-primary" target="_blank" rel="noopener">Download combined PDF</a>
            </div>
        </div>

        <?php if (!$ready): ?>
            <div class="alert alert-error" role="alert">Wholesale invoicing isn't set up yet &mdash; run the A/R migrations first.</div>
        <?php else: ?>

        <div class="sr-cards">
            <div class="sr-card"><div class="v"><?= count($accounts) ?></div><div class="l">Accounts owing</div></div>
            <div class="sr-card"><div class="v"><?= $money($tot['total']) ?></div><div class="l">Total outstanding</div></div>
            <div class="sr-card"><div class="v<?= $overdueTotal > 0.004 ? ' warn' : '' ?>"><?= $money($overdueTotal) ?></div><div class="l">Overdue (past due date)</div></div>
            <div class="sr-card"><div class="v<?= $tot['d90plus'] > 0.004 ? ' warn' : '' ?>"><?= $money($tot['d90plus']) ?></div><div class="l">90+ days</div></div>
        </div>

        <form method="get" action="/master-admin/statement-run.php" class="sr-filter">
            <label>As at date
                <input type="date" name="to" value="<?= e($asAt) ?>">
            </label>
            <button type="submit" class="btn btn-secondary">Apply</button>
        </form>

        <section class="section">
            <?php if (!$accounts): ?>
                <div style="background:var(--bg-subtle,#f8fafc);border:1px dashed var(--border);border-radius:12px;padding:1.75rem;color:var(--text-faint);text-align:center">
                    No accounts have an outstanding balance as at <?= e($fmtD($asAt)) ?>.
                </div>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th class="sr-num">Current</th>
                            <th class="sr-num">1&ndash;30</th>
                            <th class="sr-num">31&ndash;60</th>
                            <th class="sr-num">61&ndash;90</th>
                            <th class="sr-num">90+</th>
                            <th class="sr-num">Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $a): $ag = $a['aging']; ?>
                            <tr>
                                <td class="sr-name">
                                    <a href="/master-admin/statement-run-pdf.php?account_id=<?= (int) $a['account_id'] ?>&amp;<?= e($runQs) ?>" target="_blank" rel="noopener"><?= e($a['name']) ?></a>
                                    <?php if (trim((string) $a['email']) === ''): ?><span class="sr-pill" title="No email on file — include in the printed batch">no email</span><?php endif; ?>
                                </td>
                                <?= $cell($ag['current']) ?>
                                <?= $cell($ag['d30']) ?>
                                <?= $cell($ag['d60']) ?>
                                <?= $cell($ag['d90']) ?>
                                <?= $cell($ag['d90plus']) ?>
                                <td class="sr-num" style="font-weight:700"><?= $money($ag['total']) ?></td>
                                <td class="sr-num"><a href="/master-admin/statement-run-pdf.php?account_id=<?= (int) $a['account_id'] ?>&amp;<?= e($runQs) ?>" target="_blank" rel="noopener">Statement PDF</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="sr-tot">
                            <td>All accounts (<?= count($accounts) ?>)</td>
                            <td class="sr-num"><?= $money($tot['current']) ?></td>
                            <td class="sr-num"><?= $money($tot['d30']) ?></td>
                            <td class="sr-num"><?= $money($tot['d60']) ?></td>
                            <td class="sr-num"><?= $money($tot['d90']) ?></td>
                            <td class="sr-num"><?= $money($tot['d90plus']) ?></td>
                            <td class="sr-num"><?= $money($tot['total']) ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p style="color:var(--text-faint);font-size:0.85rem;margin:0.75rem 0 0">
                <strong>Download combined PDF</strong> gives you all <?= count($accounts) ?> statements in one file (one per page) to print or file.
                <?php if ($noEmail): ?><?= (int) $noEmail ?> account<?= $noEmail === 1 ? '' : 's' ?> have no email &mdash; they'll always need the printed copy.<?php endif; ?>
                Emailing each account its own statement is coming next.
            </p>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
