<?php
declare(strict_types=1);

/**
 * Migration (Phase B): per-blind production tracking.
 *
 *   factory_blind_jobs — one row per PHYSICAL BLIND released to the floor. It
 *   rides the product's route (product_route_steps): route_step_id is the stage
 *   it's currently sitting at, with station_id + seq denormalised so the floor
 *   board and per-station queues group cheaply. status: queued -> in_progress ->
 *   (advance through the route) -> complete.
 *
 * A qty-3 order line becomes three rows (unit 1..3) that move independently —
 * the workshop can have them at three different benches at once. Beverley-master
 * owned (the lines are always Beverley products). Idempotent.
 *
 * Run via web: /migrate_factory_blind_jobs.php (super-admin). Needs the routing
 * tables from /migrate_factory_routes.php.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tableExists = static function (string $t) use ($pdo): bool {
    try { $pdo->query("SELECT 1 FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

if (!$tableExists('factory_blind_jobs')) {
    $pdo->exec(
        "CREATE TABLE factory_blind_jobs (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            quote_id        INT NOT NULL,
            quote_item_id   INT NOT NULL,
            unit_no         INT NOT NULL DEFAULT 1,      -- a qty-3 line = 3 physical blinds, tracked independently
            product_id      INT NOT NULL,                -- Beverley's MASTER product, not the tenant's pushed copy: routes are keyed to the master

            route_step_id   INT NULL,                    -- current stage (product_route_steps.id); NULL = complete or unrouted
            station_id      INT NULL,                    -- denormalised current station, for fast queue grouping
            seq             INT NOT NULL DEFAULT 0,      -- denormalised position of the current stage
            status          VARCHAR(16) NOT NULL DEFAULT 'queued',   -- queued | in_progress | complete
            started_at      DATETIME NULL,               -- first time any work started on this blind
            step_started_at DATETIME NULL,               -- when it entered the current stage
            completed_at    DATETIME NULL,
            updated_by      INT NULL,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_item_unit (quote_item_id, unit_no),
            KEY idx_station (station_id, status),
            KEY idx_quote (quote_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "  Created factory_blind_jobs.\n";
} else {
    echo "  factory_blind_jobs already exists — skipped.\n";
}

echo "\nDone. Blinds hit the floor when an order is moved to \"in production\" on\n";
echo "Incoming Orders. To backfill orders already in production, run\n";
echo "/seed_factory_blind_jobs.php. Watch them on /factory/floor.php.\n";
