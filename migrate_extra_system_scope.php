<?php
declare(strict_types=1);

/**
 * Migration: extra ↔ system many-to-many junction table.
 *
 * Options (product_extras) currently apply to every system on a product.
 * This migration introduces product_extra_systems so an Option can be
 * tied to a subset of systems — symmetric with product_extra_choice_systems
 * which already does the same job at the choice level.
 *
 *   no junction rows for an extra  = available on every system (default)
 *   one or more rows               = available on exactly those systems
 *
 * Existing Options have no junction rows after this migration runs, so
 * the default behaviour is unchanged. Trade users opt in by ticking
 * specific systems on the Add/Edit Option pages once the code update
 * lands.
 *
 * Idempotent — re-runnable.
 *
 * Run via CLI:   php migrate_extra_system_scope.php
 * Run via web:   /migrate_extra_system_scope.php   (super-admin login required)
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

function table_exists_q(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $st->execute([$table]);
    return $st->fetchColumn() !== false;
}

$ops = [];

if (!table_exists_q($pdo, 'product_extra_systems')) {
    $pdo->exec("
        CREATE TABLE product_extra_systems (
            id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_extra_id   INT UNSIGNED NOT NULL,
            product_system_id  INT UNSIGNED NOT NULL,
            created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_pes_extra_system (product_extra_id, product_system_id),
            KEY idx_pes_extra  (product_extra_id),
            KEY idx_pes_system (product_system_id),
            CONSTRAINT fk_pes_extra
                FOREIGN KEY (product_extra_id)  REFERENCES product_extras(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_pes_system
                FOREIGN KEY (product_system_id) REFERENCES product_systems(id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $ops[] = 'Created table: product_extra_systems';
} else {
    $ops[] = 'Skipped product_extra_systems (already present)';
}

echo "Migration complete:\n";
foreach ($ops as $op) {
    echo '  - ' . $op . "\n";
}
echo "\nNo data to copy — Options currently apply to every system, which\n";
echo "is exactly what 'no junction rows' means in the new model. Existing\n";
echo "behaviour is preserved until you tick specific systems on the\n";
echo "Add/Edit Option pages (after the next code commit).\n";
echo "\nWhen you're done, you can delete this file from the server.\n";
