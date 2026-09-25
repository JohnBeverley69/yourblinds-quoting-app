<?php
declare(strict_types=1);

/**
 * Migration: generic "width source" flag on option groups.
 *
 * Adds product_extras.is_width_source (0/1). When an option is flagged, the
 * number the salesperson types into it (its length_input_label value) REPLACES
 * the blind's ordered width for any width-table (extra_choice_price_rows) price
 * lookup on that line — so e.g. a manual "Fascia width" can price a fascia
 * wider than the blind. Used by the roller Fascia Sizing flow so the fascia
 * width can live in its own option (a child of Fascia Sizing) instead of being
 * welded to the Fascia Options group. Generic + additive: no flagged option (or
 * a blank value) leaves every lookup on the ordered width, exactly as before.
 *
 * Additive + idempotent. Web-runnable: /migrate_extra_width_source.php (super-admin).
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$n = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE()
        AND table_name = 'product_extras'
        AND column_name = 'is_width_source'"
)->fetchColumn();
if ((int) $n === 0) {
    $pdo->exec("ALTER TABLE product_extras ADD COLUMN is_width_source TINYINT(1) NOT NULL DEFAULT 0 AFTER length_input_label");
    echo "Added product_extras.is_width_source.\n";
} else {
    echo "product_extras.is_width_source already present — skipped.\n";
}

echo "\nDone. Flag an option to make its typed value the width for width-table lookups.\n";
