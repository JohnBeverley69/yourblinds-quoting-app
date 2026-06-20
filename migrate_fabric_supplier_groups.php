<?php
declare(strict_types=1);

/**
 * Migration: supplier-level grouping for the Fabric Library.
 *
 * The Fabric Library's top-level rows ("Decora FB Roller", "Decora Contract
 * Roller"…) are really RANGES that belong to one supplier (Decora). This adds
 * a group ABOVE them so the ranges file under a supplier heading:
 *
 *   Supplier group (Decora)  ▸  range (FB Roller)  ▸  fabrics
 *
 *   fabric_supplier_groups (id, name, sort_order)
 *   fabric_suppliers.group_id  — nullable INT; NULL = ungrouped.
 *
 * Non-destructive (nothing merges — each range keeps its own fabrics) and the
 * link is by INT id, so no text-collation join. The group table is created
 * with fabric_suppliers' own collation for consistency.
 *
 * Idempotent. Run via web: /migrate_fabric_supplier_groups.php (super-admin).
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
$tableExists = static function (string $table) use ($pdo): bool {
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $st->execute([$table]);
    return (bool) $st->fetchColumn();
};

echo "Migrating: fabric supplier groups…\n\n";

if (!$tableExists('fabric_suppliers')) {
    throw new RuntimeException('fabric_suppliers is missing — set up the Fabric Library first.');
}

// Match fabric_suppliers' collation so the new table is consistent.
$collStmt = $pdo->prepare(
    "SELECT TABLE_COLLATION FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fabric_suppliers' LIMIT 1"
);
$collStmt->execute();
$collation = (string) ($collStmt->fetchColumn() ?: '');
$charset   = $collation !== '' ? (string) strtok($collation, '_') : '';
$tableOpts = ($charset !== '' && $collation !== '')
    ? " DEFAULT CHARSET=$charset COLLATE=$collation"
    : '';

if (!$tableExists('fabric_supplier_groups')) {
    $pdo->exec(
        "CREATE TABLE fabric_supplier_groups (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(120) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_name (name)
        ) ENGINE=InnoDB$tableOpts"
    );
    $ops[] = 'Created fabric_supplier_groups' . ($collation !== '' ? " (collation $collation)." : '.');
} else {
    $ops[] = 'fabric_supplier_groups already exists — skipped.';
}

if (!$colExists('fabric_suppliers', 'group_id')) {
    $pdo->exec('ALTER TABLE fabric_suppliers ADD COLUMN group_id INT NULL');
    $ops[] = 'Added fabric_suppliers.group_id (INT NULL — NULL = ungrouped).';
} else {
    $ops[] = 'fabric_suppliers.group_id already exists — skipped.';
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nOpen the Fabric Library, add a supplier group (e.g. Decora), then drag\n";
echo "each range's grip into it. Ranges keep all their fabrics.\n";
