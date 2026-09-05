<?php
declare(strict_types=1);

/**
 * Migration: make the corded centre-draw code uniform — "C / L" / "C / R" -> "C/L" /
 * "C/R" — in the stored build rules (build_variables.rows_json), matching the new
 * Draw Options choices and the R/R / L/L style. The spaced form was a silent-mismatch
 * risk (a choice typed "C/L" would never hit a rule keyed on "C / L").
 *
 * Targeted REPLACE, no re-seed, so hand-edited rules are preserved. "C / L" / "C / R"
 * are specific enough not to collide with the descriptive "… Ctrl / … Stack" draws.
 * Idempotent — re-running reports 0. Run: /migrate_draw_slash_uniform.php (super-admin).
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

echo "Making corded centre draws uniform (\"C / L\"/\"C / R\" -> \"C/L\"/\"C/R\")…\n\n";
$total = 0;
foreach ([['C / L', 'C/L'], ['C / R', 'C/R']] as [$old, $new]) {
    $st = $pdo->prepare("UPDATE build_variables SET rows_json = REPLACE(rows_json, ?, ?) WHERE rows_json LIKE ?");
    $st->execute([$old, $new, '%' . $old . '%']);
    $n = $st->rowCount();
    $total += $n;
    echo sprintf("  build_variables.rows_json  %-6s -> %-4s : %d row(s)\n", $old, $new, $n);
}
echo "\nDone. {$total} build-variable row(s) rewritten. Re-run to confirm 0.\n";
