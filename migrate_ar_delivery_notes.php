<?php
declare(strict_types=1);

/**
 * Migration: wholesale A/R — delivery notes (Phase 2B).
 *
 * The first of Beverley's own "Layer B" documents: a delivery note travels with
 * the goods to a trade account (specs + quantities, NO prices). Factory-scoped
 * (factory_client_id) with the account stored separately, snapshotting its lines
 * so the note is fixed once dispatched even if the order is later edited.
 *
 *   factory_ar_delivery_notes       — header (DN number, account, order ref, status)
 *   factory_ar_delivery_note_lines  — snapshot lines (no money)
 *
 * Idempotent. Run as super-admin: /migrate_ar_delivery_notes.php
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

if (!$tableExists('factory_ar_delivery_notes')) {
    $pdo->exec(
        "CREATE TABLE factory_ar_delivery_notes (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            factory_client_id INT          NOT NULL,       -- Beverley (the sender)
            account_client_id INT          NOT NULL,       -- clients.id of the trade account
            dn_number         VARCHAR(32)  NOT NULL,       -- 'DN-2026-0001'
            source_quote_id   INT          NULL,           -- the order this DN dispatched
            status            VARCHAR(20)  NOT NULL DEFAULT 'draft',  -- draft|dispatched|cancelled
            dispatched_at     DATETIME     NULL,
            delivery_address  TEXT         NULL,            -- snapshot of ship-to at dispatch
            notes             TEXT         NULL,
            created_by        INT          NULL,
            created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_dn_number (factory_client_id, dn_number),
            KEY idx_dn_account (account_client_id, status),
            KEY idx_dn_quote (source_quote_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created table factory_ar_delivery_notes.';
} else {
    $ops[] = 'Table factory_ar_delivery_notes already exists — skipped.';
}

if (!$tableExists('factory_ar_delivery_note_lines')) {
    $pdo->exec(
        "CREATE TABLE factory_ar_delivery_note_lines (
            id                   INT AUTO_INCREMENT PRIMARY KEY,
            delivery_note_id     INT          NOT NULL,
            source_quote_item_id INT          NULL,        -- soft pointer, traceability only
            product_name         VARCHAR(190) NULL,        -- snapshot
            system_name          VARCHAR(190) NULL,
            fabric               VARCHAR(190) NULL,         -- fabric / colour / code
            band_code            VARCHAR(64)  NULL,
            width_mm             INT          NULL,
            drop_mm              INT          NULL,
            quantity             INT          NOT NULL DEFAULT 1,
            room                 VARCHAR(190) NULL,
            options_snapshot     TEXT         NULL,         -- newline list of option labels (specs only)
            line_notes           VARCHAR(255) NULL,
            sort_order           INT          NOT NULL DEFAULT 0,
            KEY idx_dnl_dn (delivery_note_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created table factory_ar_delivery_note_lines.';
} else {
    $ops[] = 'Table factory_ar_delivery_note_lines already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nMaster Admin -> Wholesale can now raise delivery notes for placed trade-account orders.\n";
