<?php
declare(strict_types=1);

/**
 * Diagnose: has the catalogue-source marker populated after a push?
 *
 * Hit /diag_catalogue_source.php while logged in as a super-admin.
 *
 * Read-only. Reports, per tenant, how many products carry the Beverley
 * source marker (products.source_client_id = 3, the master "Your Blinds"
 * account) — i.e. copies stamped by a catalogue push. Confirms a push wrote
 * the manufacturing routing marker as intended. Makes NO changes.
 *
 * Delete this file once the marker is verified.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth/middleware.php';
requireSuperAdmin();

header('Content-Type: text/plain; charset=utf-8');

$pdo = db();
$MASTER = 3;   // Beverley Blinds Trade master = client #3

echo "Catalogue-source marker — population check\n";
echo "Read-only. Beverley master = client #{$MASTER}.\n";
echo str_repeat('-', 66) . "\n\n";

try {
    $st = $pdo->prepare(
        "SELECT p.client_id,
                COALESCE(c.company_name, '') AS company,
                COUNT(*)                              AS total,
                SUM(p.source_client_id = ?)           AS marked_bev,
                SUM(p.source_client_id IS NOT NULL)   AS any_source,
                SUM(p.source_product_id IS NOT NULL)  AS with_prod
           FROM products p
      LEFT JOIN clients c ON c.id = p.client_id
       GROUP BY p.client_id, c.company_name
       ORDER BY marked_bev DESC, p.client_id"
    );
    $st->execute([$MASTER]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo "Query failed — is migrate_catalogue_source.php run? " . $e->getMessage() . "\n";
    exit(1);
}

$totalMarked = 0;
$tenantsMarked = 0;
foreach ($rows as $r) {
    $cid    = (int) $r['client_id'];
    $marked = (int) $r['marked_bev'];
    $totalMarked += $marked;
    if ($marked > 0 && $cid !== $MASTER) $tenantsMarked++;
    printf(
        "client #%-4d %-30s products:%-5d  Bev-marked:%-5d  src_product:%-5d%s\n",
        $cid,
        substr((string) $r['company'], 0, 30),
        (int) $r['total'],
        $marked,
        (int) $r['with_prod'],
        $cid === $MASTER ? '   <- master' : ''
    );
}

echo "\n" . str_repeat('-', 66) . "\n";
if ($totalMarked > 0) {
    echo "RESULT: marker IS populated — {$totalMarked} product(s) stamped "
       . "source_client_id={$MASTER} across {$tenantsMarked} tenant(s).\n";
    echo "A push has stamped copies; manufacturing routing can rely on this\n";
    echo "for the tenants shown above with Bev-marked > 0.\n";
} else {
    echo "RESULT: no products carry source_client_id={$MASTER} yet.\n";
    echo "Either no push has run since the engine change deployed, or the push\n";
    echo "touched no products. Re-check the push summary (products added/updated).\n";
}
