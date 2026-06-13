<?php
declare(strict_types=1);

/**
 * Migration: quotes.pre_paid_status — remembers the order status a quote was
 * in just before it auto-settled to 'paid', so un-settling (a payment/deposit
 * later removed or reduced) can step it back to the RIGHT state instead of
 * hard-coding 'invoiced'.
 *
 * Background: a deposit alone can cover a small/zero-balance order and jump it
 * straight to 'paid' from 'accepted'/'ordered'/'fitted', skipping 'invoiced'.
 * If the money is then pulled back, qb_settle_if_paid used to drop it to
 * 'invoiced' — a state it may never have passed through. With this column it
 * restores the actual prior state.
 *
 * Idempotent. Run via /migrate_quote_pre_paid_status.php (super-admin).
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

if (!col_exists_q($pdo, 'quotes', 'pre_paid_status')) {
    $pdo->exec("ALTER TABLE quotes
                ADD COLUMN pre_paid_status VARCHAR(20) NULL AFTER status");
    $ops[] = 'Added quotes.pre_paid_status VARCHAR(20) NULL';
} else {
    $ops[] = 'quotes.pre_paid_status already present (skipped)';
}

echo "Migration complete:\n";
foreach ($ops as $op) echo '  - ' . $op . "\n";
echo "\n";
echo "Un-settling a 'paid' order now restores its real prior status instead\n";
echo "of always landing on 'invoiced'.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
