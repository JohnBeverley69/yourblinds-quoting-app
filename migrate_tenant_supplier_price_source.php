<?php
declare(strict_types=1);

/**
 * One-off tidy: set every TRADE ACCOUNT's copy of a factory-pushed product to the
 * 'supplier' price-source model (its grid is the factory's trade list; the account's
 * price = list − buying discount + margin). This makes trade accounts consistent —
 * everything they buy from the factory reads as a supplier list.
 *
 * SCOPE (deliberately narrow):
 *   - client_id <> factory                              → only trade accounts, never
 *                                                          the factory's own catalogue
 *                                                          (its manufactured products
 *                                                          stay 'our price list').
 *   - COALESCE(NULLIF(source_client_id,0),client_id) = factory
 *                                                       → only products pushed from the
 *                                                          factory; a product the account
 *                                                          sources elsewhere / made itself
 *                                                          is untouched.
 *
 * This CHANGES how those products price (supplier vs own branch in the engine). Run
 * knowingly. Idempotent (already-'supplier' rows are skipped). Guarded on the column.
 *
 * Run as super-admin: /migrate_tenant_supplier_price_source.php
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

$fid = (int) (function_exists('factory_client_id') ? factory_client_id() : 3);

// Column present?
$hasCol = false;
try {
    $c = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'price_source' LIMIT 1");
    $c->execute();
    $hasCol = $c->fetchColumn() !== false;
} catch (Throwable $e) { /* keep false */ }

if (!$hasCol) {
    echo "products.price_source column not present — nothing to do (run migrate_price_source.php first).\n";
    exit;
}

// Preview: how many, and which accounts, will change.
$prev = $pdo->prepare(
    "SELECT c.company_name, COUNT(*) AS n
       FROM products p JOIN clients c ON c.id = p.client_id
      WHERE p.client_id <> ?
        AND COALESCE(NULLIF(p.source_client_id, 0), p.client_id) = ?
        AND (p.price_source IS NULL OR p.price_source <> 'supplier')
   GROUP BY p.client_id, c.company_name
   ORDER BY c.company_name"
);
$prev->execute([$fid, $fid]);
$rows = $prev->fetchAll(PDO::FETCH_ASSOC);

// Apply.
$upd = $pdo->prepare(
    "UPDATE products
        SET price_source = 'supplier'
      WHERE client_id <> ?
        AND COALESCE(NULLIF(source_client_id, 0), client_id) = ?
        AND (price_source IS NULL OR price_source <> 'supplier')"
);
$upd->execute([$fid, $fid]);
$changed = $upd->rowCount();

echo "Factory id: $fid\n\n";
echo "Set to 'supplier' — $changed product(s) across " . count($rows) . " trade account(s):\n";
foreach ($rows as $r) {
    echo sprintf("  %-30s %d\n", $r['company_name'], (int) $r['n']);
}
if (!$rows) echo "  (nothing needed changing — already consistent)\n";
echo "\nThe factory's own products (client_id = $fid) were left as 'our price list'.\n";
echo "Products the accounts source elsewhere were left untouched.\n";
