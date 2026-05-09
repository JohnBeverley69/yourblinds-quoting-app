<?php
declare(strict_types=1);

/**
 * Migration: capture customer signature when accepting a quote.
 *
 * Adds two columns to quotes:
 *   acceptance_signature_name  VARCHAR(150) — what the customer typed
 *   acceptance_ip              VARCHAR(45)  — audit trail (IPv4 or IPv6)
 *
 * Idempotent — re-runnable.
 *
 * Run via CLI:   php migrate_quote_acceptance_fields.php
 * Run via web:   /migrate_quote_acceptance_fields.php   (super-admin login required)
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

if (!column_exists_q($pdo, 'quotes', 'acceptance_signature_name')) {
    $pdo->exec(
        "ALTER TABLE quotes
            ADD COLUMN acceptance_signature_name VARCHAR(150) NULL AFTER accepted_at"
    );
    $ops[] = 'Added quotes.acceptance_signature_name';
} else {
    $ops[] = 'Skipped quotes.acceptance_signature_name (already present)';
}

if (!column_exists_q($pdo, 'quotes', 'acceptance_ip')) {
    $pdo->exec(
        "ALTER TABLE quotes
            ADD COLUMN acceptance_ip VARCHAR(45) NULL AFTER acceptance_signature_name"
    );
    $ops[] = 'Added quotes.acceptance_ip';
} else {
    $ops[] = 'Skipped quotes.acceptance_ip (already present)';
}

echo "Migration complete:\n";
foreach ($ops as $op) {
    echo '  - ' . $op . "\n";
}
echo "\nWhen you're done, you can delete this file from the server.\n";
