<?php
declare(strict_types=1);

/**
 * Migration: suppliers.account_number — the tenant's account number with
 * each supplier.
 *
 * Sits alongside name + email on the Settings → Suppliers list, so a
 * tenant can record (and later print on supplier orders) the account
 * the supplier knows them by.
 *
 * Idempotent — re-runnable (adds the column only if absent).
 *
 * Run via web: /migrate_supplier_account_number.php  (super-admin login)
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

if (!col_exists_q($pdo, 'suppliers', 'account_number')) {
    $pdo->exec("ALTER TABLE suppliers
                ADD COLUMN account_number VARCHAR(100) NULL AFTER email");
    $ops[] = 'Added column: suppliers.account_number VARCHAR(100) NULL';
} else {
    $ops[] = 'Column suppliers.account_number already present (skipped)';
}

echo "Migration complete:\n";
foreach ($ops as $op) echo '  - ' . $op . "\n";
echo "\n";
echo "Settings → Suppliers now has an Account number field per supplier.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
