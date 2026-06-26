<?php
declare(strict_types=1);

/**
 * Migration: store the requester's email on a supplier request.
 *
 *   supplier_requests.email VARCHAR(255) NULL   -- the admin who submitted it
 *
 * Surfaced in the "From" column on master-admin/supplier-requests.php and used
 * as the reply-to on the new-request notification email.
 *
 * Idempotent. Run via web: /migrate_supplier_request_email.php (super-admin).
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

echo "Migrating: supplier_requests.email…\n\n";

if (!$colExists('supplier_requests', 'email')) {
    $pdo->exec("ALTER TABLE supplier_requests ADD COLUMN email VARCHAR(255) NULL AFTER client_id");
    $ops[] = 'Added supplier_requests.email.';
} else {
    $ops[] = 'supplier_requests.email already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
