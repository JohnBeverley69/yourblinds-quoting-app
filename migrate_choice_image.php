<?php
declare(strict_types=1);

/**
 * Migration: optional thumbnail image per choice.
 *
 * Adds product_extra_choices.image_path VARCHAR(255) NULL — a web-relative
 * path to a small diagram (e.g. wand-control orientation) shown in the
 * customer-facing quote builder under the choice dropdown.
 *
 * Idempotent — re-runnable.
 *
 * Run via CLI:   php migrate_choice_image.php
 * Run via web:   /migrate_choice_image.php   (super-admin login required)
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

function column_exists_q(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = ?
            AND COLUMN_NAME  = ?
          LIMIT 1'
    );
    $st->execute([$table, $column]);
    return $st->fetchColumn() !== false;
}

$ops = [];

if (!column_exists_q($pdo, 'product_extra_choices', 'image_path')) {
    $pdo->exec(
        "ALTER TABLE product_extra_choices
            ADD COLUMN image_path VARCHAR(255) NULL AFTER active"
    );
    $ops[] = 'Added product_extra_choices.image_path';
} else {
    $ops[] = 'Skipped product_extra_choices.image_path (already present)';
}

echo "Migration complete:\n";
foreach ($ops as $op) {
    echo '  - ' . $op . "\n";
}
echo "\nWhen you're done, you can delete this file from the server.\n";
