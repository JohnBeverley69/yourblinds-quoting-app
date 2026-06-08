<?php
declare(strict_types=1);

/**
 * One-off repair: restore the behaviour flags on the "Head Rail Only"
 * products that the server migration / old copy code reset to defaults.
 *
 * A headrail / track is priced on WIDTH alone and has NO fabric and NO
 * fabric colour. The correct flag state is:
 *
 *   width_only        = 1   priced on width alone (no drop column)
 *   requires_option   = 0   no-fabric product (don't force a fabric pick)
 *   show_colour_field = 0   no dedicated Colour sub-field
 *
 * The audit found these reset across every tenant, so quotes showed a
 * drop (and the wizard asked for a fabric/colour that doesn't exist).
 *
 * SAFE BY DEFAULT — runs as a DRY RUN and only reports what it WOULD
 * change. To actually apply, add ?apply=1 to the URL (or apply=1 on CLI).
 *
 *   Preview : /fix_headrail_width_only.php
 *   Apply   : /fix_headrail_width_only.php?apply=1
 *
 * Idempotent. Matches by exact product name so it can't touch anything
 * unrelated. Only updates flag columns that actually exist on the schema.
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

// Desired flag state for a headrail/track: column => target value.
$DESIRED = [
    'width_only'        => 1,
    'requires_option'   => 0,
    'show_colour_field' => 0,
];

// Apply mode? Default is dry-run (preview only).
$apply = false;
if (PHP_SAPI === 'cli') {
    foreach ($argv as $a) { if ($a === 'apply=1' || $a === '--apply') $apply = true; }
} else {
    $apply = (($_GET['apply'] ?? '') === '1');
}

// Keep only the flag columns that actually exist on this schema.
$colExists = static function (string $col) use ($pdo): bool {
    return (bool) $pdo->query(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'products'
            AND COLUMN_NAME  = " . $pdo->quote($col)
    )->fetchColumn();
};
$flags = [];
foreach ($DESIRED as $col => $want) {
    if ($colExists($col)) $flags[$col] = $want;
}
if (!$flags) {
    echo "None of the flag columns exist — run the migrate_*.php scripts first.\n";
    exit;
}

// Find the affected rows and their current flag state.
$select = 'id, client_id, name, ' . implode(', ', array_keys($flags));
$sel = $pdo->prepare(
    "SELECT $select FROM products WHERE name = ? ORDER BY client_id"
);
$sel->execute([TARGET_NAME]);
$rows = $sel->fetchAll(PDO::FETCH_ASSOC);

echo ($apply ? "APPLY" : "DRY RUN (preview only)") . " — repair flags for:\n";
echo '"' . TARGET_NAME . "\"\n";
echo "Target state: " . implode(', ',
        array_map(fn ($c, $v) => "$c=$v", array_keys($flags), $flags)) . "\n";
echo str_repeat('=', 68) . "\n";

if (!$rows) {
    echo "No products found with that exact name. Nothing to do.\n";
    exit;
}

$toFix = [];   // id => [col => want, ...]  (only the columns actually wrong)
foreach ($rows as $r) {
    $id   = (int) $r['id'];
    $wrong = [];
    foreach ($flags as $col => $want) {
        if ((int) $r[$col] !== $want) $wrong[$col] = $want;
    }
    $state = implode(' ', array_map(
        fn ($c) => "$c=" . (int) $r[$c], array_keys($flags)));
    if ($wrong) {
        $toFix[$id] = $wrong;
        printf("  #%-5d client %-3d  %s   <- fix %s\n",
            $id, (int) $r['client_id'], $state, implode(',', array_keys($wrong)));
    } else {
        printf("  #%-5d client %-3d  %s   (already correct)\n",
            $id, (int) $r['client_id'], $state);
    }
}

echo str_repeat('-', 68) . "\n";
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

// Apply — one UPDATE per product, setting only the columns that are wrong.
$pdo->beginTransaction();
$fixed = 0;
foreach ($toFix as $id => $wrong) {
    $set  = implode(', ', array_map(fn ($c) => "$c = ?", array_keys($wrong)));
    $vals = array_values($wrong);
    $vals[] = $id;
    $stmt = $pdo->prepare("UPDATE products SET $set WHERE id = ?");
    $stmt->execute($vals);
    $fixed++;
}
$pdo->commit();

echo "DONE — repaired $fixed product(s).\n";
echo "Re-open a quote for one to confirm: no drop column, no fabric/colour prompt.\n";
