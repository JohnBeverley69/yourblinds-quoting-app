<?php
declare(strict_types=1);

/**
 * Add product_extras.splits_panels — a per-option flag: when 1, the picked
 * choice's number (its label, e.g. "2", "3 panels") splits the blind into that
 * many EQUAL panels, and the base price becomes
 *     panels × grid price(width ÷ panels, drop)
 * instead of one grid price at the full width. E.g. PF Shutter "Number of
 * Panels": 1200 wide × 2 panels = 2 × price(600 × drop).
 *
 * Idempotent, super-admin, web-runnable: /migrate_extra_splits_panels.php
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$exists = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
$exists->execute(['product_extras', 'splits_panels']);
if ($exists->fetchColumn()) {
    echo "product_extras.splits_panels already exists — skipped.\n";
} else {
    $pdo->exec('ALTER TABLE product_extras ADD COLUMN splits_panels TINYINT(1) NOT NULL DEFAULT 0');
    echo "Added product_extras.splits_panels.\n";
}
echo "\nDone. Tick 'Splits the blind into equal panels' on an option in Products → its options editor.\n";
