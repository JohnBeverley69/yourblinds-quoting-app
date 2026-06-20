<?php
declare(strict_types=1);

/**
 * Migration: grouping for the Fabric Library.
 *
 * Mirrors product categories, but scoped to a MANUFACTURER (fabric supplier)
 * rather than a tenant — each manufacturer gets its own set of groups, so a
 * long fabric list can be filed under headings (e.g. Blackout / Dimout /
 * Patterned) inside that manufacturer.
 *
 *   library_fabric_categories (id, fabric_supplier_id, name, sort_order)
 *   library_fabrics.category_id  — nullable INT; NULL = ungrouped.
 *
 * The link is by INT id (category_id), so there's no text-collation join to
 * trip over. The new table is created with library_fabrics' own collation
 * anyway, to keep the whole library consistent.
 *
 * Non-destructive + idempotent. Run via web: /migrate_fabric_library_categories.php
 * (super-admin).
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

echo "Migrating: fabric library grouping…\n\n";

// The library_fabrics table must exist first (migrate_fabric_library.php).
if (!$tableExists('library_fabrics')) {
    throw new RuntimeException('library_fabrics is missing — run migrate_fabric_library.php first.');
}

// Match library_fabrics' collation so the new table is consistent with the
// rest of the library (avoids any future "illegal mix of collations").
$collStmt = $pdo->prepare(
    "SELECT TABLE_COLLATION FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_fabrics' LIMIT 1"
);
$collStmt->execute();
$collation = (string) ($collStmt->fetchColumn() ?: '');
$charset   = $collation !== '' ? (string) strtok($collation, '_') : '';
$tableOpts = ($charset !== '' && $collation !== '')
    ? " DEFAULT CHARSET=$charset COLLATE=$collation"
    : '';

if (!$tableExists('library_fabric_categories')) {
    $pdo->exec(
        "CREATE TABLE library_fabric_categories (
            id                 INT AUTO_INCREMENT PRIMARY KEY,
            fabric_supplier_id INT NOT NULL,
            name               VARCHAR(120) NOT NULL,
            sort_order         INT NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_sup_name (fabric_supplier_id, name),
            KEY idx_supplier (fabric_supplier_id)
        ) ENGINE=InnoDB$tableOpts"
    );
    $ops[] = 'Created library_fabric_categories' . ($collation !== '' ? " (collation $collation)." : '.');
} else {
    $ops[] = 'library_fabric_categories already exists — skipped.';
}

if (!$colExists('library_fabrics', 'category_id')) {
    $pdo->exec('ALTER TABLE library_fabrics ADD COLUMN category_id INT NULL');
    $ops[] = 'Added library_fabrics.category_id (INT NULL — NULL = ungrouped).';
} else {
    $ops[] = 'library_fabrics.category_id already exists — skipped.';
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nOpen the Fabric Library → expand a manufacturer → add groups and drag\n";
echo "fabrics into them. Groups are per-manufacturer; ungrouped fabrics still\n";
echo "show under an \"Ungrouped\" heading.\n";
