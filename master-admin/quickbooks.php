<?php
declare(strict_types=1);

/**
 * Master Admin → QuickBooks: the live link to the platform's OWN books
 * (the factory account, Beverley Blinds). Super-admin only.
 *
 * Connect / reconnect / disconnect and the mapping reuse admin/accounting/*
 * with for=factory, so the connection is stored against the factory account.
 * The log below is accounting_log: every failed call (with Intuit's reference)
 * and every send — the record to share with Intuit support if anything goes
 * wrong. Matching customers and sending paid invoices are added next.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/accounting.php';
require_once __DIR__ . '/../_partials/quickbooks.php';

requireSuperAdmin();

$factory  = ac_target_client_id('factory');

require_once __DIR__ . '/../_partials/qbo_push.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = (string) ($_POST['_action'] ?? '');
    try {
        if ($act === 'push_settings') {
            $on   = (string) ($_POST['push_on'] ?? '') === '1';
            $from = trim((string) ($_POST['push_from'] ?? ''));
            if ($on && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) throw new RuntimeException('Choose the date to send invoices from before switching sending on.');
            qbo_push_save_settings($factory, $on, $from);
            ac_log($factory, 'quickbooks', 'sending settings', 'ok', ($on ? 'Sending ON' : 'Sending OFF') . ($from !== '' ? ', invoices from ' . $from : ''));
            $_SESSION['flash_success'] = $on ? 'Sending is on. Paid invoices dated from ' . date('j M Y', strtotime($from)) . ' will go to QuickBooks.' : 'Sending is off. Nothing will be sent to QuickBooks.';
            if ($on) { $r = qbo_push_run($factory); $_SESSION['flash_success'] .= ' ' . $r['message']; }
        } elseif ($act === 'push_now') {
            $r = qbo_push_run($factory);
            $_SESSION[$r['ran'] ? 'flash_success' : 'flash_error'] = $r['ran'] ? 'Done: ' . $r['message'] : $r['message'];
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }
    header('Location: /master-admin/quickbooks.php#sending');
    exit;
}
$prov     = ac_provider('quickbooks');
$ready    = $prov && $prov->isConfigured();
$conn     = ac_get_connection($factory, 'quickbooks');
$linked   = ac_is_connected($conn);
$reconn   = ac_needs_reconnect($conn);
$env      = $prov ? $prov->environment() : 'sandbox';
$mapping  = json_decode((string) ($conn['mapping_json'] ?? ''), true) ?: [];

// OAuth must start and finish on the host registered with Intuit (www vs
// non-www keep separate sessions), so connect from that host.
$redirectHost = $prov ? (string) parse_url($prov->redirectUri(), PHP_URL_HOST) : '';
$thisHost     = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$wrongHost    = $redirectHost !== '' && $thisHost !== '' && strcasecmp($redirectHost, $thisHost) !== 0;

$log = [];
try {
    $st = db()->prepare('SELECT * FROM accounting_log WHERE client_id = ? AND provider = ? ORDER BY id DESC LIMIT 50');
    $st->execute([$factory, 'quickbooks']);
    $log = $st->fetchAll();
    $logReady = true;
} catch (Throwable $e) {
    $logReady = false;
}

$push    = qbo_push_settings($factory);
$blocker = qbo_push_blocker($push);
$syncRows = []; $syncCounts = [];
try {
    // Only what belongs to the QuickBooks company connected now.
    qbo_push_ensure_schema(db());
    qbc_realm_schema(db(), $factory);
    $realm = qbc_realm($factory);
    $st = db()->prepare(
        "SELECT entity, local_id, ref, status, external_id, last_error, sent_at, updated_at
           FROM accounting_sync WHERE client_id = ? AND provider = 'quickbooks' AND realm_id = ?
          ORDER BY (status IN ('held','failed')) DESC, updated_at DESC LIMIT 60"
    );
    $st->execute([$factory, $realm]);
    $syncRows = $st->fetchAll();
    $c = db()->prepare("SELECT status, COUNT(*) FROM accounting_sync WHERE client_id = ? AND provider = 'quickbooks' AND realm_id = ? AND entity = 'payment' GROUP BY status");
    $c->execute([$factory, $realm]);
    $syncCounts = $c->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $e) { /* tables not migrated yet — the log check above says so */ }

