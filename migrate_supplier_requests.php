<?php
declare(strict_types=1);

/**
 * Migration: supplier_requests — clients ask for a supplier to be added to the
 * Price-List Library, optionally attaching the supplier's price list. Requests
 * tally up in Master Admin so suppliers get added in demand order.
 *
 * Idempotent. Run via /migrate_supplier_requests.php (super-admin) then delete.
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
    'CREATE TABLE IF NOT EXISTS supplier_requests (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        client_id     INT NOT NULL,
        supplier_name VARCHAR(160) NOT NULL,
        website       VARCHAR(255) NULL,
        notes         TEXT NULL,
        file_name     VARCHAR(255) NULL,
        file_path     VARCHAR(255) NULL,
        status        VARCHAR(20) NOT NULL DEFAULT "open",
        created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_status (status)
    )'
);
echo "supplier_requests table: ensured\n";

echo "\n";
echo "Done. Clients can request a supplier (and attach a price list) on their\n";
echo "Supplier catalogues page; requests appear under Master admin -> Supplier requests.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
