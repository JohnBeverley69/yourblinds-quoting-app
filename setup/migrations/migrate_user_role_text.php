<?php
declare(strict_types=1);

/**
 * Migration: client_users.role becomes VARCHAR(32), as it already is on live.
 *
 * The roles are Admin, Office, Sales, Fitter and Factory (migrate_retire_roles.php);
 * an old ENUM('admin','owner','office','sales','agent','readonly') can't hold
 * 'fitter' or 'factory'. Live was already VARCHAR; databases built from an older
 * tests/schema.sql still had the ENUM. Found by the restore drill's schema check
 * (2026-10-17). Widening only — every existing value fits.
 *
 * Run via web: /setup/migrations/migrate_user_role_text.php?run=1 (super-admin). Idempotent.
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once dirname(__DIR__, 2) . '/auth/middleware.php'; requireSuperAdmin(); require_run_confirmation(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$type = (string) $pdo->query("SELECT DATA_TYPE FROM information_schema.COLUMNS
                               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_users' AND COLUMN_NAME = 'role'")->fetchColumn();
if ($type === 'varchar') {
    echo "  client_users.role is already VARCHAR — skipped.\n";
} else {
    $pdo->exec("ALTER TABLE client_users MODIFY role VARCHAR(32) NOT NULL DEFAULT 'sales'");
    echo "  client_users.role changed from " . ($type ?: '?') . " to VARCHAR(32).\n";
}

echo "\nDone.\n";
