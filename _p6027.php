<?php
declare(strict_types=1);
/** TEMP read-only: inspect the stray fabric-only product(s). Delete after use. */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();

$ps = $pdo->query(
    "SELECT id, client_id, name, source_client_id, source_product_id, active
       FROM products WHERE id IN (143, 6027) OR name LIKE '%ertical Fabric%' ORDER BY id"
);
foreach ($ps->fetchAll(PDO::FETCH_ASSOC) as $p) {
    printf("product %s client=%s name='%s' source_client=%s source_product=%s active=%s\n",
        $p['id'], $p['client_id'], $p['name'],
        $p['source_client_id'] ?? 'NULL', $p['source_product_id'] ?? 'NULL', $p['active']);
    // usage
    $u = $pdo->prepare('SELECT COUNT(*) FROM quote_items WHERE product_id = ?');
    $u->execute([(int) $p['id']]);
    $rs = $pdo->prepare('SELECT COUNT(*) FROM product_route_steps WHERE product_id = ?');
    $rs->execute([(int) $p['id']]);
    $bj = $pdo->prepare('SELECT COUNT(*) FROM factory_blind_jobs WHERE product_id = ?');
    $bj->execute([(int) $p['id']]);
    echo "   quote_items=" . (int) $u->fetchColumn()
       . " route_steps=" . (int) $rs->fetchColumn()
       . " floor_jobs=" . (int) $bj->fetchColumn() . "\n";
}
