<?php
declare(strict_types=1);
/**
 * TEMP read-only diagnostic: dump the vertical systems + the system-name strings
 * stored inside the build_variables rules, so we can see exactly what needs
 * re-keying after the system rename. Deletes nothing. Super-admin.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();

$prod = $pdo->query("SELECT id, name FROM products WHERE client_id = 3 AND name = 'Bev Vertical Blinds' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$prod) { echo "Bev Vertical Blinds not found on client 3.\n"; exit; }
$pid = (int) $prod['id'];
echo "Product: {$prod['name']} (id {$pid})\n\n";

echo "=== product_systems (live names) ===\n";
$s = $pdo->prepare('SELECT id, name, active, is_default, source_system_id FROM product_systems WHERE product_id = ? ORDER BY sort_order, id');
$s->execute([$pid]);
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
    printf("  id=%-5d name=%-24s active=%s default=%s src=%s\n", $r['id'], "'" . $r['name'] . "'", $r['active'], $r['is_default'], $r['source_system_id'] ?? '-');
}

echo "\n=== build_variables: system-column cell values in rows_json ===\n";
$bv = $pdo->prepare('SELECT id, name, columns_json, rows_json FROM build_variables WHERE product_id = ? ORDER BY seq, id');
$bv->execute([$pid]);
foreach ($bv->fetchAll(PDO::FETCH_ASSOC) as $v) {
    $cols = json_decode((string) $v['columns_json'], true) ?: [];
    $rows = json_decode((string) $v['rows_json'], true) ?: [];
    // Find the column whose ref/label is 'system'.
    $sysIdx = null;
    foreach ($cols as $i => $c) {
        $ref = strtolower((string) ($c['ref'] ?? ''));
        $lbl = strtolower((string) ($c['label'] ?? ''));
        if ($ref === 'system' || $lbl === 'system') { $sysIdx = $i; break; }
    }
    echo "\n  [{$v['name']}]  columns=" . implode(',', array_map(fn($c) => ($c['label'] ?? $c['ref'] ?? '?'), $cols)) . "\n";
    if ($sysIdx === null) { echo "    (no 'system' column)\n"; continue; }
    $seen = [];
    foreach ($rows as $row) {
        $cell = trim((string) ($row['cells'][$sysIdx] ?? ''));
        $seen[$cell === '' ? '(blank/any)' : $cell] = ($seen[$cell === '' ? '(blank/any)' : $cell] ?? 0) + 1;
    }
    foreach ($seen as $cell => $n) echo "    system cell '{$cell}'  ×{$n} row(s)\n";
}
echo "\nDone (read-only).\n";
