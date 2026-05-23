<?php
declare(strict_types=1);

/**
 * Migration: per-user Dashboard permission flags.
 *
 * Tenant admins (role='admin') always see the full Dashboard — these
 * flags are ignored for them. For all other users, each panel can be
 * individually enabled by the admin on /admin/users_edit.php.
 *
 * Flags added (all TINYINT(1) DEFAULT 0):
 *   dash_view_revenue  — KPI tiles + period selector header
 *   dash_view_team     — Sales-team leaderboard panel
 *   dash_view_products — Product mix panel (and its pie chart)
 *   dash_view_profit   — Gross-profit panel (further gated by
 *                        can_view_costs since it surfaces cost data)
 *   dash_view_recent   — Recent-wins activity feed
 *
 * If a user has NONE of these ticked, the Dashboard menu entry is
 * hidden and visiting /dashboard/index.php redirects back to Calendar.
 *
 * Defaults are deliberately conservative (all 0): existing non-admin
 * users see nothing until the admin actively grants access on the
 * user edit page. That's the right default for sales/finance data.
 *
 * Idempotent. Run via /migrate_dashboard_perms.php (super-admin).
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}

ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

set_exception_handler(function (Throwable $e) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "MIGRATION FAILED\n================\n\n";
    echo 'Error: ' . $e->getMessage() . "\n";
    echo 'In:    ' . $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
});

function dp_col_exists(PDO $pdo, string $col): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = "client_users"
            AND COLUMN_NAME  = ? LIMIT 1'
    );
    $st->execute([$col]);
    return $st->fetchColumn() !== false;
}

$flags = [
    'dash_view_revenue',
    'dash_view_team',
    'dash_view_products',
    'dash_view_profit',
    'dash_view_recent',
];

$ops = [];
$prevCol = 'can_view_costs';   // for AFTER clauses, keeps the schema readable

foreach ($flags as $col) {
    if (dp_col_exists($pdo, $col)) {
        $ops[] = "client_users.$col already present";
        continue;
    }
    $pdo->exec(
        "ALTER TABLE client_users
            ADD COLUMN $col TINYINT(1) NOT NULL DEFAULT 0 AFTER $prevCol"
    );
    $ops[] = "Added client_users.$col";
    $prevCol = $col;
}

echo "Migration complete:\n";
foreach ($ops as $op) echo '  - ' . $op . "\n";
echo "\n";
echo "Tenant admins (role='admin') get the full Dashboard automatically;\n";
echo "these flags only affect non-admin users.\n";
echo "\n";
echo "All non-admin users currently see NOTHING on the Dashboard. Grant\n";
echo "access per user from /admin/users_edit.php?id=<user_id>.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
