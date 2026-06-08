<?php
declare(strict_types=1);

/**
 * One-off repair: set width_only = 1 on the "Head Rail Only" products
 * that the server migration / old copy code reset to 0.
 *
 * These are headrail/track lines priced on width alone — they must not
 * show a drop. The audit (audit_product_flags.php) found them OFF across
 * every tenant.
 *
 * SAFE BY DEFAULT — runs as a DRY RUN and only reports what it WOULD
 * change. To actually apply the change, add ?apply=1 to the URL
 * (or pass apply=1 on the CLI).
 *
 *   Preview : /fix_headrail_width_only.php
 *   Apply   : /fix_headrail_width_only.php?apply=1
 *
 * Idempotent — re-running after it's applied changes nothing further.
 * Matches by exact product name so it can't touch anything unrelated.
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

// The exact product name to repair (covers every tenant's copy).
const TARGET_NAME = 'Bev Vertical Blind Head Rail Only';

// Apply mode? Default is dry-run (preview only).
$apply = false;
if (PHP_SAPI === 'cli') {
    foreach ($argv as $a) { if ($a === 'apply=1' || $a === '--apply') $apply = true; }
} else {
    $apply = (($_GET['apply'] ?? '') === '1');
}

// Make sure the column exists before we try to touch it.
$hasCol = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'products'
        AND COLUMN_NAME  = 'width_only'"
)->fetchColumn();

if (!$hasCol) {
    echo "Column products.width_only does not exist — run migrate_width_only.php first.\n";
    exit;
}

// Find the affected rows (currently OFF).
$sel = $pdo->prepare(
    'SELECT id, client_id, name, width_only
       FROM products
      WHERE name = ?
   ORDER BY client_id'
);
$sel->execute([TARGET_NAME]);
$rows = $sel->fetchAll(PDO::FETCH_ASSOC);

echo ($apply ? "APPLY" : "DRY RUN (preview only)") . " — repair width_only for:\n";
echo '"' . TARGET_NAME . "\"\n";
echo str_repeat('=', 60) . "\n";

if (!$rows) {
    echo "No products found with that exact name. Nothing to do.\n";
    exit;
}

$toFix = [];
foreach ($rows as $r) {
    $on = (int) $r['width_only'] === 1;
    printf("  #%-5d client %-3d  width_only=%s%s\n",
        (int) $r['id'], (int) $r['client_id'],
        $on ? 'ON ' : 'OFF',
        $on ? '  (already correct — skip)' : '  <- will set ON');
    if (!$on) $toFix[] = (int) $r['id'];
}

echo str_repeat('-', 60) . "\n";
echo count($rows) . " found, " . count($toFix) . " need fixing.\n\n";

if (!$toFix) {
    echo "All already correct. Nothing to change.\n";
    exit;
}

if (!$apply) {
    echo "This was a PREVIEW — nothing changed.\n";
    echo "To apply, re-run with ?apply=1 on the URL (or apply=1 on the CLI).\n";
    exit;
}

// Apply.
$in  = implode(',', array_fill(0, count($toFix), '?'));
$upd = $pdo->prepare("UPDATE products SET width_only = 1 WHERE id IN ($in)");
$upd->execute($toFix);

echo "DONE — set width_only = 1 on " . $upd->rowCount() . " product(s).\n";
echo "Re-open a quote for one to confirm the drop column is gone.\n";
