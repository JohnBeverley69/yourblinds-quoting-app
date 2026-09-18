<?php
declare(strict_types=1);

/**
 * Migration: production areas (Phase A of the factory production-areas feature).
 *
 *   production_areas       — a factory's areas (Vertical / Roller / Pleated …),
 *                            editable on Factory → Production areas.
 *   product_area_map       — which master product is made in which area (one area
 *                            per product). Keyed on the MASTER product_id, which
 *                            is what factory_blind_jobs.product_id already stores.
 *   user_production_areas   — a factory user's home area(s); the floor/scan screens
 *                            default to it (with an on-screen switcher, per computer).
 *
 * Idempotent + web-runnable: /migrate_production_areas.php (super-admin).
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
    if (PHP_SAPI !== 'cli' && !headers_sent()) header('Content-Type: text/plain; charset=utf-8');
    echo "Migration FAILED: " . $e->getMessage() . "\n\n";
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

$tableExists = static function (string $t) use ($pdo): bool {
    $s = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $s->execute([$t]);
    return (bool) $s->fetchColumn();
};

echo "Migrating: production areas…\n\n";

if (!$tableExists('production_areas')) {
    $pdo->exec(
        "CREATE TABLE production_areas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            name VARCHAR(60) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pa_client (client_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created production_areas.';
} else {
    $ops[] = 'production_areas already exists — skipped.';
}

if (!$tableExists('product_area_map')) {
    $pdo->exec(
        "CREATE TABLE product_area_map (
            id INT AUTO_INCREMENT PRIMARY KEY,
            area_id INT NOT NULL,
            product_id INT NOT NULL,
            UNIQUE KEY uq_pam_product (product_id),
            INDEX idx_pam_area (area_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created product_area_map (one area per product).';
} else {
    $ops[] = 'product_area_map already exists — skipped.';
}

if (!$tableExists('user_production_areas')) {
    $pdo->exec(
        "CREATE TABLE user_production_areas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            area_id INT NOT NULL,
            UNIQUE KEY uq_upa (user_id, area_id),
            INDEX idx_upa_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ops[] = 'Created user_production_areas.';
} else {
    $ops[] = 'user_production_areas already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nNext: set the areas up on Factory → Production areas.\n";
