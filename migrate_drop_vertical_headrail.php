<?php
declare(strict_types=1);

/**
 * Migration: remove the inert "vertical_headrail" allowance table.
 *
 * The headrail-cut deductions were stored TWICE: in the H_Cut build rule
 * (build_variables, literal "Width - N" — the value the worksheet actually uses)
 * AND in this allowance_rows table. No formula ever does LOOKUP("vertical_headrail"),
 * so this copy drove nothing — it only produced a divergent, editable duplicate on
 * the Allowances screen (editing it looked like it changed the cut but did not).
 * Deleting it removes the trap; the headrail cut stays authoritative in Build Rules.
 *
 * Global by table_name (the table is inert for every factory). Idempotent.
 * Run via web: /migrate_drop_vertical_headrail.php (super-admin).
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

try {
    $st = $pdo->prepare("DELETE FROM allowance_rows WHERE table_name = 'vertical_headrail'");
    $st->execute();
    echo "Removed {$st->rowCount()} 'vertical_headrail' allowance row(s).\n";
    echo "That table was inert (no formula referenced it) — the headrail cut lives in\n";
    echo "Build Rules (build_variables H_Cut). Re-run to confirm it reports 0.\n";
} catch (Throwable $e) {
    echo "Skipped (allowance_rows not present?): " . $e->getMessage() . "\n";
}
