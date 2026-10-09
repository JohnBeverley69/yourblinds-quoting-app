<?php
declare(strict_types=1);

/**
 * Add product_extras.joinable — a per-option flag: when 1 and the picked
 * choice's width table runs out (e.g. a 4000mm Senses fascia, longest 3500),
 * the length is split into the fewest EQUAL pieces that fit — normally two,
 * joined in the middle — and each piece is charged at its own length:
 *     4000 → 2 × the 2000 price.
 * Not ticked ⇒ the "exceeds the largest entry" error, as before.
 *
 * Idempotent, super-admin, web-runnable: /setup/migrations/migrate_extra_joinable.php
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
$exists->execute(['product_extras', 'joinable']);
if ($exists->fetchColumn()) {
    echo "product_extras.joinable already exists — skipped.\n";
} else {
    $pdo->exec('ALTER TABLE product_extras ADD COLUMN joinable TINYINT(1) NOT NULL DEFAULT 0');
    echo "Added product_extras.joinable.\n";
}
echo "\nDone. Tick 'Can be joined when longer than its width table' on an option in Products → its options editor.\n";
