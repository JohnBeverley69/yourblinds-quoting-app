<?php
declare(strict_types=1);

/**
 * Migration: per-blind price visibility on customer-facing quotes.
 *
 *   client_settings.show_line_prices  — TINYINT(1), default 1.
 *       1 (or absent) ⇒ the quote PDF and public quote show each blind's
 *         unit price + line total (current behaviour).
 *       0 ⇒ those per-line price columns are hidden and the customer only
 *         sees the quote total.
 *
 * Default 1 keeps every existing tenant exactly as they are today.
 *
 * Idempotent. Run via web: /migrate_show_line_prices.php (super-admin).
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
    echo "Steps completed before failure:\n";
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

echo "Migrating: show line prices…\n\n";

if (!$colExists('client_settings', 'show_line_prices')) {
    $pdo->exec(
        "ALTER TABLE client_settings
         ADD COLUMN show_line_prices TINYINT(1) NOT NULL DEFAULT 1"
    );
    $ops[] = 'Added client_settings.show_line_prices (TINYINT(1) DEFAULT 1 — show per-blind prices).';
} else {
    $ops[] = 'client_settings.show_line_prices already exists — skipped.';
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nToggle it on the Settings page → Quoting tab. Default is ON (prices\n";
echo "shown), so nothing changes for any tenant until they switch it off.\n";
