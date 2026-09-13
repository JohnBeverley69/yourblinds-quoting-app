<?php
declare(strict_types=1);

/**
 * Unified "New" launcher (super-admin, factory side). Raise a Beverley quote and
 * choose whether it's a TRADE sale (for a trade account, or a manually-entered
 * one-off) or a RETAIL sale. The screen defaults to the tenant's configured
 * default sale type (client_settings.default_sale_type).
 *
 *   Trade + existing account → factory quote tagged account_client_id (their
 *     trade discount applies; the account is the customer).
 *   Trade + manual, "save as account" ticked → creates a no-portal trade account
 *     first (with a duplicate-name guard), then a quote for it.
 *   Trade + manual, unticked → a one-off trade quote at standard pricing
 *     (company/contact snapshotted onto the quote, marked sale_type = trade).
 *   Retail → the ordinary retail quote builder (quote-builder/new.php).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers_new_order.php';

requireSuperAdmin();

$user       = current_user();
$factoryCid = (int) $user['client_id'];   // the quote is owned by the factory (Beverley)

$error = null;

// Manual-entry field bundle (kept to re-fill the form after a validation error).
$m = [
    'company_name' => '', 'contact_name' => '', 'email' => '', 'phone' => '', 'mobile' => '',
    'address1' => '', 'address2' => '', 'town' => '', 'county' => '', 'postcode' => '',
];
$dupAccountId = 0;   // set when a "save as account" name clashes with an existing one

// Auto-start a quote for a freshly-created account (new-client.php?after=quote
// redirects here). GET so it's a clean landing.
if (($_GET['auto'] ?? '') === '1' && (int) ($_GET['account'] ?? 0) > 0) {
    try {
        $res = no_create_account_quote(db(), $factoryCid, (int) $_GET['account'], (int) $user['user_id']);
        header('Location: /quote-builder/edit.php?id=' . $res['id'] . '#add-line');
        exit;
    } catch (Throwable $e) {
        $error = 'Could not start the quote: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $mode = (string) ($_POST['mode'] ?? '');

    if ($mode === 'account') {
        $aid = (int) ($_POST['account_id'] ?? 0);
        if ($aid <= 0) {
            $error = 'Pick a trade account from the list first.';
        } else {
            try {
                $res = no_create_account_quote(db(), $factoryCid, $aid, (int) $user['user_id']);
                header('Location: /quote-builder/edit.php?id=' . $res['id'] . '#add-line');
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($mode === 'manual') {
        foreach (array_keys($m) as $k) { $m[$k] = trim((string) ($_POST[$k] ?? '')); }
        $saveAsAccount = !empty($_POST['save_as_account']);
        $force         = !empty($_POST['force_create_account']);

        if ($m['company_name'] === '' && $m['contact_name'] === '') {
            $error = 'Enter at least a company or contact name.';
        } elseif ($saveAsAccount) {
            // Warn before creating a duplicate account (soft block — a real
            // duplicate trading name can be forced through).
            $existing = $m['company_name'] !== ''
                ? no_find_account_by_name(db(), $factoryCid, $m['company_name'])
                : 0;
            if ($existing > 0 && !$force) {
                $dupAccountId = $existing;
                $error = 'An account named "' . $m['company_name'] . '" already exists.';
            } else {
                try {
                    $pdo = db();
                    $pdo->beginTransaction();
                    $newAccId = no_create_trade_account($pdo, $m);
                    $res      = no_create_account_quote($pdo, $factoryCid, $newAccId, (int) $user['user_id']);
                    $pdo->commit();
                    header('Location: /quote-builder/edit.php?id=' . $res['id'] . '#add-line');
                    exit;
                } catch (Throwable $e) {
                    if (db()->inTransaction()) db()->rollBack();
                    $error = 'Could not create the account: ' . $e->getMessage();
                }
            }
        } else {
            try {
                $res = no_create_oneoff_trade_quote(db(), $factoryCid, $m, (int) $user['user_id']);
                header('Location: /quote-builder/edit.php?id=' . $res['id'] . '#add-line');
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($mode === 'retail') {
        header('Location: /quote-builder/new.php');
        exit;
    } else {
        $error = 'Choose how to start this quote.';
    }
}

// Trade accounts = every active client that isn't the factory's own account.
$accounts = [];
try {
    $st = db()->prepare('SELECT id, company_name FROM clients WHERE id <> ? AND active = 1 ORDER BY company_name');
    $st->execute([$factoryCid]);
    $accounts = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* empty list handled in view */ }

