<?php
declare(strict_types=1);

/**
 * One-time data fix: make Beverley's products INHERIT the tenant-default markup.
 *
 * As the manufacturer, Beverley has no "markup" as such — the price-table base IS
 * the trade price it sells at, so the sell price should equal the base (0 markup).
 * But an earlier per-system markup migration copied each product's old single
 * markup (100%) onto every system row, leaving stale `client_markups` OVERRIDE
 * rows that beat the tenant default (see pe_markup_for_system: a row > 0 wins).
 *
 * The app represents "inherit the default" as the ABSENCE of a client_markups row
 * (admin/products/edit.php deletes the row when you save 0). So this deletes every
 * markup override row for the factory client, so all its products derive markup
 * from Settings (default_price_table_markup_pct — set to 0) and only a value typed
 * on a product's Pricing-per-system screen becomes an override again.
 *
 * Scope: the FACTORY client only (client_markups.client_id = factory). Other
 * tenants' own/mirrored markups are untouched. Discounts (client_discounts) are
 * left alone. Idempotent (re-running finds nothing left to delete). Does NOT
 * re-price quotes already saved — their line prices are stored; re-save a line to
 * re-price it.
 *
 * Run via web: /migrate_inherit_markups.php (super-admin).
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

set_exception_handler(function (Throwable $e) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(1);
});

$factory = function_exists('factory_client_id') ? factory_client_id() : 3;

// Show what's there first (so the run is auditable).
$before = $pdo->prepare(
    "SELECT p.name AS product, cm.system_id, cm.markup_percent
       FROM client_markups cm
       JOIN products p ON p.id = cm.product_id
      WHERE cm.client_id = ?
   ORDER BY p.name, cm.system_id"
);
$before->execute([$factory]);
$rows = $before->fetchAll(PDO::FETCH_ASSOC);

echo "Factory client {$factory}: {$before->rowCount()} markup override row(s) before.\n";
foreach ($rows as $r) {
    printf("  %-28s system %-6s markup %s%%\n",
        $r['product'],
        $r['system_id'] === null ? 'NULL' : (string) $r['system_id'],
        rtrim(rtrim(number_format((float) $r['markup_percent'], 2), '0'), '.'));
}

// Delete them all → every product inherits default_price_table_markup_pct.
$del = $pdo->prepare('DELETE FROM client_markups WHERE client_id = ?');
$del->execute([$factory]);
$n = $del->rowCount();

// Report the resulting default the products now inherit.
$defSt = $pdo->prepare('SELECT default_price_table_markup_pct FROM client_settings WHERE client_id = ? LIMIT 1');
$defSt->execute([$factory]);
$default = (float) ($defSt->fetchColumn() ?: 0);

echo "\nDeleted {$n} override row(s). All factory products now inherit the Settings default: "
   . rtrim(rtrim(number_format($default, 2), '0'), '.') . "%.\n";
echo "New quotes price at the trade price (base) with no markup. Re-save any existing quote line to re-price it.\n";
