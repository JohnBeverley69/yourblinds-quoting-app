<?php
declare(strict_types=1);

/**
 * Migration: auto-place in-house orders on accept.
 *
 *   client_settings.auto_place_inhouse TINYINT(1) NOT NULL DEFAULT 1
 *
 * When ON (the default), accepting a quote whose blinds are ALL made in-house
 * (every line a factory-owned product with no external supplier) advances it
 * straight to 'ordered' so it drops into the factory queue — skipping the manual
 * "Place order" step. Quotes with any bought-in/supplier line are unaffected
 * (those still need the manual send so the supplier gets emailed). Per-tenant,
 * toggled on Settings.
 *
 * Idempotent. Run via web: /migrate_auto_place_inhouse.php (super-admin).
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

$colExists = static function (string $table, string $col) use ($pdo): bool {
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $st->execute([$table, $col]);
    return (bool) $st->fetchColumn();
};

echo "Migrating: auto-place in-house on accept…\n\n";

if (!$colExists('client_settings', 'auto_place_inhouse')) {
    $pdo->exec("ALTER TABLE client_settings ADD COLUMN auto_place_inhouse TINYINT(1) NOT NULL DEFAULT 1");
    $ops[] = 'Added client_settings.auto_place_inhouse (on by default).';
} else {
    $ops[] = 'client_settings.auto_place_inhouse already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nToggle it per tenant on Settings.\n";
