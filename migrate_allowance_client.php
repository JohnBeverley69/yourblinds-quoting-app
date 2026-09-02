<?php
declare(strict_types=1);

/**
 * Migration: client-scope allowance_rows (white-label build engine).
 *
 *   allowance_rows.client_id  INT NOT NULL DEFAULT <canonical factory>
 *
 * allowance_rows (the named LOOKUP/BESTFIT tables the build rules reference) was
 * a GLOBAL table — every factory saw and could edit every other factory's build
 * allowances. This adds a per-factory owner so each factory keeps its own.
 *
 * Existing rows backfill to the canonical Beverley factory (they're all Beverley's),
 * and the column DEFAULT keeps the Beverley seed scripts working unchanged. The
 * unique key moves to (client_id, table_name, key_norm) so two factories can hold
 * a same-named table without colliding.
 *
 * Because every existing row becomes client_id = <canonical>, and Beverley's
 * products resolve to that same factory, the build engine loads exactly the same
 * allowances as before — no change to Beverley's cut calculations.
 *
 * Additive + idempotent. Run via web: /migrate_allowance_client.php (super-admin).
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
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

$canonical = function_exists('factory_client_id') ? (int) factory_client_id() : 3;

$tableExists = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'allowance_rows'"
)->fetchColumn();

echo "Migrating: client-scope allowance_rows…\n\n";

if (!$tableExists) {
    echo "allowance_rows does not exist yet — nothing to scope. Run\n";
    echo "/migrate_allowance_rows.php first if you need the table.\n";
    exit;
}

$colExists = static function (string $col) use ($pdo): bool {
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'allowance_rows' AND COLUMN_NAME = ?"
    );
    $st->execute([$col]);
    return (bool) $st->fetchColumn();
};
$idxExists = static function (string $idx) use ($pdo): bool {
    return (bool) $pdo->query(
        "SELECT 1 FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'allowance_rows'
            AND INDEX_NAME = " . $pdo->quote($idx) . " LIMIT 1"
    )->fetchColumn();
};

if (!$colExists('client_id')) {
    $pdo->exec("ALTER TABLE allowance_rows ADD COLUMN client_id INT NOT NULL DEFAULT {$canonical} AFTER id");
    $ops[] = "Added allowance_rows.client_id (DEFAULT {$canonical}); existing rows backfilled to factory #{$canonical}.";
} else {
    $ops[] = 'allowance_rows.client_id already exists — skipped.';
}

// Move the unique key from (table_name, key_norm) to (client_id, table_name, key_norm).
try {
    if ($idxExists('uq_allowance') && !$idxExists('uq_allowance_client')) {
        $pdo->exec("ALTER TABLE allowance_rows DROP INDEX uq_allowance");
        $ops[] = 'Dropped old unique key uq_allowance.';
    }
    if (!$idxExists('uq_allowance_client')) {
        $pdo->exec("ALTER TABLE allowance_rows ADD UNIQUE KEY uq_allowance_client (client_id, table_name, key_norm)");
        $ops[] = 'Added unique key uq_allowance_client (client_id, table_name, key_norm).';
    } else {
        $ops[] = 'Unique key uq_allowance_client already present — skipped.';
    }
} catch (Throwable $e) {
    $ops[] = 'Unique-key step skipped: ' . $e->getMessage();
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nAllowances are now per-factory. Beverley's build engine is unchanged\n";
echo "(all existing rows belong to factory #{$canonical}).\n";
