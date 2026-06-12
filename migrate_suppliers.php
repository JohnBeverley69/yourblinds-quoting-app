<?php
declare(strict_types=1);

/**
 * Migration: supplier ordering — foundation.
 *
 * Adds the pieces needed to order materials from each product's supplier:
 *
 *   1. suppliers table           — per-tenant list of suppliers (name + the
 *                                  email orders are sent to). UNIQUE(client_id,
 *                                  name) so a name maps to one email per tenant.
 *   2. products.supplier_name    — which supplier supplies this product
 *                                  (product-level; "In House" for own manufacture).
 *   3. client_settings
 *        .supplier_delivery_address — where suppliers ship goods (the client's
 *                                  own address, entered once in Settings).
 *
 * Backfill (idempotent):
 *   - seeds suppliers from the distinct supplier names already entered on
 *     fabrics (product_options.supplier_name), so the Settings list isn't empty;
 *   - seeds an "In House" supplier for every client.
 *
 * Idempotent. Run via /migrate_suppliers.php (super-admin), then delete.
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

$columnExists = static function (PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $st->execute([$table, $col]);
    return $st->fetchColumn() !== false;
};

// 1) suppliers table -------------------------------------------------------
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS suppliers (
        id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        client_id  INT NOT NULL,
        name       VARCHAR(150) NOT NULL,
        email      VARCHAR(190) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_supplier_per_client (client_id, name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "suppliers table: ensured\n";

// Align the suppliers table's collation with product_options. A freshly-created
// table takes the server's DEFAULT collation, which often differs from the
// older tables — and comparing the two in SQL (UNION/JOIN, e.g. product ->
// supplier) throws "Illegal mix of collations". Convert to match so every
// supplier join is safe. Best-effort + idempotent.
try {
    $cSt = $pdo->query(
        "SELECT t.TABLE_COLLATION, c.CHARACTER_SET_NAME
           FROM INFORMATION_SCHEMA.TABLES t
           JOIN INFORMATION_SCHEMA.COLLATION_CHARACTER_SET_APPLICABILITY c
             ON c.COLLATION_NAME = t.TABLE_COLLATION
          WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_NAME = 'product_options' LIMIT 1"
    );
    $cc = $cSt ? $cSt->fetch(PDO::FETCH_ASSOC) : null;
    if ($cc && !empty($cc['TABLE_COLLATION']) && !empty($cc['CHARACTER_SET_NAME'])
        && preg_match('/^[A-Za-z0-9_]+$/', (string) $cc['TABLE_COLLATION'])
        && preg_match('/^[A-Za-z0-9_]+$/', (string) $cc['CHARACTER_SET_NAME'])) {
        $charset   = (string) $cc['CHARACTER_SET_NAME'];
        $collation = (string) $cc['TABLE_COLLATION'];
        $pdo->exec("ALTER TABLE suppliers CONVERT TO CHARACTER SET $charset COLLATE $collation");
        echo "suppliers collation: aligned to product_options ($collation)\n";
    } else {
        echo "suppliers collation: product_options collation not found (skipped)\n";
    }
} catch (Throwable $e) {
    echo 'suppliers collation: align skipped (' . $e->getMessage() . ")\n";
}

// 2) products.supplier_name ------------------------------------------------
if (!$columnExists($pdo, 'products', 'supplier_name')) {
    $pdo->exec('ALTER TABLE products ADD COLUMN supplier_name VARCHAR(150) NULL');
    echo "products.supplier_name: added\n";
} else {
    echo "products.supplier_name: already present (skipped)\n";
}

// 3) client_settings.supplier_delivery_address -----------------------------
if (!$columnExists($pdo, 'client_settings', 'supplier_delivery_address')) {
    $pdo->exec('ALTER TABLE client_settings ADD COLUMN supplier_delivery_address TEXT NULL');
    echo "client_settings.supplier_delivery_address: added\n";
} else {
    echo "client_settings.supplier_delivery_address: already present (skipped)\n";
}

// 4) Backfill suppliers from existing fabric supplier names ----------------
$n1 = $pdo->exec(
    "INSERT IGNORE INTO suppliers (client_id, name)
     SELECT DISTINCT client_id, TRIM(supplier_name)
       FROM product_options
      WHERE supplier_name IS NOT NULL AND TRIM(supplier_name) <> ''"
);
echo "suppliers backfilled from fabrics: $n1 new\n";

// 5) Seed "In House" for every client --------------------------------------
$n2 = $pdo->exec(
    "INSERT IGNORE INTO suppliers (client_id, name)
     SELECT id, 'In House' FROM clients"
);
echo "In House seeded: $n2 new\n";

echo "\n";
echo "Done. Set each supplier's email under Settings > Suppliers, fill in the\n";
echo "delivery address, then assign a supplier to each product (next stage).\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
