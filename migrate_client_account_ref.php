<?php
declare(strict_types=1);

/**
 * Migration: clients.account_ref — a short account code (Acc Ref), e.g. VESTA001.
 * Sourced from Blind Matrix (the login username = the account code). Used as the
 * Acc Ref on statements and as a stable human reference. Nullable.
 *
 * Run via web: /migrate_client_account_ref.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colExists = static function (string $t, string $c) use ($pdo): bool {
    try { $pdo->query("SELECT `$c` FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

if (!$colExists('clients', 'account_ref')) {
    $pdo->exec("ALTER TABLE clients ADD COLUMN account_ref VARCHAR(32) NULL");
    echo "  Added clients.account_ref.\n";
} else {
    echo "  clients.account_ref already exists — skipped.\n";
}

echo "\nDone. Trade accounts can now carry their short account code (Acc Ref).\n";
