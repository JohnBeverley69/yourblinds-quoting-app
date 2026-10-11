<?php
declare(strict_types=1);

/**
 * Master Admin → QuickBooks → Match customers.
 *
 * Pairs each trade account with the customer that ALREADY exists in the
 * company's QuickBooks, once. The accounts person checks the suggestions and
 * saves; the paid-invoice push then always uses the saved customer and never
 * creates a duplicate. "Create in QuickBooks" is only for an account that
 * genuinely isn't there yet, and asks first.
 *
 * Factory office users can use it (it's the accounts person's job); the
 * connection itself stays on the super-admin QuickBooks page.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/qbo_customers.php';

requireFactoryAccounts();   // main factory only — see factory_accounts_user()

$pdo     = db();
$factory = (int) factory_client_id();
$isSuper = is_super_admin();
$user    = current_user();
$userId  = isset($user['user_id']) ? (int) $user['user_id'] : null;
$self    = '/master-admin/quickbooks-customers.php';

$conn   = ac_get_connection($factory, 'quickbooks');
$linked = ac_is_connected($conn) && !ac_needs_reconnect($conn);

/** Trade accounts: everyone except the factory itself — active ones, plus any inactive one that has invoices. */
$accounts = [];
try {
    $st = $pdo->prepare(
        'SELECT c.id, c.company_name, c.account_ref, c.account_code, c.active, c.email, c.phone,
                c.address1, c.address2, c.town, c.county, c.postcode,
                (SELECT COUNT(*) FROM factory_ar_invoices i WHERE i.account_client_id = c.id AND i.factory_client_id = ?) AS invoices
           FROM clients c
          WHERE c.id <> ?
          ORDER BY c.company_name'
    );
    $st->execute([$factory, $factory]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
        if ((int) $a['active'] === 1 || (int) $a['invoices'] > 0) $accounts[(int) $a['id']] = $a;
    }
} catch (Throwable $e) {
    $loadError = $e->getMessage();
}

$links = [];
try { $links = qbc_links($factory); } catch (Throwable $e) { $loadError = 'Run setup/migrations/migrate_accounting_sync.php first.'; }

// ── Actions ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $linked && empty($loadError)) {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');
    try {
        $customers = qbc_list($factory);

        if ($action === 'save') {
            $posted = (array) ($_POST['link'] ?? []);
            $made = 0; $cleared = 0;
            foreach ($posted as $accId => $extId) {
                $accId = (int) $accId; $extId = trim((string) $extId);
                if (!isset($accounts[$accId])) continue;
                $current = isset($links[$accId]) ? (string) $links[$accId]['external_id'] : '';
                if ($extId === $current) continue;
                if ($extId === '') { qbc_unlink($factory, $accId); $cleared++; continue; }
                if (!isset($customers[$extId])) continue;   // not a customer in this QuickBooks — ignore
                qbc_link($factory, $accId, $extId, $customers[$extId]['name'], $userId);
                $made++;
            }
            if ($made || $cleared) {
                ac_log($factory, 'quickbooks', 'match customers', 'ok',
                       $made . ' linked' . ($cleared ? ', ' . $cleared . ' unlinked' : '') . ' by ' . ((string) ($user['full_name'] ?? '') !== '' ? $user['full_name'] : 'user #' . (int) $userId));
            }
            $_SESSION['flash_success'] = $made || $cleared
                ? 'Saved: ' . $made . ' linked' . ($cleared ? ', ' . $cleared . ' unlinked' : '') . '.'
                : 'Nothing changed.';
        } elseif ($action === 'create') {
            $accId = (int) ($_POST['account_id'] ?? 0);
            if (!isset($accounts[$accId])) throw new RuntimeException('That account was not found.');
            if (isset($links[$accId])) throw new RuntimeException('That account is already linked to a QuickBooks customer.');
            $sug = qbc_suggest($accounts[$accId], $customers);
            if ($sug && $sug['strength'] === 'exact') {
                throw new RuntimeException('QuickBooks already has "' . $customers[$sug['id']]['name']
                    . '" — link to that one instead of creating a duplicate.');
            }
            $made = qbc_create($factory, $accounts[$accId], $userId);
            $_SESSION['flash_success'] = 'Created "' . $made['name'] . '" in QuickBooks and linked it.';
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }
    header('Location: ' . $self . (isset($_POST['show']) && $_POST['show'] === 'all' ? '?show=all' : ''));
    exit;
}

// ── Page data ───────────────────────────────────────────────────────────────
$customers = [];
$listError = null;
if ($linked && empty($loadError)) {
    try { $customers = qbc_list($factory); }
    catch (Throwable $e) { $listError = $e->getMessage(); }
}

// QuickBooks customers already used by a link (to flag a customer linked twice).
$usedBy = [];
foreach ($links as $accId => $l) $usedBy[(string) $l['external_id']][] = $accId;

