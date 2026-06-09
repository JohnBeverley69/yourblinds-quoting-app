<?php
declare(strict_types=1);

/**
 * Migration: per-client "Joke of the day" toggle.
 *
 * Adds client_settings.feature_joke_of_day (default 1 = on, preserving the
 * current behaviour). A tenant can switch the dashboard joke off in
 * Settings → Company. Idempotent.
 *
 * Run via web: /migrate_joke_toggle.php (super-admin).
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

echo "Migrating: Joke of the day toggle…\n\n";

if ($colExists('feature_joke_of_day')) {
    $ops[] = "client_settings.feature_joke_of_day already exists — skipped.";
} else {
    $pdo->exec("ALTER TABLE client_settings ADD COLUMN feature_joke_of_day TINYINT(1) NOT NULL DEFAULT 1");
    $ops[] = "Added client_settings.feature_joke_of_day (TINYINT(1) DEFAULT 1 = on).";
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nToggle the dashboard joke on/off per company in Settings → Company.\n";
