<?php
declare(strict_types=1);

/**
 * Migration: help video tutorials.
 *
 *   help_videos (id, title, url, sort_order)
 *
 * Platform-wide (not per-tenant) — the super-admin curates a list of
 * how-to video links that everyone sees on the Help & guide page.
 *
 * Idempotent. Run via web: /migrate_help_videos.php (super-admin).
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
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Migration FAILED: " . $e->getMessage() . "\n\n";
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

$tableExists = static function (string $t) use ($pdo): bool {
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $st->execute([$t]);
    return (bool) $st->fetchColumn();
};

echo "Migrating: help_videos…\n\n";

if (!$tableExists('help_videos')) {
    $pdo->exec(
        "CREATE TABLE help_videos (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            title      VARCHAR(160) NOT NULL,
            url        VARCHAR(500) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB"
    );
    $ops[] = 'Created help_videos.';
} else {
    $ops[] = 'help_videos already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nAdd video links on the Help & guide page (you'll see an editor there as super-admin).\n";
