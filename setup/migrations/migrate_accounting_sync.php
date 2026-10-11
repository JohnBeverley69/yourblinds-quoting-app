<?php
declare(strict_types=1);

/**
 * Migration: the live accounting link's working tables (QuickBooks first).
 *
 *  - accounting_log            every failed call and every send, with the
 *                              provider's reference (Intuit's intuit_tid) —
 *                              the shareable error log Intuit asks for.
 *  - accounting_customer_links which customer in the accounting package each
 *                              trade account is (matched once, confirmed by
 *                              the accounts person) — never create duplicates.
 *  - accounting_sync           what has been sent: one row per invoice /
 *                              payment / credit note, with the package's id,
 *                              so nothing is ever sent twice and a failure
 *                              can be retried.
 *
 * New tables only. Run via web: /setup/migrations/migrate_accounting_sync.php
 * (super-admin). Idempotent.
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once dirname(__DIR__, 2) . '/auth/middleware.php'; requireSuperAdmin(); require_run_confirmation(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS accounting_log (
        id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        client_id    INT NOT NULL,
        provider     VARCHAR(20)  NOT NULL,
        action       VARCHAR(60)  NOT NULL DEFAULT '',
        result       VARCHAR(20)  NOT NULL DEFAULT '',
        message      TEXT NULL,
        http_status  SMALLINT NULL,
        provider_tid VARCHAR(80)  NULL,
        ref          VARCHAR(80)  NULL,
        created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_aclog_client_time (client_id, provider, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "accounting_log ready.\n";

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS accounting_customer_links (
        id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        client_id         INT NOT NULL,
        provider          VARCHAR(20)  NOT NULL,
        account_client_id INT NOT NULL,
        external_id       VARCHAR(40)  NOT NULL,
        external_name     VARCHAR(190) NOT NULL DEFAULT '',
        linked_by         INT NULL,
        linked_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_aclink (client_id, provider, account_client_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "accounting_customer_links ready.\n";

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS accounting_sync (
        id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        client_id    INT NOT NULL,
        provider     VARCHAR(20)  NOT NULL,
        entity       VARCHAR(20)  NOT NULL,
        local_id     INT NOT NULL,
        external_id  VARCHAR(40)  NULL,
        status       VARCHAR(20)  NOT NULL DEFAULT 'pending',
        attempts     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        last_error   TEXT NULL,
        provider_tid VARCHAR(80)  NULL,
        sent_at      DATETIME NULL,
        created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_acsync (client_id, provider, entity, local_id),
        KEY idx_acsync_status (client_id, provider, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "accounting_sync ready.\n";
echo "Done.\n";
