<?php
declare(strict_types=1);

/**
 * Migration: a per-business "new order" notification address.
 *
 *   client_settings.order_notify_email — when a customer accepts a quote online,
 *   the business gets a "new order came in" email at this address. Blank/NULL =
 *   no notification (current behaviour).
 *
 * Additive + nullable, so code that ignores it keeps working.
 *
 * Run via web: /migrate_order_notify_email.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colExists = static function (string $t, string $c) use ($pdo): bool {
    try { $pdo->query("SELECT `$c` FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

if (!$colExists('client_settings', 'order_notify_email')) {
    $pdo->exec("ALTER TABLE `client_settings` ADD COLUMN `order_notify_email` VARCHAR(190) NULL");
    echo "  Added client_settings.order_notify_email.\n";
} else {
    echo "  client_settings.order_notify_email already exists — skipped.\n";
}

echo "\nDone. Businesses can set an address to be emailed when a customer accepts a quote online.\n";
