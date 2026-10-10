<?php
declare(strict_types=1);

/**
 * Add product_options.is_default — the fabric (slat / colour) the quote builder
 * and InstaPrice fill in before anyone types. Optional per product; one per
 * band, set from the Fabrics page's "Make default".
 *
 * Idempotent, super-admin, web-runnable: /setup/migrations/migrate_fabric_default.php
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
$exists->execute(['product_options', 'is_default']);
if ($exists->fetchColumn()) {
    echo "product_options.is_default already exists — skipped.\n";
} else {
    $pdo->exec('ALTER TABLE product_options ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER active');
    echo "Added product_options.is_default.\n";
}
echo "\nDone. Products → Fabrics → 'Make default' on a row.\n";
