<?php
declare(strict_types=1);

/**
 * Migration: rename the American "Center Left / Center Right" wand-draw spelling
 * to British "Centre Left / Centre Right" everywhere the exact string is stored,
 * so the build engine keeps matching end-to-end:
 *
 *   - product_extra_choices.label         (what the customer picks)
 *   - build_variables.rows_json           (the cut/truck rule cells)
 *   - allowance_rows.key_norm             (lowercased lookup key: "…|center left|…")
 *   - allowance_rows.keys_display         (human label: "… · Center Left · …")
 *   - quote_item_extras.choice_label_snapshot  (saved-order picks, so old orders resize)
 *
 * REPLACE only rewrites rows that still hold the old spelling, so this is fully
 * idempotent — safe to re-run, and re-running should report 0 rows once done.
 * It does NOT re-seed, so any hand-edited build rules are preserved.
 *
 * Run via web: /migrate_centre_spelling.php (super-admin).
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

// Only touch a (table, column) that actually exists on this install — the
// snapshot column arrived in a later migration, so guard before writing.
$columnExists = static function (PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1"
    );
    $st->execute([$table, $col]);
    return (bool) $st->fetchColumn();
};

// [table, column, old, new] — case matters: key_norm is stored lowercased.
$subs = [
    ['product_extra_choices', 'label',                  'Center Left',  'Centre Left'],
    ['product_extra_choices', 'label',                  'Center Right', 'Centre Right'],
    ['build_variables',       'rows_json',              'Center Left',  'Centre Left'],
    ['build_variables',       'rows_json',              'Center Right', 'Centre Right'],
    ['allowance_rows',        'keys_display',           'Center Left',  'Centre Left'],
    ['allowance_rows',        'keys_display',           'Center Right', 'Centre Right'],
    ['allowance_rows',        'key_norm',               'center left',  'centre left'],
    ['allowance_rows',        'key_norm',               'center right', 'centre right'],
    ['quote_item_extras',     'choice_label_snapshot',  'Center Left',  'Centre Left'],
    ['quote_item_extras',     'choice_label_snapshot',  'Center Right', 'Centre Right'],
];

echo "Renaming Center -> Centre across stored values…\n\n";

$pdo->beginTransaction();
$total = 0;
$seen  = [];
foreach ($subs as [$tbl, $col, $old, $new]) {
    $key = "$tbl.$col";
    if (!isset($seen[$key])) {
        $seen[$key] = $columnExists($pdo, $tbl, $col);
    }
    if (!$seen[$key]) {
        echo sprintf("  %-22s.%-22s  SKIPPED (no such column here)\n", $tbl, $col);
        continue;
    }
    $st = $pdo->prepare(
        "UPDATE `$tbl` SET `$col` = REPLACE(`$col`, ?, ?) WHERE `$col` LIKE ?"
    );
    $st->execute([$old, $new, '%' . $old . '%']);
    $n = $st->rowCount();
    $total += $n;
    echo sprintf("  %-22s.%-22s  %-13s -> %-13s : %d row(s)\n", $tbl, $col, $old, $new, $n);
}
$pdo->commit();

echo "\nDone. {$total} value(s) rewritten. Re-run to confirm it reports 0 rows.\n";
