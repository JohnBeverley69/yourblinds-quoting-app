<?php
declare(strict_types=1);

/**
 * One-off recovery: put the roller worksheet label's FIELD LIST back after it was
 * emptied, WITHOUT touching the frame the user set (101×152 label, 20mm QR, font
 * sizes). The distinctive boxed roller label is drawn by code (worksheet-print
 * rollerLabelHtml); this restores the option/detail field list that feeds the
 * label's option area.
 *
 * Snapshots the current layout into worksheet_template_versions first (so this is
 * itself undoable), then writes only labels[0].fields — stock, qr, header and the
 * label's w/h/fs/lh are preserved as-is.
 *
 * Web-runnable: /restore_roller_label_fields.php (super-admin). Idempotent-ish —
 * safe to re-run (it just re-sets the field list).
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once __DIR__ . '/_partials/order_stage.php';   // for os_factory_id()
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

$prod = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev Roller Blinds' LIMIT 1");
$prod->execute([$MASTER]);
$productId = (int) $prod->fetchColumn();
if ($productId === 0) { exit("Bev Roller Blinds not found for client {$MASTER}.\n"); }

$t = $pdo->prepare('SELECT id, name, layout_json FROM worksheet_templates WHERE product_id = ? ORDER BY is_default DESC, id LIMIT 1');
$t->execute([$productId]);
$tpl = $t->fetch(PDO::FETCH_ASSOC);
if (!$tpl) { exit("No roller worksheet template found — build one first, or run /seed_roller_template.php.\n"); }

$layout = json_decode((string) $tpl['layout_json'], true);
if (!is_array($layout)) $layout = ['stock' => 'roll-102x76', 'labels' => [['title' => 'Roller label', 'w' => 101, 'h' => 152, 'fields' => []]]];
if (empty($layout['labels']) || !is_array($layout['labels'][0])) {
    $layout['labels'] = [['title' => 'Roller label', 'w' => 101, 'h' => 152, 'fields' => []]];
}

// The designed roller field list (from seed_roller_template.php), plus the QR.
$fld = static fn (string $source, string $caption = '', string $show = 'always'): array => ['source' => $source, 'caption' => $caption, 'show' => $show];
$brk = ['source' => '__break__'];
$fields = [
    $fld('order:order_no',   'ONO'),
    $fld('order:order_date', '', 'ifvalue'),
    $fld('order:customer',   ''),
    $fld('order:cust_ref',   'Ref', 'ifvalue'),
    $brk,
    $fld('order:line_no',    ''),
    $fld('order:location',   '', 'ifvalue'),
    $fld('order:fabric',     ''),
    $fld('order:colour',     ''),
    $fld('opt:fabric_roll',  'Roll', 'ifvalue'),
    $brk,
    $fld('order:size',         'Size'),
    $fld('order:recess_exact', 'Fit'),
    $fld('opt:fascia_options', 'Fascia', 'ifvalue'),
    $brk,
    $fld('var:Tube_Cut',     'Tube'),
    $fld('var:Fabric_W',     'Fab W'),
    $fld('var:Fabric_Drop',  'Drop'),
    $fld('var:Fascia_Cut',   'Fascia Cut', 'ifvalue'),
    $fld('var:Chain_Length', 'Chain L', 'ifvalue'),
    $brk,
    $fld('opt:control_options', 'Op'),
    $fld('opt:control_side',    'Side', 'ifvalue'),
    $fld('opt:chain_type',      'Chain', 'ifvalue'),
    $fld('opt:mech_colour',     'Mech', 'ifvalue'),
    $fld('opt:remote_options',  'Remote', 'ifvalue'),
    $brk,
    $fld('opt:bottom_bar_options',                'Btm Bar', 'ifvalue'),
    $fld('opt:unishade_bottom_bar_colour',        '', 'ifvalue'),
    $fld('opt:senses_bottom_bar_colour',          '', 'ifvalue'),
    $fld('opt:unishade_end_cap_colours',          'BB EndCap', 'ifvalue'),
    $fld('opt:senses_bottom_bar_end_cap_colours', 'BB EndCap', 'ifvalue'),
    $brk,
    $fld('opt:senses_profile_colour', 'Profile', 'ifvalue'),
    $fld('opt:ll_profile_colour',     'Profile', 'ifvalue'),
    $fld('opt:senses_end_cap_colour', 'EndCap', 'ifvalue'),
    $fld('opt:ll_end_cap_colour',     'EndCap', 'ifvalue'),
    $brk,
    $fld('opt:scallops_and_trims', 'Scallop', 'ifvalue'),
    $fld('opt:braid_colour',       'Braid', 'ifvalue'),
    $fld('opt:pole',               'Pole', 'ifvalue'),
    $fld('opt:optional_extras',    'Extra', 'ifvalue'),
    $brk,
    $fld('order:notes', 'Notes', 'ifvalue'),
    ['source' => 'qr', 'caption' => '', 'show' => 'always', 'align' => 'left'],
];

// Snapshot the current layout first (undoable), if the history table is there.
try {
    $cnt = 0; foreach (($layout['labels'][0]['fields'] ?? []) as $f) { $s = is_array($f) ? (string)($f['source'] ?? '') : ''; if ($s !== '' && $s !== '__break__') $cnt++; }
    $pdo->prepare(
        'INSERT INTO worksheet_template_versions (template_id, product_id, name, layout_json, field_count, saved_by_user_id)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([(int) $tpl['id'], $productId, (string) $tpl['name'], (string) $tpl['layout_json'], $cnt, null]);
} catch (Throwable $e) { /* history table absent — proceed */ }

// Restore fields; keep the frame (stock / qr / header / label sizes) exactly.
$layout['labels'][0]['fields'] = $fields;
$json = json_encode($layout, JSON_UNESCAPED_UNICODE);
$pdo->prepare('UPDATE worksheet_templates SET layout_json = ? WHERE id = ?')->execute([$json, (int) $tpl['id']]);

$kept = $layout['labels'][0];
echo "Restored " . (count($fields) - 1) . " field(s) + QR to the roller label (template id {$tpl['id']}).\n";
echo "Frame kept: stock=" . ($layout['stock'] ?? '?') . ", label " . ($kept['w'] ?? '?') . "×" . ($kept['h'] ?? '?') . "mm, qr=" . ($layout['qr'] ?? '?') . "mm.\n";
echo "Check it in Worksheets -> Bev Roller Blinds, and print a roll label to confirm.\n";
