<?php
declare(strict_types=1);

/**
 * "New order / quote" launcher (super-admin). Pick WHO the quote is for, then drop
 * into the normal quote builder acting as them:
 *   - an existing trade account (searchable)  → build as them; their pricing +
 *     discounts apply and the quote lands in their portal.
 *   - a new customer with no account          → either a quick quote under
 *     Beverley, or open a trade account first (then quote as the new account).
 *
 * The "acting as" itself lives in the session (auth/middleware.php). Every quote-
 * owning + pricing path reads acting_client_id(), so nothing here needs to touch
 * the builder beyond setting the flag and redirecting.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

$user = current_user();

// Stop acting (also reachable from the sidebar banner).
if (($_GET['stop'] ?? '') !== '') {
    clear_acting_client();
    $_SESSION['flash_success'] = 'Stopped acting — back on your own account.';
    header('Location: /master-admin/new-order.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $mode = (string) ($_POST['mode'] ?? '');

    if ($mode === 'account') {
        $aid = (int) ($_POST['account_id'] ?? 0);
        if ($aid <= 0) {
            $error = 'Pick a credit account from the list first.';
        } elseif (!set_acting_client($aid)) {
            $error = 'That account could not be selected — pick one from the list.';
        } else {
            header('Location: /quote-builder/new.php');
            exit;
        }
    } elseif ($mode === 'new_quick') {
        clear_acting_client();   // a straight Beverley quote
        header('Location: /quote-builder/new.php');
        exit;
    } elseif ($mode === 'new_account') {
        clear_acting_client();   // new-client.php will set acting on success
        header('Location: /master-admin/new-client.php?after=quote');
        exit;
    } else {
        $error = 'Choose who this order is for.';
    }
}

$flash = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

// Trade accounts = every active client that isn't the factory's own account.
$own      = (int) $user['client_id'];
$accounts = [];
try {
    $st = db()->prepare('SELECT id, company_name FROM clients WHERE id <> ? AND active = 1 ORDER BY company_name');
    $st->execute([$own]);
    $accounts = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* empty list handled in view */ }

$acting = acting_client();

$activeNav = '';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New order &middot; Master admin</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .no-choice { border:1px solid var(--border); border-radius:12px; padding:1rem 1.15rem; margin:0 0 1rem; background:var(--bg-subtle,#f8fafc); }
        .no-choice > label.head { display:flex; gap:0.6rem; align-items:flex-start; font-weight:700; cursor:pointer; }
        .no-choice .body { margin:0.6rem 0 0 1.8rem; display:none; }
        .no-choice input.pick:checked ~ .body { display:block; }
        .no-choice .sub { display:flex; gap:0.5rem; align-items:flex-start; margin:0.5rem 0; font-weight:400; cursor:pointer; }
        .no-choice .hint { color:var(--text-faint); font-size:0.85rem; font-weight:400; margin:0.15rem 0 0 1.8rem; }
        .no-actions { margin-left:1.8rem; }
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
                    &middot; raise a quote or order on behalf of a trade account, or for a new customer.
                </p>
            </div>
        </div>

        <?php if ($flash !== null): ?><div class="alert alert-success" role="status"><?= e((string) $flash) ?></div><?php endif; ?>
        <?php if ($error !== null): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

        <?php if ($acting): ?>
            <div class="alert" role="status" style="background:#fef3c7;border:1px solid #fcd34d;color:#92400e">
                You're currently quoting as <strong><?= e($acting['name']) ?></strong>.
                <a href="/quote-builder/new.php" style="font-weight:700">Continue their quote</a>
                &middot; <a href="/master-admin/new-order.php?stop=1">Stop</a>
            </div>
        <?php endif; ?>

        <!-- For a credit account -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.4rem">Quote for a credit account</h2>
            <p style="color:var(--text-faint);font-size:0.9rem;margin:0 0 0.9rem;max-width:70ch">
                Pick the trade account this quote is for. The builder opens acting as them — their discounts and
                pricing apply automatically, and the finished quote appears in their own portal.
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
            <p style="color:var(--text-faint);font-size:0.9rem;margin:0 0 0.9rem;max-width:70ch">
                Someone who isn't set up as a credit account yet.
            </p>
            <form method="post" action="/master-admin/new-order.php" novalidate>
                <?= csrf_field() ?>
                <div class="no-choice">
                    <label class="head">
                        <input type="radio" class="pick" name="mode" value="new_quick" checked>
                        <span>Just quote it (under Beverley Blinds Trade)</span>
                    </label>
                    <div class="hint">A straight quote at your standard pricing — the customer's name goes on the quote. Best for one-off / cash enquiries.</div>
                    <div class="body"><div class="no-actions"><button type="submit" class="btn btn-primary">Start quote &rarr;</button></div></div>
                </div>
                <div class="no-choice">
                    <label class="head">
                        <input type="radio" class="pick" name="mode" value="new_account">
                        <span>Open a trade account for them first, then quote as them</span>
                    </label>
                    <div class="hint">Creates the account (name + login), then drops you into a quote acting as them — so it's their quote from the start.</div>
                    <div class="body"><div class="no-actions"><button type="submit" class="btn btn-primary">Open account &amp; quote &rarr;</button></div></div>
                </div>
            </form>
        </section>
    </main>
</div>
<script>
(function () {
    // Account typeahead: map the picked name back to its client id.
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
