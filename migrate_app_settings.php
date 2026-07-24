<?php
declare(strict_types=1);

/**
 * Migration: the global (site-wide) key/value settings table.
 *
 * app_settings holds switches that belong to nobody's tenant — currently the
 * "pause all outgoing email" testing toggle and the "public sign-up open"
 * toggle. The accessors live in _partials/app_settings.php.
 *
 * This table shipped with the email-pause feature but never had a migration of
 * its own (it was created out-of-band on the live DB), so a fresh install had no
 * table and the flags silently read as their defaults. This backfills that gap.
 * IF NOT EXISTS makes it a no-op where the table already exists.
 *
 *   app_settings (setting_key PK, setting_value, updated_at)
 *
 * A missing key means "use the code default", so nothing changes until a value
 * is written.
 *
 * Run via web: /migrate_app_settings.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$existed = false;
try { $pdo->query('SELECT 1 FROM app_settings LIMIT 0'); $existed = true; }
catch (Throwable $e) { $existed = false; }

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS app_settings (
        setting_key   VARCHAR(64) NOT NULL PRIMARY KEY,
        setting_value TEXT NULL,
        updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

echo $existed
    ? "  app_settings already existed — no change.\n"
    : "  Created app_settings.\n";

echo "\nDone. No rows = code defaults apply (email sending ON, public sign-up ON).\n";
