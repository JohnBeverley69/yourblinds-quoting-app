<?php
declare(strict_types=1);

/**
 * Drop the legacy UNIQUE(client_id, product_id) index from
 * client_markups and client_discounts.
 *
 * The earlier migrate_markup_per_system.php tried to drop this index
 * by name, but the actual constraint name on the live DB
 * (uniq_markup_client_product / uniq_discount_client_product) wasn't
 * in the candidate list, so it survived. With BOTH the legacy and the
 * new (client_id, product_id, system_id_key) uniques in place, every
 * INSERT for a per-system markup hits the legacy unique on the
 * existing first row and ON DUPLICATE KEY UPDATE just rewrites that
 * row's markup_percent — leaving system_id stuck at whatever was
 * inserted first. Per-system saves all collapse into a single row.
 *
 * This migration finds ANY non-PRIMARY unique whose column set is
 * exactly (client_id, product_id) — name-agnostic — and drops it.
 * The FK on product_id has its own non-unique backing index, so the
 * drop is safe.
 *
 * Idempotent: re-runs report "nothing to do".
 *
 * Run via web: /migrate_drop_legacy_unique.php   (super-admin login)
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

$ops = [];

foreach (['client_markups', 'client_discounts'] as $table) {
    // Find any non-PRIMARY unique whose columns are exactly
    // client_id + product_id. GROUP_CONCAT with ORDER BY SEQ gives
    // us the canonical column list — match against the literal
    // 'client_id,product_id' to identify the legacy index.
    $st = $pdo->prepare(
        "SELECT INDEX_NAME,
                GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
           FROM INFORMATION_SCHEMA.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND NON_UNIQUE  = 0
            AND INDEX_NAME != 'PRIMARY'
          GROUP BY INDEX_NAME
          HAVING cols = 'client_id,product_id'"
    );
    $st->execute([$table]);
    $found = $st->fetchAll();
    if (!$found) {
        $ops[] = "$table: no legacy (client_id, product_id) unique present";
        continue;
    }
    foreach ($found as $r) {
        $idx = (string) $r['INDEX_NAME'];
        $pdo->exec("ALTER TABLE $table DROP INDEX $idx");
        $ops[] = "$table: dropped legacy unique '$idx'";
    }
}

echo "Migration complete:\n";
foreach ($ops as $op) echo '  - ' . $op . "\n";
echo "\n";
echo "The per-system unique uniq_client_product_system is now the only\n";
echo "uniqueness constraint, which is what the pricing engine and the\n";
echo "product Edit page assume.\n";
echo "\n";
echo "Worth doing next:\n";
echo "  - Open each product's Edit page and re-enter markup % for every\n";
echo "    system. The orphan 0%-row from earlier save attempts will get\n";
echo "    overwritten cleanly now that the constraint isn't fighting us.\n";
echo "  - Delete this migration file from the server.\n";
