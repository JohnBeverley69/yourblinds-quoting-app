<?php
declare(strict_types=1);

/**
 * Migration: automatic paid-in-full receipt.
 *
 *   quotes.receipt_sent_at        DATETIME NULL          -- one receipt only
 *   client_settings.feature_auto_receipt TINYINT(1) DEFAULT 1  -- on by default
 *
 * When an order settles in full (deposit + payments cover the total), the
 * customer is automatically emailed a thank-you receipt — once. receipt_sent_at
 * stamps the send so it can never repeat; feature_auto_receipt lets a tenant
 * turn it off.
 *
 * Idempotent. Run via web: /migrate_auto_receipt.php (super-admin).
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

echo "Migrating: automatic paid-in-full receipt…\n\n";

if (!$colExists('quotes', 'receipt_sent_at')) {
    $pdo->exec("ALTER TABLE quotes ADD COLUMN receipt_sent_at DATETIME NULL");
    $ops[] = 'Added quotes.receipt_sent_at.';
} else {
    $ops[] = 'quotes.receipt_sent_at already exists — skipped.';
}

if (!$colExists('client_settings', 'feature_auto_receipt')) {
    $pdo->exec("ALTER TABLE client_settings ADD COLUMN feature_auto_receipt TINYINT(1) NOT NULL DEFAULT 1");
    $ops[] = 'Added client_settings.feature_auto_receipt (on by default).';
} else {
    $ops[] = 'client_settings.feature_auto_receipt already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nFully-paid orders now auto-email a thank-you receipt. Toggle on Settings → Quoting.\n";
