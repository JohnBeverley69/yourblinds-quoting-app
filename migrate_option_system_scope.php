<?php
declare(strict_types=1);

/**
 * Migration: per-system scoping for product_options (fabrics / colours).
 *
 * Before:
 *   product_options has no link to product_systems. Every fabric is
 *   implicitly available with every system in its product. Fine for
 *   "vertical blinds" where colours apply universally, but wrong for
 *   "metal venetians" where the Standard slat range and Special slat
 *   range are physically different — a Special-finish colour doesn't
 *   exist on a Standard slat.
 *
 * After:
 *   product_options gains a nullable system_id column.
 *     - NULL          → universal (existing behaviour preserved)
 *     - system_id     → fabric is only available with that specific system
 *
 * FK action ON DELETE SET NULL: deleting a system promotes its
 * fabrics back to universal rather than cascading them away. Safer
 * default — the tenant can re-scope them or delete by hand later.
 *
 * Backward compat:
 *   - Existing rows get NULL by default → universal → matches current
 *     behaviour everywhere it's read (quote builder, options.php, etc.).
 *   - Application code that doesn't yet know about system_id will
 *     keep working — it just won't honour the scoping until updated.
 *
 * Idempotent — re-runnable. Detects existing column / FK and skips.
 *
 * Run via web: /migrate_option_system_scope.php (super-admin login).
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
    foreach ($ops as $i => $op) {
        echo sprintf("  %2d. %s\n", $i + 1, $op);
    }
    exit(1);
});

echo "Migrating product_options to support per-system scoping…\n\n";

// 1. Read the type of product_systems.id so the new FK column matches
// exactly (signed/unsigned, INT vs BIGINT, etc.). Mismatched types
// cause FK creation to fail with "Cannot add foreign key constraint".
$colType = null;
$colStmt = $pdo->query(
    "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'product_systems'
        AND COLUMN_NAME  = 'id'"
);
$colType = (string) ($colStmt->fetchColumn() ?: '');
if ($colType === '') {
    throw new RuntimeException(
        'Could not read product_systems.id column type — does that table exist?'
    );
}
$ops[] = "Read product_systems.id type: $colType";

// 2. Add product_options.system_id (nullable) if not present.
$hasColStmt = $pdo->query(
    "SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'product_options'
        AND COLUMN_NAME  = 'system_id'"
);
$hasCol = (bool) $hasColStmt->fetchColumn();

if (!$hasCol) {
    // Place the new column after product_id for readability in
    // tooling that doesn't reorder columns (most clients).
    $pdo->exec("ALTER TABLE product_options
                ADD COLUMN system_id $colType NULL DEFAULT NULL
                AFTER product_id");
    $ops[] = "Added product_options.system_id ($colType, NULL).";
} else {
    $ops[] = "product_options.system_id already exists — skipped.";
}

// 3. Add an index on system_id so the typical "fabrics for system X"
// query stays fast. The FK below normally creates one automatically,
// but only if there isn't already a usable one — adding it explicitly
// makes the intent obvious.
$hasIdxStmt = $pdo->query(
    "SELECT 1 FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'product_options'
        AND INDEX_NAME   = 'idx_product_options_system_id'"
);
$hasIdx = (bool) $hasIdxStmt->fetchColumn();

if (!$hasIdx) {
    $pdo->exec("CREATE INDEX idx_product_options_system_id
                  ON product_options (system_id)");
    $ops[] = 'Created index idx_product_options_system_id.';
} else {
    $ops[] = 'Index idx_product_options_system_id already exists — skipped.';
}

// 4. Add FK product_options.system_id → product_systems.id.
// ON DELETE SET NULL: deleting a system promotes its fabrics back
// to universal rather than cascading them away. Safer default.
$hasFkStmt = $pdo->query(
    "SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE()
        AND TABLE_NAME = 'product_options'
        AND REFERENCED_TABLE_NAME = 'product_systems'
        AND CONSTRAINT_NAME = 'fk_product_options_system'"
);
$hasFk = (bool) $hasFkStmt->fetchColumn();

if (!$hasFk) {
    $pdo->exec(
        "ALTER TABLE product_options
         ADD CONSTRAINT fk_product_options_system
         FOREIGN KEY (system_id) REFERENCES product_systems(id)
         ON DELETE SET NULL"
    );
    $ops[] = 'Added FK fk_product_options_system (ON DELETE SET NULL).';
} else {
    $ops[] = 'FK fk_product_options_system already exists — skipped.';
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) {
    echo sprintf("  %2d. %s\n", $i + 1, $op);
}
echo "\nproduct_options now supports per-system scoping. New fabrics\n";
echo "default to system_id = NULL (universal) so existing app code\n";
echo "keeps working unchanged.\n";
