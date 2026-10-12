<?php
declare(strict_types=1);

/**
 * Migration: offline_refs, the database record of offline outbox refs already
 * done (see _partials/offline_refs.php).
 *
 * A blind, new quote or quote email saved on a tablet with no signal carries a
 * one-off client_ref. Refs already done were only remembered in the PHP session,
 * so a resend after the tablet signed in again could add the blind (or create
 * the quote, or email the customer) twice. One row per ref, unique per tenant.
 *
 * Idempotent. Run via web: /setup/migrations/migrate_offline_refs.php (super-admin).
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

echo "Migrating: offline_refs…\n\n";

$st = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'offline_refs'");
$st->execute();
if ($st->fetchColumn()) {
    echo "  1. offline_refs already exists — skipped.\n";
} else {
    $pdo->exec(
        "CREATE TABLE offline_refs (
            client_id  INT UNSIGNED NOT NULL,
            kind       VARCHAR(16)  NOT NULL,
            ref        VARCHAR(64)  NOT NULL,
            target_id  INT UNSIGNED NULL,
            created_at DATETIME     NOT NULL,
            PRIMARY KEY (client_id, kind, ref),
            KEY idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin"
    );
    echo "  1. Created offline_refs.\n";
}

echo "\nMigration complete.\n";
