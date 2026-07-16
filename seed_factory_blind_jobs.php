<?php
declare(strict_types=1);

/**
 * Backfill (Phase B): release blinds for orders that are ALREADY "in production"
 * but pre-date the floor tracking. New orders release automatically when moved
 * to in production on Incoming Orders — this is only for the ones already there.
 *
 * Idempotent (bj_release_order skips lines already tracked). Run via web:
 * /seed_factory_blind_jobs.php (super-admin). Needs the Phase B + routing tables.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
require_once __DIR__ . '/_partials/blind_jobs.php';
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

if (!bj_tables_ready($pdo)) {
    echo "factory_blind_jobs is missing — run /migrate_factory_blind_jobs.php first.\n";
    exit;
}

$orders = $pdo->query("SELECT quote_id FROM factory_jobs WHERE status = 'in_production'")->fetchAll(PDO::FETCH_COLUMN);
if (!$orders) {
    echo "No orders are 'in production' — nothing to backfill.\n";
    echo "Press \"Start production\" on an order in Incoming Orders to put its blinds on the floor.\n";
    exit;
}

$totalNew = 0;
foreach ($orders as $qid) {
    $n = bj_release_order($pdo, (int) $qid, (int) $MASTER);
    $totalNew += $n;
    echo "  order {$qid}: {$n} new blind(s) released\n";
}
echo "\nDone — " . count($orders) . " in-production order(s), {$totalNew} blind(s) released to the floor.\n";
echo "Watch them on /factory/floor.php.\n";
