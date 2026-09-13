<?php
declare(strict_types=1);

/**
 * One-off: clear the obsolete label fields on the Bev Roller Blinds worksheet
 * template. The roller roll label is now a fixed boxed grid + cut band (built
 * into worksheet-print.php); the template's old hand-built full-label fields
 * were duplicating that content in the new "designer extras" zone. Emptying the
 * field list leaves the bottom clean (cut band + notes) and ready to design.
 * Keeps stock / qr / size. Idempotent. Delete after running.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth/middleware.php';
requireSuperAdmin();

$pdo = db();
$pid = 5; // Bev Roller Blinds

$ts = $pdo->prepare('SELECT id, layout_json FROM worksheet_templates WHERE product_id = ? ORDER BY is_default DESC, id LIMIT 1');
$ts->execute([$pid]);
$row = $ts->fetch(PDO::FETCH_ASSOC);
if (!$row) { exit('No worksheet template for product ' . $pid); }

$layout = json_decode((string) $row['layout_json'], true) ?: [];
$before = (isset($layout['labels'][0]['fields']) && is_array($layout['labels'][0]['fields']))
    ? count($layout['labels'][0]['fields']) : 0;

if (!isset($layout['labels']) || !is_array($layout['labels']) || !isset($layout['labels'][0])) {
    $layout['labels'] = [['title' => 'Label', 'w' => 101, 'h' => 152, 'fields' => []]];
} else {
    $layout['labels'][0]['fields'] = [];
}

$upd = $pdo->prepare('UPDATE worksheet_templates SET layout_json = ? WHERE id = ?');
$upd->execute([json_encode($layout), (int) $row['id']]);

header('Content-Type: text/plain; charset=utf-8');
echo "Cleared {$before} label field(s) from roller worksheet template #{$row['id']} (product {$pid}).\n";
echo "The roll-label bottom (designer extras) is now empty — cut band + notes only. Design it in Worksheets when ready.\n";
