<?php
declare(strict_types=1);

/**
 * Migration: give every pushed row a stable identity, so a tenant can be a
 * true MIRROR of the master catalogue.
 *
 * The push matched a tenant's copy by NAME — system name, fabric name, option
 * name, band code. A name is not an identity, and the consequences show up the
 * moment the master catalogue is tidied:
 *
 *   rename  -> the old name is not found, so a SECOND row is added beside it
 *   delete  -> nothing matches, and the push never removes, so it lives forever
 *
 * Restructuring Bev Infusions made that concrete: 36 fabrics and 9 price tables
 * were deleted on the master, and no push could ever clear them from a tenant.
 * The tenant keeps drifting until someone remembers to wipe and start again.
 *
 * source_choice_id already did this for choices (migrate_choice_source_id.php).
 * This extends the same idea to the rest of the catalogue:
 *
 *   product_systems.source_system_id
 *   product_options.source_option_id
 *   product_extras.source_extra_id
 *   price_tables.source_table_id
 *
 * The rule is the same everywhere: NULL means "the tenant made this", and the
 * push never touches it. Set means "this is our copy of master row N", so the
 * push can rename it in place and remove it when the master original is gone.
 *
 * No backfill. Existing rows stay NULL and keep matching by name, which is
 * exactly current behaviour; each push stamps the rows it matches, so
 * identities fill in on their own. Nothing is ever pruned until it has been
 * stamped, which means the first push after this migration removes nothing.
 *
 * Run via web: /migrate_push_source_ids.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colExists = static function (string $t, string $c) use ($pdo): bool {
    try { $pdo->query("SELECT `$c` FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

$cols = [
    ['product_systems', 'source_system_id', 'idx_source_system'],
    ['product_options', 'source_option_id', 'idx_source_option'],
    ['product_extras',  'source_extra_id',  'idx_source_extra'],
    ['price_tables',    'source_table_id',  'idx_source_table'],
];

foreach ($cols as [$table, $col, $idx]) {
    if ($colExists($table, $col)) {
        echo "  $table.$col already exists — skipped.\n";
        continue;
    }
    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` INT UNSIGNED NULL, ADD KEY `$idx` (`$col`)");
    echo "  Added $table.$col.\n";
}

echo "\nDone. NULL = the tenant's own row, never touched by a push.\n";
echo "Each push stamps what it matches; nothing is pruned until it is stamped,\n";
echo "so the first push after this removes nothing.\n";
