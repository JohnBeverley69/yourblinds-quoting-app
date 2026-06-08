<?php
declare(strict_types=1);

/**
 * Migration: length basis for per-metre option pricing.
 *
 * A per-metre option choice (price_per_metre) is charged by a length. Until
 * now that length was always the blind's WIDTH. Some trims run a different
 * length — e.g. a magnetic strip that goes all the way around the outside is
 * the PERIMETER (2×width + 2×drop).
 *
 * Adds product_extra_choices.per_metre_basis:
 *   'width'           — width only            (default; preserves old behaviour)
 *   'drop'            — drop only
 *   'width_plus_drop' — width + drop
 *   'perimeter'       — 2×width + 2×drop
 *
 * Idempotent. Run via web: /migrate_per_metre_basis.php (super-admin).
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
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'product_extra_choices' AND COLUMN_NAME = ?"
    );
    $st->execute([$col]);
    return (bool) $st->fetchColumn();
};

echo "Migrating: per-metre length basis on option choices…\n\n";

if ($colExists('per_metre_basis')) {
    $ops[] = "product_extra_choices.per_metre_basis already exists — skipped.";
} else {
    $pdo->exec(
        "ALTER TABLE product_extra_choices
           ADD COLUMN per_metre_basis VARCHAR(20) NOT NULL DEFAULT 'width'"
    );
    $ops[] = "Added product_extra_choices.per_metre_basis (VARCHAR(20) DEFAULT 'width').";
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nPer-metre option choices can now be charged by width (default), drop,\n";
echo "width + drop, or perimeter (2×W + 2×D) — e.g. a magnetic strip that runs\n";
echo "around the whole blind. Set it on the choice editor.\n";
