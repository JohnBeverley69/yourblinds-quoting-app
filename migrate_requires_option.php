<?php
declare(strict_types=1);

/**
 * Migration: "no-fabric" product type.
 *
 * Most products are priced fabric → band → price table. But some have
 * no fabric at all — e.g. a vertical-blind HEADRAIL ONLY, a track, a
 * spares line. For those the price is just system × (width × drop), with
 * nothing to pick on the "fabric" axis.
 *
 * This migration:
 *   1. Adds products.requires_option TINYINT(1) NOT NULL DEFAULT 1.
 *      1 = normal (needs a fabric/option to price).
 *      0 = no-fabric product (skip the fabric step entirely).
 *   2. Makes quote_items.option_id NULLABLE, so a saved no-fabric line
 *      can store NULL there instead of a fabric id. The exact column
 *      type is read from information_schema and preserved — we only
 *      flip the NULL flag.
 *
 * Idempotent — re-runnable. Detects existing state and skips.
 *
 * Run via web: /migrate_requires_option.php  (super-admin login).
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
    foreach ($ops as $i => $op) {
        echo sprintf("  %2d. %s\n", $i + 1, $op);
    }
    exit(1);
});

echo "Migrating: no-fabric product support…\n\n";

// ── 1. products.requires_option ───────────────────────────────────────
$hasCol = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'products'
        AND COLUMN_NAME  = 'requires_option'"
)->fetchColumn();

if (!$hasCol) {
    $pdo->exec(
        "ALTER TABLE products
         ADD COLUMN requires_option TINYINT(1) NOT NULL DEFAULT 1
         AFTER option_label"
    );
    $ops[] = 'Added column products.requires_option (TINYINT(1) NOT NULL DEFAULT 1).';
} else {
    $ops[] = 'products.requires_option already exists — skipped.';
}

// ── 2. quote_items.option_id → NULLABLE ───────────────────────────────
$col = $pdo->query(
    "SELECT COLUMN_TYPE, IS_NULLABLE
       FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'quote_items'
        AND COLUMN_NAME  = 'option_id'"
)->fetch(PDO::FETCH_ASSOC);

if (!$col) {
    $ops[] = 'quote_items.option_id not found — skipped (unexpected schema).';
} elseif (strtoupper((string) $col['IS_NULLABLE']) === 'YES') {
    $ops[] = 'quote_items.option_id already nullable — skipped.';
} else {
    // Preserve the exact column type; only flip the NULL flag. No
    // DEFAULT clause so existing NOT-NULL rows are untouched.
    $type = (string) $col['COLUMN_TYPE'];
    $pdo->exec("ALTER TABLE quote_items MODIFY option_id $type NULL");
    $ops[] = "Made quote_items.option_id nullable (was NOT NULL, type $type).";
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) {
    echo sprintf("  %2d. %s\n", $i + 1, $op);
}
echo "\nMark a product as 'no fabrics' on the product edit page or in the\n";
echo "setup wizard (e.g. for a headrail-only line). Such products skip the\n";
echo "fabric step and price on system × size alone.\n";
