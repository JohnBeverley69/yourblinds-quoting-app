<?php
declare(strict_types=1);

/**
 * Diagnose: why does extra.php show "All systems" only?
 *
 * Hit /diagnose_extra_systems.php?id=63   (use the Wand Option's extra id)
 * while logged in as the same user as the broken page.
 *
 * Prints exactly what extra.php's $systems variable would resolve to,
 * plus enough context to see if there's a session/tenant mismatch.
 * Read-only. Delete after.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth/middleware.php';
requireAdmin();

header('Content-Type: text/plain; charset=utf-8');

$user     = current_user();
$clientId = $user['client_id'];
$extraId  = (int) ($_GET['id'] ?? 63);

echo "Logged-in user:\n";
echo "  user_id     = " . (int) ($user['user_id'] ?? 0) . "\n";
echo "  client_id   = " . var_export($clientId, true)
                        . "  (type: " . gettype($clientId) . ")\n";
echo "  company     = " . (string) ($user['company_name'] ?? '') . "\n\n";

$pdo = db();

// Replicate extra.php's load.
$loadStmt = $pdo->prepare(
    'SELECT e.id, e.product_id, e.name, e.is_required, e.active,
            e.client_id AS extra_client_id,
            p.name AS product_name, p.client_id AS product_client_id
       FROM product_extras e
       JOIN products p ON p.id = e.product_id
      WHERE e.id = ? AND e.client_id = ?'
);
$loadStmt->execute([$extraId, $clientId]);
$extra = $loadStmt->fetch();

echo "Load extra (extra_id=$extraId, client_id=" . var_export($clientId, true) . "):\n";
if (!$extra) {
    echo "  *** Extra row NOT found — load query returned empty.\n";
    echo "  This would 404 the page. So if you're seeing the page,\n";
    echo "  something else is going on. Try id=" . $extraId . " literally.\n\n";
} else {
    foreach ($extra as $k => $v) {
        echo "  $k = " . var_export($v, true) . "\n";
    }
    echo "\n";
}

if ($extra) {
    // Replicate extra.php's systems query.
    $sysStmt = $pdo->prepare(
        'SELECT id, name, product_id, client_id, sort_order, active
           FROM product_systems
          WHERE product_id = ? AND client_id = ?
       ORDER BY sort_order, name'
    );
    $bindProduct = (int) $extra['product_id'];
    $sysStmt->execute([$bindProduct, $clientId]);
    $systems = $sysStmt->fetchAll();

    echo "Systems query (product_id=$bindProduct, client_id=" . var_export($clientId, true) . "):\n";
    echo "  rows returned: " . count($systems) . "\n";
    foreach ($systems as $s) {
        echo "    id=" . (int) $s['id']
           . "  name=" . (string) $s['name']
           . "  product_id=" . (int) $s['product_id']
           . "  client_id=" . (int) $s['client_id']
           . "  active=" . (int) $s['active']
           . "\n";
    }
    echo "\n";

    // Also try an unfiltered query to see EVERY system on this product
    // regardless of client_id — if THIS returns more rows, the bug is
    // a client_id mismatch we can't see from the simple query.
    $unfilt = $pdo->prepare(
        'SELECT id, name, product_id, client_id, active
           FROM product_systems WHERE product_id = ?'
    );
    $unfilt->execute([$bindProduct]);
    $rows = $unfilt->fetchAll();

    echo "Sanity check — systems on product_id=$bindProduct, no client filter:\n";
    echo "  rows returned: " . count($rows) . "\n";
    foreach ($rows as $r) {
        echo "    id=" . (int) $r['id']
           . "  name=" . (string) $r['name']
           . "  client_id=" . (int) $r['client_id']
           . "  active=" . (int) $r['active']
           . "\n";
    }
}

echo "\nDone. Delete this file from the server when finished.\n";
