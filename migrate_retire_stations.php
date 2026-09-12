<?php
declare(strict_types=1);

/**
 * Migration: retire the dead "stations" concept.
 *
 * The floor was rebuilt around STREAMS (stream + label per stage). The old
 * factory_stations table and the per-user bench pointer were superseded:
 *
 *   - factory_stations           — orphan table. Its only reader was a LEFT
 *                                  JOIN in bj_route_steps() that produced a
 *                                  station name/is_outsourced flag nothing ever
 *                                  consumed. That JOIN has been removed, so the
 *                                  table has zero consumers.
 *   - client_users.factory_station_id — the "this login IS a bench" model,
 *                                  replaced by workstation_streams (a login is
 *                                  the set of (product, stream) processes it
 *                                  covers). Zero runtime readers.
 *
 * Deliberately KEPT (inert): the always-NULL station_id columns on
 * product_route_steps, factory_blind_streams and factory_blind_jobs. They sit
 * inside the live scan-progression SQL; dropping them buys nothing functional
 * and would mean editing the scanner path, so they stay as harmless legacy
 * metadata.
 *
 * Run via web: /migrate_retire_stations.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colExists = static function (string $t, string $c) use ($pdo): bool {
    try { $pdo->query("SELECT `$c` FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};
$tableExists = static function (string $t) use ($pdo): bool {
    try { $pdo->query("SELECT 1 FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

// 1) Drop the orphan factory_stations table.
if ($tableExists('factory_stations')) {
    $pdo->exec('DROP TABLE factory_stations');
    echo "  Dropped table factory_stations.\n";
} else {
    echo "  factory_stations already gone — skipped.\n";
}

// 2) Drop the superseded per-user bench pointer.
if ($colExists('client_users', 'factory_station_id')) {
    $pdo->exec('ALTER TABLE client_users DROP COLUMN factory_station_id');
    echo "  Dropped client_users.factory_station_id.\n";
} else {
    echo "  client_users.factory_station_id already gone — skipped.\n";
}

echo "\nDone. The floor now runs purely on streams; the station tables are retired.\n";
echo "The inert station_id columns on route steps / blind streams / blind jobs were\n";
echo "left in place on purpose (always NULL, inside the live scan SQL).\n";
