<?php
declare(strict_types=1);

/**
 * Migration: tie a factory login to a bench.
 *
 *   client_users.factory_station_id — which station this account IS.
 *
 * Workshop logins are per STATION, not per person: the bench PC sits logged in
 * as "Safety Saw" all day and whoever's stood at it uses it. So the account
 * needs to know which bench it is — otherwise it lands on the incoming-orders
 * queue like an office login, and (once the scanners land) a scan has no way of
 * knowing which bench it came from.
 *
 * NULL = not a station account, which is every existing user. Nothing changes
 * for them.
 *
 * Run via web: /migrate_user_station.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colExists = static function (string $t, string $c) use ($pdo): bool {
    try { $pdo->query("SELECT `$c` FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

if (!$colExists('client_users', 'factory_station_id')) {
    $pdo->exec("ALTER TABLE client_users ADD COLUMN factory_station_id INT NULL");
    echo "  Added client_users.factory_station_id.\n";
} else {
    echo "  client_users.factory_station_id already exists — skipped.\n";
}

echo "\nDone. On Admin -> Users, a factory account can now be pointed at a bench.\n";
echo "It lands on that bench's queue at login instead of the incoming orders.\n";
