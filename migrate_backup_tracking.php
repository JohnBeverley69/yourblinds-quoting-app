<?php
declare(strict_types=1);

/**
 * Migration: data-backup tracking.
 *
 * Adds clients.last_backup_at DATETIME NULL — the timestamp of the tenant's
 * last COMPLETE backup (a full, unfiltered Excel download from
 * Settings > Back up data). It drives the "Since last backup" quick option,
 * which exports only quotes/orders created or edited since that moment.
 *
 * A partial/date-filtered export does NOT move the marker — only a full
 * backup does — so "since last backup" can never silently skip data.
 *
 * Idempotent. Run via /migrate_backup_tracking.php (super-admin), then delete.
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

if (!$columnExists($pdo, 'clients', 'last_backup_at')) {
    $pdo->exec('ALTER TABLE clients ADD COLUMN last_backup_at DATETIME NULL');
    echo "clients.last_backup_at: added\n";
} else {
    echo "clients.last_backup_at: already present (skipped)\n";
}

echo "\nDone. Delete this file from the server once you're happy.\n";
