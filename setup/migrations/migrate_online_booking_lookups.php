<?php
declare(strict_types=1);

/**
 * Migration: address lookup on the public online booking page.
 *
 *  - online_address_lookups: one row per Postcoder lookup made from the public
 *    page, so the limits per visitor and per company (ob_address_rate_limited
 *    in _partials/online_booking.php) can be counted. Each lookup costs money.
 *
 * Run via web: /setup/migrations/migrate_online_booking_lookups.php (super-admin). Idempotent.
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once dirname(__DIR__, 2) . '/auth/middleware.php'; requireSuperAdmin(); require_run_confirmation(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS online_address_lookups (
        id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        client_id   INT UNSIGNED NOT NULL,
        ip_address  VARCHAR(45)  NOT NULL DEFAULT '',
        postcode    VARCHAR(8)   NOT NULL DEFAULT '',
        created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_oal_client_time (client_id, created_at),
        KEY idx_oal_ip_time (ip_address, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
echo "online_address_lookups table ready.\n";
echo "Done.\n";
