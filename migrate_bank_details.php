<?php
declare(strict_types=1);

/**
 * Migration: tenant bank details for customer payments.
 *
 * Adds to client_settings the bank details a customer needs to pay by
 * transfer, plus a free-text instructions line. Shown on the customer-facing
 * quote / invoice (PDF + online) when filled in; blank = the block is hidden.
 *
 *   bank_account_name     VARCHAR(120)
 *   bank_sort_code        VARCHAR(12)
 *   bank_account_number   VARCHAR(40)
 *   payment_instructions  VARCHAR(500)   -- e.g. "Use your quote number as the reference"
 *
 * Idempotent. Run via web: /migrate_bank_details.php (super-admin).
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
    echo "Steps completed before failure:\n";
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

echo "Migrating: bank details for customer payments…\n\n";

$cols = [
    'bank_account_name'    => 'VARCHAR(120) NULL',
    'bank_sort_code'       => 'VARCHAR(12) NULL',
    'bank_account_number'  => 'VARCHAR(40) NULL',
    'payment_instructions' => 'VARCHAR(500) NULL',
];
foreach ($cols as $col => $def) {
    if (!$colExists('client_settings', $col)) {
        $pdo->exec("ALTER TABLE client_settings ADD COLUMN $col $def");
        $ops[] = "Added client_settings.$col ($def).";
    } else {
        $ops[] = "client_settings.$col already exists — skipped.";
    }
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nFill these in on Settings → Quoting → \"Bank details for customer payments\".\n";
echo "They then print on the customer's quote / invoice (PDF + online).\n";
