<?php
declare(strict_types=1);

/**
 * Migration: allow an Option to accept multiple choices on a quote
 * line.
 *
 * Adds product_extras.allow_multi (TINYINT(1) DEFAULT 0).
 *
 * When 0 (default), the quote builder renders the option as a
 * single-pick dropdown — the historical behaviour. When 1, the
 * option renders as a list of checkboxes and the customer can tick
 * any number of choices. Each ticked choice produces its own
 * quote_item_extras row at save time, so pricing already works
 * (the engine iterates the extras[] array and adds them up).
 *
 * quote_item_extras has no UNIQUE on (quote_item_id, product_extra_id)
 * so multiple rows per (line, option) are already legal data-wise —
 * this migration just adds the UI-level toggle.
 *
 * Idempotent. Run via /migrate_extra_allow_multi.php (super-admin).
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

$st = $pdo->prepare(
    'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = "product_extras"
        AND COLUMN_NAME  = "allow_multi" LIMIT 1'
);
$st->execute();

if ($st->fetchColumn() === false) {
    $pdo->exec(
        'ALTER TABLE product_extras
            ADD COLUMN allow_multi TINYINT(1) NOT NULL DEFAULT 0
              AFTER is_required'
    );
    echo "Added product_extras.allow_multi\n";
} else {
    echo "product_extras.allow_multi already present\n";
}

echo "\nTick \"Allow multiple choices\" on any Option in /admin/products/\n";
echo "to convert its quote-builder picker from a single dropdown into\n";
echo "a checkbox list. Each ticked choice gets recorded on the quote\n";
echo "line and contributes to the price.\n";
echo "\nDelete this file from the server once you're happy.\n";
