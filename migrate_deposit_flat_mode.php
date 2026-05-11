<?php
declare(strict_types=1);

/**
 * Migration: support flat-amount deposit default in addition to %.
 *
 * Before: client_settings.default_deposit_percent only (a % of total).
 * After:
 *   - default_deposit_mode      ENUM('percent','flat')  DEFAULT 'percent'
 *   - default_deposit_flat      DECIMAL(10,2)           DEFAULT 0.00
 *   - default_deposit_percent   (unchanged — still used in 'percent' mode)
 *
 * Mode 'percent': deposit_amount = total × default_deposit_percent / 100
 * Mode 'flat':    deposit_amount = default_deposit_flat (capped at total
 *                                  so we never demand more than the
 *                                  order's worth)
 *
 * Idempotent — re-runnable.
 *
 * Run via web: /migrate_deposit_flat_mode.php  (super-admin login)
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

function col_exists_q(PDO $pdo, string $table, string $col): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $st->execute([$table, $col]);
    return $st->fetchColumn() !== false;
}

$ops = [];

if (!col_exists_q($pdo, 'client_settings', 'default_deposit_mode')) {
    $pdo->exec(
        "ALTER TABLE client_settings
            ADD COLUMN default_deposit_mode ENUM('percent','flat')
                NOT NULL DEFAULT 'percent'"
    );
    $ops[] = "client_settings.default_deposit_mode: added (default 'percent')";
} else {
    $ops[] = 'client_settings.default_deposit_mode: already present';
}

if (!col_exists_q($pdo, 'client_settings', 'default_deposit_flat')) {
    $pdo->exec(
        'ALTER TABLE client_settings
            ADD COLUMN default_deposit_flat DECIMAL(10,2) NOT NULL DEFAULT 0.00'
    );
    $ops[] = 'client_settings.default_deposit_flat: added (default 0.00)';
} else {
    $ops[] = 'client_settings.default_deposit_flat: already present';
}

echo "Migration complete:\n";
foreach ($ops as $op) echo '  - ' . $op . "\n";
echo "\n";
echo "Settings can now seed the deposit as either a percentage of the\n";
echo "total OR a flat £ amount. Existing tenants stay on 'percent' mode\n";
echo "with whatever default_deposit_percent value they had (no behaviour\n";
echo "change at cut-over).\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
