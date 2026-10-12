<?php
declare(strict_types=1);

/**
 * Give tenants' pushed option choices their master's number box.
 *
 * A choice can carry its own number box (product_extra_choices.length_input_label):
 * "Qty" on a per-unit accessory, "Pelmet size (mm)" on a custom pelmet. The
 * catalogue push never copied it (fixed alongside this tool), so a tenant's copy
 * had the per-unit price but no box. Ordering as ABC Blinds, ticking Swivel Bracket
 * Standard on Forest Wood popped "Enter a quantity" with nowhere to type one, and
 * Custom Size had no size box (John, 2026-10-12).
 *
 * Copies the label from each pushed choice's master (via source_choice_id) where it
 * differs. Says what it would do first.
 *
 * Super-admin, web-runnable: /setup/tools/sync_choice_number_boxes.php  (?apply=1 to write)
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__, 2) . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$apply = PHP_SAPI === 'cli' ? in_array('--apply', $argv ?? [], true) : !empty($_GET['apply']);

$rows = $pdo->query(
    "SELECT t.id, t.label, t.length_input_label AS now_lbl, s.length_input_label AS master_lbl,
            te.name AS opt, tp.name AS product, c.company_name AS company
       FROM product_extra_choices t
       JOIN product_extra_choices s ON s.id = t.source_choice_id
       JOIN product_extras te       ON te.id = t.product_extra_id
       JOIN products tp             ON tp.id = te.product_id
       JOIN clients c               ON c.id = tp.client_id
      WHERE NOT (t.length_input_label <=> s.length_input_label)
   ORDER BY c.company_name, tp.name, te.name, t.label"
)->fetchAll(PDO::FETCH_ASSOC);

$upd = $pdo->prepare('UPDATE product_extra_choices SET length_input_label = ? WHERE id = ?');
foreach ($rows as $r) {
    $from = $r['now_lbl'] === null ? '(no box)' : '"' . $r['now_lbl'] . '"';
    $to   = $r['master_lbl'] === null ? '(no box)' : '"' . $r['master_lbl'] . '"';
    echo ($apply ? 'FIXED  ' : 'WOULD  ') . "{$r['company']} · {$r['product']} · {$r['opt']} = {$r['label']}: $from → $to\n";
    if ($apply) $upd->execute([$r['master_lbl'], (int) $r['id']]);
}
echo "\n" . count($rows) . ' choice(s) ' . ($apply ? 'updated' : 'to update') . ".\n";
if (!$apply && $rows) echo "Run again with ?apply=1 to make the changes.\n";
