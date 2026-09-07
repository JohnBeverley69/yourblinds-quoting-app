<?php
declare(strict_types=1);

/**
 * Migration: trade_promotions — time-boxed buying discounts, finer than the
 * standing per-account trade_discounts. Same axes plus a DATE WINDOW and an
 * optional global scope:
 *
 *   name, client_id (NULL = ALL accounts / global), product_id,
 *   system_id (NULL = All), band_code (NULL = All), discount_percent,
 *   starts_on (NULL = no start), ends_on (NULL = no end), active, notes.
 *
 * e.g. "20% off Band A on the Vertical system, 1–31 Oct, all accounts".
 *
 * Read live by the pricing engine alongside trade_discounts (best-discount-wins).
 * Idempotent. Run as super-admin: /migrate_trade_promotions.php
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

if (!$tableExists('trade_promotions')) {
    $pdo->exec(
        "CREATE TABLE trade_promotions (
            id                 INT AUTO_INCREMENT PRIMARY KEY,
            name               VARCHAR(150) NULL,
            client_id          INT          NULL,       -- NULL = all accounts (global)
            product_id         INT          NOT NULL,
            system_id          INT          NULL,       -- NULL = All systems
            band_code          VARCHAR(64)  NULL,       -- NULL = All bands
            extra_id           INT          NULL,       -- set = a Components (option) promotion
            choice_id          INT          NULL,       -- set = one choice; NULL w/ extra_id = whole option
            discount_percent   DOUBLE       NOT NULL DEFAULT 0,
            starts_on          DATE         NULL,       -- NULL = no start bound
            ends_on            DATE         NULL,       -- NULL = no end bound
            active             TINYINT      NOT NULL DEFAULT 1,
            notes              VARCHAR(255) NULL,
            created_by         INT          NULL,
            created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_tp_product (product_id),
            KEY idx_tp_client  (client_id),
            KEY idx_tp_extra   (extra_id),
            KEY idx_tp_window  (active, starts_on, ends_on)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created table trade_promotions.';
} else {
    $ops[] = 'Table trade_promotions already exists — skipped.';
    if (!$colExists('trade_promotions', 'extra_id')) {
        $pdo->exec('ALTER TABLE trade_promotions ADD COLUMN extra_id INT NULL AFTER band_code');
        $pdo->exec('ALTER TABLE trade_promotions ADD COLUMN choice_id INT NULL AFTER extra_id');
        $pdo->exec('ALTER TABLE trade_promotions ADD KEY idx_tp_extra (extra_id)');
        $ops[] = 'Added trade_promotions.extra_id / choice_id (Components promotions).';
    }
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nMaster Admin -> Promotions can now run time-boxed band/system discounts.\n";
echo "(The pricing engine reads these live, best-discount-wins with standing discounts.\n";
echo " An empty table changes no prices.)\n";
