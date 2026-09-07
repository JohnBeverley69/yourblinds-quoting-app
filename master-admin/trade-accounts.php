<?php
declare(strict_types=1);

/**
 * Master Admin: Trade Accounts — the control roster for our ~100 trade accounts.
 *
 * One place to see every account (a `clients` row) with its business/contact
 * details, portal status, plan, discount count and activity — searchable — and
 * drill into a single account to edit its details, manage its logins (1B) and
 * set its discounts (1C).
 *
 * Read-only here; all mutation happens on the per-account detail page
 * (trade-account.php). Super-admin only.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/billing_helpers.php';

requireSuperAdmin();

$user     = current_user();
$pdo      = db();
$myClient = (int) $user['client_id'];

/** Schema probes — some columns/tables vary by DB age; degrade gracefully. */
$colExists = static function (string $table, string $col) use ($pdo): bool {
    try {
        $st = $pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $col]);
        return $st->fetchColumn() !== false;
    } catch (Throwable $e) { return false; }
};
$tableExists = static function (string $table) use ($pdo): bool {
    try {
        $st = $pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);
        return $st->fetchColumn() !== false;
    } catch (Throwable $e) { return false; }
};

$hasCreated   = $colExists('clients', 'created_at');
$hasDiscounts = $tableExists('trade_discounts');

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Per-account roster (scalar subqueries — fine at ~100 accounts). ─────────
$loadError = null;
$accounts  = [];
try {
    $sql = 'SELECT c.id, c.company_name, c.active,
                   c.contact_name, c.email, c.phone,
                   ' . ($hasCreated ? 'c.created_at' : 'NULL AS created_at') . ',
                   (SELECT COUNT(*) FROM client_users u WHERE u.client_id = c.id) AS users,
                   (SELECT MAX(u.last_login_at) FROM client_users u WHERE u.client_id = c.id) AS last_login,
                   (SELECT COUNT(*) FROM products p WHERE p.client_id = c.id) AS products,
                   ' . ($hasDiscounts
                        ? '(SELECT COUNT(*) FROM trade_discounts d WHERE d.client_id = c.id) AS discounts'
                        : '0 AS discounts') . '
              FROM clients c
          ORDER BY c.company_name';
    $accounts = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $loadError = $e->getMessage();
}

// Current plan tier per account.
foreach ($accounts as &$a) {
    $a['plan'] = (string) (billing_plan(billing_current_tier_code((int) $a['id']))['name'] ?? 'Bronze');
}
unset($a);

$totAccounts = count($accounts);
$totActive   = 0; $totPortal = 0; $totNoPortal = 0;
foreach ($accounts as $a) {
    if ((int) $a['active'] === 1) $totActive++;
    if ((int) $a['users'] > 0) $totPortal++; else $totNoPortal++;
}

$fmtAgo = static function (?string $ts): string {
    if (!$ts) return 'never';
    $t = strtotime($ts);
    if ($t === false) return 'never';
    $days = (int) floor((time() - $t) / 86400);
    if ($days <= 0)  return 'today';
    if ($days === 1) return 'yesterday';
    if ($days < 30)  return $days . ' days ago';
    if ($days < 60)  return 'a month ago';
    return floor($days / 30) . ' months ago';
};

