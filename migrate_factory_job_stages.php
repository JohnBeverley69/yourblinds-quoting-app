<?php
declare(strict_types=1);

/**
 * Migration: production-stage tracking on factory_jobs.
 *
 *   factory_jobs.status_at  DATETIME NULL  -- when the current status was set
 *   factory_jobs.status_by  INT NULL       -- which factory user set it
 *
 * The status column already exists (migrate_factory_jobs.php); it now moves
 * through a small production flow — received -> in_production -> made ->
 * dispatched — and these two columns record when/who for the current stage.
 *
 * Additive + idempotent. Run via web: /migrate_factory_job_stages.php.
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
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

$colExists = static function (string $table, string $col) use ($pdo): bool {
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $st->execute([$table, $col]);
    return (bool) $st->fetchColumn();
};

echo "Migrating: factory_jobs production-stage columns…\n\n";

if (!$colExists('factory_jobs', 'status_at')) {
    $pdo->exec("ALTER TABLE factory_jobs ADD COLUMN status_at DATETIME NULL");
    $ops[] = 'Added factory_jobs.status_at.';
} else {
    $ops[] = 'factory_jobs.status_at already exists — skipped.';
}

if (!$colExists('factory_jobs', 'status_by')) {
    $pdo->exec("ALTER TABLE factory_jobs ADD COLUMN status_by INT NULL");
    $ops[] = 'Added factory_jobs.status_by.';
} else {
    $ops[] = 'factory_jobs.status_by already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nOrders can now move received -> in production -> made -> dispatched.\n";
