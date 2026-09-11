<?php
declare(strict_types=1);
/** TEMP read-only: dump route steps + a fabric order's floor jobs/streams. Delete after use. */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
require_once __DIR__ . '/_partials/blind_jobs.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');

$pdo = db();

echo "=== ALL product_route_steps (join to stations checked) ===\n";
$rs = $pdo->query(
    "SELECT prs.product_id, p.name AS pname, prs.seq,
            COALESCE(NULLIF(prs.stream,''),'main') AS stream, prs.label, prs.station_id, prs.active,
            s.name AS station
       FROM product_route_steps prs
       LEFT JOIN products p ON p.id = prs.product_id
       LEFT JOIN factory_stations s ON s.id = prs.station_id
      ORDER BY prs.product_id, prs.seq, prs.id"
);
foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $r) {
    printf("  prod %s (%s) seq %s stream=%s label='%s' station_id=%s station=%s active=%s\n",
        $r['product_id'], $r['pname'] ?? '?', $r['seq'], $r['stream'], $r['label'],
        $r['station_id'] === null ? 'NULL' : $r['station_id'],
        $r['station'] === null ? '*** MISSING ***' : $r['station'], $r['active']);
}

echo "\n=== bj_route_steps(143) [what the engine actually sees for Fabric Only] ===\n";
$got = bj_route_steps($pdo, 143);
echo "count=" . count($got) . "\n";
foreach ($got as $g) echo "  seq {$g['seq']} stream={$g['stream']} label='{$g['label']}' station={$g['station']}\n";

echo "\n=== order ABC-2026-0001 floor jobs + streams ===\n";
$q = $pdo->prepare("SELECT id FROM quotes WHERE quote_number = 'ABC-2026-0001' ORDER BY id DESC LIMIT 1");
$q->execute();
$qid = (int) $q->fetchColumn();
echo "quote id=$qid\n";
$jobs = $pdo->prepare('SELECT id, product_id, quote_item_id, unit_no, status FROM factory_blind_jobs WHERE quote_id = ?');
$jobs->execute([$qid]);
foreach ($jobs->fetchAll(PDO::FETCH_ASSOC) as $j) {
    echo "  JOB {$j['id']} product_id={$j['product_id']} item={$j['quote_item_id']} unit={$j['unit_no']} status={$j['status']}\n";
    $sr = $pdo->prepare('SELECT stream, route_step_id, station_id, seq, status FROM factory_blind_streams WHERE blind_job_id = ? ORDER BY seq, id');
    $sr->execute([(int) $j['id']]);
    foreach ($sr->fetchAll(PDO::FETCH_ASSOC) as $s) {
        echo "    stream={$s['stream']} route_step_id=" . ($s['route_step_id'] ?? 'NULL')
           . " station_id=" . ($s['station_id'] ?? 'NULL') . " seq={$s['seq']} status={$s['status']}\n";
    }
}
