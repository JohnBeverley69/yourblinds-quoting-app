<?php
declare(strict_types=1);

/**
 * Backfill: percent surcharges are supplier list add-ons — mark them face_value = 0.
 *
 * migrate_extra_face_value.php defaulted every option to face_value = 1 (added at
 * the price set). That's right for flat £ charges (a £2 split tilt stays £2), but
 * WRONG for a PERCENT surcharge (e.g. Arena "Standard Tape +20%", "Blackout +10%"):
 * a percent is inherently a % of the supplier's LIST price and must ride with the
 * base through the buying discount + markup on a supplier-priced product. Left at
 * face value it prices wrong (the code's Infusions example: £76.21 vs £74.89).
 *
 * This sets face_value = 0 on every choice with a non-zero price_percent, so those
 * surcharges go back through the discount+markup. Flat £ options stay face value.
 * (face_value is a no-op on own-price products, so scoping by percent alone is safe.)
 *
 * Idempotent. Web-runnable: /migrate_face_value_percent_backfill.php (super-admin).
 * Prereq: run migrate_extra_face_value.php first (adds the column).
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

try { $pdo->query('SELECT face_value FROM product_extra_choices LIMIT 1'); }
catch (Throwable $e) { exit("face_value column missing — run /migrate_extra_face_value.php first.\n"); }

// Show what will change (for the log).
$list = $pdo->query(
    "SELECT c.id, c.label, c.price_percent, e.name AS extra_name, p.name AS product
       FROM product_extra_choices c
       JOIN product_extras e ON e.id = c.product_extra_id
       JOIN products p       ON p.id = e.product_id
      WHERE c.price_percent IS NOT NULL AND c.price_percent <> 0
        AND c.face_value = 1
   ORDER BY p.name, e.name, c.label"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($list as $r) {
    echo sprintf("  %-22s %-22s %-22s +%s%%\n",
        substr((string) $r['product'], 0, 22), substr((string) $r['extra_name'], 0, 22),
        substr((string) $r['label'], 0, 22), rtrim(rtrim(number_format((float) $r['price_percent'], 2, '.', ''), '0'), '.'));
}

$n = $pdo->exec(
    "UPDATE product_extra_choices
        SET face_value = 0
      WHERE price_percent IS NOT NULL AND price_percent <> 0 AND face_value = 1"
);

echo "\nSet face_value = 0 on {$n} percent surcharge(s) — they run through the discount + markup again.\n";
echo "Flat-£ options stay face value. Check any per-metre / width-table supplier add-ons by hand.\n";
