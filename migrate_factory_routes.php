<?php
declare(strict_types=1);

/**
 * Migration: production routing — work centres (stations) + per-product routes.
 *
 *   factory_stations       — the machines & benches on the shop floor. Editable.
 *   product_route_steps    — the ordered sequence of stages a product runs
 *                            through, each step pointing at a station. Editable.
 *
 * Both are Beverley-master owned (client 3). A blind's live progress (which
 * stage it's on) is tracked separately once Phase B lands. Idempotent.
 *
 * Run via web: /migrate_factory_routes.php (super-admin).
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tableExists = static function (string $t) use ($pdo): bool {
    try { $pdo->query("SELECT 1 FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

$ops = [];

if (!$tableExists('factory_stations')) {
    $pdo->exec(
        "CREATE TABLE factory_stations (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            client_id     INT NOT NULL,
            name          VARCHAR(80) NOT NULL,
            sort_order    INT NOT NULL DEFAULT 0,
            is_outsourced TINYINT(1) NOT NULL DEFAULT 0,
            active        TINYINT(1) NOT NULL DEFAULT 1,
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_station_client (client_id, active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created factory_stations.';
} else {
    $ops[] = 'factory_stations already exists — skipped.';
}

if (!$tableExists('product_route_steps')) {
    $pdo->exec(
        "CREATE TABLE product_route_steps (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            seq        INT NOT NULL DEFAULT 0,
            station_id INT NOT NULL,
            label      VARCHAR(80) NULL,
            active     TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_route_product (product_id, seq),
            KEY idx_route_station (station_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created product_route_steps.';
} else {
    $ops[] = 'product_route_steps already exists — skipped.';
}

foreach ($ops as $o) echo "  $o\n";
echo "\nDone. Now run /seed_factory_routes.php to load the initial stations + routes.\n";
