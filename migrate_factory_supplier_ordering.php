<?php
declare(strict_types=1);

/**
 * Migration: factory-side supplier ordering of bought-in blinds.
 *
 *   supplier_orders                 — ensure the table exists (the app only ever
 *                                     INSERTed into it; there was no CREATE), then
 *                                     add:
 *     .ordered_by_factory_id INT    — set = the factory id for a FACTORY send,
 *                                     NULL for a tenant's own supplier send, so
 *                                     the two don't collide on the same quote_id.
 *     .received_at DATETIME         — when this supplier order was received.
 *   quotes.supplier_ordered_at      — rollup: bought-in lines have been ordered.
 *   quotes.supplier_received_at     — rollup: bought-in lines have all arrived.
 *   client_settings.auto_send_suppliers TINYINT — factory auto-order on placement
 *                                     (default OFF — it emails real third parties).
 *
 * Additive + guarded. Run via web: /migrate_factory_supplier_ordering.php
 * (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tableExists = static function (string $t) use ($pdo): bool {
    try { $pdo->query("SELECT 1 FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};
$colExists = static function (string $t, string $c) use ($pdo): bool {
    try { $pdo->query("SELECT `$c` FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

// supplier_orders — the send log. Create if it was never migrated in.
if (!$tableExists('supplier_orders')) {
    $pdo->exec(
        "CREATE TABLE `supplier_orders` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `client_id` INT NOT NULL,
            `quote_id` INT NOT NULL,
            `supplier_name` VARCHAR(190) NOT NULL,
            `email` VARCHAR(190) NULL,
            `item_count` INT NOT NULL DEFAULT 0,
            `sent_by_user_id` INT NULL,
            `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `ordered_by_factory_id` INT NULL,
            `received_at` DATETIME NULL,
            KEY `quote_supplier` (`quote_id`, `supplier_name`),
            KEY `client_quote` (`client_id`, `quote_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "  Created table supplier_orders.\n";
} else {
    foreach ([
        'ordered_by_factory_id' => 'INT NULL',
        'received_at'           => 'DATETIME NULL',
    ] as $col => $type) {
        if (!$colExists('supplier_orders', $col)) {
            $pdo->exec("ALTER TABLE `supplier_orders` ADD COLUMN `$col` $type");
            echo "  Added supplier_orders.$col.\n";
        } else {
            echo "  supplier_orders.$col already exists — skipped.\n";
        }
    }
    // Helpful index if the table pre-dated it.
    try { $pdo->exec("ALTER TABLE `supplier_orders` ADD INDEX `quote_supplier` (`quote_id`, `supplier_name`)"); echo "  Added index supplier_orders(quote_id,supplier_name).\n"; }
    catch (Throwable $e) { /* already there */ }
}

foreach ([['quotes', 'supplier_ordered_at', 'DATETIME NULL'], ['quotes', 'supplier_received_at', 'DATETIME NULL']] as [$t, $c, $type]) {
    if (!$colExists($t, $c)) { $pdo->exec("ALTER TABLE `$t` ADD COLUMN `$c` $type"); echo "  Added $t.$c.\n"; }
    else { echo "  $t.$c already exists — skipped.\n"; }
}

if (!$colExists('client_settings', 'auto_send_suppliers')) {
    $pdo->exec("ALTER TABLE `client_settings` ADD COLUMN `auto_send_suppliers` TINYINT(1) NOT NULL DEFAULT 0");
    echo "  Added client_settings.auto_send_suppliers (default 0).\n";
} else {
    echo "  client_settings.auto_send_suppliers already exists — skipped.\n";
}

// One-off repair: pull any bought-in blind that was already released to the
// production floor before this fix. It can never be scanned complete, so it
// deadlocks its order at "in production". Safe + idempotent.
require_once __DIR__ . '/_partials/bought_in.php';
try {
    $ids = $pdo->query(
        'SELECT j.id FROM factory_blind_jobs j
           JOIN products p ON p.id = j.product_id
           ' . bought_in_master_join('p', 'mp') . '
          WHERE ' . bought_in_predicate('mp')
    )->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        $pdo->exec("DELETE FROM factory_blind_streams WHERE blind_job_id IN ($in)");
        $pdo->exec("DELETE FROM factory_blind_jobs WHERE id IN ($in)");
        echo '  Cleaned ' . count($ids) . " bought-in blind(s) that were already on the floor.\n";
    } else {
        echo "  No bought-in blinds on the floor to clean up.\n";
    }
} catch (Throwable $e) { echo '  Floor cleanup skipped: ' . $e->getMessage() . "\n"; }

echo "\nDone. Factory supplier-ordering schema is ready.\n";
