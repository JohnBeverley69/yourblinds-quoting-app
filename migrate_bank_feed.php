<?php
declare(strict_types=1);

/**
 * Migration: factory_bank_transactions — the factory's own Barclays account,
 * pulled in through Lunch Flow (Open Banking, read-only). One row per bank line;
 * a money-in line is matched to a factory_ar_payments row (payment_id) or
 * ignored (not a customer payment). Factory-only — never offered to tenants.
 *
 * Run via web: /migrate_bank_feed.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); require_run_confirmation(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tableExists = static function (string $t) use ($pdo): bool {
    $s = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
    $s->execute([$t]);
    return $s->fetchColumn() !== false;
};

if (!$tableExists('factory_bank_transactions')) {
    $pdo->exec(
        "CREATE TABLE factory_bank_transactions (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            factory_client_id INT           NOT NULL,
            provider          VARCHAR(20)   NOT NULL DEFAULT 'lunchflow',
            provider_txn_id   VARCHAR(191)  NOT NULL,
            bank_account_id   VARCHAR(64)   NULL,
            txn_date          DATE          NOT NULL,
            amount            DECIMAL(12,2) NOT NULL,           -- + money in, - money out
            currency          VARCHAR(8)    NULL,
            description       VARCHAR(255)  NULL,
            merchant          VARCHAR(255)  NULL,
            status            VARCHAR(12)   NOT NULL DEFAULT 'new',   -- new|matched|ignored
            payment_id        INT           NULL,               -- factory_ar_payments.id
            actioned_by       INT           NULL,
            actioned_at       DATETIME      NULL,
            created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_bank_txn (factory_client_id, provider, provider_txn_id),
            KEY idx_bank_date (factory_client_id, txn_date),
            KEY idx_bank_payment (payment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "  Created table factory_bank_transactions.\n";
} else {
    echo "  Table factory_bank_transactions already exists — skipped.\n";
}

// Learned payers: the bank's payer name (normalised) → trade account. Written
// when a line is matched by hand or "Remember"ed on Trade → Bank.
if (!$tableExists('factory_bank_payer_aliases')) {
    $pdo->exec(
        "CREATE TABLE factory_bank_payer_aliases (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            factory_client_id INT          NOT NULL,
            payer_key         VARCHAR(64)  NOT NULL,        -- bf_payer_key()
            payer_label       VARCHAR(64)  NULL,            -- as the bank shows it
            account_client_id INT          NOT NULL,
            created_by        INT          NULL,
            updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_payer (factory_client_id, payer_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "  Created table factory_bank_payer_aliases.\n";
} else {
    echo "  Table factory_bank_payer_aliases already exists — skipped.\n";
}

echo "\nDone. Open Trade → Bank to connect Lunch Flow and fetch transactions.\n";
