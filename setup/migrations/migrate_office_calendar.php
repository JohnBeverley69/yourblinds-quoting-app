<?php
declare(strict_types=1);

/**
 * Office calendar (Factory Console stage 4, John 2026-10-09).
 *
 * factory_office_entries — the factory office's shared calendar:
 *   note      something for a day ("Louvolite delivery Tuesday")
 *   reminder  a to-do, ticked when done; not done = carries forward
 *   callback  ring a trade account back (optionally about an order); also shows
 *             on that account's page
 * Strictly internal — trade accounts never see any of it.
 *
 * Idempotent, super-admin, web-runnable: /setup/migrations/migrate_office_calendar.php
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

$s = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
$s->execute(['factory_office_entries']);
if ($s->fetchColumn()) {
    echo "factory_office_entries already exists — skipped.\n";
} else {
    $pdo->exec("CREATE TABLE factory_office_entries (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        factory_client_id INT          NOT NULL,
        kind              VARCHAR(10)  NOT NULL DEFAULT 'note',   -- note|reminder|callback
        entry_date        DATE         NOT NULL,
        entry_time        TIME         NULL,
        title             VARCHAR(255) NOT NULL,
        detail            TEXT         NULL,
        account_client_id INT          NULL,
        order_ref         VARCHAR(60)  NULL,
        quote_id          INT          NULL,
        done_at           DATETIME     NULL,
        done_by_user_id   INT          NULL,
        created_by        INT          NULL,
        created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_oe_day (factory_client_id, entry_date),
        KEY idx_oe_open (factory_client_id, done_at, kind),
        KEY idx_oe_account (account_client_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created factory_office_entries.\n";
}
echo "\nDone. Factory Console → Calendar is ready.\n";
