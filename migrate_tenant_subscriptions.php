<?php
declare(strict_types=1);

/**
 * Migration: tenant_subscriptions table — Phase 1 of the billing
 * system. One row per tenant, tracking which plan they're on and
 * whether they're in good standing.
 *
 * Phase 1 only stores the state. Status changes are made manually by
 * super-admin via /master-admin/subscriptions.php; PayPal-driven
 * automation lands in Phase 2.
 *
 * Schema:
 *   id                        PK
 *   client_id                 unique, FK → clients.id (CASCADE)
 *   plan_code                 VARCHAR — references the registry in
 *                             _partials/billing_plans.php (e.g.
 *                             'free' / 'accounts')
 *   status                    ENUM trial / active / past_due /
 *                             cancelled / expired
 *   trial_ends_at             DATETIME NULL
 *   current_period_start      DATE NULL
 *   current_period_end        DATE NULL — past this date and the
 *                             plan auto-expires (Phase 2 cron, for
 *                             now treated as a soft hint)
 *   external_provider         VARCHAR NULL — 'paypal' once Phase 2
 *                             lands
 *   external_subscription_id  VARCHAR NULL — PayPal's sub id
 *   cancelled_at              DATETIME NULL
 *   notes                     TEXT NULL — free-text admin notes
 *   created_at / updated_at   timestamps
 *
 * Backfill rules:
 *   - For each existing client, insert one row.
 *   - If the client currently has client_settings.feature_accounts
 *     = 1, seed them as plan='accounts', status='active' so they
 *     keep their paid feature after this migration.
 *   - Otherwise seed plan='free', status='active'.
 *   No end-date set on either — they sit indefinitely until
 *   super-admin changes them.
 *
 * Idempotent — re-runnable.
 *
 * Run via /migrate_tenant_subscriptions.php (super-admin login).
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

if (!table_exists_q($pdo, 'tenant_subscriptions')) {
    $clientIdType = pe_col_type($pdo, 'clients', 'id');
    $pdo->exec("
        CREATE TABLE tenant_subscriptions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id $clientIdType NOT NULL,
            plan_code VARCHAR(32) NOT NULL DEFAULT 'free',
            status ENUM('trial','active','past_due','cancelled','expired')
                   NOT NULL DEFAULT 'active',
            trial_ends_at        DATETIME NULL,
            current_period_start DATE NULL,
            current_period_end   DATE NULL,
            external_provider        VARCHAR(32)  NULL,
            external_subscription_id VARCHAR(200) NULL,
            cancelled_at DATETIME NULL,
            notes        TEXT     NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sub_client (client_id),
            CONSTRAINT fk_sub_client FOREIGN KEY (client_id)
                REFERENCES clients(id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $ops[] = "Created table: tenant_subscriptions (client_id: $clientIdType)";
} else {
    $ops[] = 'tenant_subscriptions already present (skipped)';
}

// Backfill — one row per client. Existing feature_accounts=1 clients
// get plan='accounts'; everyone else gets plan='free'. Idempotent via
// the UNIQUE on client_id (INSERT IGNORE).
$copied = $pdo->exec(
    "INSERT IGNORE INTO tenant_subscriptions (client_id, plan_code, status, notes)
     SELECT c.id,
            CASE WHEN COALESCE(s.feature_accounts, 0) = 1 THEN 'accounts'
                 ELSE 'free' END,
            'active',
            CASE WHEN COALESCE(s.feature_accounts, 0) = 1
                 THEN CONCAT('Backfilled from feature_accounts=1 on ',
                             DATE_FORMAT(NOW(), '%Y-%m-%d'))
                 ELSE CONCAT('Backfilled as free on ',
                             DATE_FORMAT(NOW(), '%Y-%m-%d')) END
       FROM clients c
       LEFT JOIN client_settings s ON s.client_id = c.id"
);
$ops[] = "Backfilled $copied subscription row(s) (existing rows untouched)";

echo "Migration complete:\n";
foreach ($ops as $op) echo '  - ' . $op . "\n";
echo "\n";
echo "Tenant subscriptions wired. Super-admin can now set plan / status\n";
echo "per tenant from /master-admin/subscriptions.php — feature_accounts\n";
echo "and any future paid flags auto-sync from the subscription on save.\n";
echo "\n";
echo "PayPal automation lands in Phase 2 (separate work) — for now the\n";
echo "state is manually managed.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
