<?php
declare(strict_types=1);

/**
 * Migration: optional AM/PM time-window slots for quote (measure) visits.
 *
 *   client_settings.feature_ampm_slots  TINYINT(1) DEFAULT 0  -- off by default
 *   client_settings.ampm_slot_capacity  INT        DEFAULT 4  -- bookings per window/day
 *   appointments.slot_window            VARCHAR(2) NULL       -- 'am' | 'pm' | NULL
 *
 * When enabled, booking a measure appointment offers "Morning (9am-1pm)" or
 * "Afternoon (1pm-5pm)" instead of a clock time. Each window holds up to
 * ampm_slot_capacity bookings per day; a full window is unavailable. The chosen
 * window is stored canonically as appointment_time/duration (AM = 09:00 +240m,
 * PM = 13:00 +240m) so every existing calendar view keeps working, plus
 * slot_window so renderers can show the window label instead of a clock time.
 * Fittings are unaffected. Off by default — a tenant opts in on Settings.
 *
 * Idempotent. Run via web: /migrate_ampm_slots.php (super-admin).
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

echo "Migrating: AM/PM time-window slots for quote visits…\n\n";

if (!$colExists('client_settings', 'feature_ampm_slots')) {
    $pdo->exec("ALTER TABLE client_settings ADD COLUMN feature_ampm_slots TINYINT(1) NOT NULL DEFAULT 0");
    $ops[] = 'Added client_settings.feature_ampm_slots (off by default).';
} else {
    $ops[] = 'client_settings.feature_ampm_slots already exists — skipped.';
}

if (!$colExists('client_settings', 'ampm_slot_capacity')) {
    $pdo->exec("ALTER TABLE client_settings ADD COLUMN ampm_slot_capacity INT NOT NULL DEFAULT 4");
    $ops[] = 'Added client_settings.ampm_slot_capacity (default 4).';
} else {
    $ops[] = 'client_settings.ampm_slot_capacity already exists — skipped.';
}

if (!$colExists('appointments', 'slot_window')) {
    $pdo->exec("ALTER TABLE appointments ADD COLUMN slot_window VARCHAR(2) NULL");
    $ops[] = 'Added appointments.slot_window (am / pm / NULL).';
} else {
    $ops[] = 'appointments.slot_window already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nEnable per tenant on Settings → Calendar → \"Morning / afternoon booking slots\".\n";
