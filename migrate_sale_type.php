<?php
declare(strict_types=1);

/**
 * Migration: explicit sale-type marker + a tenant default.
 *
 *   client_settings.default_sale_type  — 'trade' | 'retail' (per-tenant default
 *                                         for the unified "New" screen; NULL = retail)
 *   quotes.sale_type                    — 'trade' | 'retail' stamped at creation,
 *                                         so a one-off trade sale (no account link)
 *                                         is still distinguishable from a retail one.
 *
 * Both additive + nullable, so code that ignores them keeps working. Sale-type
 * for a linked account is still inferrable from account_client_id; this column
 * just makes the one-off trade case explicit.
 *
 * Run via web: /migrate_sale_type.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colExists = static function (string $t, string $c) use ($pdo): bool {
    try { $pdo->query("SELECT `$c` FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

foreach ([['client_settings', 'default_sale_type'], ['quotes', 'sale_type']] as [$table, $col]) {
    if (!$colExists($table, $col)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` VARCHAR(8) NULL");
        echo "  Added {$table}.{$col}.\n";
    } else {
        echo "  {$table}.{$col} already exists — skipped.\n";
    }
}

echo "\nDone. Tenants can set a default sale type, and quotes carry a trade/retail marker.\n";
