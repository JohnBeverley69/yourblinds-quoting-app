<?php
declare(strict_types=1);

/**
 * Migration: per-blind SIZE visibility on customer-facing quotes.
 *
 *   client_settings.show_line_sizes TINYINT(1) NOT NULL DEFAULT 1
 *
 * Sibling of show_line_prices. When ON (the default — we're a trade supplier now),
 * each line's size (width × drop) is printed on the quote PDF and the public online
 * quote so the trade customer sees exactly what each blind is. OFF keeps the old
 * retail behaviour (sizes hidden, total only). Per-tenant, toggled on Settings →
 * Quoting.
 *
 * Idempotent. Run via web: /migrate_show_line_sizes.php (super-admin).
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
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

echo "Migrating: show per-blind sizes on customer quotes…\n\n";

if (!$colExists('client_settings', 'show_line_sizes')) {
    $pdo->exec("ALTER TABLE client_settings ADD COLUMN show_line_sizes TINYINT(1) NOT NULL DEFAULT 1");
    $ops[] = 'Added client_settings.show_line_sizes (on by default).';
} else {
    $ops[] = 'client_settings.show_line_sizes already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nToggle it per tenant on Settings → Quoting.\n";
