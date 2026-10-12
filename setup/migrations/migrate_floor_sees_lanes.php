<?php
declare(strict_types=1);

/**
 * Add client_settings.floor_sees_lanes — a factory setting (Factory → Settings):
 * when 1, floor logins see Incoming orders' next-job tabs and MAKE / BUY chips
 * (read-only, no action buttons). Default 0: floor logins see the plain list
 * with Worksheet, as before. John, 2026-10-12.
 *
 * Idempotent, super-admin, web-runnable: /setup/migrations/migrate_floor_sees_lanes.php
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__, 2) . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$exists = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
$exists->execute(['client_settings', 'floor_sees_lanes']);
if ($exists->fetchColumn()) {
    echo "client_settings.floor_sees_lanes already exists — skipped.\n";
} else {
    $pdo->exec('ALTER TABLE client_settings ADD COLUMN floor_sees_lanes TINYINT(1) NOT NULL DEFAULT 0');
    echo "Added client_settings.floor_sees_lanes.\n";
}
echo "\nDone. Factory → Settings → \"Floor logins can see the order lanes\".\n";
