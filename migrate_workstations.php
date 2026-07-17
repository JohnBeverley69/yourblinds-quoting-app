<?php
declare(strict_types=1);

/**
 * Migration: a workstation is a PROCESS, not a bench.
 *
 * John: "the user is not a person its simply 'Vertical Head Rail' ... The
 * benches are just noise as the product has processes rather than benches."
 *
 * He's right, and the tell was in the old model: a "Safety Saw" login showed a
 * mixed queue of vertical headrails, roller tubes and pleated profiles. That's
 * nobody's job. "Vertical Head Rail" is exactly one person's work — profile cut
 * -> assembly -> done — and it's already a first-class thing here: it's a
 * stream.
 *
 *   workstation_streams — which processes a login covers. A login can hold any
 *                         number (staff move where they're needed), and more
 *                         than one login can cover the same process.
 *
 * A stage is now just: stream + label, in order. product_route_steps.station_id
 * becomes optional and unused.
 *
 * NOT DROPPED: factory_stations and the station columns stay in the database.
 * They're removed from every screen and every code path, but dropping tables is
 * irreversible and this is a model change made on a hunch that's a day old. If
 * "which saw" turns out to matter, it's a five-minute job to bring back rather
 * than a restore-from-backup.
 *
 * Run via web: /migrate_workstations.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tableExists = static function (string $t) use ($pdo): bool {
    try { $pdo->query("SELECT 1 FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

if (!$tableExists('workstation_streams')) {
    $pdo->exec(
        "CREATE TABLE workstation_streams (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            user_id    INT NOT NULL,              -- the login; the 'user' IS the workstation
            product_id INT NOT NULL,              -- Beverley's MASTER product
            stream     VARCHAR(40) NOT NULL DEFAULT 'main',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ws (user_id, product_id, stream),
            KEY idx_ws_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "  Created workstation_streams.\n";
} else {
    echo "  workstation_streams already exists — skipped.\n";
}

// A stage no longer needs a bench. Make the column optional so the routes editor
// can stop asking for one; existing values are left alone and simply ignored.
try {
    $pdo->exec("ALTER TABLE product_route_steps MODIFY station_id INT NULL");
    echo "  product_route_steps.station_id is now optional (and unused).\n";
} catch (Throwable $e) {
    echo "  ! could not relax station_id: " . $e->getMessage() . "\n";
}

echo "\nDone. Assign processes to each workshop login on Admin -> Users.\n";
echo "factory_stations is left in the database on purpose — it's out of the UI\n";
echo "and out of the code, but not destroyed. Say the word if you want it gone.\n";
