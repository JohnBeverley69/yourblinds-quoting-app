<?php
declare(strict_types=1);

/**
 * Migration: widen band codes from VARCHAR(20) to VARCHAR(60).
 *
 * band_code is the pricing JOIN key (a fabric's band must match a price
 * table's band) plus the extras band-gating key. 20 chars was too short for
 * descriptive band names like "Bamboo & Gloss Herringbone Tape" (31). This
 * widens every column that holds a band so they stay consistent:
 *
 *   price_tables.band_code
 *   product_options.band_code
 *   product_extra_choices.band_code          (if present)
 *   product_extra_choice_bands.band_code     (if present)
 *   library_fabrics.suggested_band           (if present — feeds band_code)
 *
 * MODIFY keeps each column's existing charset/collation (no collation clash)
 * and nullability. Widening only — never shrinks, never truncates data.
 * Idempotent: a column already >= 60 is skipped.
 *
 * Run via web: /migrate_widen_band_code.php (super-admin).
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

$TO = 60;

/** Widen one VARCHAR column to $TO, preserving its nullability + collation. */
$widen = function (string $table, string $col) use ($pdo, &$ops, $TO): void {
    $st = $pdo->prepare(
        "SELECT CHARACTER_MAXIMUM_LENGTH AS len, IS_NULLABLE AS nul
           FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $st->execute([$table, $col]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) { $ops[] = "$table.$col not present — skipped."; return; }

    $len = (int) $row['len'];
    if ($len >= $TO) { $ops[] = "$table.$col already >= $TO ($len) — skipped."; return; }

    // Preserve NULL / NOT NULL. MODIFY without CHARACTER SET keeps the
    // column's current charset + collation. band_code/suggested_band carry
    // no DEFAULT, so none is re-applied.
    $null = ((string) $row['nul'] === 'YES') ? 'NULL' : 'NOT NULL';
    $pdo->exec("ALTER TABLE `$table` MODIFY `$col` VARCHAR($TO) $null");
    $ops[] = "Widened $table.$col VARCHAR($len) -> VARCHAR($TO) ($null).";
};

echo "Migrating: widen band codes to VARCHAR($TO)…\n\n";

$widen('price_tables',              'band_code');
$widen('product_options',           'band_code');
$widen('product_extra_choices',     'band_code');
$widen('product_extra_choice_bands','band_code');
$widen('library_fabrics',           'suggested_band');

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nBand codes up to $TO characters are now allowed everywhere.\n";
