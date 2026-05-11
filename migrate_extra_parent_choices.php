<?php
declare(strict_types=1);

/**
 * Migration: a follow-up option (product_extras row) can now be gated
 * to MULTIPLE parent choices via a junction table — e.g. one "Bottom
 * Weight Colour" option that shows when either "Chained" or
 * "Chainless" is selected, instead of two separate colour options.
 *
 * Steps:
 *   1. Create product_extra_parent_choices (extra_id, choice_id) with
 *      a unique (extra_id, choice_id) pair and FKs to both sides.
 *   2. Backfill from the existing product_extras.parent_choice_id
 *      column — every row with a non-NULL parent gets one junction
 *      row, preserving current behaviour.
 *   3. Drop the uniq_extra_per_product constraint. Two follow-up
 *      options can now share a name (e.g. two "Colour" options
 *      gated to different parents) — though with multi-parent
 *      support, you usually won't need to.
 *
 * The old parent_choice_id column stays for now (back-compat); new
 * code reads the junction. A future migration can drop the column.
 *
 * Idempotent — re-runnable.
 *
 * Run via web: /migrate_extra_parent_choices.php   (super-admin login)
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

function table_exists_q(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $st->execute([$table]);
    return $st->fetchColumn() !== false;
}

function index_exists_q(PDO $pdo, string $table, string $index): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
    );
    $st->execute([$table, $index]);
    return $st->fetchColumn() !== false;
}

$ops = [];

// 1. Junction table.
if (!table_exists_q($pdo, 'product_extra_parent_choices')) {
    $pdo->exec("
        CREATE TABLE product_extra_parent_choices (
            id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_extra_id  INT UNSIGNED NOT NULL,
            product_extra_choice_id INT UNSIGNED NOT NULL,
            created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_pepc_pair (product_extra_id, product_extra_choice_id),
            KEY idx_pepc_extra  (product_extra_id),
            KEY idx_pepc_choice (product_extra_choice_id),
            CONSTRAINT fk_pepc_extra
                FOREIGN KEY (product_extra_id)        REFERENCES product_extras(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_pepc_choice
                FOREIGN KEY (product_extra_choice_id) REFERENCES product_extra_choices(id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $ops[] = 'Created table: product_extra_parent_choices';
} else {
    $ops[] = 'Skipped product_extra_parent_choices (already present)';
}

// 2. Backfill from the existing parent_choice_id column.
$copied = $pdo->exec(
    'INSERT IGNORE INTO product_extra_parent_choices
       (product_extra_id, product_extra_choice_id)
     SELECT id, parent_choice_id
       FROM product_extras
      WHERE parent_choice_id IS NOT NULL'
);
$ops[] = "Backfilled $copied row(s) from product_extras.parent_choice_id.";

// 3. Drop the uniqueness constraint that blocks same-named options.
if (index_exists_q($pdo, 'product_extras', 'uniq_extra_per_product')) {
    $pdo->exec('ALTER TABLE product_extras DROP INDEX uniq_extra_per_product');
    $ops[] = 'Dropped index: uniq_extra_per_product (allows same-named options).';
} else {
    $ops[] = 'Skipped uniq_extra_per_product (already dropped).';
}

echo "Migration complete:\n";
foreach ($ops as $op) {
    echo '  - ' . $op . "\n";
}
echo "\nAfter this + the code update, the Add/Edit Option page lets you\n";
echo "tick MULTIPLE parent choices. One option, gated to whatever set of\n";
echo "parents you pick. Existing single-parent options keep working —\n";
echo "the migration backfilled their parent into the new junction.\n";
echo "\nWhen you're done, you can delete this file from the server.\n";
