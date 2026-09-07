<?php
declare(strict_types=1);

/**
 * Migration: wholesale-price capture on order lines (Phase 2A).
 *
 * The pricing engine already COMPUTES the wholesale figures Beverley needs to
 * bill its trade accounts, but the quote save paths discard them. This adds the
 * columns so every line placed from now on records:
 *
 *   quote_items:
 *     trade_price_per_blind   — base trade price BEFORE the account's trade discount
 *     trade_discount_percent  — the account's buying discount %
 *     trade_discount_amount   — £ off the base per blind
 *       (base_price already stores the DISCOUNTED base — these three are the
 *        "trade → discount → net" breakdown a wholesale invoice shows)
 *
 *   quote_item_extras:
 *     trade_amount            — the option's WHOLESALE amount (pre-markup, net of
 *                               any Components promo); amount_applied stays RETAIL
 *     promo_discount_percent  — Components promo % on the option
 *     promo_discount_amount   — £ off the option
 *
 * All nullable and additive — no existing price changes. Pages/engine probe for
 * these columns, so an un-migrated DB behaves exactly as before. The app is
 * pre-launch (no real historical orders), so capture is forward-only.
 *
 * Idempotent. Run as super-admin: /migrate_wholesale_capture.php
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

$colExists = static function (string $t, string $c) use ($pdo): bool {
    $s = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1"
    );
    $s->execute([$t, $c]);
    return $s->fetchColumn() !== false;
};

$ops = [];

// quote_items — per-blind trade breakdown.
$qiCols = [
    'trade_price_per_blind'  => 'DECIMAL(10,2) NULL AFTER base_price',
    'trade_discount_percent' => 'DECIMAL(6,2)  NULL AFTER trade_price_per_blind',
    'trade_discount_amount'  => 'DECIMAL(10,2) NULL AFTER trade_discount_percent',
];
foreach ($qiCols as $col => $def) {
    if (!$colExists('quote_items', $col)) {
        $pdo->exec("ALTER TABLE quote_items ADD COLUMN $col $def");
        $ops[] = "Added quote_items.$col.";
    } else {
        $ops[] = "quote_items.$col already exists — skipped.";
    }
}

// quote_item_extras — per-option wholesale amount + promo breakdown.
$qieCols = [
    'trade_amount'           => 'DECIMAL(10,2) NULL AFTER amount_applied',
    'promo_discount_percent' => 'DECIMAL(6,2)  NULL AFTER trade_amount',
    'promo_discount_amount'  => 'DECIMAL(10,2) NULL AFTER promo_discount_percent',
];
foreach ($qieCols as $col => $def) {
    if (!$colExists('quote_item_extras', $col)) {
        $pdo->exec("ALTER TABLE quote_item_extras ADD COLUMN $col $def");
        $ops[] = "Added quote_item_extras.$col.";
    } else {
        $ops[] = "quote_item_extras.$col already exists — skipped.";
    }
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nOrder lines now capture the wholesale (trade) price + discount breakdown for the\n";
echo "Phase 2 wholesale A/R (delivery notes / invoices / statements). Additive only —\n";
echo "no existing prices change.\n";
