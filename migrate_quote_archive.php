<?php
declare(strict_types=1);

/**
 * Migration: soft-archive for quotes/orders.
 *
 * Adds quotes.archived_at (DATETIME NULL). NULL = active (the default views
 * show these); a timestamp = archived (hidden unless you switch to the Archived
 * view). Lets old/completed orders declutter the history without being deleted.
 *
 * Idempotent. Run via /migrate_quote_archive.php (super-admin) then delete.
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
        AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
);
$st->execute(['quotes', 'archived_at']);
if ($st->fetchColumn() === false) {
    $pdo->exec('ALTER TABLE quotes ADD COLUMN archived_at DATETIME NULL');
    echo "quotes.archived_at: added (NULL = active)\n";
} else {
    echo "quotes.archived_at: already present (skipped)\n";
}

echo "\n";
echo "Done. On Order/Quote history, tick rows and use 'Archive selected' to tidy\n";
echo "old jobs away; switch to the Archived view to see or restore them.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
