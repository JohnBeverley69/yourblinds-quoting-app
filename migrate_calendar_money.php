<?php
declare(strict_types=1);

/**
 * Migration: per-tenant toggle for showing money on the calendar.
 *
 * Adds client_settings.calendar_show_money (TINYINT, default 0 = off).
 * When ON, the month / week / day calendar cards show the order value,
 * amount received (deposit + payments) and outstanding balance for any
 * appointment linked to a quote — and a PAID badge once it's settled.
 *
 * Off by default so money never appears on a calendar until the tenant
 * deliberately ticks the box on /admin/settings.php (some tenants share
 * the calendar with fitters who shouldn't see figures).
 *
 * Idempotent. Run via /migrate_calendar_money.php (super-admin) then delete.
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

set_exception_handler(function (Throwable $e) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "MIGRATION FAILED\n================\n\n";
    echo 'Error: ' . $e->getMessage() . "\n";
    echo 'In:    ' . $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
});

$st = $pdo->prepare(
    'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
);
$st->execute(['client_settings', 'calendar_show_money']);

if ($st->fetchColumn() === false) {
    $pdo->exec(
        'ALTER TABLE client_settings
            ADD COLUMN calendar_show_money TINYINT(1) NOT NULL DEFAULT 0'
    );
    echo "client_settings.calendar_show_money: added (default 0 = off)\n";
} else {
    echo "client_settings.calendar_show_money: already present (skipped)\n";
}

echo "\n";
echo "Done. Tick 'Show order value + balance on the calendar' on\n";
echo "/admin/settings.php to switch the figures on for this tenant.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
