<?php
declare(strict_types=1);

/**
 * Migration: order-level references for the manufacturing hand-off.
 *
 *   quotes.customer_reference    VARCHAR(100) NULL  -- the customer's own PO / job ref
 *   quotes.additional_reference  VARCHAR(100) NULL  -- a second free-text ref
 *
 * Blind Matrix's order screen carries a "Customer Reference" and an
 * "Additional Reference" on every order; YourBlinds quotes have no equivalent
 * today (only the system quote_number). These two fields let a submitted order
 * carry the references the factory and the customer expect on paperwork.
 *
 * Additive + idempotent. Run via web: /migrate_order_references.php (super-admin).
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
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Migration FAILED: " . $e->getMessage() . "\n\n";
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

$colExists = static function (string $table, string $col) use ($pdo): bool {
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $st->execute([$table, $col]);
    return (bool) $st->fetchColumn();
};

echo "Migrating: order-level references (customer_reference, additional_reference)…\n\n";

if (!$colExists('quotes', 'customer_reference')) {
    $pdo->exec("ALTER TABLE quotes ADD COLUMN customer_reference VARCHAR(100) NULL");
    $ops[] = 'Added quotes.customer_reference.';
} else {
    $ops[] = 'quotes.customer_reference already exists — skipped.';
}

if (!$colExists('quotes', 'additional_reference')) {
    $pdo->exec("ALTER TABLE quotes ADD COLUMN additional_reference VARCHAR(100) NULL");
    $ops[] = 'Added quotes.additional_reference.';
} else {
    $ops[] = 'quotes.additional_reference already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nOrders can now carry the customer's own reference + an additional reference.\n";
