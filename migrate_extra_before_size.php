<?php
declare(strict_types=1);

/**
 * Add product_extras.before_size — a per-option-group flag: when 1, the option
 * (and its nested children) renders ABOVE the Width/Drop size fields in the
 * quote builder / InstaPrice, instead of below. Lets a user put e.g. the roller
 * fascia group before the size, so multi-fascia per-blind width boxes make sense.
 *
 * Idempotent, super-admin, web-runnable: /migrate_extra_before_size.php
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

$exists = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
$exists->execute(['product_extras', 'before_size']);
if ($exists->fetchColumn()) {
    echo "product_extras.before_size already exists — skipped.\n";
} else {
    $pdo->exec('ALTER TABLE product_extras ADD COLUMN before_size TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_multi');
    echo "Added product_extras.before_size.\n";
}
echo "\nDone. Set an option to 'Before the size fields' in Products → its options editor.\n";
