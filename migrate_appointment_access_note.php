<?php
declare(strict_types=1);

/**
 * Migration: per-appointment "access note".
 *
 * A short, prominent day-of note on a calendar appointment — e.g. "tap
 * gently, baby asleep" / "park on the road" / "key safe code 1234". Kept
 * separate from the long general `notes` field so it stays a one-glance
 * flag on the calendar (a 📝 marker the fitter can't miss).
 *
 * Idempotent. Run via web: /migrate_appointment_access_note.php (super-admin).
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

$hasCol = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'appointments'
        AND COLUMN_NAME  = 'access_note'"
)->fetchColumn();

echo "Migrating: appointment access note…\n\n";

if ($hasCol) {
    echo "appointments.access_note already exists — skipped.\n";
} else {
    $pdo->exec("ALTER TABLE appointments ADD COLUMN access_note VARCHAR(280) NULL");
    echo "Added appointments.access_note (VARCHAR(280) NULL).\n";
}

echo "\nDone. Add a quick note from the calendar (the 📝 on an appointment);\n";
echo "appointments with a note show a marker so they stand out at a glance.\n";
