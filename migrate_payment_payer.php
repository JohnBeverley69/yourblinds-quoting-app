<?php
declare(strict_types=1);

/**
 * Migration: payments.payer_name — who a payment came from.
 *
 * Standalone payments (no linked quote/customer) had no way to record
 * the sender; the reference field was the only option and read poorly
 * against order-linked payments that show the customer name. This adds
 * an optional free-text payer name, surfaced on the Payments page for
 * standalone payments and editable on every payment.
 *
 * Idempotent — re-runnable (adds the column only if absent).
 *
 * Run via web: /migrate_payment_payer.php  (super-admin login)
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

function col_exists_q(PDO $pdo, string $table, string $col): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $st->execute([$table, $col]);
    return $st->fetchColumn() !== false;
}

$ops = [];

if (!col_exists_q($pdo, 'payments', 'payer_name')) {
    // Sits after `reference` for a natural column order; harmless either way.
    $pdo->exec("ALTER TABLE payments
                ADD COLUMN payer_name VARCHAR(200) NULL AFTER reference");
    $ops[] = 'Added column: payments.payer_name VARCHAR(200) NULL';
} else {
    $ops[] = 'Column payments.payer_name already present (skipped)';
}

echo "Migration complete:\n";
foreach ($ops as $op) echo '  - ' . $op . "\n";
echo "\n";
echo "You can now record a 'Received from' name on payments — handy for\n";
echo "standalone payments that aren't linked to an order/customer.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
