<?php
declare(strict_types=1);

/**
 * Migration: client_settings.factory_notify_email — a dedicated address for the
 * factory "new order received" alert (separate from the customer-acceptance
 * "New order alerts" address). Blank/NULL → fall back to order_notify_email.
 *
 * Additive + nullable. Run via web: /migrate_factory_notify_email.php
 * (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colExists = static function (string $t, string $c) use ($pdo): bool {
    try { $pdo->query("SELECT `$c` FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

if (!$colExists('client_settings', 'factory_notify_email')) {
    $pdo->exec("ALTER TABLE `client_settings` ADD COLUMN `factory_notify_email` VARCHAR(190) NULL");
    echo "  Added client_settings.factory_notify_email.\n";
} else {
    echo "  client_settings.factory_notify_email already exists — skipped.\n";
}

echo "\nDone. A factory can set a dedicated address for 'new order received' alerts.\n";
