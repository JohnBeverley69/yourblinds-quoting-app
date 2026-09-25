<?php
declare(strict_types=1);

/**
 * Migration: a distinct MOBILE number on customers + quote snapshots.
 *
 * Phone stays the landline/main number; mobile is the number WhatsApp uses.
 *   customers.mobile
 *   quotes.end_customer_mobile
 *
 * Additive + nullable, so existing code that ignores it keeps working, and the
 * WhatsApp link falls back to phone for records that have no mobile yet.
 *
 * Run via web: /migrate_customer_mobile.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); require_run_confirmation(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colExists = static function (string $t, string $c) use ($pdo): bool {
    try { $pdo->query("SELECT `$c` FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

foreach ([['customers', 'mobile'], ['quotes', 'end_customer_mobile']] as [$table, $col]) {
    if (!$colExists($table, $col)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` VARCHAR(40) NULL");
        echo "  Added {$table}.{$col}.\n";
    } else {
        echo "  {$table}.{$col} already exists — skipped.\n";
    }
}

echo "\nDone. Customers + quotes now carry a separate mobile number (WhatsApp uses it).\n";
