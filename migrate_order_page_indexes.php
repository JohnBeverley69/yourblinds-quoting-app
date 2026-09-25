<?php
declare(strict_types=1);

/**
 * Harden the order-list pages for scale. The stress test surfaced that Order
 * History (orders/index.php) and factory Incoming Orders (factory/incoming-orders.php)
 * slow to ~10s once a tenant has thousands of orders — because the hot filters
 * had no supporting index. These indexes keep those pages fast no matter how many
 * orders accumulate. Safe + idempotent (skips an index that already exists).
 *
 *   quotes(client_id, status)      — Order History WHERE q.client_id=? AND q.status IN(...)
 *   quotes(status)                 — Incoming Orders WHERE q.status IN('ordered',…)
 *   products(source_client_id)     — Incoming Orders factory-owned filter
 *   quote_items(product_id)        — the qi→products join
 *   quote_items(quote_id)          — per-order line fetch
 *   payments(quote_id)             — Order History per-row payments sum
 *
 * Web-runnable by a super-admin: /migrate_order_page_indexes.php
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

$hasIndexNamed = static function (string $table, string $index) use ($pdo): bool {
    $s = $pdo->prepare(
        'SELECT 1 FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
    );
    $s->execute([$table, $index]);
    return (bool) $s->fetchColumn();
};
$tableExists = static function (string $t) use ($pdo): bool {
    $s = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    $s->execute([$t]);
    return (bool) $s->fetchColumn();
};

$wanted = [
    ['quotes',      'idx_quotes_client_status', '(client_id, status)'],
    ['quotes',      'idx_quotes_status',        '(status)'],
    ['products',    'idx_products_source',      '(source_client_id)'],
    ['quote_items', 'idx_qi_product',           '(product_id)'],
    ['quote_items', 'idx_qi_quote',             '(quote_id)'],
    ['payments',    'idx_pay_quote',            '(quote_id)'],
];

echo "Adding order-page indexes…\n\n";
$ops = [];
foreach ($wanted as [$table, $index, $cols]) {
    if (!$tableExists($table)) { $ops[] = "{$table}: table absent — skipped."; continue; }
    if ($hasIndexNamed($table, $index)) { $ops[] = "{$table}.{$index} already exists — skipped."; continue; }
    try {
        $pdo->exec("ALTER TABLE {$table} ADD INDEX {$index} {$cols}");
        $ops[] = "{$table}: added {$index} {$cols}.";
    } catch (Throwable $e) {
        // Duplicate index (same columns under another name) or similar — non-fatal.
        $ops[] = "{$table}.{$index}: skipped (" . $e->getMessage() . ").";
    }
}

echo "Done.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %d. %s\n", $i + 1, $op);
