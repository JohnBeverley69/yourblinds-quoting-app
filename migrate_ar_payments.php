<?php
declare(strict_types=1);

/**
 * Wholesale A/R — Phase 2D-1: payments received + allocations.
 *
 *   factory_ar_payments             — a payment received FROM a trade account
 *                                     (date, method, amount, reference), gap-free
 *                                     PAY-YYYY-#### number, void-able.
 *   factory_ar_payment_allocations  — how each payment is applied across the
 *                                     account's invoices (payment_id → invoice_id,
 *                                     amount). SUM(allocations) per invoice is
 *                                     cached on factory_ar_invoices.amount_paid.
 *
 * Money DECIMAL(10,2). Factory-scoped like the rest of the AR tables. Idempotent.
 * Run via web: /migrate_ar_payments.php (super-admin).
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

$ops = [];
set_exception_handler(function (Throwable $e) use (&$ops) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) header('Content-Type: text/plain; charset=utf-8');
    echo "Migration FAILED: " . $e->getMessage() . "\n\n";
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

$tableExists = static function (string $t) use ($pdo): bool {
    $s = $pdo->prepare(
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $s->execute([$t]);
    return (bool) $s->fetchColumn();
};

echo "Migrating: wholesale A/R payments…\n\n";

if (!$tableExists('factory_ar_payments')) {
    $pdo->exec(
        "CREATE TABLE factory_ar_payments (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            factory_client_id INT           NOT NULL,
            account_client_id INT           NOT NULL,
            pay_number        VARCHAR(32)   NOT NULL,        -- 'PAY-2026-0001'
            payment_date      DATE          NOT NULL,
            method            VARCHAR(32)   NOT NULL DEFAULT 'bank',  -- bank|cash|card|cheque|other
            amount            DECIMAL(10,2) NOT NULL DEFAULT 0,
            reference         VARCHAR(120)  NULL,
            notes             TEXT          NULL,
            voided_at         DATETIME      NULL,
            void_reason       VARCHAR(255)  NULL,
            created_by        INT           NULL,
            created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_pay_number (factory_client_id, pay_number),
            KEY idx_acc (factory_client_id, account_client_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created table factory_ar_payments.';
} else {
    $ops[] = 'Table factory_ar_payments already exists — skipped.';
}

if (!$tableExists('factory_ar_payment_allocations')) {
    $pdo->exec(
        "CREATE TABLE factory_ar_payment_allocations (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            payment_id INT           NOT NULL,
            invoice_id INT           NOT NULL,
            amount     DECIMAL(10,2) NOT NULL DEFAULT 0,
            created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_payment (payment_id),
            KEY idx_invoice (invoice_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created table factory_ar_payment_allocations.';
} else {
    $ops[] = 'Table factory_ar_payment_allocations already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nRecord payments from the trade-account page → Record payment.\n";
