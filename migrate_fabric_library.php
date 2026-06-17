<?php
declare(strict_types=1);

/**
 * Migration: Fabric Library — a master-curated catalogue of fabrics grouped by
 * their MANUFACTURER (fabric supplier), separate from the blind/price-list
 * supplier catalogue. Independents fit fabrics from many manufacturers onto
 * their own blind systems, so this is a second library that feeds products.
 *
 *   fabric_suppliers  — the manufacturer registry (Louvolite, etc.)
 *   library_fabrics   — fabrics per manufacturer: name, colour, code, a
 *                       SUGGESTED band (overridable when pulled into a product),
 *                       and an optional blind-type tag.
 *
 * Master-curated (super-admin owns these; no client_id). Fabrics are PULLED
 * into a client's product later, copied into product_options (with band =
 * suggested, overridable) — that copy is done in PHP, so no cross-collation
 * join with the older client tables.
 *
 * Idempotent. Run via /migrate_fabric_library.php (super-admin) then delete.
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

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS fabric_suppliers (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(120) NOT NULL,
        active     TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )'
);
echo "fabric_suppliers table: ensured\n";

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS library_fabrics (
        id                 INT AUTO_INCREMENT PRIMARY KEY,
        fabric_supplier_id INT NOT NULL,
        name               VARCHAR(160) NOT NULL,
        colour             VARCHAR(120) NULL,
        code               VARCHAR(80)  NULL,
        suggested_band     VARCHAR(20)  NULL,
        blind_type         VARCHAR(60)  NULL,
        active             TINYINT(1) NOT NULL DEFAULT 1,
        sort_order         INT NOT NULL DEFAULT 0,
        created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_supplier (fabric_supplier_id)
    )'
);
echo "library_fabrics table: ensured\n";

echo "\n";
echo "Done. Manage it under Master admin -> Fabric Library: add a manufacturer,\n";
echo "then add (or, next step, import) its fabrics.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
