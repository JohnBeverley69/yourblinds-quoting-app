<?php
declare(strict_types=1);

/**
 * Master Admin: Resource & Activity Monitor (read-only).
 *
 * One dashboard: platform totals + data/resource size at the top, then a
 * per-tenant activity table (plan, users, last login, quotes, customers,
 * products) with quiet tenants flagged. Changes nothing.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/billing_helpers.php';

requireSuperAdmin();

$user     = current_user();
$pdo      = db();
$myClient = (int) $user['client_id'];

/** Does a column exist? (some timestamp columns vary by schema age.) */
$colExists = static function (string $table, string $col) use ($pdo): bool {
    try {
        $st = $pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $col]);
        return $st->fetchColumn() !== false;
    } catch (Throwable $e) {
        return false;
    }
};

$hasClientCreated = $colExists('clients', 'created_at');
$hasQuoteCreated  = $colExists('quotes', 'created_at');

// ── Per-tenant aggregates (scalar subqueries; fine for the tenant count
//    a platform like this has). created_at metrics included only when the
//    column exists. ─────────────────────────────────────────────────────
$loadError = null;
$tenants   = [];
try {
    $sql = 'SELECT c.id, c.company_name, c.active,
                   ' . ($hasClientCreated ? 'c.created_at' : 'NULL AS created_at') . ',
                   (SELECT COUNT(*)            FROM client_users u WHERE u.client_id = c.id) AS users,
                   (SELECT MAX(u.last_login_at) FROM client_users u WHERE u.client_id = c.id) AS last_login,
                   (SELECT COUNT(*)            FROM quotes q       WHERE q.client_id = c.id) AS quotes,
                   ' . ($hasQuoteCreated
                            ? '(SELECT COUNT(*) FROM quotes q WHERE q.client_id = c.id AND q.created_at >= (NOW() - INTERVAL 30 DAY)) AS quotes_30d'
                            : '0 AS quotes_30d') . ',
                   (SELECT COUNT(*)            FROM customers cu   WHERE cu.client_id = c.id) AS customers,
                   (SELECT COUNT(*)            FROM products p     WHERE p.client_id = c.id) AS products
              FROM clients c
          ORDER BY c.company_name';
    $tenants = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $loadError = $e->getMessage();
}

// Current plan tier per tenant (subscription or comp).
foreach ($tenants as &$t) {
    $t['plan'] = (string) (billing_plan(billing_current_tier_code((int) $t['id']))['name'] ?? 'Bronze');
}
unset($t);

// Sort: most recently active first; never-logged-in to the bottom.
usort($tenants, static function ($a, $b) {
    $la = $a['last_login'] ? strtotime((string) $a['last_login']) : 0;
    $lb = $b['last_login'] ? strtotime((string) $b['last_login']) : 0;
    return $lb <=> $la;
});

// ── Platform totals (summed from the per-tenant rows). ───────────────────
$totTenants   = count($tenants);
$totActive    = 0;
$totUsers     = 0;
$totQuotes    = 0;
$totQuotes30  = 0;
$totCustomers = 0;
$totProducts  = 0;
$quietCount   = 0;
$newTenants30 = 0;
$thirtyAgo    = strtotime('-30 days');
foreach ($tenants as $t) {
    if ((int) $t['active'] === 1) $totActive++;
    $totUsers     += (int) $t['users'];
    $totQuotes    += (int) $t['quotes'];
    $totQuotes30  += (int) $t['quotes_30d'];
    $totCustomers += (int) $t['customers'];
    $totProducts  += (int) $t['products'];
    $ll = $t['last_login'] ? strtotime((string) $t['last_login']) : 0;
    if ($ll === 0 || $ll < $thirtyAgo) $quietCount++;
    if ($hasClientCreated && $t['created_at'] && strtotime((string) $t['created_at']) >= $thirtyAgo) $newTenants30++;
}

