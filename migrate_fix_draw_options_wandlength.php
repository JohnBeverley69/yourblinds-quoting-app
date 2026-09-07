<?php
declare(strict_types=1);

/**
 * Fix: the corded "Draw Options" extra (the cord-side selector — C/L, C/R) was
 * mistakenly given a "Wand length (mm)" number input, so a Corded vertical asked
 * for a wand length it never needs. A wand length only belongs on the "Wand
 * Options" extra (shown for Wand control). Clear length_input_label on every
 * "Draw Options" extra — a draw-side choice never takes a typed length. Covers
 * the master product AND all mirrored copies (matched by name, so id-agnostic).
 *
 * Idempotent. Run as super-admin: /migrate_fix_draw_options_wandlength.php
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

// Guard: only if the column exists (pre-migration schemas have no length input).
$hasCol = false;
try {
    $c = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $c->execute(['product_extras', 'length_input_label']);
    $hasCol = $c->fetchColumn() !== false;
} catch (Throwable $e) { /* treated as absent */ }

if (!$hasCol) {
    echo "product_extras.length_input_label not present — nothing to do.\n";
    exit;
}

// Show what will change first (audit).
$sel = $pdo->prepare(
    "SELECT id, product_id, length_input_label FROM product_extras
      WHERE name = 'Draw Options' AND length_input_label IS NOT NULL"
);
$sel->execute();
$rows = $sel->fetchAll(PDO::FETCH_ASSOC);

echo "Draw Options extras with a stray length input: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo sprintf("  - extra #%d (product #%d): '%s'\n", (int) $r['id'], (int) $r['product_id'], (string) $r['length_input_label']);
}

$upd = $pdo->prepare(
    "UPDATE product_extras SET length_input_label = NULL
      WHERE name = 'Draw Options' AND length_input_label IS NOT NULL"
);
$upd->execute();

echo "\nCleared length input on {$upd->rowCount()} 'Draw Options' extra(s).\n";
echo "Corded verticals no longer ask for a wand length.\n";
