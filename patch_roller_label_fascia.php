<?php
declare(strict_types=1);

/**
 * Patch: add the missing "Fascia Cut" field (var:Fascia_Cut) to the roller
 * worksheet/label template, WITHOUT disturbing the rest of the hand-built
 * layout. Inserted right after the other cut fields, show='ifvalue' so it only
 * prints for cassette / Grip Fix fascias (blank for None).
 *
 * Idempotent — does nothing if the field is already there.
 * Run via web: /patch_roller_label_fascia.php (super-admin).
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

$pid = (int) $pdo->query("SELECT id FROM products WHERE client_id = {$MASTER} AND name = 'Bev Roller Blinds' LIMIT 1")->fetchColumn();
if ($pid === 0) { exit("No master 'Bev Roller Blinds'.\n"); }

$row = $pdo->prepare('SELECT id, layout_json FROM worksheet_templates WHERE product_id = ? ORDER BY is_default DESC, id LIMIT 1');
$row->execute([$pid]);
$tpl = $row->fetch(PDO::FETCH_ASSOC);
if (!$tpl) { exit("No worksheet template for roller product {$pid}.\n"); }

$layout = json_decode((string) $tpl['layout_json'], true);
if (!is_array($layout) || empty($layout['labels'][0])) { exit("Template layout has no label to patch.\n"); }

$fields = $layout['labels'][0]['fields'] ?? [];
foreach ($fields as $f) {
    if (($f['source'] ?? '') === 'var:Fascia_Cut') { exit("Fascia Cut already on the label — nothing to do.\n"); }
}

// Insert after the last of the cut fields (Fabric_Drop / Fabric_W / Tube_Cut).
$after = -1;
foreach ($fields as $i => $f) {
    if (in_array($f['source'] ?? '', ['var:Fabric_Drop', 'var:Fabric_W', 'var:Tube_Cut'], true)) $after = $i;
}
$new = ['source' => 'var:Fascia_Cut', 'caption' => 'Fascia Cut', 'show' => 'ifvalue'];
if ($after >= 0) { array_splice($fields, $after + 1, 0, [$new]); } else { $fields[] = $new; }

$layout['labels'][0]['fields'] = $fields;
$pdo->prepare('UPDATE worksheet_templates SET layout_json = ? WHERE id = ?')
    ->execute([json_encode($layout, JSON_UNESCAPED_UNICODE), (int) $tpl['id']]);

echo "Added 'Fascia Cut' (var:Fascia_Cut, if-value) to roller template {$tpl['id']} on product {$pid}.\n";
echo "It prints the fascia cut for cassette / Grip Fix fascias, blank for None.\n";
