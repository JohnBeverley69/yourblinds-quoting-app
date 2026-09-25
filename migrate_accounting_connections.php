<?php
declare(strict_types=1);

/**
 * accounting_connections — one row per (client_id, provider) holding a tenant's
 * OAuth connection to their accounting package (QuickBooks first; Xero / Sage
 * slot in behind the same AccountingProvider interface later).
 *
 * Stores the OAuth tokens (sealed via _partials/accounting.php ac_seal), the
 * provider's company/realm id, the field-mapping (JSON, filled in Phase 2), and
 * a small status/last-error so Settings can show the connection state.
 *
 * Idempotent, super-admin, web-runnable: /migrate_accounting_connections.php
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

$exists = $pdo->query("SHOW TABLES LIKE 'accounting_connections'")->fetchColumn();
if ($exists) {
    echo "accounting_connections already exists — skipped.\n";
} else {
    $pdo->exec(
        "CREATE TABLE accounting_connections (
            id                  INT AUTO_INCREMENT PRIMARY KEY,
            client_id           INT NOT NULL,
            provider            VARCHAR(32)  NOT NULL,                 -- quickbooks | xero | sage
            environment         VARCHAR(16)  NOT NULL DEFAULT 'sandbox',
            realm_id            VARCHAR(64)  NULL,                     -- QBO company id (realmId) / Xero tenantId
            company_name        VARCHAR(255) NULL,                     -- friendly name shown in Settings
            access_token        TEXT NULL,                            -- sealed
            refresh_token       TEXT NULL,                            -- sealed
            access_expires_at   DATETIME NULL,
            refresh_expires_at  DATETIME NULL,
            mapping_json        TEXT NULL,                            -- Phase 2: sales item / VAT code / income+bank account
            status              VARCHAR(16)  NOT NULL DEFAULT 'disconnected', -- connected | disconnected | error
            last_error          TEXT NULL,
            connected_at        DATETIME NULL,
            created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_client_provider (client_id, provider),
            KEY idx_client (client_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "Created accounting_connections.\n";
}

echo "\nDone. Configure QUICKBOOKS_* in .env, then Settings → Accounting → Connect.\n";
