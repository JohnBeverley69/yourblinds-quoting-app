<?php
declare(strict_types=1);
/**
 * TEMP read-only diagnostic: where does the markup come from for the master's
 * vertical products? Prints the tenant default + every per-system client_markups
 * row. Delete after use.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');

$pdo = db();
$cid = function_exists('factory_client_id') ? factory_client_id() : 3;

$def = $pdo->prepare('SELECT default_price_table_markup_pct, default_options_markup_pct FROM client_settings WHERE client_id = ? LIMIT 1');
$def->execute([$cid]);
$d = $def->fetch(PDO::FETCH_ASSOC) ?: [];
echo "Client {$cid} tenant defaults:\n";
echo "  default_price_table_markup_pct = " . var_export($d['default_price_table_markup_pct'] ?? null, true) . "\n";
echo "  default_options_markup_pct     = " . var_export($d['default_options_markup_pct'] ?? null, true) . "\n\n";

$st = $pdo->prepare(
    "SELECT p.id AS pid, p.name AS product, cm.system_id, s.name AS system_name, cm.markup_percent
       FROM client_markups cm
       JOIN products p ON p.id = cm.product_id
  LEFT JOIN systems  s ON s.id = cm.system_id
      WHERE cm.client_id = ? AND p.name LIKE '%Vertical%'
   ORDER BY p.name, cm.system_id"
);
$st->execute([$cid]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
echo "client_markups rows for Vertical products (client {$cid}):\n";
if (!$rows) { echo "  (none — engine would use the tenant default)\n"; }
foreach ($rows as $r) {
    printf("  product %-24s (id %d)  system %-14s  markup = %s%%\n",
        $r['product'], (int) $r['pid'],
        $r['system_name'] ?? ('#' . ($r['system_id'] ?? 'NULL')),
        rtrim(rtrim(number_format((float) $r['markup_percent'], 2), '0'), '.'));
}
