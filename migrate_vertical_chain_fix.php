<?php
declare(strict_types=1);
/**
 * One-time: fix the vertical CH_L (tilt chain) rule so a fit height at/below 1500
 * no longer yields a NEGATIVE chain. Change the condition Fit_height > 0 to
 * Fit_height > 1500 in the Corded row (below that, it uses Drop * 1.5). Surgical:
 * only touches the CH_L variable's condition, nothing else. Idempotent.
 * Super-admin: /migrate_vertical_chain_fix.php   (delete after running.)
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pid = (int) $pdo->query("SELECT id FROM products WHERE client_id = 3 AND name = 'Bev Vertical Blinds' LIMIT 1")->fetchColumn();
if ($pid <= 0) { echo "Bev Vertical Blinds not found.\n"; exit; }

$v = $pdo->prepare("SELECT id, rows_json FROM build_variables WHERE product_id = ? AND name = 'CH_L' LIMIT 1");
$v->execute([$pid]);
$row = $v->fetch(PDO::FETCH_ASSOC);
if (!$row) { echo "CH_L not found on product {$pid}.\n"; exit; }

$rows = json_decode((string) $row['rows_json'], true) ?: [];
$changed = 0;
foreach ($rows as &$r) {
    if (isset($r['result']) && strpos((string) $r['result'], 'Fit_height > 0') !== false) {
        $before = (string) $r['result'];
        $r['result'] = str_replace('Fit_height > 0', 'Fit_height > 1500', $before);
        echo "  '{$before}'\n  -> '{$r['result']}'\n";
        $changed++;
    }
}
unset($r);
if ($changed > 0) {
    $pdo->prepare('UPDATE build_variables SET rows_json = ? WHERE id = ?')->execute([json_encode($rows), (int) $row['id']]);
    echo "\nUpdated {$changed} CH_L row(s).\n";
} else {
    echo "Nothing to change (already fixed or no matching row).\n";
}
echo "Cord (C_L = CH_L + 2*Width) follows automatically — no change needed.\n";
