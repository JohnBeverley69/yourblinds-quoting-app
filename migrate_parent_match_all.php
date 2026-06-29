<?php
declare(strict_types=1);

/**
 * Migration: multi-condition (AND) sub-option gating.
 *
 * Adds one column to product_extras:
 *
 *   parent_match_all  TINYINT(1) NOT NULL DEFAULT 0
 *
 * Sub-option visibility is normally OR: the option shows if ANY of its
 * parent_choice_ids is currently selected. When parent_match_all = 1 the
 * gate becomes AND across DISTINCT parent options (still OR within one
 * option) — e.g. show "Wand Colour" only when Control = Wand AND Headrail =
 * Slimline Vogue. Default 0 preserves the historic OR behaviour for every
 * existing option, so nothing changes until a seeder/UI sets it.
 *
 * Idempotent. Run via web (super-admin): /migrate_parent_match_all.php
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
    if (PHP_SAPI !== 'cli' && !headers_sent()) header('Content-Type: text/plain; charset=utf-8');
    echo "Migration FAILED: " . $e->getMessage() . "\n\nSteps completed:\n";
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

echo "Migrating: multi-condition (AND) sub-option gating…\n\n";

if (!$colExists('product_extras', 'parent_match_all')) {
    $pdo->exec("ALTER TABLE product_extras ADD COLUMN parent_match_all TINYINT(1) NOT NULL DEFAULT 0");
    $ops[] = "Added product_extras.parent_match_all (default 0 = OR / unchanged).";
} else {
    $ops[] = 'product_extras.parent_match_all already exists — skipped.';
}

echo "Migration complete.\n\nSteps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nOptions default to OR gating. Set parent_match_all = 1 to require ALL parents.\n";
