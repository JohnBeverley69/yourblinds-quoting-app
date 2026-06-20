<?php
declare(strict_types=1);

/**
 * Migration: a fabric's library GROUP, carried onto the product.
 *
 *   product_options.fabric_group  — VARCHAR(120) NULL.
 *       The name of the Fabric Library group the fabric came from (set when
 *       fabrics are pulled in via options-from-library.php). NULL = ungrouped.
 *
 * We store the group NAME (not the library category id) because the library
 * lives on the master tenant while products are per-client — an id wouldn't
 * translate, a name does. Purely organisational on the product's Fabrics page.
 *
 * Idempotent. Run via web: /migrate_option_fabric_group.php (super-admin).
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

$ops = [];
set_exception_handler(function (Throwable $e) use (&$ops) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Migration FAILED: " . $e->getMessage() . "\n\n";
    echo "Steps completed before failure:\n";
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

$colExists = static function (string $table, string $col) use ($pdo): bool {
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $st->execute([$table, $col]);
    return (bool) $st->fetchColumn();
};

echo "Migrating: product_options.fabric_group…\n\n";

if (!$colExists('product_options', 'fabric_group')) {
    $pdo->exec('ALTER TABLE product_options ADD COLUMN fabric_group VARCHAR(120) NULL');
    $ops[] = 'Added product_options.fabric_group (VARCHAR(120) NULL — NULL = ungrouped).';
} else {
    $ops[] = 'product_options.fabric_group already exists — skipped.';
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nPull fabrics from the library (Products → a product → Fabrics → Add from\n";
echo "library) and their library group rides along, shown as a Group column.\n";
echo "Set or change it per fabric on its Edit page.\n";
