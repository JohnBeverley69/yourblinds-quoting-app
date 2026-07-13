<?php
declare(strict_types=1);

/**
 * Seed: starter worksheet template for Bev Roller Blinds — a single self-
 * contained 102x76mm roll label per blind (stock "roll-102x76"), as opposed to
 * the vertical A4 die-cut sheet. Gives the roller something to print and a
 * layout to rearrange in the Worksheets builder.
 *
 * Insert-if-absent by default (won't clobber a layout you've already built);
 * pass ?force=1 to overwrite the default template.
 *
 * Run via web: /seed_roller_template.php (super-admin).
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

$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

$prod = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev Roller Blinds' LIMIT 1");
$prod->execute([$MASTER]);
$productId = (int) $prod->fetchColumn();
if ($productId === 0) { exit("Could not find product 'Bev Roller Blinds' for client {$MASTER}.\n"); }

$force = isset($_GET['force']) && $_GET['force'] !== '0';

$exists = $pdo->prepare('SELECT id FROM worksheet_templates WHERE product_id = ? ORDER BY is_default DESC, id LIMIT 1');
$exists->execute([$productId]);
$existingId = (int) $exists->fetchColumn();
if ($existingId && !$force) {
    exit("Roller template already exists (id {$existingId}) — left untouched. Re-run with ?force=1 to overwrite.\n");
}

$fld = static fn (string $source, string $caption = '', string $show = 'always'): array =>
    ['source' => $source, 'caption' => $caption, 'show' => $show];
$brk = ['source' => '__break__'];

$layout = [
    'stock'  => 'roll-102x76',
    'header' => ['w' => 102, 'h' => 10, 'fields' => []],   // unused for a roll label; each label is self-contained
    'labels' => [[
        'title' => 'Roller label', 'w' => 102, 'h' => 76, 'fields' => [
            $fld('order:order_no',     'ONO'),
            $fld('order:customer',     ''),
            $fld('order:cust_ref',     'Ref', 'ifvalue'),
            $brk,
            $fld('order:line_no',      ''),
            $fld('order:fabric',       ''),
            $fld('order:colour',       ''),
            $brk,
            $fld('order:size',         'Size'),
            $fld('order:recess_exact', 'Fit'),
            $fld('opt:fascia options', 'Fascia', 'ifvalue'),
            $brk,
            $fld('var:Tube_Cut',       'Tube'),
            $fld('var:Fabric_W',       'Fabric'),
            $fld('var:Fascia_Cut',     'Fascia Cut', 'ifvalue'),
            $fld('var:Fabric_Drop',    'Drop'),
            $brk,
            $fld('order:location',     'Loc', 'ifvalue'),
            $fld('order:notes',        'Notes', 'ifvalue'),
        ],
    ]],
];

$json = json_encode($layout, JSON_UNESCAPED_UNICODE);

if ($existingId) {
    $pdo->prepare('UPDATE worksheet_templates SET name = ?, is_default = 1, layout_json = ? WHERE id = ?')
        ->execute(['Roller label', $json, $existingId]);
    echo "Overwrote roller template id {$existingId} (102x76 roll label).\n";
} else {
    $pdo->prepare('INSERT INTO worksheet_templates (product_id, name, is_default, layout_json) VALUES (?, ?, 1, ?)')
        ->execute([$productId, 'Roller label', $json]);
    echo "Created roller template on product {$productId} (102x76 roll label).\n";
}

echo "Lay it out in Worksheets → Bev Roller Blinds; print via the order Worksheet → Roll label print.\n";
