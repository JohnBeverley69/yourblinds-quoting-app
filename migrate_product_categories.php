<?php
declare(strict_types=1);

/**
 * Migration: product categories (grouping headings).
 *
 * A category is a non-destructive heading that products are filed under — e.g.
 * "Woods" containing Forest Wood, Infusions, Embassy Faux Wood. Products keep
 * all their own systems, options, fabrics and price tables; the category only
 * groups them in the products list and (next step) the quote builder.
 *
 *   product_categories  — id, client_id, name, sort_order, created_at
 *   products.category_id — nullable FK (NULL = ungrouped)
 *
 * One category per product. Joined on an INT id, so no collation concerns.
 *
 * Idempotent. Run via /migrate_product_categories.php (super-admin) then delete.
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
    'CREATE TABLE IF NOT EXISTS product_categories (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        client_id  INT NOT NULL,
        name       VARCHAR(120) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_client (client_id)
    )'
);
echo "product_categories table: ensured\n";

$col = $pdo->prepare(
    'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
);
$col->execute(['products', 'category_id']);
if ($col->fetchColumn() === false) {
    $pdo->exec('ALTER TABLE products ADD COLUMN category_id INT NULL');
    echo "products.category_id: added\n";
} else {
    echo "products.category_id: already present (skipped)\n";
}

echo "\n";
echo "Done. On Products, use the Group dropdown on each row to file it under a\n";
echo "category (or create one). Products keep everything they have.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
