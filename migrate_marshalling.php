<?php
declare(strict_types=1);

/**
 * Migration: marshalling (Phase D of the production-areas feature).
 *
 *   factory_blind_jobs.marshalled_at / marshalled_by
 *       — when a finished blind was scanned into the collection/dispatch bench.
 *         An order can only be dispatched once every one of its blinds is in.
 *
 * The marshalling bench's own scan key lives in factory_kv ('marshal_scan_key'),
 * created on demand from the Marshalling board — no schema needed for it.
 *
 * Idempotent + web-runnable: /migrate_marshalling.php (super-admin).
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

$ops = [];
set_exception_handler(function (Throwable $e) use (&$ops) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) header('Content-Type: text/plain; charset=utf-8');
    echo "Migration FAILED: " . $e->getMessage() . "\n\n";
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

$colExists = static function (string $table, string $col) use ($pdo): bool {
    $s = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $s->execute([$table, $col]);
    return (bool) $s->fetchColumn();
};

echo "Migrating: marshalling…\n\n";

if (!$colExists('factory_blind_jobs', 'marshalled_at')) {
    $pdo->exec('ALTER TABLE factory_blind_jobs ADD COLUMN marshalled_at DATETIME NULL AFTER completed_at');
    $pdo->exec('ALTER TABLE factory_blind_jobs ADD INDEX idx_fbj_marshalled (marshalled_at)');
    $ops[] = 'Added factory_blind_jobs.marshalled_at (+ index).';
} else {
    $ops[] = 'factory_blind_jobs.marshalled_at already exists — skipped.';
}

if (!$colExists('factory_blind_jobs', 'marshalled_by')) {
    $pdo->exec('ALTER TABLE factory_blind_jobs ADD COLUMN marshalled_by INT NULL AFTER marshalled_at');
    $ops[] = 'Added factory_blind_jobs.marshalled_by.';
} else {
    $ops[] = 'factory_blind_jobs.marshalled_by already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nNext: open Factory → Marshalling, generate the bench scan key, and scan\n";
echo "finished blinds in. Dispatch unlocks once every blind on an order is marshalled.\n";
