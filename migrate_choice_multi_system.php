<?php
declare(strict_types=1);

/**
 * Migration: choice ↔ system many-to-many.
 *
 * Today, product_extra_choices.system_id is a single nullable FK:
 *   NULL   = available on every system
 *   N      = available only on system N
 *
 * That doesn't model "available on Vogue AND Slim Line, but NOT No Frills"
 * — the most common real-world need. This migration introduces a junction
 * table so a choice can be tied to any subset of systems:
 *
 *   no junction rows for a choice  = available on every system
 *   one or more rows               = available on exactly those systems
 *
 * Existing single-system choices are migrated by inserting one junction
 * row each. The old system_id column stays in place for now so any code
 * that still reads it doesn't crash — a follow-up commit updates every
 * caller, after which the column can be dropped if desired.
 *
 * Idempotent — re-runnable.
 *
 * Run via CLI:   php migrate_choice_multi_system.php
 * Run via web:   /migrate_choice_multi_system.php   (super-admin login required)
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

// 1. Create the junction table.
if (!table_exists_q($pdo, 'product_extra_choice_systems')) {
    $pdo->exec("
        CREATE TABLE product_extra_choice_systems (
            id                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_extra_choice_id  INT UNSIGNED NOT NULL,
            product_system_id        INT UNSIGNED NOT NULL,
            created_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_pecs_choice_system (product_extra_choice_id, product_system_id),
            KEY idx_pecs_choice (product_extra_choice_id),
            KEY idx_pecs_system (product_system_id),
            CONSTRAINT fk_pecs_choice
                FOREIGN KEY (product_extra_choice_id) REFERENCES product_extra_choices(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_pecs_system
                FOREIGN KEY (product_system_id) REFERENCES product_systems(id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $ops[] = 'Created table: product_extra_choice_systems';
} else {
    $ops[] = 'Skipped product_extra_choice_systems (already present)';
}

// 2. Copy any existing single-system bindings into the junction. INSERT IGNORE
//    so re-runs are no-ops.
$copied = $pdo->exec(
    'INSERT IGNORE INTO product_extra_choice_systems
       (product_extra_choice_id, product_system_id)
     SELECT id, system_id
       FROM product_extra_choices
      WHERE system_id IS NOT NULL'
);
$ops[] = "Copied $copied row(s) from product_extra_choices.system_id into the junction";

echo "Migration complete:\n";
foreach ($ops as $op) {
    echo '  - ' . $op . "\n";
}
echo "\nThe old product_extra_choices.system_id column is intentionally left in\n";
echo "place. It's now redundant — the junction table is the source of truth — but\n";
echo "every reader still works until the next code commit lands. Once you've\n";
echo "uploaded the updated PHP files (extra.php, extra-choice-edit.php, the\n";
echo "pricing engine, etc.), the column is unused and can be dropped if desired.\n";
echo "\nWhen you're done, you can delete this file from the server.\n";