$nLinks = 0;
try { $nLinks = qbc_link_count($factory); } catch (Throwable $e) { /* not migrated yet */ }

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$activeNav = 'quickbooks';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QuickBooks &middot; Master Admin &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .qb-card { border: 1px solid var(--border-strong); border-radius: 10px; padding: 1rem 1.125rem; max-width: 46rem; margin-bottom: 1rem; background: var(--bg-card); }
        .qb-badge { margin-left: .5rem; font-size: .75rem; padding: .1rem .45rem; border-radius: 6px; background: var(--bg-subtle-2); color: var(--text-secondary); }
        .qb-muted { color: var(--text-faint); font-size: .8125rem; }
        .qb-log td { vertical-align: top; font-size: .8125rem; }
        .qb-pill { display: inline-block; padding: 0 .45rem; border-radius: 999px; font-size: .75rem; font-weight: 600; }
        .qb-pill.ok { background: #dcfce7; color: #166534; }
        .qb-pill.failed { background: #fee2e2; color: #991b1b; }
        .qb-steps li { margin-bottom: .25rem; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">QuickBooks</h1>
                <p class="page-subtitle">The live link to the company's own QuickBooks: invoices and payments go across once they're paid.</p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?><div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div><?php endif; ?>
        <?php if ($flashErr !== null): ?><div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div><?php endif; ?>
        <?php if (!$logReady): ?>
            <div class="alert alert-error" role="alert">Run <a href="/setup/migrations/migrate_accounting_sync.php">migrate_accounting_sync.php</a> first.</div>
        <?php endif; ?>

        <div class="qb-card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
                <div>
                    <strong style="font-size:1.0625rem">QuickBooks Online</strong>
                    <span class="qb-badge"><?= $env === 'production' ? 'Live' : 'Sandbox (test)' ?></span>
                </div>
                <?php if ($reconn): ?>
                    <span style="font-size:.8125rem;font-weight:600;color:#991b1b">● Needs reconnecting</span>
                <?php elseif ($linked): ?>
                    <span style="font-size:.8125rem;font-weight:600;color:#065f46">● Connected</span>
                <?php else: ?>
                    <span class="qb-muted"><?= $ready ? 'Not connected' : 'Not set up' ?></span>
                <?php endif; ?>
            </div>

            <?php if (!$ready): ?>
                <p style="margin:.75rem 0 0;color:var(--text-secondary)">
                    The QuickBooks app keys aren't in yet. Add them under
                    <a href="/admin/settings.php#accounting">Settings &rarr; Accounting &rarr; QuickBooks app credentials</a>.
                </p>
            <?php else: ?>
                <?php if ($conn): ?>
                    <p style="margin:.75rem 0 0;color:var(--text-secondary)">
                        Linked to <strong><?= e((string) ($conn['company_name'] ?? 'your QuickBooks company')) ?></strong>
                        <?php if (!empty($conn['connected_at'])): ?><span class="qb-muted"> &middot; since <?= e(date('j M Y', strtotime((string) $conn['connected_at']))) ?></span><?php endif; ?>
                    </p>
                <?php endif; ?>
                <?php if ($reconn): ?>
                    <div class="alert alert-error" role="alert" style="margin:.75rem 0 0">
                        QuickBooks has stopped accepting the link (it happens after about 100 days unused, or if
                        someone disconnected YourBlinds inside QuickBooks). Nothing can be sent until you reconnect.
                        <?php if (!empty($conn['last_error'])): ?><br><span class="qb-muted"><?= e((string) $conn['last_error']) ?></span><?php endif; ?>
                    </div>
                <?php elseif ($linked && ($conn['status'] ?? '') === 'error' && !empty($conn['last_error'])): ?>
                    <p class="qb-muted" style="margin:.5rem 0 0">Last problem (will retry): <?= e((string) $conn['last_error']) ?></p>
                <?php endif; ?>

                <?php if ($wrongHost): ?>
                    <p style="margin:.75rem 0 0;color:#92400e;font-size:.875rem">
                        Connecting starts on <a href="https://<?= e($redirectHost) ?>/master-admin/quickbooks.php"><strong><?= e($redirectHost) ?></strong></a>
                        (QuickBooks sends you back there), so the button takes you there &mdash; sign in again if it asks.
                    </p>
                <?php endif; ?>

                <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.875rem">
                    <?php if (!$linked || $reconn): ?>
                        <a class="btn btn-primary" href="<?= $wrongHost ? 'https://' . e($redirectHost) : '' ?>/admin/accounting/connect.php?provider=quickbooks&amp;for=factory"><?= $conn ? 'Reconnect to QuickBooks' : 'Connect to QuickBooks' ?></a>
                    <?php endif; ?>
                    <?php if ($linked && !$reconn): ?>
                        <a class="btn btn-primary" href="/admin/accounting/quickbooks-mapping.php?for=factory"><?= !empty($mapping['sales_item_id']) ? 'Edit mapping' : 'Set up mapping' ?></a>
                    <?php endif; ?>
                    <?php if ($conn): ?>
                        <form method="post" action="/admin/accounting/disconnect.php" style="display:inline"
                              onsubmit="return confirm('Disconnect QuickBooks? Nothing more will be sent until you reconnect.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="provider" value="quickbooks">
                            <input type="hidden" name="for" value="factory">
                            <button type="submit" class="btn btn-secondary">Disconnect</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($linked && !empty($mapping['sales_item_id'])): ?>
                    <p class="qb-muted" style="margin:.75rem 0 0">
                        Mapping: item <strong><?= e((string) ($mapping['sales_item_name'] ?? '')) ?></strong>
                        <?php if (!empty($mapping['tax_code_name'])): ?> &middot; VAT <strong><?= e((string) $mapping['tax_code_name']) ?></strong><?php endif; ?>
                        <?php if (!empty($mapping['deposit_account_name'])): ?> &middot; paid into <strong><?= e((string) $mapping['deposit_account_name']) ?></strong><?php endif; ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="qb-card">
            <strong>How it will work</strong>
            <ol class="qb-steps" style="margin:.5rem 0 0;padding-left:1.25rem;color:var(--text-secondary);font-size:.875rem">
                <li>Connect, and set up the mapping (sales item, VAT code, bank account).</li>
                <li>Match each trade account to its customer already in QuickBooks, once.
                    <?php if ($linked && !$reconn): ?>
                        <a href="/master-admin/quickbooks-customers.php">Match customers &rarr;</a>
                        <span class="qb-muted">(<?= (int) $nLinks ?> linked so far)</span>
                    <?php else: ?>
                        <span class="qb-muted">(connect first)</span>
                    <?php endif; ?>
                </li>
                <li>When a payment is confirmed (bank feed or recorded by hand), the invoice(s) it pays go across with one payment against them, matching the bank line. Nothing goes across before it's paid. <span class="qb-muted">(below)</span></li>
            </ol>
        </div>

        <div class="qb-card" id="sending">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
                <strong>Sending to QuickBooks</strong>
                <?php if ($push['on'] && $blocker === ''): ?>
                    <span style="font-size:.8125rem;font-weight:600;color:#065f46">● On</span>
                <?php elseif ($push['on']): ?>
                    <span style="font-size:.8125rem;font-weight:600;color:#92400e">● On, but waiting</span>
                <?php else: ?>
                    <span class="qb-muted">Off</span>
                <?php endif; ?>
            </div>
            <p style="margin:.5rem 0 .75rem;color:var(--text-secondary);font-size:.875rem">
                When on, every payment recorded in YourBlinds (Bank screen or by hand) sends the invoice(s) it pays,
                then one payment against them, into QuickBooks. Voiding a payment or invoice here voids it there.
                Only invoices dated on or after the date below are sent &mdash; anything earlier (for example entered from
                Blind Matrix) is left alone, so nothing goes in twice.
            </p>
            <?php if ($push['on'] && $blocker !== ''): ?>
                <p style="margin:0 0 .75rem;color:#92400e;font-size:.875rem"><?= e($blocker) ?></p>
            <?php endif; ?>
            <form method="post" action="/master-admin/quickbooks.php" style="display:flex;gap:.75rem 1rem;align-items:flex-end;flex-wrap:wrap">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="push_settings">
                <label style="display:flex;flex-direction:column;gap:.25rem;font-size:.8125rem;color:var(--text-secondary)">
                    Send invoices dated from
                    <input type="date" name="push_from" value="<?= e($push['from']) ?>" class="form-control" style="min-width:10rem">
                </label>
                <label style="display:flex;gap:.4rem;align-items:center;font-size:.875rem;padding-bottom:.45rem">
                    <input type="checkbox" name="push_on" value="1" <?= $push['on'] ? 'checked' : '' ?>> Sending on
                </label>
                <button type="submit" class="btn btn-primary">Save</button>
            </form>
            <?php if ($push['on'] && $blocker === ''): ?>
                <form method="post" action="/master-admin/quickbooks.php" style="margin-top:.75rem">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="push_now">
                    <button type="submit" class="btn btn-secondary">Send now</button>
                    <span class="qb-muted" style="margin-left:.5rem">It also runs by itself after each payment and every 15 minutes, retrying anything that failed.</span>
                </form>
            <?php endif; ?>
            <?php if ($syncCounts): ?>
                <p class="qb-muted" style="margin:.75rem 0 0">
                    Payments: <?= (int) ($syncCounts['sent'] ?? 0) ?> sent
                    &middot; <?= (int) ($syncCounts['held'] ?? 0) ?> held
                    &middot; <?= (int) ($syncCounts['failed'] ?? 0) ?> failed
                    &middot; <?= (int) ($syncCounts['skipped'] ?? 0) ?> nothing to send
                </p>
            <?php endif; ?>
        </div>

        <?php if ($syncRows): ?>
        <section class="section">
            <div class="section-header"><h2 class="section-title">What's been sent</h2></div>
            <p class="qb-muted" style="margin:0 0 .75rem">Held and failed ones first, with the reason. Fix the reason (for example match the customer) and it goes on the next run.</p>
            <div class="table-wrap">
            <table class="table qb-log">
                <thead><tr><th>Item</th><th>Status</th><th>Details</th><th>QuickBooks id</th><th>When</th></tr></thead>
                <tbody>
                <?php foreach ($syncRows as $s): $st = (string) $s['status']; ?>
                    <tr>
                        <td style="white-space:nowrap"><?= e($s['entity'] === 'payment' ? 'Payment' : 'Invoice') ?> <strong><?= e((string) ($s['ref'] ?? '#' . $s['local_id'])) ?></strong></td>
                        <td><span class="qb-pill <?= $st === 'sent' ? 'ok' : ($st === 'skipped' || $st === 'voided' ? '' : 'failed') ?>"><?= e(['sent' => 'sent', 'held' => 'held', 'failed' => 'failed — will retry', 'skipped' => 'nothing to send', 'voided' => 'voided', 'pending' => 'waiting'][$st] ?? $st) ?></span></td>
                        <td style="max-width:30rem;white-space:normal"><?= e((string) ($s['last_error'] ?? '')) ?></td>
                        <td class="qb-muted"><?= e((string) ($s['external_id'] ?? '')) ?></td>
                        <td style="white-space:nowrap" class="qb-muted"><?= e(date('j M H:i', strtotime((string) ($s['sent_at'] ?: $s['updated_at'])))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </section>
        <?php endif; ?>

        <section class="section">
            <div class="section-header"><h2 class="section-title">Log</h2></div>
            <p class="qb-muted" style="margin:0 0 .75rem">Connections, sends and any problems. The Intuit reference is what their support asks for.</p>
            <?php if (!$log): ?>
                <p class="qb-muted">Nothing yet.</p>
            <?php else: ?>
            <div class="table-wrap">
            <table class="table qb-log">
                <thead><tr><th>When</th><th>What</th><th>Result</th><th>Details</th><th>Intuit ref</th></tr></thead>
                <tbody>
                <?php foreach ($log as $l): ?>
                    <tr>
                        <td style="white-space:nowrap"><?= e(date('j M H:i', strtotime((string) $l['created_at']))) ?></td>
                        <td><?= e((string) $l['action']) ?><?= !empty($l['ref']) ? ' <span class="qb-muted">' . e((string) $l['ref']) . '</span>' : '' ?></td>
                        <td><span class="qb-pill <?= $l['result'] === 'ok' ? 'ok' : 'failed' ?>"><?= e((string) $l['result']) ?></span><?= $l['http_status'] ? ' <span class="qb-muted">' . (int) $l['http_status'] . '</span>' : '' ?></td>
                        <td style="max-width:28rem;white-space:normal"><?= e((string) ($l['message'] ?? '')) ?></td>
                        <td style="white-space:nowrap" class="qb-muted"><?= e((string) ($l['provider_tid'] ?? '')) ?></td>
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
