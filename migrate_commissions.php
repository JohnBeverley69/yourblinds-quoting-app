<?php
declare(strict_types=1);

/**
 * Migration: sales_consultants + trade_commissions (+ a per-account notify flag).
 *
 * Mirrors Blind Matrix's per-account COMMISSION tab: a list of rows, each
 *   Commission %  ·  Sales Consultant (rep)  ·  Product (NULL = All)
 * plus a per-account "email the consultant when orders are placed through the
 * trade portal" toggle. This is CONFIG only — the commission statement/report
 * (turnover × %) is Phase 2, since it needs the accounts' purchase turnover.
 *
 *   sales_consultants  — the reps who introduce business (e.g. "SS").
 *   trade_commissions  — per (account, consultant, product) commission rate.
 *   clients.notify_consultant_orders — the email toggle (added if missing).
 *
 * Idempotent. Run as super-admin: /migrate_commissions.php
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
$colExists = static function (string $t, string $c) use ($pdo): bool {
    $s = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    $s->execute([$t, $c]);
    return $s->fetchColumn() !== false;
};

$ops = [];

if (!$tableExists('sales_consultants')) {
    $pdo->exec(
        "CREATE TABLE sales_consultants (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(150) NOT NULL,
            email      VARCHAR(190) NULL,
            active     TINYINT      NOT NULL DEFAULT 1,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_sc_active (active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created table sales_consultants.';
} else {
    $ops[] = 'Table sales_consultants already exists — skipped.';
}

if (!$tableExists('trade_commissions')) {
    $pdo->exec(
        "CREATE TABLE trade_commissions (
            id                 INT AUTO_INCREMENT PRIMARY KEY,
            client_id          INT          NOT NULL,   -- the trade account
            consultant_id      INT          NOT NULL,   -- sales_consultants.id
            product_id         INT          NULL,       -- NULL = All products
            commission_percent DOUBLE       NOT NULL DEFAULT 0,
            active             TINYINT      NOT NULL DEFAULT 1,
            notes              VARCHAR(255) NULL,
            created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_tc_client (client_id),
            KEY idx_tc_consultant (consultant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created table trade_commissions.';
} else {
    $ops[] = 'Table trade_commissions already exists — skipped.';
}

// Per-account "email the consultant on trade-portal orders" flag.
if (!$colExists('clients', 'notify_consultant_orders')) {
    try {
        $pdo->exec('ALTER TABLE clients ADD COLUMN notify_consultant_orders TINYINT NOT NULL DEFAULT 0');
        $ops[] = 'Added clients.notify_consultant_orders.';
    } catch (Throwable $e) {
        $ops[] = 'Could not add clients.notify_consultant_orders: ' . $e->getMessage();
    }
} else {
    $ops[] = 'Column clients.notify_consultant_orders already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nTrade Accounts → an account → Commission can now record a rep + rate per product.\n";
echo "(The commission statement/report is Phase 2 — it needs the accounts' purchase turnover.)\n";
