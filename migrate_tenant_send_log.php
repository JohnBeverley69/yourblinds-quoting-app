<?php
declare(strict_types=1);

/**
 * Migration: tenant_send_log — counts customer emails (quote PDFs, invoices)
 * each tenant sends, so _partials/send_quota.php can cap them per day.
 *
 * New table only — nothing existing changes. Until this runs, sending is
 * simply uncapped (the quota helper is defensive).
 *
 * Run via web: /migrate_tenant_send_log.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); require_run_confirmation(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS tenant_send_log (
        id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        client_id  INT NOT NULL,
        user_id    INT NULL,
        kind       VARCHAR(30) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_tsl_client_time (client_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

echo "tenant_send_log ready.\n";
