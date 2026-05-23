<?php
declare(strict_types=1);

/**
 * Migration: trial-period support on client_plan_overrides.
 *
 * Adds two things:
 *   1. 'trial' as a valid override_type (alongside 'comp').
 *      Comp = free forever. Trial = free until expires_at.
 *   2. expires_at DATE NULL column. NULL means "never expires"
 *      (canonical for comps). For trials, set to start + 30 days.
 *
 * Backfills: existing rows are 'comp' with expires_at = NULL — no
 * action needed.
 *
 * After this lands, every newly-created tenant gets one 'trial' row
 * per paid plan with expires_at = today + 30 days. The Pricing page
 * shows trials separately from comps; the tenant Billing page shows
 * a countdown and a Subscribe button so they can lock in before the
 * trial lapses.
 *
 * Idempotent. Run via /migrate_trials.php (super-admin login).
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

function colInfo(PDO $pdo, string $table, string $col): ?array
{
    $st = $pdo->prepare(
        'SELECT COLUMN_TYPE, IS_NULLABLE
           FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = ?
            AND COLUMN_NAME  = ?
          LIMIT 1'
    );
    $st->execute([$table, $col]);
    return $st->fetch() ?: null;
}

$ops = [];

// ---- override_type ENUM widening ------------------------------------
$override = colInfo($pdo, 'client_plan_overrides', 'override_type');
if (!$override) {
    throw new RuntimeException(
        "client_plan_overrides.override_type missing — run "
        . "migrate_plan_pricing.php first."
    );
}
$currentType = (string) $override['COLUMN_TYPE'];
if (stripos($currentType, "'trial'") === false) {
    $pdo->exec(
        "ALTER TABLE client_plan_overrides
            MODIFY COLUMN override_type ENUM('comp','trial')
              NOT NULL DEFAULT 'comp'"
    );
    $ops[] = "Widened override_type ENUM to include 'trial'";
} else {
    $ops[] = "override_type ENUM already includes 'trial'";
}

// ---- expires_at column ----------------------------------------------
$expires = colInfo($pdo, 'client_plan_overrides', 'expires_at');
if (!$expires) {
    $pdo->exec(
        "ALTER TABLE client_plan_overrides
            ADD COLUMN expires_at DATE NULL AFTER override_type"
    );
    $ops[] = 'Added expires_at DATE NULL column';
} else {
    $ops[] = 'expires_at column already present';
}

echo "Migration complete:\n";
foreach ($ops as $op) echo '  - ' . $op . "\n";
echo "\n";
echo "Newly-created tenants will now get a 30-day free trial on every\n";
echo "paid add-on. Existing tenants are unaffected — to grant one an\n";
echo "ad-hoc trial, use /master-admin/pricing.php → Comp overrides.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
