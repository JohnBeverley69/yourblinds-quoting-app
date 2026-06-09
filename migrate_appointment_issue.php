<?php
declare(strict_types=1);

/**
 * Migration: "Issue" flag + note on appointments.
 *
 * Lets any job be flagged as having a problem (wrong product, access issue,
 * remake, complaint…) with a short note. The calendar shows flagged jobs with
 * a red ⚠ pill + ring (keeping their stage colour underneath), and there's an
 * Issues filter to pull up just the problem jobs.
 *
 * Adds appointments.has_issue + appointments.issue_note. Idempotent.
 * Run via web: /migrate_appointment_issue.php (super-admin).
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

echo "Migrating: appointment Issue flag + note…\n\n";

if ($colExists('has_issue')) {
    $ops[] = "appointments.has_issue already exists — skipped.";
} else {
    $pdo->exec("ALTER TABLE appointments ADD COLUMN has_issue TINYINT(1) NOT NULL DEFAULT 0");
    $ops[] = "Added appointments.has_issue (TINYINT(1) DEFAULT 0).";
}

if ($colExists('issue_note')) {
    $ops[] = "appointments.issue_note already exists — skipped.";
} else {
    $pdo->exec("ALTER TABLE appointments ADD COLUMN issue_note VARCHAR(280) NULL");
    $ops[] = "Added appointments.issue_note (VARCHAR(280) NULL).";
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nFlag a job with the ⚠ marker on its calendar card (or the edit form). Use\n";
echo "the Issues filter on the calendar to see just the flagged jobs.\n";
