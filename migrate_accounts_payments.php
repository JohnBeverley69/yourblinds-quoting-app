<?php
declare(strict_types=1);

/**
 * Migration: payments table — Phase 1 of the accounts module.
 *
 * Tracks every payment received against a quote/order. The deposit
 * field on quotes (deposit_amount + deposit_paid_at) keeps working
 * unchanged; the payments table is additive. "Outstanding" for an
 * order is computed as:
 *
 *   total − (deposit_amount IF deposit_paid_at ELSE 0) − SUM(payments)
 *
 * so a tenant who's been using the deposit field gets a sensible
 * answer even before they start recording explicit payment rows.
 *
 * Column types for FKs are read live from INFORMATION_SCHEMA — the
 * parent column types may be plain INT or INT UNSIGNED depending on
 * how the schema was bootstrapped. Same trick as
 * migrate_markup_per_system.php.
 *
 * Idempotent — re-runnable.
 *
 * Run via web: /migrate_accounts_payments.php  (super-admin login)
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
    if (!$t) {
        throw new RuntimeException("$table.$col not found");
    }
    return (string) $t;
}

$ops = [];

if (!table_exists_q($pdo, 'payments')) {
    // Match parent column types so the FKs are accepted.
    $clientIdType   = pe_col_type($pdo, 'clients',   'id');
    $quoteIdType    = pe_col_type($pdo, 'quotes',    'id');
    $customerIdType = pe_col_type($pdo, 'customers', 'id');

    $pdo->exec("
        CREATE TABLE payments (
            id          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
            client_id   $clientIdType  NOT NULL,
            quote_id    $quoteIdType   NULL,
            customer_id $customerIdType NULL,
            amount      DECIMAL(10,2)  NOT NULL,
            received_at DATE           NOT NULL,
            method      VARCHAR(32)    NOT NULL DEFAULT 'bank_transfer',
            reference   VARCHAR(200)   NULL,
            notes       TEXT           NULL,
            created_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_payments_client   (client_id),
            KEY idx_payments_quote    (quote_id),
            KEY idx_payments_customer (customer_id),
            KEY idx_payments_date     (received_at),
            CONSTRAINT fk_payments_client
                FOREIGN KEY (client_id) REFERENCES clients(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_payments_quote
                FOREIGN KEY (quote_id) REFERENCES quotes(id)
                ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_payments_customer
                FOREIGN KEY (customer_id) REFERENCES customers(id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $ops[] = "Created table: payments (client_id=$clientIdType, quote_id=$quoteIdType, customer_id=$customerIdType)";
} else {
    $ops[] = 'Table payments already present (skipped)';
}

echo "Migration complete:\n";
foreach ($ops as $op) echo '  - ' . $op . "\n";
echo "\n";
echo "Accounts → Payments is now wired. Use the Payments section on each\n";
echo "quote edit page to record payments; the Orders page shows the\n";
echo "outstanding balance per order; the Accounts page (sidebar) lists\n";
echo "all payments across the tenant.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
