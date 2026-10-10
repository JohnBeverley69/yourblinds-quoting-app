<?php
declare(strict_types=1);

/**
 * Add products.band_start_first — a per-product flag: when 1, the band box in
 * the quote builder / InstaPrice opens on the system's FIRST band (the top of
 * its Price tables order, e.g. String on a wood venetian) instead of
 * "All bands".
 *
 * Idempotent, super-admin, web-runnable: /setup/migrations/migrate_band_start_first.php
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__, 2) . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$exists = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
$exists->execute(['products', 'band_start_first']);
if ($exists->fetchColumn()) {
    echo "products.band_start_first already exists — skipped.\n";
} else {
    $pdo->exec('ALTER TABLE products ADD COLUMN band_start_first TINYINT(1) NOT NULL DEFAULT 0 AFTER band_label');
    echo "Added products.band_start_first.\n";
}
echo "\nDone. Tick 'Start on the first band' under Band label in Products → edit product.\n";
