<?php
declare(strict_types=1);

/**
 * Read-only diagnostic. Dumps the current state of client_markups,
 * client_discounts, the column definitions, and the FK / unique
 * constraints — so we can see exactly what's there after an attempted
 * save on the product Edit page.
 *
 * Steps: attempt a save on /admin/products/edit.php with the value
 * that's "not sticking", then immediately visit this URL and paste
 * the output back.
 *
 * Super-admin only. Read-only — no writes, safe to leave up while
 * debugging.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();

header('Content-Type: text/plain; charset=utf-8');

$user     = current_user();
$clientId = (int) $user['client_id'];
$pdo      = db();

echo "Logged in as client_id=$clientId\n";
echo "=================================================\n\n";

echo "=== client_markups rows for this client ===\n";
$st = $pdo->prepare(
    'SELECT cm.id, cm.product_id, p.name AS product,
            cm.system_id, cm.system_id_key, ps.name AS system,
            cm.markup_percent
       FROM client_markups cm
       LEFT JOIN products p        ON p.id  = cm.product_id
       LEFT JOIN product_systems ps ON ps.id = cm.system_id
      WHERE cm.client_id = ?
      ORDER BY p.name, ps.sort_order, ps.name, cm.system_id'
);
$st->execute([$clientId]);
$rows = $st->fetchAll();
if (!$rows) {
    echo "  (no rows)\n";
}
foreach ($rows as $r) {
    printf(
        "  #%-4d  %-30s  sys=%-15s sysid=%-5s key=%-3s  markup=%s%%\n",
        $r['id'], $r['product'] ?? '?',
        $r['system'] ?? '(NULL — all-systems)',
        $r['system_id'] === null ? 'NULL' : $r['system_id'],
        $r['system_id_key'],
        $r['markup_percent']
    );
}

echo "\n=== client_discounts rows for this client ===\n";
$st = $pdo->prepare(
    'SELECT cd.id, cd.product_id, p.name AS product,
            cd.system_id, cd.system_id_key, ps.name AS system,
            cd.discount_percent
       FROM client_discounts cd
       LEFT JOIN products p        ON p.id  = cd.product_id
       LEFT JOIN product_systems ps ON ps.id = cd.system_id
      WHERE cd.client_id = ?
      ORDER BY p.name, ps.sort_order, ps.name, cd.system_id'
);
$st->execute([$clientId]);
$rows = $st->fetchAll();
if (!$rows) {
    echo "  (no rows)\n";
}
foreach ($rows as $r) {
    printf(
        "  #%-4d  %-30s  sys=%-15s sysid=%-5s key=%-3s  discount=%s%%\n",
        $r['id'], $r['product'] ?? '?',
        $r['system'] ?? '(NULL — all-systems)',
        $r['system_id'] === null ? 'NULL' : $r['system_id'],
        $r['system_id_key'],
        $r['discount_percent']
    );
}

echo "\n=== column definitions ===\n";
$st = $pdo->query(
    "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
       FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('client_markups', 'client_discounts')
      ORDER BY TABLE_NAME, ORDINAL_POSITION"
);
foreach ($st->fetchAll() as $r) {
    printf(
        "  %-18s %-18s %-22s null=%-3s default=%-10s extra=%s\n",
        $r['TABLE_NAME'], $r['COLUMN_NAME'], $r['COLUMN_TYPE'],
        $r['IS_NULLABLE'], $r['COLUMN_DEFAULT'] ?? '-',
        $r['EXTRA'] ?? ''
    );
}

echo "\n=== indexes on client_markups / client_discounts ===\n";
$st = $pdo->query(
    "SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE,
            GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
       FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('client_markups', 'client_discounts')
      GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE
      ORDER BY TABLE_NAME, INDEX_NAME"
);
foreach ($st->fetchAll() as $r) {
    printf(
        "  %-18s %-32s unique=%s cols=(%s)\n",
        $r['TABLE_NAME'], $r['INDEX_NAME'],
        $r['NON_UNIQUE'] ? 'no' : 'YES', $r['cols']
    );
}

echo "\n=== MySQL session sql_mode ===\n";
$mode = (string) $pdo->query("SELECT @@SESSION.sql_mode")->fetchColumn();
echo "  $mode\n";
echo "  STRICT_*: " . (str_contains($mode, 'STRICT_') ? 'on' : 'off') . "\n";

echo "\nDone.\n";
