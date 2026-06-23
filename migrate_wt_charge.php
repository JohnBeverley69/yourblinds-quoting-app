<?php
declare(strict_types=1);

/**
 * Migration: WT charge (internal-only discretionary surcharge).
 *
 *   quotes.wt_amount        DECIMAL(10,2) NOT NULL DEFAULT 0   -- per-quote WT
 *   client_settings.feature_wt TINYINT(1) NOT NULL DEFAULT 0   -- on/off per tenant
 *
 * The WT is a salesperson-only "wally tax": added to the quote PRE-VAT (so it
 * sits inside the price and is VAT-able), and NEVER shown to the customer.
 * When per-blind prices are shown it's spread proportionally across the blind
 * prices so the lines still reconcile to the subtotal; otherwise it just lifts
 * the total. Off by default (opt-in on Settings → Quoting).
 *
 * Idempotent. Run via web: /migrate_wt_charge.php (super-admin).
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

echo "Migrating: WT charge…\n\n";

if (!$colExists('quotes', 'wt_amount')) {
    $pdo->exec("ALTER TABLE quotes ADD COLUMN wt_amount DECIMAL(10,2) NOT NULL DEFAULT 0");
    $ops[] = 'Added quotes.wt_amount.';
} else {
    $ops[] = 'quotes.wt_amount already exists — skipped.';
}

if (!$colExists('client_settings', 'feature_wt')) {
    $pdo->exec("ALTER TABLE client_settings ADD COLUMN feature_wt TINYINT(1) NOT NULL DEFAULT 0");
    $ops[] = 'Added client_settings.feature_wt (off by default).';
} else {
    $ops[] = 'client_settings.feature_wt already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nEnable it per tenant on Settings → Quoting (\"WT charge\").\n";
