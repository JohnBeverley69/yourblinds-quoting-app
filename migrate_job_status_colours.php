<?php
declare(strict_types=1);

/**
 * Migration: per-client traffic-light status colours.
 *
 * Adds a single JSON column to client_settings holding each tenant's colour
 * overrides for the pipeline statuses (draft, sent, accepted, declined,
 * ordered, booked, fitted, invoiced, paid, cancelled, no_show). NULL / empty
 * = use the built-in defaults from _partials/job_status_colours.php.
 *
 * Only changed colours are stored; the helper merges them over the defaults,
 * so adding a new status later doesn't need a re-save. Idempotent.
 *
 * Run via web: /migrate_job_status_colours.php (super-admin).
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}

ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$ops = [];
set_exception_handler(function (Throwable $e) use (&$ops) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Migration FAILED: " . $e->getMessage() . "\n\n";
    echo "Steps completed before failure:\n";
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

$colExists = static function (string $col) use ($pdo): bool {
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_settings' AND COLUMN_NAME = ?"
    );
    $st->execute([$col]);
    return (bool) $st->fetchColumn();
};

echo "Migrating: per-client status colours…\n\n";

if ($colExists('job_status_colours')) {
    $ops[] = "client_settings.job_status_colours already exists — skipped.";
} else {
    $pdo->exec("ALTER TABLE client_settings ADD COLUMN job_status_colours TEXT NULL");
    $ops[] = "Added client_settings.job_status_colours (TEXT NULL, JSON map of overrides).";
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nEach tenant can now customise their traffic-light colours on the\n";
echo "Settings page. Until a tenant changes one, the built-in defaults apply\n";
echo "on both the calendar and the orders list.\n";
