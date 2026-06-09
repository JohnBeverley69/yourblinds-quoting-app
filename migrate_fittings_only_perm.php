<?php
declare(strict_types=1);

/**
 * Migration: "fittings only" user permission.
 *
 * A fitter usually shouldn't see sales/measure visits — only the fitting
 * jobs. Adds client_users.can_view_fittings_only; when set, the calendar
 * shows that user only appt_kind = 'fitting' appointments (combined with the
 * existing all-vs-own-jobs scope). Default 0 = unchanged behaviour. Idempotent.
 *
 * Run via web: /migrate_fittings_only_perm.php (super-admin).
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
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_users' AND COLUMN_NAME = ?"
    );
    $st->execute([$col]);
    return (bool) $st->fetchColumn();
};

echo "Migrating: 'fittings only' user permission…\n\n";

if ($colExists('can_view_fittings_only')) {
    $ops[] = "client_users.can_view_fittings_only already exists — skipped.";
} else {
    $pdo->exec("ALTER TABLE client_users ADD COLUMN can_view_fittings_only TINYINT(1) NOT NULL DEFAULT 0");
    $ops[] = "Added client_users.can_view_fittings_only (TINYINT(1) DEFAULT 0).";
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nTick 'Fittings only' on a user (Settings -> Users) to limit their calendar\n";
echo "to fitting jobs.\n";
