<?php
declare(strict_types=1);

/**
 * Trade · Bank — the factory's Barclays account, read through Lunch Flow.
 *
 * Money-in lines are matched to trade-account invoices: the page suggests the
 * account + invoices (invoice number in the reference > Acc Ref / company name >
 * a unique amount), "Confirm" records the payment (factory_ar_payments, method
 * bank, dated the bank date) and links the line to it. Anything else is matched
 * by hand on the account's Payments page (prefilled) or ignored. Super-admin /
 * factory only — never a tenant feature.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/bank_feed.php';

requireSuperAdmin();

$pdo     = db();
$factory = ar_factory_id();
$uid     = (int) (current_user()['user_id'] ?? 0);
$ready   = bf_ready($pdo) && ar_payments_ready($pdo);
$view    = (string) ($_GET['view'] ?? 'new');
if (!in_array($view, ['new', 'matched', 'ignored', 'out'], true)) $view = 'new';
$back    = '/master-admin/bank.php' . ($view !== 'new' ? '?view=' . $view : '');

// A payment voided on the account page frees its bank line to be matched again.
if ($ready) {
    $pdo->prepare(
        "UPDATE factory_bank_transactions t
           JOIN factory_ar_payments p ON p.id = t.payment_id
            SET t.status = 'new', t.payment_id = NULL
          WHERE t.factory_client_id = ? AND t.status = 'matched' AND p.voided_at IS NOT NULL"
    )->execute([$factory]);
}

// ── POST ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_conn') {
        try {
            $key = trim((string) ($_POST['api_key'] ?? ''));
            if ($key !== '') pc_set('LUNCHFLOW_API_KEY', ac_seal($key));
            if (array_key_exists('account_id', $_POST)) {
                pc_set('LUNCHFLOW_ACCOUNT_ID', trim((string) $_POST['account_id']));
            }
            $_SESSION['flash_success'] = 'Bank connection saved.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage();
        }
        header('Location: ' . $back); exit;
    }

    if (!$ready) { header('Location: ' . $back); exit; }

    if ($action === 'sync') {
        try {
            $n = bf_sync($pdo, $factory);
            $_SESSION['flash_success'] = $n === 1 ? '1 new bank line fetched.' : $n . ' new bank lines fetched.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not fetch from the bank: ' . $e->getMessage();
        }
        header('Location: ' . $back); exit;
    }

    // Clear the backlog: every unmatched money-in line up to a date is ignored
    // (history already dealt with in Blind Matrix / QuickBooks). Undo per line
    // under Ignored.
    if ($action === 'ignore_before') {
        $upTo = (string) ($_POST['up_to'] ?? '');
        if (!DateTimeImmutable::createFromFormat('!Y-m-d', $upTo)) {
            $_SESSION['flash_error'] = 'Pick a date.';
        } else {
            $st = $pdo->prepare(
                "UPDATE factory_bank_transactions
                    SET status = 'ignored', actioned_by = ?, actioned_at = NOW()
                  WHERE factory_client_id = ? AND status = 'new' AND amount > 0 AND txn_date <= ?"
            );
            $st->execute([$uid ?: null, $factory, $upTo]);
            $_SESSION['flash_success'] = $st->rowCount() . ' line(s) up to ' . date('j M Y', strtotime($upTo)) . ' moved to Ignored.';
        }
        header('Location: ' . $back); exit;
    }

    $txnId = (int) ($_POST['txn_id'] ?? 0);
    $txn   = bf_txn($pdo, $factory, $txnId);
    if (!$txn) { $_SESSION['flash_error'] = 'Bank line not found.'; header('Location: ' . $back); exit; }

    if ($action === 'confirm') {
        // Re-work the suggestion here — never trust allocations from the browser.
        $sug = ($txn['status'] === 'new') ? bf_suggest(bf_open_book($pdo, $factory), $txn, bf_aliases($pdo, $factory)) : null;
        if (!$sug || !$sug['confirmable']) {
            $_SESSION['flash_error'] = 'That line has no invoice to settle — match it by hand.';
            header('Location: ' . $back); exit;
        }
        try {
            $pdo->beginTransaction();
            $res = ar_create_payment($pdo, $factory, (int) $sug['account_id'], (string) $txn['txn_date'], 'bank',
                                     (float) $txn['amount'], bf_payment_ref($txn), 'From the Barclays bank feed.',
                                     $sug['alloc'], $uid);
            bf_mark_matched($pdo, $factory, $txnId, (int) $res['id'], $uid);
            $pdo->commit();
            $msg = 'Recorded ' . $res['number'] . ' for ' . $sug['account_name'] . ' — £' . number_format((float) $txn['amount'], 2);
            $un  = round((float) $txn['amount'] - $res['allocated'], 2);
            $msg .= $un > 0.004 ? ' (£' . number_format($un, 2) . ' left as credit on account).' : '.';
            $_SESSION['flash_success'] = $msg;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not record the payment: ' . $e->getMessage();
        }
        header('Location: ' . $back); exit;
    }

    // One row form, two buttons: Match… (record a payment on the account's
    // Payments page) or Remember (just learn who this payer is — for accounts
    // still invoiced in Blind Matrix, where recording a payment here would sit
    // as credit on account).
    if ($action === 'match' || $action === 'remember') {
        $acc = (int) ($_POST['account_id'] ?? 0);
        $ok  = $pdo->prepare('SELECT company_name FROM clients WHERE id = ? AND id <> ? LIMIT 1');
        $ok->execute([$acc, $factory]);
        $accName = $ok->fetchColumn();
        if ($accName === false) {
            $_SESSION['flash_error'] = 'Choose the trade account first.';
            header('Location: ' . $back); exit;
        }
        if ($action === 'match') {
            header('Location: /master-admin/record-payment.php?account_id=' . $acc . '&bank_txn=' . $txnId); exit;
        }
        if (bf_remember_payer($pdo, $factory, $txn, $acc, $uid)) {
            $_SESSION['flash_success'] = 'Remembered: "' . bf_payer_label($txn) . '" is ' . $accName . ' — every payment from them will now show it.';
        } else {
            $_SESSION['flash_error'] = 'That line has too little payer detail to remember.';
        }
        header('Location: ' . $back); exit;
    }

    if ($action === 'ignore' || $action === 'unignore') {
        if ($action === 'ignore' && $txn['status'] === 'new') {
            $pdo->prepare("UPDATE factory_bank_transactions SET status = 'ignored', actioned_by = ?, actioned_at = NOW() WHERE id = ?")
                ->execute([$uid ?: null, $txnId]);
            $_SESSION['flash_success'] = 'Line ignored — it won\'t be asked about again (undo under Ignored).';
        } elseif ($action === 'unignore' && $txn['status'] === 'ignored') {
            $pdo->prepare("UPDATE factory_bank_transactions SET status = 'new', actioned_by = NULL, actioned_at = NULL WHERE id = ?")
                ->execute([$txnId]);
            $_SESSION['flash_success'] = 'Line is back in To match.';
        }
        header('Location: ' . $back); exit;
    }

    header('Location: ' . $back); exit;
}

// ── GET ─────────────────────────────────────────────────────────────────────
$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$hasKey   = bf_api_key() !== '';
$savedAcc = (string) (pc_get('LUNCHFLOW_ACCOUNT_ID', '') ?? '');
$accounts = [];
$connErr  = null;
if ($hasKey) {
    try { $accounts = bf_lf_accounts(); } catch (Throwable $e) { $connErr = $e->getMessage(); }
}

// Fetch automatically when the page is opened, at most every 30 minutes
// (Lunch Flow itself refreshes from the bank once a day).
$lastSync = (int) (pc_get('BANK_LAST_SYNC', '0') ?? 0);
if ($ready && $hasKey && $connErr === null && time() - $lastSync > 1800) {
    try { bf_sync($pdo, $factory); $lastSync = time(); }
    catch (Throwable $e) { $flashErr = $flashErr ?? ('Automatic fetch failed: ' . $e->getMessage()); }
}

$rows = []; $counts = ['new' => 0, 'matched' => 0, 'ignored' => 0, 'out' => 0];
$book = []; $accOpts = []; $aliases = [];
if ($ready) {
    $c = $pdo->prepare(
        "SELECT CASE WHEN amount <= 0 THEN 'out' ELSE status END AS k, COUNT(*) n
           FROM factory_bank_transactions WHERE factory_client_id = ? GROUP BY k"
    );
    $c->execute([$factory]);
    foreach ($c->fetchAll(PDO::FETCH_ASSOC) as $r) $counts[$r['k']] = (int) $r['n'];

    $where = $view === 'out' ? 't.amount <= 0' : "t.amount > 0 AND t.status = '" . $view . "'";
    $st = $pdo->prepare(
        "SELECT t.*, p.pay_number, p.account_client_id, c.company_name
           FROM factory_bank_transactions t
      LEFT JOIN factory_ar_payments p ON p.id = t.payment_id
      LEFT JOIN clients c ON c.id = p.account_client_id
          WHERE t.factory_client_id = ? AND $where
          ORDER BY t.txn_date DESC, t.id DESC
          LIMIT 300"
    );
    $st->execute([$factory]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    if ($view === 'new') {
        $book = bf_open_book($pdo, $factory);
        $aliases = bf_aliases($pdo, $factory);
        foreach (ar_account_options($pdo, $factory) as $o) $accOpts[(int) $o['id']] = (string) $o['name'];
        foreach ($book as $aid => $a) $accOpts[$aid] = $a['name'];
        asort($accOpts, SORT_NATURAL | SORT_FLAG_CASE);
    }
}

$money = static fn ($n) => '&pound;' . number_format((float) $n, 2);
$fmtD  = static function ($d): string { $t = $d ? strtotime((string) $d) : false; return $t ? date('j M Y', $t) : '&mdash;'; };
$tabs  = ['new' => 'To match', 'matched' => 'Matched', 'ignored' => 'Ignored', 'out' => 'Money out'];

$activeNav = 'bank';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bank &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .bk-tabs { display:flex; gap:0.4rem; flex-wrap:wrap; margin:0 0 1rem }
        .bk-tabs a { padding:0.35rem 0.8rem; border:1px solid var(--border); border-radius:999px; text-decoration:none; color:var(--text-secondary); font-size:0.85rem }
        .bk-tabs a.on { background:var(--primary,#2563eb); border-color:var(--primary,#2563eb); color:#fff }
        .bk-num { font-variant-numeric:tabular-nums; text-align:right; white-space:nowrap }
        .bk-ref { font-size:0.85rem; color:var(--text-secondary); word-break:break-word }
        .bk-sug { font-size:0.85rem }
        .bk-sug .why { color:var(--text-faint); font-size:0.78rem }
        .bk-sug.weak b { color:#b45309 }
        .bk-acts { display:flex; gap:0.35rem; flex-wrap:wrap; justify-content:flex-end; align-items:center }
        .bk-acts form { margin:0; display:inline-flex; gap:0.3rem }
        .bk-acts select { max-width:11rem; padding:0.25rem 0.35rem; border:1px solid var(--border-strong); border-radius:6px; font:inherit; font-size:0.8rem }
        .bk-conn label { display:flex; flex-direction:column; gap:0.2rem; font-size:0.85rem; margin:0 0 0.75rem; max-width:28rem }
        .bk-conn input, .bk-conn select { padding:0.4rem 0.5rem; border:1px solid var(--border-strong); border-radius:6px; font:inherit }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Bank</h1>
                <p class="page-subtitle">Money into the Barclays account, matched to trade-account invoices.
                    <?php if ($lastSync > 0): ?>Last fetched <?= e(date('j M, H:i', $lastSync)) ?>.<?php endif; ?></p>
            </div>
            <?php if ($ready && $hasKey): ?>
                <form method="post" style="margin:0">
                    <?= csrf_field() ?><input type="hidden" name="action" value="sync">
                    <button type="submit" class="btn btn-secondary">Fetch now</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($flashMsg !== null): ?><div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div><?php endif; ?>
        <?php if ($flashErr !== null): ?><div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div><?php endif; ?>

        <?php if (!$ready): ?>
            <section class="section"><p style="margin:0">Run <code>migrate_bank_feed.php</code> (and <code>migrate_ar_payments.php</code> if not done) to switch this on.</p></section>
        <?php endif; ?>

        <details class="section bk-conn"<?= (!$hasKey || $connErr !== null) ? ' open' : '' ?>>
            <summary style="cursor:pointer;font-weight:600">Connection (Lunch Flow)</summary>
            <p style="font-size:0.85rem;color:var(--text-secondary)">
                Lunch Flow reads the Barclays account through Open Banking (read-only — it can't move money).
                Barclays asks you to re-approve access every 90 days; if fetching stops, reconnect in Lunch Flow.
            </p>
            <?php if ($connErr !== null): ?><div class="alert alert-error"><?= e($connErr) ?></div><?php endif; ?>
            <?php if ($hasKey && $connErr === null && !$accounts): ?>
                <div class="alert alert-error">
                    The key works, but Lunch Flow isn't sharing any bank accounts with it. In Lunch Flow, open the
                    API destination this key belongs to and add the Barclays account to it, then reload this page.
                    <div style="font-size:0.78rem;opacity:.75;margin-top:.3rem">Lunch Flow replied: <?= e((string) ($GLOBALS['bf_last_reply'] ?? '')) ?></div>
                </div>
            <?php endif; ?>
            <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="save_conn">
                <label>API key
                    <input type="password" name="api_key" autocomplete="off"
                           placeholder="<?= $hasKey ? 'Saved — leave blank to keep it' : 'Paste the key from Lunch Flow → API' ?>">
                </label>
                <?php if ($accounts): ?>
                    <label>Bank account to read
                        <select name="account_id">
                            <option value="">— choose —</option>
                            <?php foreach ($accounts as $a): $aid = (string) ($a['id'] ?? ''); ?>
                                <option value="<?= e($aid) ?>"<?= ($aid === $savedAcc || (count($accounts) === 1 && $savedAcc === '')) ? ' selected' : '' ?>>
                                    <?= e(trim(($a['institution_name'] ?? '') . ' — ' . ($a['name'] ?? '') . ' (' . ($a['status'] ?? '') . ')')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary">Save</button>
            </form>
        </details>

        <?php if ($ready): ?>
        <nav class="bk-tabs">
            <?php foreach ($tabs as $k => $label): ?>
                <a href="/master-admin/bank.php<?= $k !== 'new' ? '?view=' . $k : '' ?>" class="<?= $view === $k ? 'on' : '' ?>"><?= e($label) ?> (<?= (int) $counts[$k] ?>)</a>
            <?php endforeach; ?>
        </nav>

        <?php if ($view === 'new' && $counts['new'] > 0): ?>
            <form method="post" style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;margin:0 0 1rem;font-size:0.85rem"
                  data-confirm="Move every unmatched payment up to that date into Ignored? (You can undo any of them under Ignored.)">
                <?= csrf_field() ?><input type="hidden" name="action" value="ignore_before">
                <span>Clear the backlog &mdash; ignore everything up to</span>
                <input type="date" name="up_to" value="<?= e(date('Y-m-d')) ?>" required
                       style="padding:0.3rem 0.4rem;border:1px solid var(--border-strong);border-radius:6px;font:inherit">
                <button type="submit" class="btn btn-secondary btn-sm">Ignore</button>
            </form>
        <?php endif; ?>

        <section class="section">
            <?php if (!$rows): ?>
                <p style="color:var(--text-faint);margin:0"><?= $view === 'new' ? 'Nothing waiting to be matched.' : 'Nothing here.' ?></p>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th>Date</th><th>From / reference</th><th class="bk-num">Amount</th>
                        <?php if ($view === 'new'): ?><th>Suggested match</th><th></th>
                        <?php elseif ($view === 'matched'): ?><th>Recorded as</th>
                        <?php elseif ($view === 'ignored'): ?><th></th><?php endif; ?>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($rows as $t):
                        $ref = trim((string) ($t['merchant'] ?? ''));
                        $desc = trim((string) ($t['description'] ?? ''));
                    ?>
                        <tr id="bk-t<?= (int) $t['id'] ?>">
                            <td style="white-space:nowrap"><?= $fmtD($t['txn_date']) ?></td>
                            <td class="bk-ref">
                                <?php if ($ref !== ''): ?><strong><?= e($ref) ?></strong><br><?php endif; ?>
                                <?= e($desc) ?>
                            </td>
                            <td class="bk-num"><?= $money($t['amount']) ?></td>

                            <?php if ($view === 'new'): $sug = bf_suggest($book, $t, $aliases); ?>
                                <td class="bk-sug<?= ($sug && !$sug['strong']) ? ' weak' : '' ?>">
                                    <?php if ($sug): ?>
                                        <b><?= e($sug['account_name']) ?></b>
                                        <?php if ($sug['confirmable']): ?>
                                            &middot; <?= e(implode(', ', $sug['invoices'])) ?>
                                            <?php if ($sug['unallocated'] > 0.004): ?> &middot; <?= $money($sug['unallocated']) ?> on account<?php endif; ?>
                                        <?php else: ?>
                                            &middot; <span style="color:var(--text-faint)">no open invoices in YourBlinds</span>
                                        <?php endif; ?>
                                        <div class="why"><?= e($sug['why']) ?></div>
                                    <?php else: ?>
                                        <span style="color:var(--text-faint)">No match found</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="bk-acts">
                                        <?php if ($sug && $sug['confirmable']): ?>
                                            <form method="post"
                                                  data-confirm="Record <?= e(number_format((float) $t['amount'], 2)) ?> from <?= e($sug['account_name']) ?> against <?= e(implode(', ', $sug['invoices'])) ?>?">
                                                <?= csrf_field() ?><input type="hidden" name="action" value="confirm">
                                                <input type="hidden" name="txn_id" value="<?= (int) $t['id'] ?>">
                                                <button type="submit" class="btn btn-primary btn-sm">Confirm</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="txn_id" value="<?= (int) $t['id'] ?>">
                                            <select name="account_id" required aria-label="Match to account">
                                                <option value="">Other account…</option>
                                                <?php foreach ($accOpts as $aid => $nm): ?>
                                                    <option value="<?= (int) $aid ?>"<?= ($sug && (int) $sug['account_id'] === (int) $aid) ? ' selected' : '' ?>><?= e($nm) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (!$sug || $sug['why'] !== 'remembered payer'): ?>
                                                <button type="submit" name="action" value="remember" class="btn btn-secondary btn-sm"
                                                        title="Remember who this payer is (records no payment)">Remember</button>
                                            <?php endif; ?>
                                            <button type="submit" name="action" value="match" class="btn btn-secondary btn-sm"
                                                    title="Record this as a payment on the account">Match…</button>
                                        </form>
                                        <form method="post">
                                            <?= csrf_field() ?><input type="hidden" name="action" value="ignore">
                                            <input type="hidden" name="txn_id" value="<?= (int) $t['id'] ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm" title="Not a customer payment">Ignore</button>
                                        </form>
                                    </div>
                                </td>
                            <?php elseif ($view === 'matched'): ?>
                                <td>
                                    <?php if (!empty($t['pay_number'])): ?>
                                        <a href="/master-admin/record-payment.php?account_id=<?= (int) $t['account_client_id'] ?>"><?= e((string) $t['pay_number']) ?></a>
                                        &middot; <?= e((string) $t['company_name']) ?>
                                    <?php endif; ?>
                                </td>
                            <?php elseif ($view === 'ignored'): ?>
                                <td style="text-align:right">
                                    <form method="post" style="margin:0">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="unignore">
                                        <input type="hidden" name="txn_id" value="<?= (int) $t['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm">Undo</button>
                                    </form>
                                </td>
                            <?php endif; ?>
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
// Working down a long list: every action reloads the page, so remember the
// scroll position (and which row was touched) and come back to it, rather
// than jumping to the top each time. Click, not submit — confirm-modal forms
// submit programmatically without a submit event.
(function () {
    var KEY = 'yb-bank-scroll';
    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('button[type=submit]');
        if (!btn) return;
        var tr = btn.closest('tr');
        try { sessionStorage.setItem(KEY, JSON.stringify({ y: window.scrollY, row: tr ? tr.id : '', top: tr ? tr.getBoundingClientRect().top : 0, path: location.pathname })); } catch (err) {}
    }, true);
    var saved = null;
    try { saved = JSON.parse(sessionStorage.getItem(KEY) || 'null'); sessionStorage.removeItem(KEY); } catch (err) {}
    if (!saved || saved.path !== location.pathname) return;
    if (document.querySelector('.alert-error')) return;   // an error needs seeing — stay at the top
    var row = saved.row ? document.getElementById(saved.row) : null;
    // Put the row back where it sat on screen (a new flash message above can
    // shift the page); if it's gone (ignored/matched), fall back to the old spot.
    // The browser's own scroll restoration runs after this script and would put
    // it back to the top, so switch it off and re-apply once the page has loaded.
    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
    var go = function () {
        window.scrollTo(0, row ? row.getBoundingClientRect().top + window.scrollY - (saved.top || 0) : (saved.y || 0));
    };
    go();
    window.addEventListener('load', go);
    if (row) {
        row.style.transition = 'background-color 1.2s';
        row.style.backgroundColor = 'rgba(37, 99, 235, 0.12)';
        setTimeout(function () { row.style.backgroundColor = ''; }, 1500);
    }
})();
</script>
</body>
</html>
