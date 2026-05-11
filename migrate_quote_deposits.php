<?php
declare(strict_types=1);

/**
 * Migration: deposits against accepted orders.
 *
 * Three new columns:
 *   quotes.deposit_amount       DECIMAL(10,2) NULL
 *     — what's owed as a deposit. Auto-calculated from
 *       total × client_settings.default_deposit_percent the moment
 *       the quote moves into 'accepted'. Editable per quote.
 *
 *   quotes.deposit_paid_at      DATETIME NULL
 *     — when the deposit was received. NULL = unpaid, timestamp =
 *       paid. A single column drives both "is it paid?" and "when?"
 *       which keeps the schema flat.
 *
 *   client_settings.default_deposit_percent  DECIMAL(5,2) DEFAULT 50.00
 *     — tenant-wide deposit policy (typically 50%). Used as the
 *       seed when a quote first lands in accepted; the trade user
 *       can override per-quote.
 *
 * Idempotent — re-runnable.
 *
 * Run via web: /migrate_quote_deposits.php  (super-admin login)
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

if (!col_exists_q($pdo, 'quotes', 'deposit_amount')) {
    $pdo->exec('ALTER TABLE quotes ADD COLUMN deposit_amount DECIMAL(10,2) NULL AFTER total');
    $ops[] = 'quotes.deposit_amount: added';
} else {
    $ops[] = 'quotes.deposit_amount: already present';
}

if (!col_exists_q($pdo, 'quotes', 'deposit_paid_at')) {
    $pdo->exec('ALTER TABLE quotes ADD COLUMN deposit_paid_at DATETIME NULL AFTER deposit_amount');
    $ops[] = 'quotes.deposit_paid_at: added';
} else {
    $ops[] = 'quotes.deposit_paid_at: already present';
}

if (!col_exists_q($pdo, 'client_settings', 'default_deposit_percent')) {
    $pdo->exec(
        'ALTER TABLE client_settings
            ADD COLUMN default_deposit_percent DECIMAL(5,2) NOT NULL DEFAULT 50.00'
    );
    $ops[] = 'client_settings.default_deposit_percent: added (default 50.00)';
} else {
    $ops[] = 'client_settings.default_deposit_percent: already present';
}

echo "Migration complete:\n";
foreach ($ops as $op) echo '  - ' . $op . "\n";
echo "\n";
echo "Deposits are now stored per-quote. The Settings page exposes a\n";
echo "tenant-wide default deposit % (50% out of the box). When a quote\n";
echo "moves into 'accepted' the deposit amount is auto-calculated; the\n";
echo "user can edit it and mark it paid from the quote Edit page.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
