<?php
declare(strict_types=1);

/**
 * Seed + backfill for production times and due dates.
 *
 *  1. Gives every Beverley product a starting production time (only if it has
 *     none — an existing figure is never overwritten).
 *  2. Backfills a due date onto already-PLACED orders that have none, computed
 *     from each order's OWN placement date + today's production times.
 *
 * (2) is a one-off catch-up for orders placed before due dates existed. It is
 * an estimate, not a promise the customer was actually given — there was no
 * date to record at the time. It only ever fills a NULL, so it can't move a
 * date that's already there, and normal operation never recomputes anything.
 *
 * Run via web: /seed_factory_due_dates.php (super-admin). Idempotent.
 * Add ?leads_only=1 to skip the backfill and just seed the production times.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
require_once __DIR__ . '/_partials/due_dates.php';
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

if (!dd_ready($pdo)) {
    echo "Run /migrate_factory_due_dates.php first.\n";
    exit;
}

// ---- 1. Starting production times, in working days ------------------------
$defaults = [
    'Bev Roller Blinds'   => 5,
    'Bev Vertical Blinds' => 8,
    'Bev Pleated'         => 10,
];
$find = $pdo->prepare('SELECT id FROM products WHERE client_id = ? AND name = ? LIMIT 1');
$has  = $pdo->prepare('SELECT 1 FROM product_lead_times WHERE product_id = ? LIMIT 1');
foreach ($defaults as $name => $days) {
    $find->execute([$MASTER, $name]);
    $pid = (int) $find->fetchColumn();
    if ($pid === 0) { echo "  ! product not found: {$name}\n"; continue; }
    $has->execute([$pid]);
    if ($has->fetchColumn()) { echo "  = {$name}: already set, left alone\n"; continue; }
    dd_set_lead_days($pdo, $pid, $days);
    echo "  + {$name}: {$days} working days\n";
}

// Every other Bev product falls back to the default until it's given a time.
$others = $pdo->prepare(
    "SELECT p.id, p.name FROM products p
      WHERE p.client_id = ? AND p.name LIKE 'Bev%'
        AND p.id NOT IN (SELECT product_id FROM product_lead_times)"
);
$others->execute([$MASTER]);
foreach ($others->fetchAll(PDO::FETCH_ASSOC) as $p) {
    dd_set_lead_days($pdo, (int) $p['id'], DD_DEFAULT_LEAD_DAYS);
    echo "  + {$p['name']}: " . DD_DEFAULT_LEAD_DAYS . " working days (default)\n";
}

if (isset($_GET['leads_only'])) { echo "\nProduction times seeded. Backfill skipped (leads_only).\n"; exit; }

// ---- 2. Backfill placed orders that have no due date ----------------------
$placed = $pdo->prepare(
    "SELECT DISTINCT q.id, q.created_at
       FROM quotes q
       JOIN quote_items qi ON qi.quote_id = q.id
       JOIN products p     ON p.id = qi.product_id
      WHERE q.status IN ('ordered','fitted','invoiced','paid')
        AND q.due_date IS NULL
        AND p.source_client_id = ?"
);
$placed->execute([$MASTER]);
$rows = $placed->fetchAll(PDO::FETCH_ASSOC);

$upd = $pdo->prepare('UPDATE quotes SET due_date = ? WHERE id = ? AND due_date IS NULL');
$n = 0;
foreach ($rows as $r) {
    $lead = dd_order_lead_days($pdo, (int) $r['id'], (int) $MASTER);
    if ($lead === null) continue;
    // From the order's OWN placement date, not today.
    try { $from = new DateTimeImmutable((string) $r['created_at']); }
    catch (Throwable $e) { continue; }
    $upd->execute([dd_add_working_days($from, $lead)->format('Y-m-d'), (int) $r['id']]);
    $n += $upd->rowCount();
}
echo "\nBackfilled {$n} of " . count($rows) . " undated placed order(s).\n";
echo "New orders are stamped automatically at placement. See /factory/floor.php.\n";
