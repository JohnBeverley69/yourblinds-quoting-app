<?php
declare(strict_types=1);

/**
 * Migration: support_tickets.fix_requested_at — when "🛠 Draft a fix" was last
 * pressed for a ticket (see _partials/support_fix.php). Additive + nullable.
 *
 * Run via web: /migrate_support_fix.php?run=1 (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); require_run_confirmation(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->query('SELECT fix_requested_at FROM support_tickets LIMIT 0');
    echo "  support_tickets.fix_requested_at already exists — skipped.\n";
} catch (Throwable $e) {
    $pdo->exec('ALTER TABLE support_tickets ADD COLUMN fix_requested_at DATETIME NULL');
    echo "  Added support_tickets.fix_requested_at.\n";
}

echo "\nDone.\n";
