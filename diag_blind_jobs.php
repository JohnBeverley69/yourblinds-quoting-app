<?php
declare(strict_types=1);

/** TEMPORARY read-only diagnostic: what stage is each blind at, and when did it last move? */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');

$pdo = db();

echo "=== Bev Roller Blinds route (product_route_steps) ===\n";
foreach ($pdo->query(
    "SELECT rs.id, rs.product_id, rs.seq, rs.label, s.name AS station, rs.active
       FROM product_route_steps rs JOIN factory_stations s ON s.id = rs.station_id
       JOIN products p ON p.id = rs.product_id
      WHERE p.name = 'Bev Roller Blinds' ORDER BY rs.seq"
)->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  step id={$r['id']} product={$r['product_id']} seq={$r['seq']} station={$r['station']} label={$r['label']} active={$r['active']}\n";
}

echo "\n=== factory_blind_jobs ===\n";
foreach ($pdo->query(
    "SELECT bj.id, bj.quote_id, bj.unit_no, bj.product_id, bj.route_step_id, bj.station_id,
            bj.seq, bj.status, bj.updated_by, bj.created_at, bj.updated_at, bj.step_started_at,
            s.name AS station_name
       FROM factory_blind_jobs bj
       LEFT JOIN factory_stations s ON s.id = bj.station_id
      ORDER BY bj.id"
)->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  job {$r['id']} q={$r['quote_id']} unit={$r['unit_no']} prod={$r['product_id']}"
       . " step={$r['route_step_id']} station={$r['station_id']}({$r['station_name']}) seq={$r['seq']}"
       . " status={$r['status']} by={$r['updated_by']}"
       . " created={$r['created_at']} updated={$r['updated_at']} step_started={$r['step_started_at']}\n";
}
