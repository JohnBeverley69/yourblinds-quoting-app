<?php
declare(strict_types=1);
/** TEMP read-only: dump the worksheet templates for the vertical products. Delete after use. */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');

$pdo = db();
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;
foreach (['Bev Vertical Blinds', 'Bev Vertical Fabrics Only'] as $name) {
    $p = $pdo->prepare('SELECT id FROM products WHERE client_id = ? AND name = ? LIMIT 1');
    $p->execute([$MASTER, $name]);
    $pid = (int) $p->fetchColumn();
    echo "==================== $name (product $pid) ====================\n";
    if ($pid === 0) { echo "(not found)\n\n"; continue; }
    $t = $pdo->prepare('SELECT id, is_default, layout_json FROM worksheet_templates WHERE product_id = ? ORDER BY is_default DESC, id');
    $t->execute([$pid]);
    foreach ($t->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "-- template id {$row['id']} is_default={$row['is_default']} --\n";
        $j = json_decode((string) $row['layout_json'], true);
        echo json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
    }
}
