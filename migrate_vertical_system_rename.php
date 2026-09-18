<?php
declare(strict_types=1);

/**
 * One-time fix: the Bev Vertical Blinds systems were renamed on the product
 * (SlimLine→SlimLine Vert, Vogue→Vogue Vert, Nova→Nova Vert, No Thrills→No Frills
 * Vert) but the build rules still held the OLD names, so they stopped matching.
 * Re-label the System cells to the new names, then drop the No Frills centre-draw
 * head-rail rows (No Frills = Slimline wand WITHOUT a centre option).
 *
 * Idempotent. Super-admin, web-runnable: /migrate_vertical_system_rename.php
 * (delete this file after it has run.)
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
require_once __DIR__ . '/_partials/build_var_rename_system.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pid = (int) ($pdo->query("SELECT id FROM products WHERE client_id = 3 AND name = 'Bev Vertical Blinds' LIMIT 1")->fetchColumn());
if ($pid <= 0) { echo "Bev Vertical Blinds not found.\n"; exit; }
echo "Bev Vertical Blinds = product {$pid}\n\n";

// 1) Re-label stale system names in the build rules → the live product names.
$map = [
    'SlimLine'   => 'SlimLine Vert',
    'Vogue'      => 'Vogue Vert',
    'Nova'       => 'Nova Vert',
    'No Thrills' => 'No Frills Vert',
];
echo "1) Re-labelling system cells in build rules:\n";
foreach ($map as $old => $new) {
    $n = build_var_rename_system($pdo, $pid, $old, $new);
    echo "   '{$old}' -> '{$new}'  ({$n} cell(s) updated)\n";
}

// 2) Drop the No Frills centre-draw head-rail rows (wand without centre).
echo "\n2) Removing No Frills centre-draw rows from H_Cut:\n";
$v = $pdo->prepare("SELECT id, columns_json, rows_json FROM build_variables WHERE product_id = ? AND name = 'H_Cut' LIMIT 1");
$v->execute([$pid]);
$hc = $v->fetch(PDO::FETCH_ASSOC);
if (!$hc) {
    echo "   H_Cut not found — skipped.\n";
} else {
    $cols = json_decode((string) $hc['columns_json'], true) ?: [];
    $rows = json_decode((string) $hc['rows_json'], true) ?: [];
    $sysIdx = bv_system_col_index($cols);
    $wandIdx = null;
    foreach ($cols as $i => $c) { if (strpos(strtolower((string) ($c['label'] ?? $c['ref'] ?? '')), 'wand') !== false) { $wandIdx = (int) $i; break; } }
    $before = count($rows);
    if ($sysIdx !== null && $wandIdx !== null) {
        $rows = array_values(array_filter($rows, static function ($r) use ($sysIdx, $wandIdx) {
            $sys  = mb_strtolower(trim((string) ($r['cells'][$sysIdx] ?? '')));
            $wand = strtolower(trim((string) ($r['cells'][$wandIdx] ?? '')));
            $isNoFrills = $sys === 'no frills vert';
            $isCentre   = in_array($wand, ['centre left', 'centre right', 'center left', 'center right'], true);
            return !($isNoFrills && $isCentre);   // drop No Frills centre rows
        }));
        $removed = $before - count($rows);
        if ($removed > 0) {
            $pdo->prepare('UPDATE build_variables SET rows_json = ? WHERE id = ?')->execute([json_encode($rows), (int) $hc['id']]);
        }
        echo "   Removed {$removed} centre row(s). H_Cut now has " . count($rows) . " rows.\n";
    } else {
        echo "   Could not locate System/Wand columns — skipped.\n";
    }
}

echo "\nDone. Check Factory → Build rules: all four systems (incl. No Frills Vert) should now show their cuts.\n";
