<?php
declare(strict_types=1);

/**
 * Migration: multi-add-on subscriptions.
 *
 * Phase 1 of billing assumed one subscription per tenant (UNIQUE
 * client_id on tenant_subscriptions). This worked when "Accounts" was
 * the only paid plan. To support multiple add-ons running side-by-side
 * — Maps + Postcode + Accounts as three separate PayPal subscriptions
 * — we relax that unique key to (client_id, plan_code).
 *
 * It also re-seeds the plan_pricing table so newly-added plans (maps,
 * postcode_lookup) get rows with the registry's default price. Run it
 * any time you add a new plan to _partials/billing_plans.php to bring
 * its plan_pricing row into existence.
 *
 * Idempotent — safe to re-run. Inspects the live key set before
 * altering, and uses INSERT IGNORE for the seed step.
 *
 * Run via /migrate_addon_subscriptions.php (super-admin login).
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

function ts_has_unique_on_client_id_only(PDO $pdo): bool
{
    $rows = $pdo->query(
        "SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE
           FROM INFORMATION_SCHEMA.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'tenant_subscriptions'
            AND NON_UNIQUE   = 0
          ORDER BY INDEX_NAME, SEQ_IN_INDEX"
    )->fetchAll();

    // Group by INDEX_NAME, then check whether the (client_id) one
    // exists as a single-column unique that we need to widen.
    $byIndex = [];
    foreach ($rows as $r) {
        $byIndex[$r['INDEX_NAME']][] = $r['COLUMN_NAME'];
    }
    foreach ($byIndex as $name => $cols) {
        if ($cols === ['client_id'] && $name !== 'PRIMARY') return true;
    }
    return false;
}

function ts_has_unique_client_plan(PDO $pdo): bool
{
    $rows = $pdo->query(
        "SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX
           FROM INFORMATION_SCHEMA.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'tenant_subscriptions'
            AND NON_UNIQUE   = 0
          ORDER BY INDEX_NAME, SEQ_IN_INDEX"
    )->fetchAll();

    $byIndex = [];
    foreach ($rows as $r) $byIndex[$r['INDEX_NAME']][] = $r['COLUMN_NAME'];
    foreach ($byIndex as $name => $cols) {
        if ($cols === ['client_id', 'plan_code']) return true;
    }
    return false;
}

$ops = [];

// ---- tenant_subscriptions UNIQUE key swap ----------------------------
// Goal: replace UNIQUE(client_id) with UNIQUE(client_id, plan_code) so
// one tenant can hold multiple subscriptions (one per add-on).
//
// Order matters! There's an FK on tenant_subscriptions.client_id →
// clients.id, which MySQL satisfies via whatever index happens to
// cover client_id. If the old UNIQUE(client_id) is the only such
// index, dropping it errors with "Cannot drop index … needed in a
// foreign key constraint." So we ADD the new composite unique FIRST
// (its leftmost prefix is client_id, which keeps the FK happy), and
// only THEN drop the old single-column unique.

if (!ts_has_unique_client_plan($pdo)) {
    $pdo->exec(
        "ALTER TABLE tenant_subscriptions
            ADD UNIQUE KEY uniq_tenant_plan (client_id, plan_code)"
    );
    $ops[] = 'Added UNIQUE(client_id, plan_code) — multi-add-on subs now legal';
} else {
    $ops[] = 'UNIQUE(client_id, plan_code) already present';
}

if (ts_has_unique_on_client_id_only($pdo)) {
    $st = $pdo->query(
        "SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
           FROM INFORMATION_SCHEMA.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'tenant_subscriptions'
            AND NON_UNIQUE   = 0
            AND INDEX_NAME  != 'PRIMARY'
          GROUP BY INDEX_NAME"
    );
    $oldKeyName = null;
    foreach ($st->fetchAll() as $row) {
        if ($row['cols'] === 'client_id') {
            $oldKeyName = (string) $row['INDEX_NAME'];
            break;
        }
    }
    if ($oldKeyName) {
        $pdo->exec(
            "ALTER TABLE tenant_subscriptions DROP INDEX `$oldKeyName`"
        );
        $ops[] = "Dropped UNIQUE($oldKeyName) on (client_id) — FK now satisfied by composite key";
    }
}

// ---- Re-seed plan_pricing from the registry --------------------------
// Picks up newly-added plans (maps, postcode_lookup as of writing) with
// their default prices. Existing rows stay untouched (INSERT IGNORE),
// so any admin-edited price isn't reset.
require_once __DIR__ . '/_partials/billing_helpers.php';
$staticPlans = require __DIR__ . '/_partials/billing_plans.php';

$ins = $pdo->prepare(
    'INSERT IGNORE INTO plan_pricing
        (plan_code, price_gbp_monthly, paypal_plan_id, notes)
        VALUES (?, ?, NULL, ?)'
);
$seeded = 0;
$newCodes = [];
foreach ($staticPlans as $code => $plan) {
    if ($code === 'free') continue;
    $price = (float) ($plan['price_gbp_monthly'] ?? 0);
    $notes = 'Seeded by migrate_addon_subscriptions on ' . date('Y-m-d');
    $ins->execute([$code, $price, $notes]);
    if ($ins->rowCount() > 0) {
        $seeded++;
        $newCodes[] = $code;
    }
}
if ($seeded > 0) {
    $ops[] = "Seeded $seeded new plan_pricing row(s): " . implode(', ', $newCodes);
} else {
    $ops[] = 'No new plan_pricing rows needed (all plans already seeded)';
}

echo "Migration complete:\n";
foreach ($ops as $op) echo '  - ' . $op . "\n";
echo "\n";
echo "Multiple add-on subscriptions per tenant are now supported.\n";
echo "Tenants can subscribe to Maps, Postcode lookup, and Accounts independently\n";
echo "from the Billing page. Per-add-on PayPal Plans are created from\n";
echo "/master-admin/pricing.php (the 'Create on PayPal' button).\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
