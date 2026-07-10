<?php
declare(strict_types=1);

/**
 * Migration: catalogue-source marker on products (manufacturing routing).
 *
 *   products.source_client_id   INT NULL  -- master client this copy came from
 *   products.source_product_id  INT NULL  -- master product this copy came from
 *
 * YourBlinds is multi-tenant and tenants copy the master "Beverley Blinds Trade"
 * catalogue into their own account (the catalogue push). The copies keep no link
 * back to the master, so an order line can't be told apart from a tenant's own
 * product. These two columns stamp each copy's origin, so the manufacturing
 * hand-off can route ONLY the Beverley-catalogue lines to the factory —
 *
 *     a line is Beverley's  iff  products.source_client_id = <Beverley master id>
 *
 * and never a tenant's own product. The link also lets future master-catalogue
 * changes be pushed down to the copies.
 *
 * Additive + idempotent. Setting the values for NEW copies (at provisioning
 * time) and BACKFILLING existing copies are separate follow-on steps — this
 * migration only adds the columns.
 *
 * Run via web: /migrate_catalogue_source.php (super-admin).
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

$colExists = static function (string $table, string $col) use ($pdo): bool {
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $st->execute([$table, $col]);
    return (bool) $st->fetchColumn();
};

echo "Migrating: catalogue-source marker on products…\n\n";

if (!$colExists('products', 'source_client_id')) {
    $pdo->exec("ALTER TABLE products ADD COLUMN source_client_id INT NULL");
    $ops[] = 'Added products.source_client_id.';
} else {
    $ops[] = 'products.source_client_id already exists — skipped.';
}

if (!$colExists('products', 'source_product_id')) {
    $pdo->exec("ALTER TABLE products ADD COLUMN source_product_id INT NULL");
    $ops[] = 'Added products.source_product_id.';
} else {
    $ops[] = 'products.source_product_id already exists — skipped.';
}

// Helpful index for the routing query (WHERE source_client_id = ?). Ignore if
// it already exists — MySQL has no "ADD INDEX IF NOT EXISTS" before 8.0.
try {
    $hasIdx = (bool) $pdo->query(
        "SELECT 1 FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
            AND INDEX_NAME = 'idx_products_source_client'"
    )->fetchColumn();
    if (!$hasIdx) {
        $pdo->exec("ALTER TABLE products ADD INDEX idx_products_source_client (source_client_id)");
        $ops[] = 'Added index idx_products_source_client.';
    } else {
        $ops[] = 'Index idx_products_source_client already exists — skipped.';
    }
} catch (Throwable $e) {
    $ops[] = 'Index step skipped: ' . $e->getMessage();
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nNext (separate steps): stamp these on NEW catalogue copies at provisioning,\n";
echo "and backfill EXISTING copies, so manufacturing routing can rely on them.\n";
