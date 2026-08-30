<?php
declare(strict_types=1);

/**
 * One-off cleanup: remove the junk "Hem" build variable from Bev Vertical Blinds.
 *
 * "Hem" is a stale leftover (flat Drop − 55, no Recess/Exact split) — it is NOT
 * used by the vertical blind or the vertical headrail. The real vertical fabric
 * drop reference is "Hem_To_Hem" (Recess 55 / Exact 45), which stays.
 *
 * Safe by default: lists what it would delete. Pass ?confirm=1 to actually
 * delete. Super-admin only. Delete this file after it has run.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth/middleware.php';
requireSuperAdmin();

header('Content-Type: text/plain; charset=utf-8');

$pdo    = db();
$MASTER = function_exists('factory_client_id') ? (int) factory_client_id() : 0;

// Resolve the Bev Vertical Blinds master product.
$pid = 0;
$ps = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev Vertical Blinds' LIMIT 1");
$ps->execute([$MASTER]);
$pid = (int) ($ps->fetchColumn() ?: 0);

if ($pid === 0) { echo "Bev Vertical Blinds product not found (master client #{$MASTER}).\n"; exit; }

// Show every 'Hem' variable across products first, so we can see it's isolated.
echo "All build variables named 'Hem':\n";
$all = $pdo->query("SELECT bv.id, bv.product_id, p.name AS product, bv.rows_json
                      FROM build_variables bv JOIN products p ON p.id = bv.product_id
                     WHERE bv.name = 'Hem' ORDER BY bv.product_id");
foreach ($all->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  id={$r['id']}  product_id={$r['product_id']}  ({$r['product']})  rows={$r['rows_json']}\n";
}
echo "\nTarget: name='Hem' on product_id={$pid} (Bev Vertical Blinds).\n";

$confirm = (string) ($_GET['confirm'] ?? '') === '1';
if (!$confirm) {
    echo "\nDRY RUN — nothing deleted. Re-run with ?confirm=1 to delete the target.\n";
    exit;
}

$del = $pdo->prepare("DELETE FROM build_variables WHERE product_id = ? AND name = 'Hem'");
$del->execute([$pid]);
echo "\nDeleted {$del->rowCount()} row(s). 'Hem' removed from Bev Vertical Blinds.\n";
echo "You can now delete this cleanup_vertical_hem.php file.\n";
