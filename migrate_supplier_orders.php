<?php
declare(strict_types=1);

/**
 * Migration: supplier_orders — a log of orders emailed to suppliers.
 *
 * One row per (quote, supplier) each time an order is sent, so the
 * "Send to suppliers" screen can show what's already gone out and warn
 * before a re-send. Purely a record; sending isn't blocked.
 *
 * Idempotent. Run via /migrate_supplier_orders.php (super-admin), then delete.
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
    "CREATE TABLE IF NOT EXISTS supplier_orders (
        id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
        client_id     INT NOT NULL,
        quote_id      INT NOT NULL,
        supplier_name VARCHAR(150) NOT NULL,
        email         VARCHAR(190) NULL,
        item_count    INT NOT NULL DEFAULT 0,
        sent_by_user_id INT NULL,
        sent_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_quote (client_id, quote_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "supplier_orders table: ensured\n";

echo "\n";
echo "Done. The 'Send to suppliers' button on an accepted order now records\n";
echo "each send here and shows when an order was last sent.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
