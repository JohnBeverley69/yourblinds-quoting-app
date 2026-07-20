<?php
declare(strict_types=1);

/**
 * Migration: is a product's price table OURS or the SUPPLIER's?
 *
 * Two pricing models sit side by side in the catalogue and the system had no
 * way to tell them apart, so the catalogue push guessed — and got it wrong:
 *
 *   Our price list      — blinds we manufacture. We hold a cost grid and a
 *                         selling grid. Selling = cost + a percentage + labour
 *                         + overhead, so it can NOT be re-derived by a formula;
 *                         both grids are entered. The table already holds the
 *                         trade price, so it pushes to a tenant untouched.
 *
 *   Supplier price list — blinds we buy in. The table holds THEIR standard
 *                         trade list. Our price = list − our buying discount +
 *                         our margin, so the push must apply
 *                         (1 - discount) x (1 + markup).
 *
 * Without this flag the push applied the discount/markup factor to everything.
 * On an in-house product carrying a 100% RETAIL markup (verticals, rollers)
 * that doubled the tenant's price — £18.92 landed as £37.82.
 *
 *   products.price_source  VARCHAR(10) NOT NULL DEFAULT 'own'  -- 'own'|'supplier'
 *
 * Backfill: a buying discount only ever exists on a bought-in product, so any
 * product with a discount % > 0 is 'supplier'; everything else is 'own'. That
 * reproduces the correct behaviour for the products already pushed (Forest
 * Wood, Embassy Faux Wood) and un-doubles the in-house ones.
 *
 * Run via web: /migrate_price_source.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colExists = static function (string $t, string $c) use ($pdo): bool {
    try { $pdo->query("SELECT `$c` FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

$fresh = false;
if (!$colExists('products', 'price_source')) {
    $pdo->exec("ALTER TABLE products ADD COLUMN price_source VARCHAR(10) NOT NULL DEFAULT 'own'");
    echo "  Added products.price_source (default 'own').\n";
    $fresh = true;
} else {
    echo "  products.price_source already exists — skipped.\n";
}

// Backfill only on the first run, so a later re-run can never stomp a choice
// someone has since made by hand on the product screen.
if ($fresh) {
    $n = $pdo->exec(
        "UPDATE products
            SET price_source = 'supplier'
          WHERE id IN (SELECT DISTINCT product_id
                         FROM client_discounts
                        WHERE discount_percent > 0)"
    );
    echo "  Marked " . (int) $n . " product(s) as 'supplier' (they carry a buying discount).\n";
    echo "  Everything else stays 'own' — its table is already our selling price.\n";
} else {
    echo "  Backfill skipped — column pre-existed, leaving current choices alone.\n";
}

echo "\nDone. Set it per product on its Edit screen (\"Pricing source\").\n";
echo "Re-push affected tenants afterwards so their price lists pick up the fix.\n";
