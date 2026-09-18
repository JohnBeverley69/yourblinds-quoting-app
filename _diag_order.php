<?php
declare(strict_types=1);
/**
 * TEMP read-only diagnostic: for an order, show its lines vs the blind jobs that
 * were released to the floor, so we can see why the count differs.
 *   ?order=<quote id>   or   ?q=<quote_number substring>   (default '0001')
 * Super-admin. Deletes nothing.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
require_once __DIR__ . '/_partials/bought_in.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();
$factory = function_exists('factory_client_id') ? factory_client_id() : 3;

$orderId = (int) ($_GET['order'] ?? 0);
$q = (string) ($_GET['q'] ?? '0001');

if ($orderId <= 0) {
    echo "Matching orders for quote_number LIKE '%{$q}%':\n";
    $s = $pdo->prepare("SELECT qu.id, qu.quote_number, c.company_name AS tenant, qu.status
                          FROM quotes qu JOIN clients c ON c.id = qu.client_id
                         WHERE qu.quote_number LIKE ? ORDER BY qu.id DESC LIMIT 10");
    $s->execute(['%' . $q . '%']);
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) printf("  order id=%d  %s  tenant=%s  status=%s\n", $r['id'], $r['quote_number'], $r['tenant'], $r['status']);
    if (!$rows) { echo "  (none)\n"; exit; }
    $orderId = (int) $rows[0]['id'];
    echo "\n-- using order id {$orderId} --\n\n";
}

$q0 = $pdo->prepare("SELECT qu.id, qu.quote_number, qu.client_id, c.company_name AS tenant, qu.status
                       FROM quotes qu JOIN clients c ON c.id = qu.client_id WHERE qu.id = ?");
$q0->execute([$orderId]);
$ord = $q0->fetch(PDO::FETCH_ASSOC);
if (!$ord) { echo "Order not found.\n"; exit; }
printf("Order %s (id %d)  tenant=%s  status=%s  factory=%d\n\n", $ord['quote_number'], $ord['id'], $ord['tenant'], $ord['status'], $factory);

echo "=== quote_items ===\n";
$it = $pdo->prepare(
    "SELECT qi.id, qi.line_no, qi.product_id, p.name AS pname, qi.quantity,
            COALESCE(NULLIF(p.source_client_id,0), p.client_id) AS owner,
            COALESCE(p.source_product_id, p.id) AS master_pid
       FROM quote_items qi JOIN products p ON p.id = qi.product_id
      WHERE qi.quote_id = ? ORDER BY qi.line_no, qi.id"
);
$it->execute([$orderId]);
$items = $it->fetchAll(PDO::FETCH_ASSOC);
foreach ($items as $r) {
    $factoryOwned = ((int) $r['owner'] === (int) $factory) ? 'YES' : 'no(' . $r['owner'] . ')';
    printf("  item=%-6d line=%-3d qty=%-2d  factory=%s  product=%s (id %d, master %d)\n",
        $r['id'], $r['line_no'], $r['quantity'], $factoryOwned, $r['pname'], $r['product_id'], $r['master_pid']);
}

echo "\n=== which lines bj_release_order WOULD release (in-house, factory-owned) ===\n";
try {
    $rel = $pdo->prepare(
        'SELECT qi.id, qi.quantity, COALESCE(p.source_product_id, p.id) AS master_pid, mp.name AS mname
           FROM quote_items qi JOIN products p ON p.id = qi.product_id
           ' . bought_in_master_join('p', 'mp') . '
          WHERE qi.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
            AND ' . bought_in_inhouse_predicate('mp') . '
          ORDER BY qi.line_no, qi.id'
    );
    $rel->execute([$orderId, $factory]);
    $relItems = $rel->fetchAll(PDO::FETCH_ASSOC);
    foreach ($relItems as $r) printf("  item=%-6d qty=%-2d  master=%s (%d)\n", $r['id'], $r['quantity'], $r['mname'], $r['master_pid']);
    echo "  => " . count($relItems) . " line(s) qualify for the floor.\n";
    $missing = array_diff(array_map(fn($i)=>(int)$i['id'],$items), array_map(fn($i)=>(int)$i['id'],$relItems));
    if ($missing) echo "  !! quote_items NOT released (bought-in or not factory-owned): " . implode(',', $missing) . "\n";
} catch (Throwable $e) { echo "  (error: " . $e->getMessage() . ")\n"; }

echo "\n=== factory_blind_jobs actually on the floor ===\n";
$bj = $pdo->prepare('SELECT id, quote_item_id, unit_no, product_id, area_id, status FROM factory_blind_jobs WHERE quote_id = ? ORDER BY quote_item_id, unit_no');
$bj->execute([$orderId]);
$jobs = $bj->fetchAll(PDO::FETCH_ASSOC);
foreach ($jobs as $j) printf("  job=%-6d item=%-6d unit=%-2d area=%s status=%s product=%d\n", $j['id'], $j['quote_item_id'], $j['unit_no'], $j['area_id'] ?? '-', $j['status'], $j['product_id']);
echo "  => " . count($jobs) . " blind job(s).\n";

echo "\nDone (read-only).\n";
