<?php
declare(strict_types=1);

/**
 * Migration: wholesale A/R — credit notes (Phase 2C).
 *
 * A credit note reverses/credits an invoice (returns, faults, corrections). Same
 * shape as an invoice: factory-scoped, snapshot lines, gap-free CN numbering, per-
 * document VAT. Amounts stored POSITIVE; the ledger/statement treats a CN as a
 * negative document. `settle_mode` credit = sits on the account; refund = a cash
 * payment out (recorded, never executed).
 *
 *   factory_ar_credit_notes       — header (CN number, account, against invoice, totals)
 *   factory_ar_credit_note_lines  — snapshot lines
 *
 * Idempotent. Run as super-admin: /migrate_ar_credit_notes.php
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

$tableExists = static function (string $t) use ($pdo): bool {
    $s = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
    $s->execute([$t]);
    return $s->fetchColumn() !== false;
};

$ops = [];

if (!$tableExists('factory_ar_credit_notes')) {
    $pdo->exec(
        "CREATE TABLE factory_ar_credit_notes (
            id                 INT AUTO_INCREMENT PRIMARY KEY,
            factory_client_id  INT           NOT NULL,
            account_client_id  INT           NOT NULL,
            cn_number          VARCHAR(32)   NOT NULL,       -- 'CN-2026-0001'
            against_invoice_id INT           NULL,           -- NULL = standalone account credit
            status             VARCHAR(20)   NOT NULL DEFAULT 'issued',  -- issued|void
            issue_date         DATE          NULL,
            reason             VARCHAR(255)  NULL,
            vat_percent        DECIMAL(5,2)  NOT NULL DEFAULT 20.00,
            subtotal           DECIMAL(10,2) NOT NULL DEFAULT 0,
            vat                DECIMAL(10,2) NOT NULL DEFAULT 0,
            total              DECIMAL(10,2) NOT NULL DEFAULT 0,
            settle_mode        VARCHAR(16)   NOT NULL DEFAULT 'credit',   -- credit|refund
            refund_payment_id  INT           NULL,
            bill_to_snapshot   TEXT          NULL,
            voided_at          DATETIME      NULL,
            created_by         INT           NULL,
            created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cn_number (factory_client_id, cn_number),
            KEY idx_cn_account (account_client_id, status),
            KEY idx_cn_invoice (against_invoice_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created table factory_ar_credit_notes.';
} else {
    $ops[] = 'Table factory_ar_credit_notes already exists — skipped.';
}

if (!$tableExists('factory_ar_credit_note_lines')) {
    $pdo->exec(
        "CREATE TABLE factory_ar_credit_note_lines (
            id                     INT           AUTO_INCREMENT PRIMARY KEY,
            credit_note_id         INT           NOT NULL,
            source_invoice_line_id INT           NULL,
            description            VARCHAR(255)  NOT NULL,
            width_mm               INT           NULL,
            drop_mm                INT           NULL,
            quantity               INT           NOT NULL DEFAULT 1,
            unit_net               DECIMAL(10,2) NOT NULL DEFAULT 0,
            line_net               DECIMAL(10,2) NOT NULL DEFAULT 0,
            sort_order             INT           NOT NULL DEFAULT 0,
            KEY idx_cnl_cn (credit_note_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created table factory_ar_credit_note_lines.';
} else {
    $ops[] = 'Table factory_ar_credit_note_lines already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nMaster Admin -> Wholesale can now raise credit notes against invoices.\n";
