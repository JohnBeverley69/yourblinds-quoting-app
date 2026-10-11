<?php
declare(strict_types=1);

/**
 * Master Admin → Swap-over: Blind Matrix balances.
 *
 * Upload Blind Matrix's Statements export (Excel), check which YourBlinds
 * trade account each Blind Matrix account is, see every unpaid invoice and the
 * totals — then, on the swap-over day only, create them as brought-forward
 * invoices (see _partials/bm_opening_balances.php).
 *
 * TEST MODE until platform_config BM_IMPORT_ON = '1': upload + preview only,
 * NOTHING is written to the database. The uploaded file is read and deleted
 * straight away; the preview is kept only in the browser session.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/bm_opening_balances.php';

requireSuperAdmin();

$pdo     = db();
$factory = (int) factory_client_id();
$self    = '/master-admin/bm-opening-balances.php';
$enabled = bmo_import_enabled();
$user    = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = (string) ($_POST['_action'] ?? '');
    try {
        if ($act === 'upload') {
            $f = $_FILES['statement'] ?? null;
            if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) throw new RuntimeException('Choose the Blind Matrix statements file (Excel) first.');
            if (!preg_match('/\.(xlsx|xls|csv)$/i', (string) $f['name'])) throw new RuntimeException('That isn\'t an Excel file — export the statements from Blind Matrix as Excel.');
            $tmp = (string) $f['tmp_name'];
            try {
                $parsed = bmo_parse($tmp);
            } finally {
                @unlink($tmp);   // never kept on the server
            }
            if (!$parsed['accounts']) throw new RuntimeException('No unpaid invoices found in that file — is it the Statements export?');
            $accounts = bmo_trade_accounts($pdo, $factory);
            $map = [];
            foreach ($parsed['accounts'] as $i => $a) {
                $m = bmo_match_account($a['name'], $accounts);
                $map[$i] = $m ? $m['id'] : 0;
            }
            $_SESSION['bmo'] = ['file' => (string) $f['name'], 'at' => time(), 'parsed' => $parsed, 'map' => $map];
            $_SESSION['flash_success'] = 'Read ' . $f['name'] . '. Nothing has been saved — check the accounts below.';
        } elseif ($act === 'map' && isset($_SESSION['bmo'])) {
            foreach ((array) ($_POST['acct'] ?? []) as $i => $id) $_SESSION['bmo']['map'][(int) $i] = (int) $id;
            $_SESSION['flash_success'] = 'Updated. Still nothing saved.';
        } elseif ($act === 'clear') {
            unset($_SESSION['bmo']);
        } elseif ($act === 'import' && isset($_SESSION['bmo'])) {
            if (!$enabled) throw new RuntimeException('Importing is switched off (test mode) — nothing was saved.');
            if (trim((string) ($_POST['confirm'] ?? '')) !== 'IMPORT') throw new RuntimeException('Type IMPORT in the box to confirm.');
            $plan = [];
            foreach ($_SESSION['bmo']['parsed']['accounts'] as $i => $a) {
                $id = (int) ($_SESSION['bmo']['map'][$i] ?? 0);
                if ($id === -1) continue;   // left out (bad debt / not brought in)
                if ($id <= 0) throw new RuntimeException('"' . $a['name'] . '" isn\'t matched to a YourBlinds account yet (or set it to Leave out).');
                $plan[] = [$id, $a['invoices']];
            }
            $r = bmo_import($pdo, $factory, $plan, (int) ($user['user_id'] ?? 0));
            unset($_SESSION['bmo']);
            $_SESSION['flash_success'] = sprintf('Imported %d invoices (£%s). %d already in YourBlinds were skipped.', $r['made'], number_format($r['total'], 2), $r['skipped']);
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }
    header('Location: ' . $self);
    exit;
}

$st = $_SESSION['bmo'] ?? null;
$accounts = bmo_trade_accounts($pdo, $factory);
$byId = [];
foreach ($accounts as $a) $byId[(int) $a['id']] = $a;
$existing = $st ? bmo_existing_numbers($pdo, $factory) : [];

$rows = []; $tot = ['acc' => 0, 'inv' => 0, 'out' => 0.0, 'unmatched' => 0, 'dupes' => 0, 'part' => 0,
                    'left' => 0, 'left_out' => 0.0, 'in_inv' => 0, 'in_out' => 0.0];
if ($st) {
    foreach ($st['parsed']['accounts'] as $i => $a) {
        $id = (int) ($st['map'][$i] ?? 0);
        $dupes = 0; $part = 0; $oldest = null;
        foreach ($a['invoices'] as $inv) {
            if (isset($existing[$inv['no']])) $dupes++;
            if ($inv['paid'] > 0.004) $part++;
            if ($oldest === null || $inv['date'] < $oldest) $oldest = $inv['date'];
        }
        $auto = bmo_match_account($a['name'], $accounts);
        $rows[] = ['i' => $i, 'a' => $a, 'id' => $id, 'dupes' => $dupes, 'part' => $part, 'oldest' => $oldest,
                   'how' => $id === -1 ? 'left' : ($id === 0 ? 'none' : (($auto && $auto['id'] === $id) ? $auto['strength'] : 'manual'))];
        $tot['acc']++; $tot['inv'] += count($a['invoices']); $tot['out'] += $a['total'];
        if ($id === 0) $tot['unmatched']++;
        if ($id === -1) { $tot['left']++; $tot['left_out'] += $a['total']; }
        else { $tot['in_inv'] += count($a['invoices']) - $dupes; $tot['in_out'] += $a['total']; }
        $tot['dupes'] += $dupes; $tot['part'] += $part;
    }
    $rank = ['none' => 0, 'likely' => 1, 'manual' => 2, 'exact' => 3, 'left' => 4];
    usort($rows, static fn ($x, $y) => ($rank[$x['how']] <=> $rank[$y['how']]) ?: strnatcasecmp($x['a']['name'], $y['a']['name']));
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$activeNav = 'quickbooks';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Swap-over: Blind Matrix balances &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .bo-card { border: 1px solid var(--border-strong); border-radius: 10px; padding: 1rem 1.125rem; max-width: 60rem; margin-bottom: 1rem; background: var(--bg-card); }
        .bo-test { border-left: 4px solid #f59e0b; }
        .bo-chips { display: flex; gap: .5rem; flex-wrap: wrap; margin: .25rem 0 1rem; }
        .bo-chip { border: 1px solid var(--border); border-radius: 999px; padding: .2rem .7rem; font-size: .8125rem; color: var(--text-secondary); background: var(--bg-card); }
        .bo-chip b { color: var(--text-primary); }
        .bo-chip.warn { border-color: #f59e0b; }
        .bo-pill { display: inline-block; padding: .05rem .5rem; border-radius: 999px; font-size: .75rem; font-weight: 600; white-space: nowrap; }
        .bo-pill.exact { background: #dcfce7; color: #166534; }
        .bo-pill.likely, .bo-pill.manual { background: #fef3c7; color: #92400e; }
        .bo-pill.none { background: #fee2e2; color: #991b1b; }
        .bo-pill.left { background: var(--bg-subtle-2); color: var(--text-secondary); }
        .bo-muted { color: var(--text-faint); font-size: .8125rem; }
        .bo-table td { vertical-align: top; }
        .bo-table select { min-width: 14rem; max-width: 22rem; }
        .bo-inv { font-size: .8125rem; margin: .4rem 0 0; }
        .bo-inv td, .bo-inv th { padding: .15rem .5rem; }
        .bo-num { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Swap-over: Blind Matrix balances</h1>
                <p class="page-subtitle">Bring every unpaid Blind Matrix invoice into YourBlinds, so balances, statements and the Bank screen carry straight on from the swap-over.</p>
            </div>
            <div><a class="btn btn-secondary" href="/master-admin/quickbooks.php">&larr; QuickBooks</a></div>
        </div>

        <?php if ($flashMsg !== null): ?><div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div><?php endif; ?>
        <?php if ($flashErr !== null): ?><div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div><?php endif; ?>

        <?php if (!$enabled): ?>
        <div class="bo-card bo-test">
            <strong>Test mode &mdash; nothing is saved.</strong>
            <span style="color:var(--text-secondary)">Upload a statement to check how it reads and which accounts it matches. The file is read and thrown away;
            no invoices are created. Importing is switched on only for the swap-over itself.</span>
        </div>
        <?php endif; ?>

        <div class="bo-card">
            <strong>1. Upload the Blind Matrix statements</strong>
            <p class="bo-muted" style="margin:.35rem 0 .75rem">In Blind Matrix: Statements, all trade accounts, <b>From</b> a date well before the oldest unpaid invoice
                (a few years back), <b>To</b> today, export to Excel. It lists every unpaid and part-paid invoice.</p>
            <form method="post" action="<?= e($self) ?>" enctype="multipart/form-data" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="upload">
                <input type="file" name="statement" accept=".xlsx,.xls,.csv" required>
                <button type="submit" class="btn btn-primary">Read it</button>
                <?php if ($st): ?><span class="bo-muted">Showing: <?= e($st['file']) ?> (read <?= e(date('j M H:i', (int) $st['at'])) ?>)</span><?php endif; ?>
            </form>
        </div>

        <?php if ($st): $p = $st['parsed']; ?>
        <div class="bo-card">
            <strong>2. Check it</strong>
            <p class="bo-muted" style="margin:.35rem 0 .5rem">Statement period <?= e($p['from'] ? date('j M Y', strtotime($p['from'])) : '?') ?> to <?= e($p['to'] ? date('j M Y', strtotime($p['to'])) : '?') ?>.</p>
            <div class="bo-chips">
                <span class="bo-chip"><b><?= $tot['acc'] ?></b> accounts</span>
                <span class="bo-chip"><b><?= $tot['inv'] ?></b> unpaid invoices</span>
                <span class="bo-chip"><b>&pound;<?= number_format($tot['out'], 2) ?></b> owed</span>
                <span class="bo-chip"><b><?= $tot['part'] ?></b> part-paid (brought in for the balance)</span>
                <span class="bo-chip <?= $tot['unmatched'] ? 'warn' : '' ?>"><b><?= $tot['unmatched'] ?></b> accounts not matched</span>
                <span class="bo-chip <?= $tot['dupes'] ? 'warn' : '' ?>"><b><?= $tot['dupes'] ?></b> already in YourBlinds (would be skipped)</span>
                <span class="bo-chip"><b><?= $tot['left'] ?></b> accounts left out (&pound;<?= number_format($tot['left_out'], 2) ?>)</span>
                <span class="bo-chip"><b>&pound;<?= number_format($tot['in_out'], 2) ?></b> would come into YourBlinds</span>
            </div>
            <?php if ($p['warnings']): ?>
                <details style="margin:0 0 .75rem"><summary class="bo-muted"><?= count($p['warnings']) ?> rows to look at</summary>
                    <ul class="bo-muted"><?php foreach ($p['warnings'] as $w): ?><li><?= e($w) ?></li><?php endforeach; ?></ul></details>
            <?php endif; ?>
            <p class="bo-muted" style="margin:0 0 .75rem"><b>Green</b> = same name as a YourBlinds account. <b>Amber</b> = a close name or picked by hand — check it.
                <b>Red</b> = no YourBlinds account found: pick it, or create the trade account first and read the file again.</p>

            <form method="post" action="<?= e($self) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="map">
                <div class="table-wrap">
                <table class="table bo-table">
                    <thead><tr><th>Blind Matrix account</th><th>YourBlinds trade account</th><th class="bo-num">Invoices</th><th class="bo-num">Owed</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): $a = $r['a']; ?>
                        <tr>
                            <td>
                                <strong><?= e($a['name']) ?></strong>
                                <div class="bo-muted">oldest <?= e(date('j M Y', strtotime((string) $r['oldest']))) ?><?= $r['part'] ? ' &middot; ' . $r['part'] . ' part-paid' : '' ?><?= $r['dupes'] ? ' &middot; <span style="color:#92400e">' . $r['dupes'] . ' already in YourBlinds</span>' : '' ?></div>
                                <details><summary class="bo-muted" style="cursor:pointer">Show invoices</summary>
                                    <table class="bo-inv"><tr><th>Date</th><th>No.</th><th>Order</th><th>Their ref</th><th class="bo-num">Amount</th><th class="bo-num">Paid</th><th class="bo-num">Owed</th></tr>
                                    <?php foreach ($a['invoices'] as $inv): ?>
                                        <tr><td><?= e(date('d/m/Y', strtotime($inv['date']))) ?></td><td><?= e($inv['no']) ?></td><td><?= e($inv['order']) ?></td><td><?= e($inv['cref']) ?></td>
                                            <td class="bo-num"><?= number_format($inv['amount'], 2) ?></td><td class="bo-num"><?= number_format($inv['paid'], 2) ?></td><td class="bo-num"><?= number_format($inv['out'], 2) ?></td></tr>
                                    <?php endforeach; ?></table>
                                </details>
                            </td>
                            <td>
                                <span class="bo-pill <?= e($r['how']) ?>"><?= ['exact' => 'Same name', 'likely' => 'Check this one', 'manual' => 'Picked by hand', 'none' => 'Not found', 'left' => 'Left out'][$r['how']] ?></span><br>
                                <select name="acct[<?= (int) $r['i'] ?>]" style="margin-top:.3rem" aria-label="YourBlinds account for <?= e($a['name']) ?>">
                                    <option value="0">&mdash; choose &mdash;</option>
                                    <option value="-1"<?= $r['id'] === -1 ? ' selected' : '' ?>>Leave out (bad debt / don't bring in)</option>
                                    <?php foreach ($accounts as $acc): ?>
                                        <option value="<?= (int) $acc['id'] ?>"<?= (int) $acc['id'] === $r['id'] ? ' selected' : '' ?>><?= e((string) $acc['company_name']) ?><?= (int) $acc['active'] === 1 ? '' : ' (inactive)' ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="bo-num"><?= count($a['invoices']) ?></td>
                            <td class="bo-num">&pound;<?= number_format($a['total'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <button type="submit" class="btn btn-secondary" style="margin-top:.75rem">Update matches</button>
                <span class="bo-muted" style="margin-left:.5rem">Only changes this preview &mdash; nothing is saved.</span>
            </form>
        </div>

        <div class="bo-card <?= $enabled ? '' : 'bo-test' ?>">
            <strong>3. Import</strong>
            <?php if (!$enabled): ?>
                <p style="margin:.35rem 0 0;color:var(--text-secondary)">Switched off until the swap-over &mdash; nothing can be saved from this page yet.
                    On the day, with the bank and accounts up to date in Blind Matrix, read a fresh export and import it here.</p>
            <?php else: ?>
                <p style="margin:.35rem 0 .75rem;color:var(--text-secondary)">Creates <?= $tot['in_inv'] ?> brought-forward invoices (&pound;<?= number_format($tot['in_out'], 2) ?>, less any already in YourBlinds; left-out accounts are not brought in), all in one go &mdash; if anything fails, nothing is saved.</p>
                <form method="post" action="<?= e($self) ?>" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="import">
                    <input type="text" name="confirm" placeholder="Type IMPORT" autocomplete="off" style="max-width:9rem">
                    <button type="submit" class="btn btn-primary" <?= $tot['unmatched'] ? 'disabled title="Match every account first"' : '' ?>>Import</button>
                </form>
            <?php endif; ?>
            <form method="post" action="<?= e($self) ?>" style="margin-top:.75rem">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="clear">
                <button type="submit" class="btn btn-secondary btn-sm">Clear this preview</button>
            </form>
        </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
