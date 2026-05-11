<?php
declare(strict_types=1);

/**
 * Widen markup_percent and discount_percent columns so big values
 * (e.g. 1000%) round-trip cleanly.
 *
 * The original schema was DECIMAL(5,2) — max 999.99. Anything ≥ 1000
 * triggered MySQL's out-of-range error in strict mode, which the
 * product Edit page caught and rolled back, leaving the value at its
 * previous 0%. To the user it looked like "save didn't work" with no
 * obvious reason.
 *
 * After: DECIMAL(8,2) — max 999999.99, well past anything sensible.
 * Same shape, more headroom.
 *
 * Idempotent — checks the existing definition and only ALTERs if
 * narrower than the target.
 *
 * Run via web: /migrate_widen_markup_columns.php   (super-admin login)
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

set_exception_handler(function (Throwable $e) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "MIGRATION FAILED\n================\n\n";
    echo 'Error: ' . $e->getMessage() . "\n";
    echo 'In:    ' . $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
});

function column_type(PDO $pdo, string $table, string $col): ?string
{
    $st = $pdo->prepare(
        'SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $st->execute([$table, $col]);
    $val = $st->fetchColumn();
    return $val !== false ? (string) $val : null;
}

$targets = [
    ['table' => 'client_markups',   'col' => 'markup_percent'],
    ['table' => 'client_discounts', 'col' => 'discount_percent'],
    // client_settings is being retired but the column is still in
    // place. Widen it too so anyone re-running an old build doesn't
    // hit the same wall.
    ['table' => 'client_settings',  'col' => 'default_markup_percent'],
];

$ops = [];
foreach ($targets as $t) {
    $current = column_type($pdo, $t['table'], $t['col']);
    if ($current === null) {
        $ops[] = "{$t['table']}.{$t['col']}: column not present (skipped)";
        continue;
    }
    if (stripos($current, 'decimal(8,2)') !== false) {
        $ops[] = "{$t['table']}.{$t['col']}: already decimal(8,2) (skipped)";
        continue;
    }
    $pdo->exec(
        "ALTER TABLE {$t['table']}
            MODIFY COLUMN {$t['col']} DECIMAL(8,2) NOT NULL DEFAULT 0.00"
    );
    $ops[] = "{$t['table']}.{$t['col']}: widened ($current → decimal(8,2))";
}

echo "Migration complete:\n";
foreach ($ops as $op) {
    echo '  - ' . $op . "\n";
}
echo "\n";
echo "Markup / discount columns now accept up to 999999.99 percent.\n";
echo "Re-try the 1000% entry on the product Edit page — it should save\n";
echo "cleanly. When you're happy, delete this file from the server.\n";
