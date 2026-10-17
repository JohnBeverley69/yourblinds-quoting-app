<?php
declare(strict_types=1);

/**
 * Add client_settings.quote_valid_days — how many days a sent quote can be
 * accepted for (Settings → Quoting → Quote defaults). Default 30, the old
 * fixed window (QUOTE_ACCEPT_DAYS). Per company. John, 2026-10-17.
 *
 * Idempotent, super-admin, web-runnable: /setup/migrations/migrate_quote_valid_days.php
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
$exists->execute(['client_settings', 'quote_valid_days']);
if ($exists->fetchColumn()) {
    echo "client_settings.quote_valid_days already exists — skipped.\n";
} else {
    $pdo->exec('ALTER TABLE client_settings ADD COLUMN quote_valid_days INT NOT NULL DEFAULT 30');
    echo "Added client_settings.quote_valid_days (default 30).\n";
}
echo "\nDone. Settings → Quoting → Quote defaults → \"Quotes can be accepted for … days\".\n";
