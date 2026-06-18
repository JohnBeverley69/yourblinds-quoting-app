<?php
declare(strict_types=1);

/**
 * Migration: price_change_log — a history of the % price changes applied on the
 * Master Catalogue (per supplier or per product), so there's a record of who
 * raised what, when, and by how much.
 *
 *   scope='supplier'|'product', target=name, pct, cells_changed, changed_by, created_at
 *
 * Idempotent. Run via /migrate_price_change_log.php (super-admin) then delete.
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
    'CREATE TABLE IF NOT EXISTS price_change_log (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        client_id     INT NOT NULL,
        scope         VARCHAR(20)  NOT NULL,
        target        VARCHAR(160) NOT NULL,
        pct           DECIMAL(7,2) NOT NULL,
        cells_changed INT NOT NULL DEFAULT 0,
        changed_by    VARCHAR(120) NULL,
        created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_client (client_id)
    )'
);
echo "price_change_log table: ensured\n";

echo "\n";
echo "Done. % price changes on the Master Catalogue now record a history entry,\n";
echo "shown under 'Price change history' on that page.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
