<?php
declare(strict_types=1);

/**
 * Migration: per-CHOICE number input.
 *
 * Extends the existing length-input feature (which lived on the whole
 * option group via product_extras.length_input_label) down to the
 * individual choice. When a choice carries a label, the quote builder
 * renders a number box next to THAT choice — so e.g. an "Offset" option
 * can have Top / Bottom / Left / Right choices, each with its own mm
 * value, or a "Mid rail" choice can capture its height.
 *
 * Adds:
 *   product_extra_choices.length_input_label  — VARCHAR(60) NULL.
 *       NULL/empty = no input for this choice (the default). Non-empty
 *       = the label shown above the number box (e.g. "Top offset (mm)").
 *
 * The typed value is stored on the same quote_item_extras.user_value
 * column the group-level input already uses — each selected choice is
 * its own quote_item_extras row, so each carries its own value with no
 * further schema change.
 *
 * No price impact (same as the group-level input — spec only).
 *
 * Idempotent. Run via /migrate_choice_length_input.php (super-admin).
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

function cli_column_exists(PDO $pdo, string $table, string $col): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $st->execute([$table, $col]);
    return $st->fetchColumn() !== false;
}

$ops = [];

if (!cli_column_exists($pdo, 'product_extra_choices', 'length_input_label')) {
    $pdo->exec(
        "ALTER TABLE product_extra_choices
            ADD COLUMN length_input_label VARCHAR(60) NULL AFTER label"
    );
    $ops[] = 'Added product_extra_choices.length_input_label';
} else {
    $ops[] = 'product_extra_choices.length_input_label already present';
}

echo "Migration complete:\n";
foreach ($ops as $op) echo '  - ' . $op . "\n";
echo "\n";
echo "On any choice (Products -> option -> Edit choice) tick 'Also ask for\n";
echo "a number on this choice' and label it (e.g. 'Top offset (mm)'). The\n";
echo "salesperson gets a number box next to that choice in the quote builder.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
