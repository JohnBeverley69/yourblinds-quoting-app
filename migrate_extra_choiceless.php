<?php
declare(strict_types=1);

/**
 * Migration: allow "number-only" options on a quote line.
 *
 * Some options have no choices to pick — they're just a measurement the
 * salesperson types (e.g. "Distance From Bottom to Handle Centre"). Those
 * save a quote_item_extras row with NO choice, so product_extra_choice_id
 * must be nullable.
 *
 * Reads the column's current type from information_schema and only alters it
 * when it's still NOT NULL, preserving the exact type (and its foreign key —
 * NULLs are exempt from FK checks). Idempotent.
 *
 * Run via web: /migrate_extra_choiceless.php (super-admin).
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

$ops = [];
set_exception_handler(function (Throwable $e) use (&$ops) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Migration FAILED: " . $e->getMessage() . "\n\n";
    echo "Steps completed before failure:\n";
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

echo "Migrating: allow number-only options (nullable choice on a quote line)…\n\n";

$col = $pdo->query(
    "SELECT COLUMN_TYPE, IS_NULLABLE
       FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'quote_item_extras'
        AND COLUMN_NAME = 'product_extra_choice_id'"
)->fetch();

if (!$col) {
    $ops[] = "quote_item_extras.product_extra_choice_id not found — nothing to do.";
} elseif (strtoupper((string) $col['IS_NULLABLE']) === 'YES') {
    $ops[] = "quote_item_extras.product_extra_choice_id is already nullable — skipped.";
} else {
    // Preserve the exact existing type, just drop the NOT NULL constraint.
    $type = (string) $col['COLUMN_TYPE'];
    $pdo->exec("ALTER TABLE quote_item_extras MODIFY product_extra_choice_id $type NULL");
    $ops[] = "quote_item_extras.product_extra_choice_id ($type) is now NULL-able.";
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nOptions with a number input but no choices now render as just that input\n";
echo "in the quote builder, and the typed value is saved against the line.\n";
