<?php
declare(strict_types=1);

/**
 * Migration: deposit -> single payments ledger.
 *
 * The deposit used to be counted from two places — quotes.deposit_amount/
 * deposit_paid_at AND the payments table — which let the same money be
 * counted twice if a user logged the deposit as a payment. This unifies it:
 * a paid deposit becomes a single payments row flagged is_deposit = 1, so
 * every "received / outstanding" figure comes from ONE ledger.
 *
 * Adds:
 *   payments.is_deposit TINYINT(1) NOT NULL DEFAULT 0
 *
 * Backfills:
 *   For every quote with deposit_paid_at set and deposit_amount > 0 that does
 *   NOT already have an is_deposit row, inserts one (amount = deposit_amount,
 *   received_at = the deposit's paid date, method 'deposit'). Idempotent.
 *
 * quotes.deposit_amount / deposit_paid_at are KEPT — they remain the deposit's
 * display state and the management point on the order; the application keeps
 * the is_deposit payment row in sync with them.
 *
 * Run via /migrate_deposit_to_payments.php (super-admin). Re-runnable.
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

// 1) Column.
if (!col_exists_q($pdo, 'payments', 'is_deposit')) {
    $pdo->exec('ALTER TABLE payments
                ADD COLUMN is_deposit TINYINT(1) NOT NULL DEFAULT 0 AFTER method');
    $ops[] = 'Added payments.is_deposit';
} else {
    $ops[] = 'payments.is_deposit already present (skipped)';
}

// 2) Backfill a deposit payment row for every paid deposit that doesn't have
//    one yet. NOT EXISTS keeps it idempotent.
$inserted = $pdo->exec(
    "INSERT INTO payments
        (client_id, quote_id, customer_id, amount, received_at, method, reference, is_deposit)
     SELECT q.client_id, q.id, q.customer_id,
            ROUND(q.deposit_amount, 2),
            DATE(q.deposit_paid_at),
            'deposit', 'Deposit', 1
       FROM quotes q
      WHERE q.deposit_paid_at IS NOT NULL
        AND q.deposit_amount IS NOT NULL
        AND q.deposit_amount > 0
        AND NOT EXISTS (
            SELECT 1 FROM payments p
             WHERE p.quote_id = q.id AND p.client_id = q.client_id AND p.is_deposit = 1
        )"
);
$ops[] = 'Backfilled deposit payment rows: ' . (int) $inserted;

echo "Migration complete:\n";
foreach ($ops as $op) echo '  - ' . $op . "\n";
echo "\n";
echo "Deposits now live in the payments ledger (is_deposit = 1). Every\n";
echo "received/outstanding figure is computed from payments alone; the\n";
echo "order's deposit panel still manages it and keeps the row in sync.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
