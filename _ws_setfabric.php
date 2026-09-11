<?php
declare(strict_types=1);
/**
 * TEMP one-time setter: install a correct worksheet template for "Bev Vertical
 * Fabrics Only" — header carries ORDER-level fields only; a single cutting label
 * carries the fabric-only build vars (Fabric_Cut, Metres, Vanes) + line details,
 * one_per_line=true. Sizes copied from the Vertical Blinds die-cut template.
 * Read+write, super-admin. Delete after use.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');

$pdo = db();
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

$p = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev Vertical Fabrics Only' LIMIT 1");
$p->execute([$MASTER]);
$pid = (int) $p->fetchColumn();
if ($pid === 0) { exit("Product 'Bev Vertical Fabrics Only' not found for client {$MASTER}.\n"); }
echo "Product id: {$pid}\n";

$f = static fn (string $src, string $cap = '', string $show = 'always', ?string $align = null): array =>
    array_filter(['source' => $src, 'caption' => $cap, 'show' => $show, 'align' => $align], static fn ($v) => $v !== null);
$brk = ['source' => '__break__'];

$layout = [
    'stock'  => 'a4-diecut',
    'header' => [
        'fields' => [
            $f('order:order_no'), $f('order:order_date', '', 'always', 'right'), $brk,
            $f('order:customer'), $brk,
            $f('order:address'), $brk,
            $f('order:post_code'), $brk,
            $f('order:cust_ref', 'Cust ref', 'always', 'right'),
        ],
        'w' => 90, 'h' => 50, 'fs' => 10, 'lh' => 1.05,
    ],
    'labels' => [[
        'title'  => 'Cutting label',
        'fields' => [
            $f('order:customer'), $f('order:cust_ref', '', 'ifvalue'),
            $f('order:location', '', 'ifvalue', 'right'), $f('order:order_no', '', 'always', 'right'), $brk,
            $f('order:fabric'), $f('order:colour'), $brk,
            $f('order:drop', 'Drop'), $f('var:Fabric_Cut', 'Cut', 'always', 'right'),
            $f('var:Metres', 'Mtrs', 'always', 'right'), $brk,
            $f('order:qty', 'Slats'), $f('var:Vanes', 'Vanes', 'always', 'right'), $brk,
            $f('order:welded', '', 'ifvalue'), $f('order:bottom_weight', 'Weight', 'ifvalue', 'centre'),
            $f('order:weight_colour', '', 'ifvalue', 'centre'), $brk,
            $f('order:order_date', '', 'always', 'right'), $brk,
            $f('order:notes', 'Notes', 'ifvalue'),
            $f('qr'),
        ],
        'w' => 90, 'h' => 20.9, 'fs' => 8, 'lh' => 1.1,
    ]],
    'qr' => 9,
    'one_per_line' => true,
];
$json = json_encode($layout, JSON_UNESCAPED_SLASHES);

$ex = $pdo->prepare('SELECT id, name FROM worksheet_templates WHERE product_id = ? ORDER BY is_default DESC, id LIMIT 1');
$ex->execute([$pid]);
$row = $ex->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $pdo->prepare('UPDATE worksheet_templates SET layout_json = ?, is_default = 1 WHERE id = ?')
        ->execute([$json, (int) $row['id']]);
    echo "UPDATED template id {$row['id']} ('{$row['name']}').\n";
} else {
    $pdo->prepare("INSERT INTO worksheet_templates (product_id, name, is_default, layout_json) VALUES (?, ?, 1, ?)")
        ->execute([$pid, 'Bev Vertical Fabrics Only worksheet', $json]);
    echo "INSERTED new default template id " . (int) $pdo->lastInsertId() . ".\n";
}
echo "Header = order-level only; one Cutting label with Fabric_Cut / Metres / Vanes + line details; one_per_line=true.\n";
