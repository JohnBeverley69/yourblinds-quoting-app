<?php
declare(strict_types=1);

/**
 * Migration: track blinds PER UNIT, not per order line.
 *
 * A qty-3 line is three physical blinds that can sit at three different benches,
 * so each unit gets its own card. Adds factory_blind_jobs.unit_no and moves the
 * uniqueness from (quote_item_id) to (quote_item_id, unit_no).
 *
 * Non-destructive: no column or row is dropped. Existing rows become unit 1 of
 * their line; the missing units 2..N are topped up next time the order is
 * released (the release is idempotent) — run /seed_factory_blind_jobs.php after
 * this to top up orders already in production.
 *
 * Run via web: /migrate_blind_jobs_per_unit.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try { $pdo->query('SELECT 1 FROM factory_blind_jobs LIMIT 0'); }
catch (Throwable $e) { echo "factory_blind_jobs is missing — run /migrate_factory_blind_jobs.php first.\n"; exit; }

$colExists = static function (string $t, string $c) use ($pdo): bool {
    try { $pdo->query("SELECT `$c` FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};
$indexExists = static function (string $t, string $k) use ($pdo): bool {
    $st = $pdo->prepare("SHOW INDEX FROM `$t` WHERE Key_name = ?");
    $st->execute([$k]);
    return (bool) $st->fetch();
};

if (!$colExists('factory_blind_jobs', 'unit_no')) {
    $pdo->exec("ALTER TABLE factory_blind_jobs ADD COLUMN unit_no INT NOT NULL DEFAULT 1 AFTER quote_item_id");
    echo "  Added factory_blind_jobs.unit_no (existing rows = unit 1).\n";
} else {
    echo "  unit_no already exists — skipped.\n";
}

// Uniqueness is now per unit of a line, not per line.
if ($indexExists('factory_blind_jobs', 'uq_item')) {
    $pdo->exec("ALTER TABLE factory_blind_jobs DROP INDEX uq_item");
    echo "  Dropped the old per-line unique key.\n";
}
if (!$indexExists('factory_blind_jobs', 'uq_item_unit')) {
    $pdo->exec("ALTER TABLE factory_blind_jobs ADD UNIQUE KEY uq_item_unit (quote_item_id, unit_no)");
    echo "  Added unique key (quote_item_id, unit_no).\n";
} else {
    echo "  uq_item_unit already exists — skipped.\n";
}

echo "\nDone. New orders release one card per unit automatically.\n";
echo "Run /seed_factory_blind_jobs.php to top up units 2..N on orders already in production.\n";
