<?php
declare(strict_types=1);

/**
 * Migration: make appointments.appointment_date nullable.
 *
 * The "auto-book on quote acceptance" feature originally landed the
 * appointment on a placeholder date (today + 14 days). Real-world use
 * showed that's worse than no date at all — the placeholder gets lost
 * in the future calendar grid, and trade users want a "pending
 * scheduling" tray they can drag onto the right date when they've
 * spoken to the customer.
 *
 * NULL appointment_date = pending scheduling.
 *
 * Idempotent — checks IS_NULLABLE on the column first.
 *
 * Run via web: /migrate_appointment_date_nullable.php   (super-admin login)
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

$check = $pdo->prepare(
    'SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
);
$check->execute(['appointments', 'appointment_date']);
$nullable = (string) ($check->fetchColumn() ?: '');

$ops = [];

if ($nullable === 'YES') {
    $ops[] = 'Skipped appointments.appointment_date (already nullable).';
} else {
    $pdo->exec(
        'ALTER TABLE appointments
           MODIFY COLUMN appointment_date DATE NULL'
    );
    $ops[] = 'Modified appointments.appointment_date → DATE NULL.';
}

echo "Migration complete:\n";
foreach ($ops as $op) {
    echo '  - ' . $op . "\n";
}
echo "\nNULL appointment_date = pending scheduling. The calendar's new\n";
echo "Pending Scheduling tray surfaces these so you can drag them onto\n";
echo "the right date when ready.\n";
echo "\nWhen you're done, you can delete this file from the server.\n";
