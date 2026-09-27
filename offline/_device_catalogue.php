<?php
declare(strict_types=1);

/**
 * The tablet's catalogue database: a SQLite copy of offline/_catalogue_export.php's
 * JSON that behaves like the server's MySQL for everything the pricing code does.
 * Runs on the device (PHP-in-WebAssembly) and in the parity harness.
 *
 * Two MySQL behaviours matter for identical prices:
 *   • CURDATE() for date-windowed promotions, pinned to the server's date in the
 *     snapshot, so a promotion ends offline exactly when it ends on the server.
 *   • *_ci text matching: case-insensitive and blind to trailing spaces. Band
 *     codes typed "50mm Tape" on a fabric and "50mm tape " on its price table
 *     still match on the server; without this, SQLite says "No price table".
 *     This was found by the parity proof (Venetians, tenant 51).
 */

/** Open (or create) the catalogue DB with MySQL-compatible behaviour. */
function offline_sqlite_open(string $dsnPath, string $today): PDO
{
    $pdo = new PDO('sqlite:' . $dsnPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->sqliteCreateFunction('CURDATE', static fn () => $today, 0);
    $pdo->sqliteCreateCollation('MYSQL_CI', static fn ($a, $b) =>
        strcmp(mb_strtolower(rtrim((string) $a, ' ')), mb_strtolower(rtrim((string) $b, ' '))));
    return $pdo;
}

/** Create every table from the snapshot's schema and load its rows. Returns the row count. */
function offline_sqlite_build(PDO $pdo, array $d): int
{
    $indexes = [
        'price_table_rows'        => ['price_table_id, width_mm, drop_mm', 'price_table_id, drop_mm'],
        'price_tables'            => ['client_id, product_id, system_id, band_code'],
        'extra_choice_price_rows' => ['product_extra_choice_id, width_mm'],
        'product_extra_choices'   => ['product_extra_id'],
        'product_extras'          => ['product_id'],
        'product_options'         => ['product_id'],
        'client_markups'          => ['client_id, product_id, system_id'],
        'client_discounts'        => ['client_id, product_id, system_id'],
        'trade_discounts'         => ['client_id, product_id'],
    ];
    $rows = 0;
    $pdo->beginTransaction();
    foreach ($d['schema'] as $table => $cols) {
        $defs = [];
        foreach ($cols as $col => $type) {
            $defs[] = '"' . $col . '" ' . $type . ($type === 'TEXT' ? ' COLLATE MYSQL_CI' : '')
                    . ($col === 'id' ? ' PRIMARY KEY' : '');
        }
        $pdo->exec('DROP TABLE IF EXISTS "' . $table . '"');
        $pdo->exec('CREATE TABLE "' . $table . '" (' . implode(', ', $defs) . ')');
        foreach ($indexes[$table] ?? [] as $i => $idxCols) {
            $pdo->exec("CREATE INDEX \"ix_{$table}_{$i}\" ON \"{$table}\" ({$idxCols})");
        }
        $ins = $pdo->prepare('INSERT INTO "' . $table . '" VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')');
        foreach ($d['data'][$table] ?? [] as $row) {
            $ins->execute($row);
            $rows++;
        }
    }
    $pdo->commit();
    return $rows;
}
