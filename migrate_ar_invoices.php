<?php
declare(strict_types=1);

/**
 * Migration: wholesale A/R — invoices (Phase 2C).
 *
 * Beverley's VAT invoices to its trade accounts, raised from placed orders. Like
 * delivery notes: factory-scoped, snapshot lines (fixed at the tax point — never
 * re-read from quote_items), gap-free numbering. Money DECIMAL(10,2), per-document
 * VAT. A raised/sent invoice is immutable: correct by VOID + reissue, or a credit
 * note (separate migration).
 *
 *   factory_ar_invoices        — header (INV number, account, VAT totals, status)
 *   factory_ar_invoice_lines   — snapshot lines (net wholesale + display discount)
 *   factory_ar_invoice_orders  — invoice ↔ order (many-to-many; split/consolidated)
 *
 * Idempotent. Run as super-admin: /migrate_ar_invoices.php
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

if (!$tableExists('factory_ar_invoices')) {
    $pdo->exec(
        "CREATE TABLE factory_ar_invoices (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            factory_client_id INT           NOT NULL,
            account_client_id INT           NOT NULL,
            inv_number        VARCHAR(32)   NOT NULL,       -- 'INV-2026-0001'
            status            VARCHAR(20)   NOT NULL DEFAULT 'draft',
                              -- draft|raised|sent|part_paid|paid|void
            issue_date        DATE          NULL,           -- tax point (set at raise)
            due_date          DATE          NULL,
            vat_percent       DECIMAL(5,2)  NOT NULL DEFAULT 20.00,
            subtotal          DECIMAL(10,2) NOT NULL DEFAULT 0,   -- net (sum of lines)
            vat               DECIMAL(10,2) NOT NULL DEFAULT 0,
            total             DECIMAL(10,2) NOT NULL DEFAULT 0,
            amount_paid       DECIMAL(10,2) NOT NULL DEFAULT 0,   -- cache = SUM(allocations)
            bill_to_snapshot  TEXT          NULL,            -- account name/addr/VAT at issue
            notes             TEXT          NULL,
            sent_at           DATETIME      NULL,
            voided_at         DATETIME      NULL,
            void_reason       VARCHAR(255)  NULL,
            replaced_by_id    INT           NULL,            -- void → reissue chain
            created_by        INT           NULL,
            created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_inv_number (factory_client_id, inv_number),
            KEY idx_inv_account (account_client_id, status),
            KEY idx_inv_status (status),
            KEY idx_inv_issue (issue_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created table factory_ar_invoices.';
} else {
    $ops[] = 'Table factory_ar_invoices already exists — skipped.';
}

if (!$tableExists('factory_ar_invoice_lines')) {
    $pdo->exec(
        "CREATE TABLE factory_ar_invoice_lines (
            id                   INT           AUTO_INCREMENT PRIMARY KEY,
            invoice_id           INT           NOT NULL,
            source_quote_id      INT           NULL,
            source_quote_item_id INT           NULL,         -- soft pointer (traceability)
            line_type            VARCHAR(16)   NOT NULL DEFAULT 'blind',  -- blind|option|adjust|carriage
            description          VARCHAR(255)  NOT NULL,     -- snapshot
            width_mm             INT           NULL,
            drop_mm              INT           NULL,
            quantity             INT           NOT NULL DEFAULT 1,
            unit_net             DECIMAL(10,2) NOT NULL DEFAULT 0,   -- wholesale net per unit
            line_net             DECIMAL(10,2) NOT NULL DEFAULT 0,   -- round(unit_net*qty,2)
            list_trade_unit      DECIMAL(10,2) NULL,          -- pre-discount trade price (display)
            discount_percent     DECIMAL(6,2)  NULL,
            discount_amount      DECIMAL(10,2) NULL,
            sort_order           INT           NOT NULL DEFAULT 0,
            KEY idx_invl_invoice (invoice_id),
            KEY idx_invl_srcitem (source_quote_item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created table factory_ar_invoice_lines.';
} else {
    $ops[] = 'Table factory_ar_invoice_lines already exists — skipped.';
}

if (!$tableExists('factory_ar_invoice_orders')) {
    $pdo->exec(
        "CREATE TABLE factory_ar_invoice_orders (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT NOT NULL,
            quote_id   INT NOT NULL,
            UNIQUE KEY uq_inv_order (invoice_id, quote_id),
            KEY idx_io_quote (quote_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created table factory_ar_invoice_orders.';
} else {
    $ops[] = 'Table factory_ar_invoice_orders already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nMaster Admin -> Wholesale can now raise VAT invoices for placed trade-account orders.\n";
