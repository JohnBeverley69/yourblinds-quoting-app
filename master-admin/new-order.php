<?php
declare(strict_types=1);

/**
 * "New order / quote" launcher (super-admin). Raise a Beverley quote and choose
 * who it's FOR:
 *   - an existing trade account  → the quote is OWNED by Beverley but the customer
 *     is the account (details auto-filled), and pricing uses the account's trade
 *     discount (pe_calculate_item's $forAccountId). It's your quote, sent TO them.
 *   - a new customer with no account → a quick Beverley quote, or open a trade
 *     account first (new-client.php?after=quote) then quote for it.
 *
 * No "acting as" — the super-admin stays themselves; only the quote records which
 * account it's for (quotes.account_client_id) so the discount + customer resolve.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers_new_order.php';

requireSuperAdmin();

$user       = current_user();
$factoryCid = (int) $user['client_id'];   // the quote is owned by the factory (Beverley)

$error = null;

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
            $error = 'Pick a credit account from the list first.';
        } else {
            try {
                $res = no_create_account_quote(db(), $factoryCid, $aid, (int) $user['user_id']);
                header('Location: /quote-builder/edit.php?id=' . $res['id'] . '#add-line');
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($mode === 'new_quick') {
        header('Location: /quote-builder/new.php');
        exit;
    } elseif ($mode === 'new_account') {
        header('Location: /master-admin/new-client.php?after=quote');
        exit;
    } else {
        $error = 'Choose who this order is for.';
    }
}

// Trade accounts = every active client that isn't the factory's own account.
$accounts = [];
try {
    $st = db()->prepare('SELECT id, company_name FROM clients WHERE id <> ? AND active = 1 ORDER BY company_name');
    $st->execute([$factoryCid]);
    $accounts = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* empty list handled in view */ }

$activeNav = '';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New order &middot; Master admin</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .no-choice { border:1px solid var(--border); border-radius:12px; padding:0.9rem 1.1rem; margin:0 0 0.85rem; background:var(--bg-subtle,#f8fafc); }
        .no-choice > label.head { display:flex; gap:0.6rem; align-items:flex-start; font-weight:700; cursor:pointer; }
        .no-choice .body { margin:0.55rem 0 0 1.8rem; display:none; }
        .no-choice input.pick:checked ~ .body { display:block; }
        .no-choice .hint { color:var(--text-faint); font-size:0.85rem; font-weight:400; margin:0.15rem 0 0 1.8rem; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">New order</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/index.php">&larr; Master Admin</a>
                    &middot; raise a quote for a trade account (their pricing, sent to them) or a new customer.
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

        <!-- For a credit account -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.4rem">Quote for a credit account</h2>
            <p style="color:var(--text-faint);font-size:0.9rem;margin:0 0 0.9rem;max-width:72ch">
                Pick the account this quote is for. Their name and address fill in as the customer, and their
                trade discount is applied to the price &mdash; so the quote shows what they pay you.
            </p>
            <form method="post" action="/master-admin/new-order.php" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="mode" value="account">
                <div class="form-row full">
                    <div class="form-group">
                        <label for="account_search">Credit account</label>
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
                    <button type="submit" class="btn btn-primary">Start quote for this account &rarr;</button>
                </div>
            </form>
        </section>

        <!-- For a new customer with no account -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.4rem">Quote for a new customer</h2>
            <p style="color:var(--text-faint);font-size:0.9rem;margin:0 0 0.9rem;max-width:72ch">Someone who isn't set up as a credit account yet.</p>
            <form method="post" action="/master-admin/new-order.php" novalidate>
                <?= csrf_field() ?>
                <div class="no-choice">
                    <label class="head">
                        <input type="radio" class="pick" name="mode" value="new_quick" checked>
                        <span>Just quote it (under Beverley Blinds Trade)</span>
                    </label>
                    <div class="hint">A straight quote at your standard pricing — the customer's name goes on the quote.</div>
                    <div class="body"><button type="submit" class="btn btn-primary">Start quote &rarr;</button></div>
                </div>
                <div class="no-choice">
                    <label class="head">
                        <input type="radio" class="pick" name="mode" value="new_account">
                        <span>Open a trade account for them first, then quote them</span>
                    </label>
                    <div class="hint">Creates the account, then drops you into a quote for it (their trade pricing from the start).</div>
                    <div class="body"><button type="submit" class="btn btn-primary">Open account &amp; quote &rarr;</button></div>
                </div>
            </form>
        </section>
    </main>
</div>
<script>
(function () {
    var search = document.getElementById('account_search');
    var hidden = document.getElementById('account_id');
    var list   = document.getElementById('account-options');
    if (!search || !hidden || !list) return;
    function sync() {
        var typed = search.value.trim(), matched = null;
        for (var i = 0; i < list.options.length; i++) {
            if (list.options[i].value === typed) { matched = list.options[i]; break; }
        }
        hidden.value = matched ? (matched.dataset.id || '0') : '0';
    }
    search.addEventListener('input', sync);
    search.addEventListener('change', sync);
})();
</script>
</body>
</html>
