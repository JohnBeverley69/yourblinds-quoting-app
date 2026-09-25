<?php
declare(strict_types=1);

/**
 * Migration: a MOBILE number on trade accounts.
 *
 * Trade accounts (clients rows) already carry company_name / contact_name /
 * phone. Phase 2 gives them a mobile too — the number a trade order's WhatsApp
 * link uses, mirroring customers.mobile from Phase 1.
 *   clients.mobile
 *
 * Additive + nullable, so code that ignores it keeps working (the account
 * new-order helper already reads $acc['mobile'] ?? '').
 *
 * Run via web: /migrate_clients_mobile.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); require_run_confirmation(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colExists = static function (string $t, string $c) use ($pdo): bool {
    try { $pdo->query("SELECT `$c` FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

if (!$colExists('clients', 'mobile')) {
    $pdo->exec("ALTER TABLE `clients` ADD COLUMN `mobile` VARCHAR(40) NULL");
    echo "  Added clients.mobile.\n";
} else {
    echo "  clients.mobile already exists — skipped.\n";
}

echo "\nDone. Trade accounts now carry a mobile number (trade orders' WhatsApp uses it).\n";
