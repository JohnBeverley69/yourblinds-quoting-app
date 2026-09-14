<?php
declare(strict_types=1);

/**
 * Migration: per-option "face value" flag.
 *
 * Adds product_extra_choices.face_value (1/0, default 1). When 1 (default) the
 * option's price is added at FACE VALUE — the price you set is what's charged.
 * When 0 the option is treated as a supplier LIST add-on: on a supplier-priced
 * product it is run through the product's buying discount + markup with the base
 * (e.g. an Infusions "taped +20%" that's part of the supplier's own list).
 *
 * Default 1 means every existing option becomes face value — so on supplier
 * products, flat charges (e.g. a £2 split tilt) stop being marked up. Any genuine
 * supplier list add-on must be switched to face_value = 0 on the options page.
 * (Own-price products already add options at face value, so the flag is a no-op
 * there.)
 *
 * Additive + idempotent. Web-runnable: /migrate_extra_face_value.php (super-admin).
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

$n = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE()
        AND table_name = 'product_extra_choices'
        AND column_name = 'face_value'"
)->fetchColumn();
if ((int) $n === 0) {
    $pdo->exec("ALTER TABLE product_extra_choices ADD COLUMN face_value TINYINT(1) NOT NULL DEFAULT 1 AFTER price_per_metre");
    echo "Added product_extra_choices.face_value (default 1 = face value).\n";
} else {
    echo "product_extra_choices.face_value already present — skipped.\n";
}

echo "\nDone. All options default to face value; flag genuine supplier list\n";
echo "add-ons as face_value = 0 to run them through the product discount + markup.\n";
