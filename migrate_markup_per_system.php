<?php
declare(strict_types=1);

/**
 * Migration: markup + discount become per-system, not per-product.
 *
 * Before:
 *   client_markups   (client_id, product_id, markup_percent)
 *   client_discounts (client_id, product_id, discount_percent)
 *   One markup per product. Premium / motorised / standard all got
 *   the same number — wrong for businesses where the margin model
 *   varies by system.
 *
 * After:
 *   client_markups   (client_id, product_id, system_id NULL, markup_percent)
 *   client_discounts (client_id, product_id, system_id NULL, discount_percent)
 *
 *   system_id NULL  → applies to products with no systems (rare, but legal)
 *   system_id != NULL → applies only when that system is picked
 *
 * Uniqueness: MySQL treats NULLs as distinct in UNIQUE keys, so a raw
 * UNIQUE(client_id, product_id, system_id) would allow multiple
 * "system_id IS NULL" rows for the same (client, product). We add a
 * generated stored column system_id_key = IFNULL(system_id, 0) and
 * UNIQUE on that instead — guarantees one row per (client, product,
 * system) combination including the NULL case.
 *
 * Migration steps:
 *   1. Add system_id (nullable INT UNSIGNED) + system_id_key (generated
 *      stored, IFNULL(system_id, 0)) on both tables.
 *   2. Drop the old uniq_client_product index (if present).
 *   3. Add uniq_client_product_system over (client_id, product_id,
 *      system_id_key).
 *   4. Add FK system_id → product_systems(id) ON DELETE CASCADE so
 *      deleting a system cleans up its markup row.
 *   5. Backfill: for each existing markup/discount row, if the product
 *      has any systems, INSERT one copy per system carrying the same
 *      percent, then DELETE the original (system_id NULL) row. Rows
 *      for products without systems stay as-is (system_id NULL).
 *
 * Idempotent — re-runnable. Existing column / index / FK is detected
 * and skipped. Pricing output is unchanged at cut-over (every system
 * inherits its product's pre-migration markup).
 *
 * Run via web: /migrate_markup_per_system.php   (super-admin login)
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

set_exception_handler(function (Throwable $e) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "MIGRATION FAILED\n================\n\n";
    echo 'Error: ' . $e->getMessage() . "\n";
    echo 'In:    ' . $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
});

function col_exists(PDO $pdo, string $table, string $col): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $st->execute([$table, $col]);
    return $st->fetchColumn() !== false;
}

function index_exists(PDO $pdo, string $table, string $index): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
    );
    $st->execute([$table, $index]);
    return $st->fetchColumn() !== false;
}

function fk_exists(PDO $pdo, string $table, string $fk): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
            AND CONSTRAINT_TYPE = "FOREIGN KEY" LIMIT 1'
    );
    $st->execute([$table, $fk]);
    return $st->fetchColumn() !== false;
}

$ops = [];

foreach (
    [
        'client_markups'   => ['percent_col' => 'markup_percent',   'fk_name' => 'fk_client_markups_system'],
        'client_discounts' => ['percent_col' => 'discount_percent', 'fk_name' => 'fk_client_discounts_system'],
    ] as $table => $meta
) {
    // 1. system_id column
    if (!col_exists($pdo, $table, 'system_id')) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN system_id INT UNSIGNED NULL AFTER product_id");
        $ops[] = "$table: added column system_id";
    } else {
        $ops[] = "$table: column system_id already present";
    }

    // 2. system_id_key generated stored column (for NULL-safe uniqueness)
    if (!col_exists($pdo, $table, 'system_id_key')) {
        $pdo->exec(
            "ALTER TABLE $table
                ADD COLUMN system_id_key INT UNSIGNED
                AS (IFNULL(system_id, 0)) STORED AFTER system_id"
        );
        $ops[] = "$table: added generated column system_id_key";
    } else {
        $ops[] = "$table: column system_id_key already present";
    }

    // 3. Drop old uniq (single-product) if present. Tables predate the
    //    in-repo migrations, so the constraint name may vary — handle
    //    the common name and skip silently if not found.
    foreach (['uniq_client_product', 'uniq_client_markup', 'uniq_client_discount'] as $oldIdx) {
        if (index_exists($pdo, $table, $oldIdx)) {
            // FKs on client_id / product_id need a backing index. Add
            // a plain index if there isn't one already, otherwise the
            // DROP fails with "needed in a foreign key constraint".
            if (!index_exists($pdo, $table, 'idx_' . $table . '_product')) {
                $pdo->exec(
                    "ALTER TABLE $table ADD INDEX idx_${table}_product (client_id, product_id)"
                );
                $ops[] = "$table: added backing index idx_${table}_product";
            }
            $pdo->exec("ALTER TABLE $table DROP INDEX $oldIdx");
            $ops[] = "$table: dropped old unique $oldIdx";
        }
    }

    // 4. Add new uniq over (client_id, product_id, system_id_key)
    if (!index_exists($pdo, $table, 'uniq_client_product_system')) {
        $pdo->exec(
            "ALTER TABLE $table
                ADD UNIQUE KEY uniq_client_product_system
                    (client_id, product_id, system_id_key)"
        );
        $ops[] = "$table: added unique uniq_client_product_system";
    } else {
        $ops[] = "$table: unique uniq_client_product_system already present";
    }

    // 5. FK system_id → product_systems(id)
    if (!fk_exists($pdo, $table, $meta['fk_name'])) {
        $pdo->exec(
            "ALTER TABLE $table
                ADD CONSTRAINT {$meta['fk_name']}
                    FOREIGN KEY (system_id) REFERENCES product_systems(id)
                    ON DELETE CASCADE ON UPDATE CASCADE"
        );
        $ops[] = "$table: added FK {$meta['fk_name']}";
    } else {
        $ops[] = "$table: FK {$meta['fk_name']} already present";
    }
}

// 6. Backfill — expand each (system_id IS NULL) row into one row per
//    system for products that have systems. Idempotent: INSERT IGNORE
//    skips already-expanded rows, and the DELETE only fires for rows
//    that we successfully expanded.
$expandRows = function (string $table, string $col) use ($pdo): int {
    // Find rows still on system_id NULL where the product DOES have systems.
    $sel = $pdo->query(
        "SELECT m.id, m.client_id, m.product_id, m.$col
           FROM $table m
          WHERE m.system_id IS NULL
            AND EXISTS (
                SELECT 1 FROM product_systems s
                 WHERE s.product_id = m.product_id
                   AND s.client_id  = m.client_id
                   AND s.active     = 1
            )"
    );
    $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) return 0;

    $sysStmt = $pdo->prepare(
        'SELECT id FROM product_systems
          WHERE product_id = ? AND client_id = ? AND active = 1'
    );
    $insStmt = $pdo->prepare(
        "INSERT IGNORE INTO $table (client_id, product_id, system_id, $col)
         VALUES (?, ?, ?, ?)"
    );
    $delStmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");

    $expanded = 0;
    foreach ($rows as $r) {
        $sysStmt->execute([$r['product_id'], $r['client_id']]);
        $systemIds = $sysStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$systemIds) continue;
        foreach ($systemIds as $sid) {
            $insStmt->execute([
                $r['client_id'], $r['product_id'], (int) $sid, $r[$col],
            ]);
            $expanded++;
        }
        $delStmt->execute([$r['id']]);
    }
    return $expanded;
};

$mExpanded = $expandRows('client_markups',   'markup_percent');
$dExpanded = $expandRows('client_discounts', 'discount_percent');
$ops[] = "client_markups:   expanded $mExpanded per-system row(s)";
$ops[] = "client_discounts: expanded $dExpanded per-system row(s)";

echo "Migration complete:\n";
foreach ($ops as $op) {
    echo '  - ' . $op . "\n";
}
echo "\n";
echo "Markup and discount are now stored per (product, system). Existing\n";
echo "values were copied across each product's systems, so prices don't\n";
echo "change at cut-over — you can now tune Premium vs Standard vs\n";
echo "Motorised independently from the product Edit page.\n";
echo "\n";
echo "When you're confident, you can delete this file from the server.\n";
