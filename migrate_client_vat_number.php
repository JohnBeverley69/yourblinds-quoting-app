<?php
declare(strict_types=1);

/**
 * Migration: add clients.vat_number for trade users who need their VAT
 * registration number printed on customer-facing quotes / invoices.
 *
 * Nullable — not every trade user is VAT-registered, so blank stays valid
 * and the PDF simply omits the line when the field is empty.
 *
 * Idempotent — checks INFORMATION_SCHEMA before adding.
 *
 * Run via web: /migrate_client_vat_number.php   (super-admin login required)
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

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $st->execute([$table, $column]);
    return $st->fetchColumn() !== false;
}

$ops = [];

if (!column_exists($pdo, 'clients', 'vat_number')) {
    $pdo->exec(
        'ALTER TABLE clients
           ADD COLUMN vat_number VARCHAR(50) NULL AFTER phone'
    );
    $ops[] = 'Added clients.vat_number (VARCHAR(50) NULL).';
} else {
    $ops[] = 'Skipped clients.vat_number (already present).';
}

echo "Migration complete:\n";
foreach ($ops as $op) {
    echo '  - ' . $op . "\n";
}
echo "\nFill it in via Admin → Company details. The quote PDF will\n";
echo "automatically show 'VAT No. <number>' below your phone / email\n";
echo "when the field is non-empty.\n";
echo "\nWhen you're done, you can delete this file from the server.\n";
