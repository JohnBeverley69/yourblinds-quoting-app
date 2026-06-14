<?php
declare(strict_types=1);

/**
 * Migration: supplier price-list library — client subscriptions.
 *
 *   1. client_library_suppliers — which library suppliers a tenant has enabled
 *      (so the catalogue page shows state, and updates know who to notify).
 *   2. client_settings.feature_price_library — the paid add-on flag. The free
 *      "Beverley Blinds Trade" supplier is available to everyone; other
 *      suppliers require this flag.
 *
 * Idempotent. Run via /migrate_library_subscriptions.php (super-admin), delete.
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

// 1) client_library_suppliers ------------------------------------------------
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS client_library_suppliers (
        id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
        client_id        INT NOT NULL,
        supplier_key     VARCHAR(64) NOT NULL,
        enabled_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_imported_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_client_supplier (client_id, supplier_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "client_library_suppliers table: ensured\n";

// 2) client_settings.feature_price_library -----------------------------------
if (!$columnExists($pdo, 'client_settings', 'feature_price_library')) {
    $pdo->exec('ALTER TABLE client_settings ADD COLUMN feature_price_library TINYINT(1) NOT NULL DEFAULT 0');
    echo "client_settings.feature_price_library: added\n";
} else {
    echo "client_settings.feature_price_library: already present (skipped)\n";
}

echo "\nDone. Delete this file from the server once you're happy.\n";
