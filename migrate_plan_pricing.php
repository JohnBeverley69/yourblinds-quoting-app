<?php
declare(strict_types=1);

/**
 * Migration: editable plan pricing + per-client comp overrides.
 *
 * Two tables:
 *   plan_pricing            — one row per plan_code carrying the
 *                             current default price + PayPal Plan ID.
 *                             Replaces the hard-coded
 *                             price_gbp_monthly in
 *                             _partials/billing_plans.php as the
 *                             source of truth.
 *
 *   client_plan_overrides   — per (client, plan) override. For v1
 *                             only override_type='comp' is supported
 *                             — "this client gets the feature free,
 *                             no PayPal billing." Future versions
 *                             can add 'custom_price' if needed.
 *
 * Seed: each plan in _partials/billing_plans.php gets a row with
 *   its current price. The 'accounts' plan's PayPal Plan ID is
 *   pulled from env('PAYPAL_PLAN_ACCOUNTS') if set, so existing
 *   sandbox setups keep working unchanged.
 *
 * Idempotent — re-runnable. Existing rows are left alone (so price
 * edits made via the admin UI aren't reset to the registry default).
 *
 * Run via /migrate_plan_pricing.php (super-admin login).
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

function table_exists_q(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $st->execute([$table]);
    return $st->fetchColumn() !== false;
}

function pe_col_type(PDO $pdo, string $table, string $col): string
{
    $st = $pdo->prepare(
        'SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $st->execute([$table, $col]);
    $t = $st->fetchColumn();
    if (!$t) throw new RuntimeException("$table.$col not found");
    return (string) $t;
}

$ops = [];

// ---- plan_pricing -----------------------------------------------------
if (!table_exists_q($pdo, 'plan_pricing')) {
    $pdo->exec(
        "CREATE TABLE plan_pricing (
            id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            plan_code         VARCHAR(32)   NOT NULL,
            price_gbp_monthly DECIMAL(10,2) NOT NULL,
            paypal_plan_id    VARCHAR(50)   NULL,
            notes             TEXT          NULL,
            created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_plan_pricing (plan_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $ops[] = 'Created table plan_pricing';
} else {
    $ops[] = 'plan_pricing already present';
}

// ---- client_plan_overrides --------------------------------------------
if (!table_exists_q($pdo, 'client_plan_overrides')) {
    $clientIdType = pe_col_type($pdo, 'clients', 'id');
    $pdo->exec(
        "CREATE TABLE client_plan_overrides (
            id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            client_id     $clientIdType NOT NULL,
            plan_code     VARCHAR(32)   NOT NULL,
            override_type ENUM('comp')  NOT NULL DEFAULT 'comp',
            notes         TEXT          NULL,
            active        TINYINT(1)    NOT NULL DEFAULT 1,
            created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_override (client_id, plan_code),
            CONSTRAINT fk_cpo_client FOREIGN KEY (client_id)
                REFERENCES clients(id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $ops[] = "Created table client_plan_overrides (client_id: $clientIdType)";
} else {
    $ops[] = 'client_plan_overrides already present';
}

// ---- Seed plan_pricing from the static registry -----------------------
require_once __DIR__ . '/_partials/billing_helpers.php';
$staticPlans = require __DIR__ . '/_partials/billing_plans.php';
$envPaypalPlanId = (string) (env('PAYPAL_PLAN_ACCOUNTS', '') ?? '');

$ins = $pdo->prepare(
    'INSERT IGNORE INTO plan_pricing
        (plan_code, price_gbp_monthly, paypal_plan_id, notes)
        VALUES (?, ?, ?, ?)'
);
$seeded = 0;
foreach ($staticPlans as $code => $plan) {
    $price = (float) ($plan['price_gbp_monthly'] ?? 0);
    $paypalId = $code === 'accounts' && $envPaypalPlanId !== ''
        ? $envPaypalPlanId : null;
    $notes = 'Seeded from billing_plans.php on ' . date('Y-m-d');
    $ins->execute([$code, $price, $paypalId, $notes]);
    $seeded += $ins->rowCount();
}
$ops[] = "Seeded $seeded plan_pricing row(s) from billing_plans.php "
       . ($envPaypalPlanId !== '' ? "(carried over PAYPAL_PLAN_ACCOUNTS for 'accounts')" : '');

echo "Migration complete:\n";
foreach ($ops as $op) echo '  - ' . $op . "\n";
echo "\n";
echo "Default plan prices and PayPal Plan IDs are now editable from\n";
echo "/master-admin/pricing.php. Per-client comps go on the same page.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
