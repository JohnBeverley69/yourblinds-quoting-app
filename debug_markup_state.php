<?php
declare(strict_types=1);

/**
 * Read-only diagnostic. Each section is independently try/catch'd so
 * a failure in one (e.g. column doesn't exist yet) doesn't blank the
 * rest of the output — which is what happens when PHP fatal-errors
 * silently and display_errors is off on production.
 *
 * Super-admin only. Read-only — no writes, safe to leave up.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();

ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

$user     = current_user();
$clientId = (int) $user['client_id'];
$pdo      = db();

echo "Logged in as client_id=$clientId\n";
echo "=================================================\n";

function section(string $title, callable $fn): void
{
    echo "\n=== $title ===\n";
    try {
        $fn();
    } catch (Throwable $e) {
        echo "  !! threw: " . $e->getMessage() . "\n";
    }
}

section('column definitions (client_markups + client_discounts)', function () use ($pdo) {
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
});

section('indexes on client_markups + client_discounts', function () use ($pdo) {
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
});

section('foreign keys on client_markups + client_discounts', function () use ($pdo) {
    $st = $pdo->query(
        "SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME,
                REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
           FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME IN ('client_markups', 'client_discounts')
            AND REFERENCED_TABLE_NAME IS NOT NULL
          ORDER BY TABLE_NAME, CONSTRAINT_NAME"
    );
    foreach ($st->fetchAll() as $r) {
        printf(
            "  %-18s %-32s %s → %s.%s\n",
            $r['TABLE_NAME'], $r['CONSTRAINT_NAME'], $r['COLUMN_NAME'],
            $r['REFERENCED_TABLE_NAME'], $r['REFERENCED_COLUMN_NAME']
        );
    }
});

section('client_markups rows (all clients)', function () use ($pdo, $clientId) {
    // Show ALL clients' rows so we can spot cross-tenant leakage if any.
    $st = $pdo->query(
        'SELECT cm.id, cm.client_id, cm.product_id, cm.system_id,
                cm.markup_percent
           FROM client_markups cm
          ORDER BY cm.client_id, cm.product_id, cm.system_id'
    );
    $rows = $st->fetchAll();
    if (!$rows) { echo "  (no rows in the entire table)\n"; return; }
    foreach ($rows as $r) {
        $marker = (int) $r['client_id'] === $clientId ? ' *' : '  ';
        printf(
            "%s #%-4d  client=%-3s product=%-4s system=%-5s  markup=%s%%\n",
            $marker, $r['id'], $r['client_id'], $r['product_id'],
            $r['system_id'] === null ? 'NULL' : $r['system_id'],
            $r['markup_percent']
        );
    }
    echo "  (rows marked * belong to your client_id=$clientId)\n";
});

section('client_discounts rows (all clients)', function () use ($pdo, $clientId) {
    $st = $pdo->query(
        'SELECT cd.id, cd.client_id, cd.product_id, cd.system_id,
                cd.discount_percent
           FROM client_discounts cd
          ORDER BY cd.client_id, cd.product_id, cd.system_id'
    );
    $rows = $st->fetchAll();
    if (!$rows) { echo "  (no rows in the entire table)\n"; return; }
    foreach ($rows as $r) {
        $marker = (int) $r['client_id'] === $clientId ? ' *' : '  ';
        printf(
            "%s #%-4d  client=%-3s product=%-4s system=%-5s  discount=%s%%\n",
            $marker, $r['id'], $r['client_id'], $r['product_id'],
            $r['system_id'] === null ? 'NULL' : $r['system_id'],
            $r['discount_percent']
        );
    }
    echo "  (rows marked * belong to your client_id=$clientId)\n";
});

section('your products', function () use ($pdo, $clientId) {
    $st = $pdo->prepare(
        'SELECT id, name, active FROM products
          WHERE client_id = ? ORDER BY sort_order, name'
    );
    $st->execute([$clientId]);
    foreach ($st->fetchAll() as $r) {
        printf("  #%-4d  active=%d  %s\n", $r['id'], $r['active'], $r['name']);
    }
});

section('your systems', function () use ($pdo, $clientId) {
    $st = $pdo->prepare(
        'SELECT ps.id, ps.product_id, p.name AS product, ps.name, ps.active
           FROM product_systems ps
           JOIN products p ON p.id = ps.product_id
          WHERE ps.client_id = ?
          ORDER BY p.name, ps.sort_order, ps.name'
    );
    $st->execute([$clientId]);
    foreach ($st->fetchAll() as $r) {
        printf(
            "  #%-4d  product=#%-4s %-25s  system=%s  active=%d\n",
            $r['id'], $r['product_id'], $r['product'], $r['name'], $r['active']
        );
    }
});

section('MySQL session sql_mode', function () use ($pdo) {
    $mode = (string) $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
    echo "  $mode\n";
    echo '  STRICT_*: ' . (str_contains($mode, 'STRICT_') ? 'on' : 'off') . "\n";
});

echo "\nDone.\n";
