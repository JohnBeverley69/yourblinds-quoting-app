<?php
declare(strict_types=1);

/**
 * Migration: per-customer "has WhatsApp" flag.
 *
 * Adds:
 *   customers.has_whatsapp  TINYINT(1) NOT NULL DEFAULT 0
 *   quotes.has_whatsapp     TINYINT(1) NOT NULL DEFAULT 0
 *
 * Lives on both: customers is the master record (the trade user keeps
 * the truth there), quotes carries a snapshot like the other
 * end_customer_* fields so a historical quote knows whether WhatsApp
 * was an option at send-time.
 *
 * Defaults to 0 — trade user explicitly opts in, so a stray tap of
 * the Send-via-WhatsApp button never lands on a wa.me "not on
 * WhatsApp" error page.
 *
 * Idempotent — re-runnable.
 *
 * Run via CLI:   php migrate_customer_whatsapp.php
 * Run via web:   /migrate_customer_whatsapp.php   (super-admin login required)
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

function column_exists_q(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = ?
            AND COLUMN_NAME  = ?
          LIMIT 1'
    );
    $st->execute([$table, $column]);
    return $st->fetchColumn() !== false;
}

$ops = [];

if (!column_exists_q($pdo, 'customers', 'has_whatsapp')) {
    $pdo->exec(
        "ALTER TABLE customers
            ADD COLUMN has_whatsapp TINYINT(1) NOT NULL DEFAULT 0 AFTER phone"
    );
    $ops[] = 'Added customers.has_whatsapp';
} else {
    $ops[] = 'Skipped customers.has_whatsapp (already present)';
}

if (!column_exists_q($pdo, 'quotes', 'has_whatsapp')) {
    $pdo->exec(
        "ALTER TABLE quotes
            ADD COLUMN has_whatsapp TINYINT(1) NOT NULL DEFAULT 0 AFTER end_customer_phone"
    );
    $ops[] = 'Added quotes.has_whatsapp';
} else {
    $ops[] = 'Skipped quotes.has_whatsapp (already present)';
}

echo "Migration complete:\n";
foreach ($ops as $op) {
    echo '  - ' . $op . "\n";
}
echo "\nWhen you're done, you can delete this file from the server.\n";
