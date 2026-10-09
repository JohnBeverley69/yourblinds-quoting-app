<?php
declare(strict_types=1);

/**
 * Direct orders (John, 2026-10-09): trade clients who don't quote through the
 * system can place an order straight with us (quote-builder/new_order.php).
 * Adds quotes.direct_order — 1 = an order raised directly (order header, buying
 * prices, one Place order step), 0 = an ordinary quote.
 *
 * Idempotent, super-admin, web-runnable: /migrate_direct_orders.php
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

$exists = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
$exists->execute(['quotes', 'direct_order']);
if ($exists->fetchColumn()) {
    echo "quotes.direct_order already exists — skipped.\n";
} else {
    $pdo->exec('ALTER TABLE quotes ADD COLUMN direct_order TINYINT(1) NOT NULL DEFAULT 0');
    echo "Added quotes.direct_order.\n";
}
// "Sold for" on a direct order (John, 2026-10-09): what the client's own
// customer pays, so the Dashboard can count the sale and its profit.
foreach ([
    'sold_for_amount'  => 'DECIMAL(10,2) NULL',
    'sold_for_inc_vat' => 'TINYINT(1) NULL',
    'sold_for_net'     => 'DECIMAL(10,2) NULL',
    'sold_for_gross'   => 'DECIMAL(10,2) NULL',
] as $col => $def) {
    $exists->execute(['quotes', $col]);
    if ($exists->fetchColumn()) {
        echo "quotes.$col already exists — skipped.\n";
    } else {
        $pdo->exec("ALTER TABLE quotes ADD COLUMN $col $def");
        echo "Added quotes.$col.\n";
    }
}

// Direct orders were first filed as sale_type 'trade', which hid them from a
// client's own Quotes / Orders lists (those show type=retail). Re-file them.
try {
    $n = $pdo->exec("UPDATE quotes SET sale_type = 'retail'
                      WHERE direct_order = 1 AND account_client_id IS NULL AND sale_type = 'trade'");
    echo "Re-filed $n direct order(s) so they show in the client's lists.\n";
} catch (Throwable $e) {
    echo "sale_type back-fill skipped: " . $e->getMessage() . "\n";
}
echo "\nDone. \"New order\" now works for users with Create orders.\n";
