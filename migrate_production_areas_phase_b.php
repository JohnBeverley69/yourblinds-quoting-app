<?php
declare(strict_types=1);

/**
 * Migration: production areas — Phase B (floor & scan scoping).
 *
 *   factory_blind_jobs.area_id   — the area a released blind belongs to, stamped
 *                                  from product_area_map at release. Lets the floor
 *                                  filter to one area and the scan-in enforce it.
 *   production_areas.scan_key    — each area's own WiFi scan-in secret, so an area's
 *                                  scanner authenticates (and scopes) on its own key,
 *                                  separate from the global fallback key.
 *
 * Also backfills area_id on any blinds already on the floor.
 *
 * Idempotent + web-runnable: /migrate_production_areas_phase_b.php (super-admin).
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

$colExists = static function (string $table, string $col) use ($pdo): bool {
    $s = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $s->execute([$table, $col]);
    return (bool) $s->fetchColumn();
};

echo "Migrating: production areas — Phase B…\n\n";

if (!$colExists('factory_blind_jobs', 'area_id')) {
    $pdo->exec('ALTER TABLE factory_blind_jobs ADD COLUMN area_id INT NULL AFTER product_id');
    $pdo->exec('ALTER TABLE factory_blind_jobs ADD INDEX idx_fbj_area (area_id)');
    $ops[] = 'Added factory_blind_jobs.area_id (+ index).';
} else {
    $ops[] = 'factory_blind_jobs.area_id already exists — skipped.';
}

if (!$colExists('production_areas', 'scan_key')) {
    $pdo->exec('ALTER TABLE production_areas ADD COLUMN scan_key VARCHAR(64) NULL AFTER active');
    $pdo->exec('ALTER TABLE production_areas ADD INDEX idx_pa_scankey (scan_key)');
    $ops[] = 'Added production_areas.scan_key (+ index).';
} else {
    $ops[] = 'production_areas.scan_key already exists — skipped.';
}

// Backfill: stamp area_id on blinds already on the floor, from the product→area map.
$upd = $pdo->exec(
    'UPDATE factory_blind_jobs bj
       JOIN product_area_map pam ON pam.product_id = bj.product_id
        SET bj.area_id = pam.area_id
      WHERE bj.area_id IS NULL OR bj.area_id <> pam.area_id'
);
$ops[] = 'Backfilled area_id on ' . (int) $upd . ' existing blind job(s).';

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nNext: generate each area's scan key on Factory → Production areas,\n";
echo "and set each factory login's home area on Admin → Users.\n";
