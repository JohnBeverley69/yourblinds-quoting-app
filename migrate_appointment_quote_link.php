<?php
declare(strict_types=1);

/**
 * Migration: link appointments back to the originating quote.
 *
 * Adds appointments.quote_id (nullable INT UNSIGNED + index + FK to quotes).
 * Once present, the accept handler can auto-create an installation
 * appointment when the customer accepts a quote, and the appointment
 * itself can deep-link back to the quote.
 *
 * Idempotent — INFORMATION_SCHEMA check.
 *
 * Run via web: /migrate_appointment_quote_link.php   (super-admin login)
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

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $st->execute([$table, $column]);
    return $st->fetchColumn() !== false;
}

function fk_exists(PDO $pdo, string $table, string $name): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
            AND CONSTRAINT_TYPE = "FOREIGN KEY" LIMIT 1'
    );
    $st->execute([$table, $name]);
    return $st->fetchColumn() !== false;
}

$ops = [];

if (!column_exists($pdo, 'appointments', 'quote_id')) {
    $pdo->exec(
        'ALTER TABLE appointments
           ADD COLUMN quote_id INT UNSIGNED NULL AFTER customer_id,
           ADD INDEX  idx_appointments_quote (quote_id)'
    );
    $ops[] = 'Added appointments.quote_id (INT UNSIGNED NULL) + index.';
} else {
    $ops[] = 'Skipped appointments.quote_id (already present).';
}

if (!fk_exists($pdo, 'appointments', 'fk_appointments_quote')) {
    // ON DELETE SET NULL — we don't want a hard-deleted quote to wipe
    // the appointment; the fitter still needs to know about the job.
    $pdo->exec(
        'ALTER TABLE appointments
           ADD CONSTRAINT fk_appointments_quote
           FOREIGN KEY (quote_id) REFERENCES quotes(id)
           ON DELETE SET NULL ON UPDATE CASCADE'
    );
    $ops[] = 'Added FK fk_appointments_quote (quote_id → quotes.id, SET NULL on delete).';
} else {
    $ops[] = 'Skipped FK fk_appointments_quote (already present).';
}

echo "Migration complete:\n";
foreach ($ops as $op) {
    echo '  - ' . $op . "\n";
}
echo "\nAfter this + the code update, accepting a quote will auto-book\n";
echo "an installation appointment for ~14 days from acceptance, copying\n";
echo "the customer's address. The trade user can then drag the date /\n";
echo "assign a fitter from the calendar.\n";
echo "\nWhen you're done, you can delete this file from the server.\n";