// ── Database size + biggest tables. ──────────────────────────────────────
$dbSizeMb = null;
$bigTables = [];
try {
    $dbSizeMb = (float) $pdo->query(
        'SELECT ROUND(SUM(data_length + index_length) / 1048576, 1)
           FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()'
    )->fetchColumn();
    $bigTables = $pdo->query(
        'SELECT TABLE_NAME AS name,
                ROUND((data_length + index_length) / 1048576, 2) AS mb,
                table_rows AS row_est
           FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE()
       ORDER BY (data_length + index_length) DESC
          LIMIT 10'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* leave nulls */ }

// ── Uploads folder size (rough — sums file sizes under /uploads). ────────
$uploadsMb = null;
try {
    $dir = realpath(__DIR__ . '/../uploads');
    if ($dir !== false && is_dir($dir)) {
        $bytes = 0;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->isFile()) $bytes += $f->getSize();
        }
        $uploadsMb = round($bytes / 1048576, 1);
    }
} catch (Throwable $e) { /* leave null */ }

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

$activeNav = 'monitor';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monitor &middot; Master admin</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .mon-grid {
            display: grid; gap: 0.75rem;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            margin-bottom: 1rem;
        }
        .mon-stat {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px;
            padding: 0.875rem 1rem;
        }
        .mon-stat .v { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); line-height: 1.1; }
        .mon-stat .l { color: var(--text-faint); font-size: 0.8125rem; margin-top: 0.25rem; }
        .mon-stat .sub { color: var(--text-muted); font-size: 0.75rem; margin-top: 0.125rem; }
        .quiet-row td { color: var(--text-faint); }
        .quiet-badge {
            display: inline-block; margin-left: 0.4rem; padding: 0.0625rem 0.5rem;
            font-size: 0.6875rem; font-weight: 700; border-radius: 999px;
            background: #fef3c7; color: #92400e; text-transform: uppercase; letter-spacing: 0.04em;
        }
        .you-badge {
            display: inline-block; margin-left: 0.4rem; padding: 0.0625rem 0.5rem;
            font-size: 0.6875rem; font-weight: 700; border-radius: 999px;
            background: #1f3b5b; color: #fff; text-transform: uppercase; letter-spacing: 0.04em;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Resource &amp; activity monitor</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/index.php">&larr; Master Admin</a>
                    &middot; platform totals, data size, and per-tenant activity. Read-only.
                </p>
            </div>
        </div>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-error" role="alert">Could not load activity: <?= e($loadError) ?></div>
        <?php endif; ?>

        <!-- Platform totals -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.75rem">Platform</h2>
            <div class="mon-grid">
                <div class="mon-stat">
                    <div class="v"><?= (int) $totTenants ?></div>
                    <div class="l">Tenants</div>
                    <div class="sub"><?= (int) $totActive ?> active · <?= (int) ($totTenants - $totActive) ?> inactive</div>
                </div>
                <div class="mon-stat">
                    <div class="v"><?= (int) $quietCount ?></div>
                    <div class="l">Quiet tenants</div>
                    <div class="sub">no login in 30 days</div>
                </div>
                <?php if ($hasClientCreated): ?>
                    <div class="mon-stat">
                        <div class="v"><?= (int) $newTenants30 ?></div>
                        <div class="l">New tenants</div>
                        <div class="sub">last 30 days</div>
                    </div>
                <?php endif; ?>
                <div class="mon-stat">
                    <div class="v"><?= number_format($totUsers) ?></div>
                    <div class="l">Users</div>
                </div>
                <div class="mon-stat">
                    <div class="v"><?= number_format($totQuotes) ?></div>
                    <div class="l">Quotes</div>
                    <?php if ($hasQuoteCreated): ?><div class="sub"><?= number_format($totQuotes30) ?> in last 30 days</div><?php endif; ?>
                </div>
                <div class="mon-stat">
                    <div class="v"><?= number_format($totCustomers) ?></div>
                    <div class="l">Customers</div>
                </div>
                <div class="mon-stat">
                    <div class="v"><?= number_format($totProducts) ?></div>
                    <div class="l">Products</div>
                </div>
                <div class="mon-stat">
                    <div class="v"><?= $dbSizeMb !== null ? number_format($dbSizeMb, 1) . ' MB' : '—' ?></div>
                    <div class="l">Database size</div>
                </div>
                <div class="mon-stat">
                    <div class="v"><?= $uploadsMb !== null ? number_format($uploadsMb, 1) . ' MB' : '—' ?></div>
                    <div class="l">Uploads</div>
                </div>
                <div class="mon-stat">
                    <div class="v" style="font-size:1rem"><?= e(PHP_VERSION) ?></div>
                    <div class="l">PHP</div>
                    <div class="sub">mem <?= e((string) ini_get('memory_limit')) ?> · upload <?= e((string) ini_get('upload_max_filesize')) ?></div>
                </div>
            </div>
        </section>

        <!-- Per-tenant activity -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.5rem">Tenant activity</h2>
            <p style="color:var(--text-faint);font-size:0.8125rem;margin:0 0 0.75rem">
                Sorted by most recently active. Tenants with no login in 30 days are flagged
                <span class="quiet-badge">Quiet</span>.
            </p>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Plan</th>
                            <th style="text-align:right">Users</th>
                            <th>Last login</th>
                            <th style="text-align:right">Quotes<?= $hasQuoteCreated ? ' (30d)' : '' ?></th>
                            <th style="text-align:right">Customers</th>
                            <th style="text-align:right">Products</th>
                            <?php if ($hasClientCreated): ?><th>Joined</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$tenants): ?>
                            <tr><td colspan="<?= $hasClientCreated ? 8 : 7 ?>" class="table-empty">No tenants.</td></tr>
                        <?php else: foreach ($tenants as $t):
                            $ll = $t['last_login'] ? strtotime((string) $t['last_login']) : 0;
                            $isQuiet = ($ll === 0 || $ll < $thirtyAgo);
                        ?>
                            <tr class="<?= $isQuiet ? 'quiet-row' : '' ?>">
                                <td>
                                    <strong><?= e((string) $t['company_name']) ?></strong>
                                    <?php if ((int) $t['id'] === $myClient): ?><span class="you-badge">You</span><?php endif; ?>
                                    <?php if ((int) $t['active'] !== 1): ?>
                                        <span style="color:var(--text-faint);font-size:.6875rem;font-weight:600;text-transform:uppercase;margin-left:.4rem">Inactive</span>
                                    <?php endif; ?>
                                    <?php if ($isQuiet): ?><span class="quiet-badge">Quiet</span><?php endif; ?>
                                </td>
                                <td><?= e((string) $t['plan']) ?></td>
                                <td style="text-align:right"><?= (int) $t['users'] ?></td>
                                <td title="<?= $t['last_login'] ? e(date('j M Y H:i', $ll)) : 'never' ?>"><?= e($fmtAgo($t['last_login'] ? (string) $t['last_login'] : null)) ?></td>
                                <td style="text-align:right">
                                    <?= number_format((int) $t['quotes']) ?><?php if ($hasQuoteCreated): ?>
                                        <span style="color:var(--text-faint)"> (<?= number_format((int) $t['quotes_30d']) ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right"><?= number_format((int) $t['customers']) ?></td>
                                <td style="text-align:right"><?= number_format((int) $t['products']) ?></td>
                                <?php if ($hasClientCreated): ?>
                                    <td><?= $t['created_at'] ? e(date('j M Y', strtotime((string) $t['created_at']))) : '—' ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Biggest tables -->
        <?php if ($bigTables): ?>
            <section class="section">
                <h2 class="section-title" style="margin:0 0 0.5rem">Largest tables</h2>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Table</th><th style="text-align:right">Size</th><th style="text-align:right">Rows (approx)</th></tr></thead>
                        <tbody>
                            <?php foreach ($bigTables as $bt): ?>
                                <tr>
                                    <td><code><?= e((string) $bt['name']) ?></code></td>
                                    <td style="text-align:right"><?= number_format((float) $bt['mb'], 2) ?> MB</td>
                                    <td style="text-align:right"><?= number_format((int) $bt['row_est']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p style="color:var(--text-faint);font-size:0.75rem;margin:0.5rem 0 0">
                    Row counts are MySQL's own estimate (InnoDB) and can be approximate.
                </p>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
