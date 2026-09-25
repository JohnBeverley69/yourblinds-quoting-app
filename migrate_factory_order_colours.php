<?php
declare(strict_types=1);

/**
 * client_settings.factory_order_colours — per-factory JSON map of order-state to
 * colour, for the Incoming Orders colour-coding (Settings → factory Settings tab).
 * Absent/blank ⇒ built-in defaults (see _partials/factory_order_colours.php).
 *
 * Idempotent, super-admin, web-runnable: /migrate_factory_order_colours.php
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$exists = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
$exists->execute(['client_settings', 'factory_order_colours']);
if ($exists->fetchColumn()) {
    echo "client_settings.factory_order_colours already exists — skipped.\n";
} else {
    $pdo->exec('ALTER TABLE client_settings ADD COLUMN factory_order_colours TEXT NULL AFTER quote_prefix');
    echo "Added client_settings.factory_order_colours.\n";
}
echo "\nDone. Set the colours in the factory Settings tab.\n";
