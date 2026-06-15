<?php
declare(strict_types=1);

/**
 * Migration: per-tenant choice of navigation app (Google Maps or Waze).
 *
 * Adds client_settings.map_provider (VARCHAR, default 'google'). Controls
 * which app the "open in maps" address links on My Schedule and the day
 * calendar deep-link into — 'google' opens Google Maps, 'waze' opens Waze.
 *
 * Google by default so behaviour is unchanged until a tenant picks Waze on
 * /admin/settings.php (Company tab, Calendar section).
 *
 * Idempotent. Run via /migrate_map_provider.php (super-admin) then delete.
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
$st->execute(['client_settings', 'map_provider']);

if ($st->fetchColumn() === false) {
    $pdo->exec(
        "ALTER TABLE client_settings
            ADD COLUMN map_provider VARCHAR(10) NOT NULL DEFAULT 'google'"
    );
    echo "client_settings.map_provider: added (default 'google')\n";
} else {
    echo "client_settings.map_provider: already present (skipped)\n";
}

echo "\n";
echo "Done. Choose Google Maps or Waze under Settings -> Company -> Calendar\n";
echo "(Navigation app). Address links on My Schedule and the day calendar\n";
echo "will open in the chosen app.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
