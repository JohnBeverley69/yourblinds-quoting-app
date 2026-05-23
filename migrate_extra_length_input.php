<?php
declare(strict_types=1);

/**
 * Migration: free-form length input on extras.
 *
 * Lets admins flag an Extra (e.g. "Wand Option" on Vertical Blinds) as
 * requiring a user-entered numeric value — typically a length in mm.
 * The quote builder renders a number input next to the choice picker;
 * the typed value is snapshotted onto the quote line for the supplier.
 *
 * Adds:
 *   product_extras.length_input_label  — VARCHAR(60) NULL.
 *                                        NULL/empty = no input (standard
 *                                        choice-only extra). Non-empty
 *                                        = the label rendered next to
 *                                        the number input (e.g. "Wand
 *                                        length (mm)" or "Cable length
 *                                        (mm)" — admin's choice).
 *
 *   quote_item_extras.user_value      — DECIMAL(10,2) NULL.
 *                                        The number the salesperson
 *                                        typed for this extra on this
 *                                        quote line. Frozen at save
 *                                        time; editing the line re-
 *                                        captures it (same pattern as
 *                                        the other snapshot fields).
 *
 * No price impact for now — the length is a spec field that shows on
 * quote outputs but doesn't change £. If pricing-by-length is wanted
 * later, the pricing engine can multiply choice.price_per_metre by
 * (user_value / 1000) when length_input_label is set on the extra.
 *
 * Idempotent. Run via /migrate_extra_length_input.php (super-admin).
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

function eli_column_exists(PDO $pdo, string $table, string $col): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = ?
            AND COLUMN_NAME  = ? LIMIT 1'
    );
    $st->execute([$table, $col]);
    return $st->fetchColumn() !== false;
}

$ops = [];

if (!eli_column_exists($pdo, 'product_extras', 'length_input_label')) {
    $pdo->exec(
        "ALTER TABLE product_extras
            ADD COLUMN length_input_label VARCHAR(60) NULL
                AFTER is_required"
    );
    $ops[] = 'Added product_extras.length_input_label';
} else {
    $ops[] = 'product_extras.length_input_label already present';
}

if (!eli_column_exists($pdo, 'quote_item_extras', 'user_value')) {
    $pdo->exec(
        "ALTER TABLE quote_item_extras
            ADD COLUMN user_value DECIMAL(10,2) NULL
                AFTER amount_applied"
    );
    $ops[] = 'Added quote_item_extras.user_value';
} else {
    $ops[] = 'quote_item_extras.user_value already present';
}

echo "Migration complete:\n";
foreach ($ops as $op) echo '  - ' . $op . "\n";
echo "\n";
echo "Set the 'Length input label' on any extra in /admin/products/\n";
echo "(e.g. 'Wand length (mm)' on Vertical Blinds → Wand Option).\n";
echo "Salespeople will see a number input next to that extra's choice\n";
echo "picker in the quote builder. The typed value is stored against\n";
echo "the quote line for supplier docs.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
