<?php
declare(strict_types=1);

/**
 * Migration: appointment kind (measure vs fitting).
 *
 * The calendar now shows the measure/survey visit and the fitting as two
 * separate entries, each colour-tracking its own part of the job:
 *   measure  → Appointment booked → Quote sent → Accepted / Declined
 *   fitting  → Fitting booked → Fitted → Invoiced → Paid
 *
 * Adds appointments.appt_kind ('measure' default, 'fitting' for the install
 * auto-created on quote acceptance). Existing quote-linked appointments were
 * all auto-created fittings, so they're backfilled to 'fitting'. Idempotent.
 *
 * Run via web: /migrate_appointment_kind.php (super-admin).
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

$colExists = static function (string $col) use ($pdo): bool {
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = ?"
    );
    $st->execute([$col]);
    return (bool) $st->fetchColumn();
};

echo "Migrating: appointment kind (measure / fitting)…\n\n";

if ($colExists('appt_kind')) {
    $ops[] = "appointments.appt_kind already exists — skipped.";
} else {
    $pdo->exec("ALTER TABLE appointments ADD COLUMN appt_kind VARCHAR(20) NOT NULL DEFAULT 'measure'");
    $ops[] = "Added appointments.appt_kind (VARCHAR(20) DEFAULT 'measure').";

    // Existing quote-linked appointments were the auto-created installs.
    $n = $pdo->exec("UPDATE appointments SET appt_kind = 'fitting' WHERE quote_id IS NOT NULL");
    $ops[] = "Backfilled $n existing quote-linked appointment(s) to 'fitting'.";
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nMeasures and fittings are now distinct on the calendar — fittings carry a\n";
echo "dark outline, and each entry follows its own stage colours.\n";
