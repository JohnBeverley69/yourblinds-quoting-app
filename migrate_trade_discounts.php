<?php
declare(strict_types=1);

/**
 * Migration: trade_discounts (+ trade_discount_audit).
 *
 * The wholesale/buying discount a trade account gets on OUR products, mirroring
 * Blind Matrix's "Price Table Discount" grid: a list of rows per account, each
 *   Discount %  ·  Product  ·  Material Group (band; NULL = All)
 * Read live by the pricing engine (a later PR) as a buying discount off the
 * trade price. One row per (client, product, band) — enforced in the app so we
 * don't need a generated column (portability).
 *
 * trade_discount_audit records every add/update/delete for the change history
 * shown on the Trade Account page.
 *
 * Idempotent. Run as super-admin: /migrate_trade_discounts.php
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
    $s = $pdo->prepare(
        "SELECT 1 FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1"
    );
    $s->execute([$t]);
    return $s->fetchColumn() !== false;
};
$colExists = static function (string $t, string $c) use ($pdo): bool {
    $s = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1"
    );
    $s->execute([$t, $c]);
    return $s->fetchColumn() !== false;
};

$ops = [];

if (!$tableExists('trade_discounts')) {
    $pdo->exec(
        "CREATE TABLE trade_discounts (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            client_id        INT           NOT NULL,
            product_id       INT           NOT NULL,
            system_id        INT           NULL,          -- NULL = All systems
            band_code        VARCHAR(64)   NULL,          -- NULL = All materials/bands
            discount_percent DOUBLE        NOT NULL DEFAULT 0,
            active           TINYINT       NOT NULL DEFAULT 1,
            notes            VARCHAR(255)  NULL,
            created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_td_client (client_id),
            KEY idx_td_client_product (client_id, product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created table trade_discounts.';
} else {
    $ops[] = 'Table trade_discounts already exists — skipped.';
    if (!$colExists('trade_discounts', 'system_id')) {
        $pdo->exec('ALTER TABLE trade_discounts ADD COLUMN system_id INT NULL AFTER product_id');
        $ops[] = 'Added trade_discounts.system_id (NULL = All systems).';
    }
}

if (!$tableExists('trade_discount_audit')) {
    $pdo->exec(
        "CREATE TABLE trade_discount_audit (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            client_id        INT           NOT NULL,
            product_id       INT           NULL,
            product_name     VARCHAR(150)  NULL,
            system_id        INT           NULL,
            system_name      VARCHAR(150)  NULL,
            band_code        VARCHAR(64)   NULL,
            old_pct          DOUBLE        NULL,
            new_pct          DOUBLE        NULL,
            action           VARCHAR(20)   NOT NULL,      -- add | update | delete
            changed_by       INT           NULL,
            changed_by_name  VARCHAR(150)  NULL,
            changed_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_tda_client (client_id, changed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created table trade_discount_audit.';
} else {
    $ops[] = 'Table trade_discount_audit already exists — skipped.';
    if (!$colExists('trade_discount_audit', 'system_id')) {
        $pdo->exec('ALTER TABLE trade_discount_audit ADD COLUMN system_id INT NULL AFTER product_name');
        $pdo->exec('ALTER TABLE trade_discount_audit ADD COLUMN system_name VARCHAR(150) NULL AFTER system_id');
        $ops[] = 'Added trade_discount_audit.system_id / system_name.';
    }
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nTrade Accounts → an account → Discounts can now record per-product/band buying discounts.\n";
echo "(The pricing engine reads these in a later PR; creating the tables changes no prices.)\n";
