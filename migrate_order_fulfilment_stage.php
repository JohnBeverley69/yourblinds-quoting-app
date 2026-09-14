<?php
declare(strict_types=1);

/**
 * Migration: the single order fulfilment stage (Phase 0 of the flow redesign).
 *
 * Adds quotes.fulfilment_stage and backfills it for every placed order by
 * running the read-only reconciler (recompute_order_stage), which derives the
 * one stage — Confirmed / In Production / Ready / Dispatched — from the existing
 * detail trackers. Behaviour-preserving: nothing else is changed.
 *
 * Additive + idempotent. Web-runnable: /migrate_order_fulfilment_stage.php
 * (super-admin).
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

// 1. Add the column (idempotent).
$has = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'quotes' AND column_name = 'fulfilment_stage'"
)->fetchColumn();
if ((int) $has === 0) {
    $pdo->exec("ALTER TABLE quotes ADD COLUMN fulfilment_stage VARCHAR(20) NULL AFTER status");
    echo "Added quotes.fulfilment_stage.\n";
} else {
    echo "quotes.fulfilment_stage already present — skipped.\n";
}

// 2. Backfill every placed order through the reconciler.
require_once __DIR__ . '/_partials/order_stage.php';

$ph  = implode(',', array_fill(0, count(os_placed_statuses()), '?'));
$ids = $pdo->prepare("SELECT id FROM quotes WHERE status IN ($ph) ORDER BY id");
$ids->execute(os_placed_statuses());
$ids = array_map('intval', $ids->fetchAll(PDO::FETCH_COLUMN));

$counts = ['confirmed' => 0, 'in_production' => 0, 'ready' => 0, 'dispatched' => 0, 'null' => 0];
foreach ($ids as $qid) {
    $stage = recompute_order_stage($pdo, $qid);
    $counts[$stage ?? 'null']++;
}

echo "\nBackfilled " . count($ids) . " placed order(s):\n";
foreach ($counts as $k => $n) {
    echo sprintf("  %-14s %d\n", $k, $n);
}
echo "\nDone. quotes.fulfilment_stage is now derived; the reconciler keeps it in\n";
echo "sync as orders move. Nothing else changed (behaviour-preserving).\n";
