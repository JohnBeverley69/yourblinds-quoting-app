<?php
declare(strict_types=1);

/**
 * Production areas — Phase E: assign areas per ROUTE/STREAM, not per whole product.
 * A vertical's Headrail and Fabric routes can then live in different areas (so each
 * area's scanner only accepts its own route — misscan-proof), while single-route
 * products (roller, pleated) keep behaving as one flow → one area.
 *
 *   product_area_map.stream       — which route of the product this mapping is for
 *                                   ('main' for single-stream products).
 *   factory_blind_streams.area_id — the area stamped onto each released stream.
 *
 * Backfills existing whole-product mappings into per-stream rows (all keeping the
 * current area), and stamps area_id onto streams already on the floor.
 *
 * Idempotent + web-runnable: /migrate_production_areas_stream.php (super-admin).
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
@set_time_limit(300);
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colExists = static function (string $t, string $c) use ($pdo): bool {
    $s = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
    $s->execute([$t, $c]); return (bool) $s->fetchColumn();
};
$indexExists = static function (string $t, string $i) use ($pdo): bool {
    $s = $pdo->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1');
    $s->execute([$t, $i]); return (bool) $s->fetchColumn();
};
/** A product's route stream names, or ['main'] if it has no route. */
$streamsOf = static function (int $pid) use ($pdo): array {
    $s = $pdo->prepare("SELECT DISTINCT COALESCE(NULLIF(stream,''),'main') AS s FROM product_route_steps WHERE product_id=? AND active=1 ORDER BY s");
    $s->execute([$pid]);
    $r = array_map('strval', $s->fetchAll(PDO::FETCH_COLUMN));
    return $r ?: ['main'];
};

$ops = [];
echo "Production areas — Phase E (per-stream)…\n\n";

// 1) product_area_map.stream
if (!$colExists('product_area_map', 'stream')) {
    $pdo->exec("ALTER TABLE product_area_map ADD COLUMN stream VARCHAR(40) NOT NULL DEFAULT 'main' AFTER product_id");
    $ops[] = 'Added product_area_map.stream.';
} else { $ops[] = 'product_area_map.stream exists — skipped.'; }

// 2) drop the old product-only unique so multiple streams per product are allowed
if ($indexExists('product_area_map', 'uq_pam_product')) {
    $pdo->exec('ALTER TABLE product_area_map DROP INDEX uq_pam_product');
    $ops[] = 'Dropped uq_pam_product.';
}

// 3) Backfill: expand each existing whole-product row into one row per route stream
//    (same area for each), so current behaviour is preserved. A row already carrying
//    a real stream (re-run) is left alone.
$rows = $pdo->query('SELECT id, area_id, product_id, stream FROM product_area_map')->fetchAll(PDO::FETCH_ASSOC);
$expanded = 0;
$ins = $pdo->prepare('INSERT IGNORE INTO product_area_map (area_id, product_id, stream) VALUES (?, ?, ?)');
foreach ($rows as $r) {
    $streams = $streamsOf((int) $r['product_id']);
    // If this row's stream is the default 'main' but the product actually has named
    // streams (e.g. the vertical: Headrail/Fabric), expand it.
    if ((string) $r['stream'] === 'main' && !(count($streams) === 1 && $streams[0] === 'main')) {
        foreach ($streams as $st) $ins->execute([(int) $r['area_id'], (int) $r['product_id'], $st]);
        $pdo->prepare('DELETE FROM product_area_map WHERE id = ?')->execute([(int) $r['id']]);
        $expanded++;
    }
}
$ops[] = "Expanded {$expanded} whole-product mapping(s) into per-stream rows.";

// 4) new unique (product_id, stream)
if (!$indexExists('product_area_map', 'uq_pam_product_stream')) {
    // Clear any accidental dupes first (keep lowest id).
    $pdo->exec('DELETE p1 FROM product_area_map p1 JOIN product_area_map p2 ON p1.product_id=p2.product_id AND p1.stream=p2.stream AND p1.id>p2.id');
    $pdo->exec('ALTER TABLE product_area_map ADD UNIQUE KEY uq_pam_product_stream (product_id, stream)');
    $ops[] = 'Added unique (product_id, stream).';
}

// 4b) production_areas.scanner_name — the scanner/device assigned to the area,
//     baked into the scan-in URL as &s= so the scan log shows the bench name.
if (!$colExists('production_areas', 'scanner_name')) {
    $pdo->exec('ALTER TABLE production_areas ADD COLUMN scanner_name VARCHAR(60) NULL AFTER scan_key');
    $ops[] = 'Added production_areas.scanner_name.';
} else { $ops[] = 'production_areas.scanner_name exists — skipped.'; }

// 5) factory_blind_streams.area_id
if (!$colExists('factory_blind_streams', 'area_id')) {
    $pdo->exec('ALTER TABLE factory_blind_streams ADD COLUMN area_id INT NULL');
    $pdo->exec('ALTER TABLE factory_blind_streams ADD INDEX idx_fbs_area (area_id)');
    $ops[] = 'Added factory_blind_streams.area_id (+ index).';
} else { $ops[] = 'factory_blind_streams.area_id exists — skipped.'; }

// 6) Backfill stream area_id from the (now per-stream) map for streams on the floor.
$upd = $pdo->exec(
    'UPDATE factory_blind_streams s
       JOIN factory_blind_jobs j ON j.id = s.blind_job_id
       JOIN product_area_map pam ON pam.product_id = j.product_id
        AND pam.stream = COALESCE(NULLIF(s.stream,\'\'),\'main\')
        SET s.area_id = pam.area_id
      WHERE s.area_id IS NULL OR s.area_id <> pam.area_id'
);
$ops[] = 'Backfilled area_id on ' . (int) $upd . ' stream row(s) on the floor.';

echo "Done.\n\n";
foreach ($ops as $i => $o) echo sprintf("  %d. %s\n", $i + 1, $o);
echo "\nNext: on Factory → Production areas, set the vertical's Fabric route to Vertical Fabrics.\n";
