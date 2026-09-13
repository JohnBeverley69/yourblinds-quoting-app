<?php
declare(strict_types=1);

/**
 * Migration: quotes.factory_notified_at — when the factory was emailed that this
 * order landed in its queue. Guards the factory "new order received" email so it
 * sends once, regardless of how (or how many times) an order is placed.
 *
 * Additive + nullable. Run via web: /migrate_factory_notified.php (super-admin).
 * Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colExists = static function (string $t, string $c) use ($pdo): bool {
    try { $pdo->query("SELECT `$c` FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

if (!$colExists('quotes', 'factory_notified_at')) {
    $pdo->exec("ALTER TABLE `quotes` ADD COLUMN `factory_notified_at` DATETIME NULL");
    echo "  Added quotes.factory_notified_at.\n";
} else {
    echo "  quotes.factory_notified_at already exists — skipped.\n";
}

echo "\nDone. The factory 'new order received' email now fires once per placed order.\n";
