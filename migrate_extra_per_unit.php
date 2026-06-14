<?php
declare(strict_types=1);

/**
 * Migration: per-unit (× quantity) pricing for option choices.
 *
 * Adds product_extra_choices.price_per_unit DECIMAL(10,2) NULL. When set, the
 * choice's typed number input is treated as a QUANTITY and the line adds
 * price_per_unit × quantity (e.g. face-fix brackets at £2.50 each × however
 * many). This is the 5th extra pricing mode, alongside flat / percent /
 * per-metre / width-table. The pricing engine + admin degrade gracefully if
 * this column is absent, so running the migration is safe at any time.
 *
 * Idempotent. Run via /migrate_extra_per_unit.php (super-admin), then delete.
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

if (!$columnExists($pdo, 'product_extra_choices', 'price_per_unit')) {
    $pdo->exec('ALTER TABLE product_extra_choices ADD COLUMN price_per_unit DECIMAL(10,2) NULL');
    echo "product_extra_choices.price_per_unit: added\n";
} else {
    echo "product_extra_choices.price_per_unit: already present (skipped)\n";
}

echo "\nDone. Delete this file from the server once you're happy.\n";
