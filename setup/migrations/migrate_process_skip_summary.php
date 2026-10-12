<?php
declare(strict_types=1);

/**
 * Add client_settings.process_skip_summary — a factory setting (Factory →
 * Settings → Process order): when 1, Process order goes straight through with
 * no summary. Default 0: the summary shows first. Per factory. John, 2026-10-12.
 *
 * Idempotent, super-admin, web-runnable: /setup/migrations/migrate_process_skip_summary.php
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
$exists->execute(['client_settings', 'process_skip_summary']);
if ($exists->fetchColumn()) {
    echo "client_settings.process_skip_summary already exists — skipped.\n";
} else {
    $pdo->exec('ALTER TABLE client_settings ADD COLUMN process_skip_summary TINYINT(1) NOT NULL DEFAULT 0');
    echo "Added client_settings.process_skip_summary.\n";
}
echo "\nDone. Factory → Settings → \"Process orders without the summary\".\n";
