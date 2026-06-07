<?php
declare(strict_types=1);

/**
 * Migration: configurable measurement units.
 *
 * Dimensions are always STORED in millimetres. These columns only drive
 * how sizes are entered and displayed:
 *
 *   client_settings.default_measurement_unit  — the tenant's default
 *       ('mm' | 'cm' | 'm' | 'in'). NULL/absent ⇒ 'mm'.
 *   quotes.measurement_unit  — optional per-quote override (the unit
 *       switcher on the quote builder). NULL ⇒ use the tenant default.
 *
 * Idempotent. Run via web: /migrate_measurement_unit.php (super-admin).
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

echo "Migrating: measurement units…\n\n";

if (!$colExists('client_settings', 'default_measurement_unit')) {
    $pdo->exec(
        "ALTER TABLE client_settings
         ADD COLUMN default_measurement_unit VARCHAR(4) NOT NULL DEFAULT 'mm'"
    );
    $ops[] = "Added client_settings.default_measurement_unit (VARCHAR(4) DEFAULT 'mm').";
} else {
    $ops[] = 'client_settings.default_measurement_unit already exists — skipped.';
}

if (!$colExists('quotes', 'measurement_unit')) {
    $pdo->exec(
        "ALTER TABLE quotes
         ADD COLUMN measurement_unit VARCHAR(4) NULL"
    );
    $ops[] = 'Added quotes.measurement_unit (VARCHAR(4) NULL — per-quote override).';
} else {
    $ops[] = 'quotes.measurement_unit already exists — skipped.';
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nSet the company default on the Settings page. Sizes stay stored in\n";
echo "millimetres; only entry + display use the chosen unit.\n";