$activeNav = 'trade-accounts';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trade Accounts &middot; Master admin</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .ta-grid { display:grid; gap:0.75rem; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); margin-bottom:1rem; }
        .ta-stat { background:var(--bg-card); border:1px solid var(--border); border-radius:10px; padding:0.75rem 1rem; }
        .ta-stat .v { font-size:1.4rem; font-weight:800; color:var(--text-primary); line-height:1.1; }
        .ta-stat .l { color:var(--text-faint); font-size:0.8125rem; margin-top:0.2rem; }
        .ta-searchbar { display:flex; gap:0.625rem; align-items:center; margin:0 0 0.75rem; flex-wrap:wrap; }
        .ta-searchbar input { flex:1 1 20rem; max-width:30rem; padding:0.5rem 0.7rem; border:1px solid var(--border-strong); border-radius:8px; font:inherit; font-size:0.9375rem; background:var(--bg-input); }
        .ta-count { font-size:0.875rem; color:var(--text-faint); }
        .ta-badge { display:inline-block; margin-left:0.4rem; padding:0.0625rem 0.5rem; font-size:0.6875rem; font-weight:700; border-radius:999px; text-transform:uppercase; letter-spacing:0.04em; }
        .ta-badge.you { background:#1f3b5b; color:#fff; }
        .ta-badge.inactive { background:var(--bg-subtle-2); color:var(--text-faint); }
        .ta-badge.noportal { background:#fef3c7; color:#92400e; }
        .ta-badge.portal { background:#d1fae5; color:#065f46; }
        .ta-name a { text-decoration:none; color:var(--text-primary); font-weight:700; }
        .ta-name a:hover { text-decoration:underline; }
        .ta-sub { color:var(--text-faint); font-size:0.8125rem; }
        tr.is-hidden { display:none; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Trade Accounts</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/index.php">&larr; Master Admin</a>
                    &middot; your trade accounts &mdash; details, logins and discounts in one place.
                </p>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
                <a href="/master-admin/new-client.php" class="btn btn-primary">+ New account</a>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?><div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div><?php endif; ?>
        <?php if ($flashErr !== null): ?><div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div><?php endif; ?>
        <?php if ($loadError !== null): ?><div class="alert alert-error" role="alert">Could not load accounts: <?= e($loadError) ?></div><?php endif; ?>

        <section class="section">
            <div class="ta-grid">
                <div class="ta-stat"><div class="v"><?= (int) $totAccounts ?></div><div class="l">Accounts</div></div>
                <div class="ta-stat"><div class="v"><?= (int) $totActive ?></div><div class="l">Active</div></div>
                <div class="ta-stat"><div class="v"><?= (int) $totPortal ?></div><div class="l">With portal login</div></div>
                <div class="ta-stat"><div class="v"><?= (int) $totNoPortal ?></div><div class="l">No-portal (one-offs)</div></div>
            </div>

            <div class="ta-searchbar">
                <input type="search" id="ta-search" placeholder="Search accounts — name, contact, email…" autocomplete="off" autofocus>
                <span class="ta-count" id="ta-search-count"><?= (int) $totAccounts ?> accounts</span>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th>Contact</th>
                            <th>Portal</th>
                            <th>Plan</th>
                            <th style="text-align:right">Discounts</th>
                            <th>Last login</th>
                            <th style="text-align:right">Products</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$accounts && $loadError === null): ?>
                            <tr><td colspan="8" class="table-empty">No accounts yet.</td></tr>
                        <?php else: foreach ($accounts as $a):
                            $ll       = $a['last_login'] ? strtotime((string) $a['last_login']) : 0;
                            $hasLogin = (int) $a['users'] > 0;
                            $blob = strtolower(trim(implode(' ', array_filter([
                                (string) $a['company_name'], (string) ($a['contact_name'] ?? ''),
                                (string) ($a['email'] ?? ''), (string) ($a['phone'] ?? ''),
                            ]))));
                        ?>
                            <tr data-search="<?= e($blob) ?>">
                                <td class="ta-name">
                                    <a href="/master-admin/trade-account.php?id=<?= (int) $a['id'] ?>"><?= e((string) $a['company_name']) ?></a>
                                    <?php if ((int) $a['id'] === $myClient): ?><span class="ta-badge you">You</span><?php endif; ?>
                                    <?php if ((int) $a['active'] !== 1): ?><span class="ta-badge inactive">Inactive</span><?php endif; ?>
                                </td>
                                <td>
                                    <?php if (($a['contact_name'] ?? '') !== ''): ?><?= e((string) $a['contact_name']) ?><br><?php endif; ?>
                                    <span class="ta-sub"><?= e((string) ($a['email'] ?? '')) ?></span>
                                </td>
                                <td>
                                    <?php if ($hasLogin): ?>
                                        <span class="ta-badge portal"><?= (int) $a['users'] ?> login<?= (int) $a['users'] === 1 ? '' : 's' ?></span>
                                    <?php else: ?>
                                        <span class="ta-badge noportal">No portal</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e((string) $a['plan']) ?></td>
                                <td style="text-align:right"><?= (int) $a['discounts'] ?: '&mdash;' ?></td>
                                <td title="<?= $ll ? e(date('j M Y H:i', $ll)) : 'never' ?>"><?= e($fmtAgo($a['last_login'] ? (string) $a['last_login'] : null)) ?></td>
                                <td style="text-align:right"><?= number_format((int) $a['products']) ?></td>
                                <td style="text-align:right"><a href="/master-admin/trade-account.php?id=<?= (int) $a['id'] ?>">Manage &rarr;</a></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<script>
(function () {
    var search = document.getElementById('ta-search');
    var count  = document.getElementById('ta-search-count');
    if (!search) return;
    var rows = Array.prototype.slice.call(document.querySelectorAll('tbody tr[data-search]'));
    var total = rows.length;
    function apply() {
        var q = (search.value || '').trim().toLowerCase();
        var words = q ? q.split(/\s+/) : [];
        var shown = 0;
        rows.forEach(function (tr) {
            var hay = tr.getAttribute('data-search') || '';
            var match = words.every(function (w) { return hay.indexOf(w) !== -1; });
            tr.classList.toggle('is-hidden', !match);
            if (match) shown++;
        });
        if (count) count.textContent = q ? ('Showing ' + shown + ' of ' + total) : (total + ' accounts');
    }
    search.addEventListener('input', apply);
})();
</script>
</body>
</html>
