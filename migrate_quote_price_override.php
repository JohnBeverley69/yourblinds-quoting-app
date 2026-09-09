<?php
declare(strict_types=1);

/**
 * Migration: quote total price override (a manual "agreed final price").
 *
 *   quotes.price_override   DECIMAL(10,2) NULL   -- the agreed INC-VAT total,
 *                                                   or NULL for no override
 *
 * A salesperson can pin the quote's final inc-VAT total to a negotiated figure
 * ("I'll do it for £X") without fiddling with per-line prices or percentages.
 * When set, qb_recompute_totals() derives net + VAT backwards from it and the
 * gap between the natural line prices and the agreed price is shown to the
 * customer as a single "Discount" line. NULL = no override (natural totals).
 *
 * Idempotent. Run via web: /migrate_quote_price_override.php (super-admin).
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

echo "Migrating: quote price override…\n\n";

if (!$colExists('quotes', 'price_override')) {
    $pdo->exec("ALTER TABLE quotes ADD COLUMN price_override DECIMAL(10,2) NULL DEFAULT NULL");
    $ops[] = 'Added quotes.price_override (NULL = no override).';
} else {
    $ops[] = 'quotes.price_override already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nSet the agreed total on the quote builder totals block.\n";
