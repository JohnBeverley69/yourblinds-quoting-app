<?php
declare(strict_types=1);

/**
 * Migration: one "work part" per factory on an order (see _partials/order_parts.php).
 *
 *   factory_jobs        + factory_client_id; the unique key becomes
 *                       (quote_id, factory_client_id) instead of quote_id alone.
 *                       Existing rows get the factory that made most of the order
 *                       (the only factory that could have processed it so far).
 *   order_factory_parts new: (quote_id, factory_client_id) → fulfilment_stage,
 *                       due_date, notified_at. Backfilled for every placed order:
 *                       each making factory gets the order's current stage/due
 *                       date; the notified stamp goes to the factory that was
 *                       emailed (the one owning most lines).
 *
 * Idempotent. Run via web: /setup/migrations/migrate_order_factory_parts.php?run=1 (super-admin).
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__, 2) . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/quote-builder/_helpers.php';
require_once dirname(__DIR__, 2) . '/_partials/order_parts.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$ops = [];
set_exception_handler(function (Throwable $e) use (&$ops) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) header('Content-Type: text/plain; charset=utf-8');
    echo "Migration FAILED: " . $e->getMessage() . "\n\n";
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

$col = static fn (string $t, string $c): bool => (bool) $pdo->query(
    "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = " . $pdo->quote($t) . " AND COLUMN_NAME = " . $pdo->quote($c))->fetchColumn();
$idx = static fn (string $t, string $i): bool => (bool) $pdo->query(
    "SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = " . $pdo->quote($t) . " AND INDEX_NAME = " . $pdo->quote($i))->fetchColumn();

echo "Migrating: per-factory order parts…\n\n";

// 1. factory_jobs.factory_client_id
if (!$col('factory_jobs', 'factory_client_id')) {
    $pdo->exec('ALTER TABLE factory_jobs ADD COLUMN factory_client_id INT NOT NULL DEFAULT 0 AFTER quote_id');
    $ops[] = 'Added factory_jobs.factory_client_id.';
}
// The main factory, also when run from the command line (no auth/middleware.php,
// so the helpers that read it — qb_factory_client_ids() too — need it defined).
if (!function_exists('factory_client_id')) {
    function factory_client_id(): int { return (int) (function_exists('env') ? (env('FACTORY_CLIENT_ID', '3') ?? 3) : 3); }
}
$mainFactory = (int) factory_client_id();
$fill = $pdo->query('SELECT id, quote_id FROM factory_jobs WHERE factory_client_id = 0')->fetchAll(PDO::FETCH_ASSOC);
$up = $pdo->prepare('UPDATE factory_jobs SET factory_client_id = ? WHERE id = ?');
foreach ($fill as $r) $up->execute([qb_order_factory_id($pdo, (int) $r['quote_id']) ?: $mainFactory, (int) $r['id']]);
$ops[] = 'Stamped the factory on ' . count($fill) . ' existing factory_jobs row(s).';

if ($idx('factory_jobs', 'uq_factory_jobs_quote')) {
    $pdo->exec('ALTER TABLE factory_jobs DROP INDEX uq_factory_jobs_quote');
    $ops[] = 'Dropped the one-row-per-order key on factory_jobs.';
}
if (!$idx('factory_jobs', 'uq_factory_jobs_quote_factory')) {
    $pdo->exec('ALTER TABLE factory_jobs ADD UNIQUE KEY uq_factory_jobs_quote_factory (quote_id, factory_client_id)');
    $ops[] = 'Added the one-row-per-order-per-factory key on factory_jobs.';
}

// 2. order_factory_parts
$exists = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_factory_parts'"
)->fetchColumn();
if (!$exists) {
    $pdo->exec(
        "CREATE TABLE order_factory_parts (
            quote_id          INT NOT NULL,
            factory_client_id INT NOT NULL,
            fulfilment_stage  VARCHAR(20) NULL,
            due_date          DATE NULL,
            notified_at       DATETIME NULL,
            created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (quote_id, factory_client_id),
            KEY idx_ofp_factory (factory_client_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $ops[] = 'Created table order_factory_parts.';
}

// 3. Backfill a part per making factory on every placed order that has none.
$hasNotified = $col('quotes', 'factory_notified_at');
$placed = $pdo->query(
    "SELECT q.id, q.fulfilment_stage, q.due_date" . ($hasNotified ? ', q.factory_notified_at' : ', NULL AS factory_notified_at') . "
       FROM quotes q
      WHERE q.status IN ('ordered','fitted','invoiced','paid')
        AND NOT EXISTS (SELECT 1 FROM order_factory_parts o WHERE o.quote_id = q.id)"
)->fetchAll(PDO::FETCH_ASSOC);
$ins = $pdo->prepare(
    'INSERT IGNORE INTO order_factory_parts (quote_id, factory_client_id, fulfilment_stage, due_date, notified_at)
     VALUES (?, ?, ?, ?, ?)'
);
$parts = 0; $mixed = [];
foreach ($placed as $q) {
    $qid = (int) $q['id'];
    $facs = op_order_factories($pdo, $qid);
    if (!$facs) continue;
    $main = qb_order_factory_id($pdo, $qid);
    foreach ($facs as $f) {
        $ins->execute([$qid, $f, $q['fulfilment_stage'], $q['due_date'], $f === $main ? $q['factory_notified_at'] : null]);
        $parts++;
    }
    if (count($facs) > 1) $mixed[] = $qid;
}
$ops[] = "Backfilled $parts part(s) on " . count($placed) . ' placed order(s).';

// Mixed orders: derive each factory's own stage now that factory_jobs is per factory.
if ($mixed) {
    require_once dirname(__DIR__, 2) . '/_partials/order_stage.php';
    $nums = $pdo->prepare('SELECT quote_number FROM quotes WHERE id = ?');
    foreach ($mixed as $qid) {
        recompute_order_stage($pdo, $qid);
        $nums->execute([$qid]);
        $ops[] = 'Mixed-factory order ' . (string) $nums->fetchColumn() . ': each factory\'s stage worked out separately.';
    }
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
