<?php
declare(strict_types=1);

/**
 * Migration: factory_jobs — the factory's per-order production record.
 *
 * One row per order (quote) the factory has actioned. First use: "mark as
 * received" (the order is pulled into production). The status / received_*
 * here are the FACTORY's own state, kept separate from the retailer's quote
 * status (which the retailer controls). Extensible for later production
 * sub-states and dispatch.
 *
 *   factory_jobs(id, quote_id UNIQUE, status, received_at, received_by,
 *                created_at, updated_at)
 *
 * Idempotent. Run via web: /migrate_factory_jobs.php (super-admin).
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

echo "Migrating: factory_jobs table…\n\n";

$exists = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'factory_jobs'"
)->fetchColumn();

if (!$exists) {
    $pdo->exec(
        "CREATE TABLE factory_jobs (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            quote_id    INT NOT NULL,
            status      VARCHAR(20) NOT NULL DEFAULT 'received',
            received_at DATETIME NULL,
            received_by INT NULL,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_factory_jobs_quote (quote_id),
            KEY idx_factory_jobs_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created table factory_jobs.';
} else {
    $ops[] = 'Table factory_jobs already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nThe factory can now mark incoming orders as received (into production).\n";