$rows = []; $nLinked = 0; $nSuggested = 0; $nNone = 0;
foreach ($accounts as $id => $a) {
    $l   = $links[$id] ?? null;
    $sug = (!$l && $customers) ? qbc_suggest($a, $customers) : null;
    if ($l) {
        $state = ($customers && !isset($customers[(string) $l['external_id']])) ? 'missing' : 'linked';
        $nLinked++;
    } elseif ($sug) {
        $state = $sug['strength'];   // exact | likely
        $nSuggested++;
    } else {
        $state = 'none';
        $nNone++;
    }
    $rows[] = ['a' => $a, 'link' => $l, 'sug' => $sug, 'state' => $state];
}
// Needing a decision first, then alphabetical.
$rank = ['missing' => 0, 'likely' => 1, 'exact' => 2, 'none' => 3, 'linked' => 4];
usort($rows, static fn ($x, $y) => ($rank[$x['state']] <=> $rank[$y['state']])
    ?: strnatcasecmp((string) $x['a']['company_name'], (string) $y['a']['company_name']));

$show = (string) ($_GET['show'] ?? '') === 'all' || $nSuggested + $nNone === 0 ? 'all' : 'todo';
$visible = $show === 'all' ? $rows : array_values(array_filter($rows, static fn ($r) => $r['state'] !== 'linked'));