// Tenant default sale type (guarded — column added by migrate_sale_type.php).
$defaultSaleType = 'trade';
try {
    $ds = db()->prepare('SELECT default_sale_type FROM client_settings WHERE client_id = ? LIMIT 1');
    $ds->execute([$factoryCid]);
    $v = (string) ($ds->fetchColumn() ?: '');
    if ($v === 'retail' || $v === 'trade') $defaultSaleType = $v;
} catch (Throwable $e) { /* pre-migration — default to trade on the factory */ }

// If we came back with a manual validation error, keep the manual panel open.
$manualOpen = ($error !== null && ($_POST['mode'] ?? '') === 'manual');
$startMode  = $manualOpen ? 'trade' : $defaultSaleType;

$activeNav = '';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New &middot; Master admin</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .sale-toggle { display:inline-flex; border:1px solid var(--border); border-radius:10px; overflow:hidden; margin:0 0 1.25rem; }
        .sale-toggle label { padding:0.55rem 1.4rem; font-weight:600; cursor:pointer; background:var(--bg-subtle,#f8fafc); color:var(--text-muted); user-select:none; }
        .sale-toggle label + label { border-left:1px solid var(--border); }
        .sale-toggle input { position:absolute; opacity:0; pointer-events:none; }
        .sale-toggle input:checked + label { background:#2563eb; color:#fff; }
        .pane { display:none; }
        .manual-fields { margin-top:0.75rem; border-top:1px dashed var(--border); padding-top:1rem; }
        .manual-fields[hidden] { display:none; }
        details.manual > summary { cursor:pointer; font-weight:600; color:#2563eb; margin-top:0.5rem; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">New</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/index.php">&larr; Master Admin</a>
                    &middot; start a trade sale (for an account, or one-off) or a retail sale.
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert">
                <?= e($error) ?>
                <?php if ($dupAccountId > 0): ?>
                    <div style="margin-top:0.6rem;display:flex;gap:0.6rem;flex-wrap:wrap">
                        <form method="post" action="/master-admin/new-order.php" style="margin:0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="mode" value="account">
                            <input type="hidden" name="account_id" value="<?= (int) $dupAccountId ?>">
                            <button type="submit" class="btn btn-primary btn-sm">Use the existing account</button>
                        </form>
                        <form method="post" action="/master-admin/new-order.php" style="margin:0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="mode" value="manual">
                            <input type="hidden" name="save_as_account" value="1">
                            <input type="hidden" name="force_create_account" value="1">
                            <?php foreach ($m as $k => $v): ?>
                                <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-secondary btn-sm">Create a separate account anyway</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <section class="section">
            <div class="sale-toggle" role="tablist" aria-label="Sale type">
                <input type="radio" name="sale_ui" id="sale_trade" value="trade" <?= $startMode === 'trade' ? 'checked' : '' ?>>
                <label for="sale_trade">Trade</label>
                <input type="radio" name="sale_ui" id="sale_retail" value="retail" <?= $startMode === 'retail' ? 'checked' : '' ?>>
                <label for="sale_retail">Retail</label>
            </div>

            <!-- ===================== TRADE ===================== -->
            <div class="pane" id="pane-trade">
                <h2 class="section-title" style="margin:0 0 0.4rem">Trade sale</h2>
                <p style="color:var(--text-faint);font-size:0.9rem;margin:0 0 0.9rem;max-width:72ch">
                    Pick the trade account this is for &mdash; their details fill in as the customer and their
                    trade discount is applied. Not on the list? Enter them below.
                </p>

                <form method="post" action="/master-admin/new-order.php" class="form" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="mode" value="account">
                    <div class="form-row full">
                        <div class="form-group">
                            <label for="account_search">Trade account</label>
                            <input type="text" id="account_search" list="account-options" autocomplete="off"
                                   placeholder="Type to search accounts by name…">
                            <input type="hidden" id="account_id" name="account_id" value="0">
                            <datalist id="account-options">
                                <?php foreach ($accounts as $a): ?>
                                    <option value="<?= e((string) $a['company_name']) ?>" data-id="<?= (int) $a['id'] ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <small style="color:var(--text-faint);font-size:0.8125rem"><?= count($accounts) ?> accounts — start typing to filter.</small>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" id="account_submit" class="btn btn-primary" disabled>Start quote for this account &rarr;</button>
                    </div>
                </form>

                <details class="manual" <?= $manualOpen ? 'open' : '' ?>>
                    <summary>Customer not listed? Enter them manually</summary>
                    <form method="post" action="/master-admin/new-order.php" class="form manual-fields" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="mode" value="manual">
                        <div class="form-row cols-2">
                            <div class="form-group">
                                <label for="company_name">Company name</label>
                                <input id="company_name" name="company_name" type="text" maxlength="150" value="<?= e($m['company_name']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="contact_name">Contact name</label>
                                <input id="contact_name" name="contact_name" type="text" maxlength="150" value="<?= e($m['contact_name']) ?>">
                            </div>
                        </div>
                        <div class="form-row cols-epm">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input id="email" name="email" type="email" maxlength="150" value="<?= e($m['email']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone <span style="color:var(--text-faint);font-weight:400">(landline)</span></label>
                                <input id="phone" name="phone" type="tel" maxlength="50" value="<?= e($m['phone']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="mobile">Mobile <span style="color:var(--text-faint);font-weight:400">(WhatsApp)</span></label>
                                <input id="mobile" name="mobile" type="tel" maxlength="40" value="<?= e($m['mobile']) ?>">
                            </div>
                        </div>
                        <div class="form-row cols-2">
                            <div class="form-group">
                                <label for="address1">Address line 1</label>
                                <input id="address1" name="address1" type="text" maxlength="150" value="<?= e($m['address1']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="address2">Address line 2</label>
                                <input id="address2" name="address2" type="text" maxlength="150" value="<?= e($m['address2']) ?>">
                            </div>
                        </div>
                        <div class="form-row cols-3">
                            <div class="form-group">
                                <label for="town">Town</label>
                                <input id="town" name="town" type="text" maxlength="100" value="<?= e($m['town']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="county">County</label>
                                <input id="county" name="county" type="text" maxlength="100" value="<?= e($m['county']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="postcode">Postcode</label>
                                <input id="postcode" name="postcode" type="text" maxlength="20" value="<?= e($m['postcode']) ?>">
                            </div>
                        </div>
                        <label style="display:inline-flex;align-items:center;gap:0.5rem;margin:0.25rem 0 0.75rem;font-weight:500;cursor:pointer">
                            <input type="checkbox" name="save_as_account" value="1">
                            Save as a new trade account (so their pricing &amp; details are kept for next time)
                        </label>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Start trade quote &rarr;</button>
                        </div>
                        <p style="color:var(--text-faint);font-size:0.8125rem;margin:0.4rem 0 0">
                            Left un-ticked, this is a one-off at your standard pricing (no trade discount).
                        </p>
                    </form>
                </details>
            </div>

            <!-- ===================== RETAIL ===================== -->
            <div class="pane" id="pane-retail">
                <h2 class="section-title" style="margin:0 0 0.4rem">Retail sale</h2>
                <p style="color:var(--text-faint);font-size:0.9rem;margin:0 0 0.9rem;max-width:72ch">
                    A direct retail customer at your standard pricing. You'll add their details in the quote.
                </p>
                <form method="post" action="/master-admin/new-order.php" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="mode" value="retail">
                    <div class="form-actions"><button type="submit" class="btn btn-primary">Start retail quote &rarr;</button></div>
                </form>
            </div>
        </section>
    </main>
</div>
<script>
(function () {
    // Sale-type toggle → show the matching pane.
    var panes = { trade: document.getElementById('pane-trade'), retail: document.getElementById('pane-retail') };
    function showPane(which) {
        for (var k in panes) if (panes[k]) panes[k].style.display = (k === which) ? 'block' : 'none';
    }
    var radios = document.querySelectorAll('input[name="sale_ui"]');
    for (var i = 0; i < radios.length; i++) {
        radios[i].addEventListener('change', function () { showPane(this.value); });
    }
    var checked = document.querySelector('input[name="sale_ui"]:checked');
    showPane(checked ? checked.value : 'trade');

    // Account typeahead → only enable submit on an exact match (avoids a silent
    // account_id=0 submit and accidental duplicates).
    var search = document.getElementById('account_search');
    var hidden = document.getElementById('account_id');
    var submit = document.getElementById('account_submit');
    var list   = document.getElementById('account-options');
    if (search && hidden && list) {
        function sync() {
            var typed = search.value.trim(), matched = null;
            for (var i = 0; i < list.options.length; i++) {
                if (list.options[i].value === typed) { matched = list.options[i]; break; }
            }
            hidden.value = matched ? (matched.dataset.id || '0') : '0';
            if (submit) submit.disabled = !matched;
        }
        search.addEventListener('input', sync);
        search.addEventListener('change', sync);
    }
})();
</script>
</body>
</html>