$custSorted = $customers;
uasort($custSorted, static fn ($x, $y) => strcasecmp($x['name'], $y['name']));

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$activeNav = $isSuper ? 'quickbooks' : 'qbo-customers';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Match customers &middot; QuickBooks &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .qm-summary { display: flex; gap: .5rem; flex-wrap: wrap; margin: 0 0 1rem; }
        .qm-chip { border: 1px solid var(--border); border-radius: 999px; padding: .2rem .7rem; font-size: .8125rem; color: var(--text-secondary); background: var(--bg-card); }
        .qm-chip b { color: var(--text-primary); }
        .qm-table td { vertical-align: middle; }
        .qm-table select { width: 100%; min-width: 14rem; max-width: 26rem; }
        .qm-ref { color: var(--text-faint); font-size: .8125rem; }
        .qm-pill { display: inline-block; padding: .05rem .5rem; border-radius: 999px; font-size: .75rem; font-weight: 600; white-space: nowrap; }
        .qm-pill.linked  { background: #dcfce7; color: #166534; }
        .qm-pill.exact   { background: #dbeafe; color: #1e40af; }
        .qm-pill.likely  { background: #fef3c7; color: #92400e; }
        .qm-pill.none    { background: var(--bg-subtle-2); color: var(--text-secondary); }
        .qm-pill.missing { background: #fee2e2; color: #991b1b; }
        tr.qm-likely td { background: color-mix(in srgb, #f59e0b 7%, transparent); }
        .qm-note { font-size: .75rem; color: #92400e; margin-top: .2rem; }
        .qm-actions { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; margin: 1rem 0; }
        .qm-sticky { position: sticky; bottom: 0; background: var(--bg-page, var(--bg-card)); padding: .75rem 0; border-top: 1px solid var(--border); }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Match customers</h1>
                <p class="page-subtitle">Pair each trade account with its customer that's already in QuickBooks. Done once; after that, paid invoices always go to the right customer and no duplicates are made.</p>
            </div>
            <?php if ($isSuper): ?><div><a class="btn btn-secondary" href="/master-admin/quickbooks.php">&larr; QuickBooks</a></div><?php endif; ?>
        </div>

        <?php if ($flashMsg !== null): ?><div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div><?php endif; ?>
        <?php if ($flashErr !== null): ?><div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div><?php endif; ?>

        <?php if (!empty($loadError)): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $loadError) ?></div>
        <?php elseif (!$linked): ?>
            <div class="alert alert-error" role="alert">
                QuickBooks isn't connected<?= ac_needs_reconnect($conn) ? ' (it needs reconnecting)' : '' ?>.
                <?= $isSuper ? 'Connect it on the <a href="/master-admin/quickbooks.php">QuickBooks page</a> first.' : 'Ask the administrator to connect it first.' ?>
            </div>
        <?php elseif ($listError !== null): ?>
            <div class="alert alert-error" role="alert">Couldn't load the customer list from QuickBooks: <?= e($listError) ?></div>
        <?php else: ?>

            <p style="margin:0 0 .5rem;color:var(--text-secondary)">
                Connected to <strong><?= e((string) ($conn['company_name'] ?? 'QuickBooks')) ?></strong>
                &middot; <?= count($customers) ?> customers in QuickBooks
            </p>
            <div class="qm-summary">
                <span class="qm-chip"><b><?= $nLinked ?></b> linked</span>
                <span class="qm-chip"><b><?= $nSuggested ?></b> suggested &mdash; check and save</span>
                <span class="qm-chip"><b><?= $nNone ?></b> no match found</span>
            </div>

            <p style="margin:0 0 1rem;font-size:.875rem;color:var(--text-secondary);max-width:52rem">
                Each row shows the QuickBooks customer we think it is. <b>Blue</b> = same name. <b>Amber</b> = a close name &mdash; please
                check it. Change any that are wrong (or choose <em>&mdash; not linked &mdash;</em>), then press <b>Save matches</b>.
                Nothing is linked until you save. Only use <b>Create in QuickBooks</b> for an account that really isn't in QuickBooks yet.
            </p>

            <div class="qm-actions">
                <?php if ($show === 'todo'): ?>
                    <span class="qm-ref">Showing the <?= count($visible) ?> that need a decision.</span>
                    <a href="<?= e($self) ?>?show=all">Show all <?= count($rows) ?> accounts</a>
                <?php else: ?>
                    <span class="qm-ref">Showing all <?= count($rows) ?> accounts.</span>
                    <?php if ($nSuggested + $nNone > 0): ?><a href="<?= e($self) ?>">Show only those needing a decision</a><?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if (!$visible): ?>
                <p class="qm-ref">Every trade account is linked. 🎉</p>
            <?php else: ?>
            <form method="post" action="<?= e($self) ?>" id="qmForm">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="save">
                <input type="hidden" name="show" value="<?= e($show) ?>">
                <div class="table-wrap">
                <table class="table qm-table">
                    <thead><tr><th>Trade account (YourBlinds)</th><th>Status</th><th>QuickBooks customer</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($visible as $r):
                        $a = $r['a']; $id = (int) $a['id'];
                        $sel = $r['link'] ? (string) $r['link']['external_id'] : ($r['sug']['id'] ?? '');
                        $ref = trim((string) ($a['account_ref'] ?: $a['account_code']));
                        $twice = $r['link'] && count($usedBy[(string) $r['link']['external_id']] ?? []) > 1;
                    ?>
                        <tr class="<?= $r['state'] === 'likely' ? 'qm-likely' : '' ?>">
                            <td>
                                <strong><?= e((string) $a['company_name']) ?></strong>
                                <?php if ($ref !== ''): ?><span class="qm-ref"> &middot; <?= e($ref) ?></span><?php endif; ?>
                                <?php if ((int) $a['active'] !== 1): ?><span class="qm-ref"> &middot; inactive</span><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['state'] === 'linked'): ?><span class="qm-pill linked">Linked</span>
                                <?php elseif ($r['state'] === 'exact'): ?><span class="qm-pill exact">Same name</span>
                                <?php elseif ($r['state'] === 'likely'): ?><span class="qm-pill likely">Check this one</span>
                                <?php elseif ($r['state'] === 'missing'): ?><span class="qm-pill missing">Not found in QuickBooks</span>
                                <?php else: ?><span class="qm-pill none">No match found</span><?php endif; ?>
                            </td>
                            <td>
                                <select name="link[<?= $id ?>]" aria-label="QuickBooks customer for <?= e((string) $a['company_name']) ?>">
                                    <option value="">&mdash; not linked &mdash;</option>
                                    <?php if ($r['state'] === 'missing'): ?>
                                        <option value="<?= e($sel) ?>" selected><?= e((string) $r['link']['external_name']) ?> (no longer in QuickBooks)</option>
                                    <?php endif; ?>
                                    <?php foreach ($custSorted as $c): ?>
                                        <option value="<?= e($c['id']) ?>"<?= $c['id'] === $sel ? ' selected' : '' ?>><?= e($c['name']) ?><?= $c['active'] ? '' : ' (inactive)' ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($twice): ?><div class="qm-note">Another trade account is linked to this same QuickBooks customer.</div><?php endif; ?>
                                <?php if ($r['state'] === 'missing'): ?><div class="qm-note">This customer was deleted or merged in QuickBooks &mdash; pick the right one.</div><?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$r['link'] && $r['state'] !== 'exact'): ?>
                                    <button type="submit" form="qmCreate<?= $id ?>" class="btn btn-secondary btn-sm">Create in QuickBooks</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <div class="qm-sticky">
                    <button type="submit" class="btn btn-primary">Save matches</button>
                    <span class="qm-ref" style="margin-left:.5rem">Saves every row as shown above.</span>
                </div>
            </form>

            <?php foreach ($visible as $r): if ($r['link'] || $r['state'] === 'exact') continue; $a = $r['a']; ?>
                <form method="post" action="<?= e($self) ?>" id="qmCreate<?= (int) $a['id'] ?>" style="display:none"
                      onsubmit="return confirm(<?= e(json_encode('Create a NEW customer "' . $a['company_name'] . "\" in QuickBooks?\n\nOnly do this if it really isn't in QuickBooks already. Otherwise pick it from the list instead.")) ?>);">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="create">
                    <input type="hidden" name="account_id" value="<?= (int) $a['id'] ?>">
                    <input type="hidden" name="show" value="<?= e($show) ?>">
                </form>
            <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
