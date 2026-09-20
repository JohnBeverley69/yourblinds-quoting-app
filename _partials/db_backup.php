<?php
declare(strict_types=1);

/**
 * Database dump + restore helpers, shared by the Backup & Restore screen and by
 * the scheduled backup (cron_backup.php).
 *
 * These used to live inside master-admin/backup.php. That file is a PAGE — it
 * requires a signed-in super-admin and writes HTML — so a cron job could not
 * borrow the dump without pretending to be a browser. Lifted out verbatim; the
 * page requires this and behaves exactly as before.
 *
 * Pure helpers: no auth, no output, no side effects beyond the handle you pass.
 */

// =============================================================
//  Helpers — SQL dump + statement splitter + restore
// =============================================================

/**
 * Per-tenant export: table → SQL fragment that restricts the dump to
 * the chosen client's rows.
 *
 * The `?` placeholder gets bound to the client_id at execute time so
 * the values are properly parameter-quoted (the table/column names
 * inside the fragment are not user input — they live in this file
 * verbatim, so the SQL fragment itself isn't an injection vector).
 *
 * Tables not listed here fall back to:
 *   - "WHERE client_id = ?" if the table has a client_id column;
 *   - otherwise skipped entirely with a comment in the output, so
 *     anyone auditing the dump can spot a gap.
 */
function pe_tenant_scoping_map(): array
{
    return [
        // -- Direct (table has a client_id / id column tied to client) --
        'clients'           => 'id = ?',
        'client_settings'   => 'client_id = ?',
        'client_users'      => 'client_id = ?',
        'client_markups'    => 'client_id = ?',
        'client_discounts'  => 'client_id = ?',
        'customers'         => 'client_id = ?',
        'quotes'            => 'client_id = ?',
        'products'          => 'client_id = ?',
        'product_systems'   => 'client_id = ?',
        'product_options'   => 'client_id = ?',
        'product_extras'    => 'client_id = ?',
        'appointments'      => 'client_id = ?',
        'price_tables'      => 'client_id = ?',

        // -- Indirect (one or two FK hops to a client-scoped parent) --
        'client_user_roles' =>
            'user_id IN (SELECT id FROM client_users WHERE client_id = ?)',
        'quote_items' =>
            'quote_id IN (SELECT id FROM quotes WHERE client_id = ?)',
        'quote_item_extras' =>
            'quote_item_id IN (SELECT id FROM quote_items
              WHERE quote_id IN (SELECT id FROM quotes WHERE client_id = ?))',
        'product_extra_choices' =>
            'product_extra_id IN (SELECT id FROM product_extras WHERE client_id = ?)',
        'product_extra_parent_choices' =>
            'product_extra_id IN (SELECT id FROM product_extras WHERE client_id = ?)',
        'extra_choice_price_rows' =>
            'product_extra_choice_id IN (SELECT id FROM product_extra_choices
              WHERE product_extra_id IN (SELECT id FROM product_extras WHERE client_id = ?))',
        'price_table_rows' =>
            'price_table_id IN (SELECT id FROM price_tables WHERE client_id = ?)',
    ];
}

/**
 * Stream a per-tenant SQL dump scoped to one client_id.
 *
 * Output shape mirrors pe_stream_dump:
 *   - DROP TABLE IF EXISTS + CREATE TABLE for every relevant table
 *     (so the file can load cleanly into a fresh empty database)
 *   - INSERT rows, filtered to the tenant via pe_tenant_scoping_map()
 *
 * Tables with no tenant scope (login_attempts, password_resets,
 * anything system-wide) are emitted in the file as commented stubs
 * so the recipient knows they were intentionally skipped.
 *
 * IMPORTANT — loading this file:
 *   - Into a FRESH empty DB: works as-is (DROP+CREATE+INSERT).
 *   - Into a DB that already contains this tenant's data:
 *     the DROP TABLE statements will WIPE EVERY OTHER TENANT too
 *     (tables are shared). For in-place tenant restores, strip the
 *     DROP/CREATE block before loading. The file's leading comment
 *     spells this out.
 */
function pe_stream_tenant_dump(PDO $pdo, $handle, int $clientId, string $companyLabel): void
{
    $write = static function (string $s) use ($handle): void {
        fwrite($handle, $s);
    };
    $map = pe_tenant_scoping_map();

    $write("-- YourBlinds — PER-TENANT export\n");
    $write('-- Tenant:    ' . $companyLabel . " (client_id=$clientId)\n");
    $write('-- Generated: ' . date('c') . "\n");
    $write('-- DB:        ' . (string) $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n");
    $write("--\n");
    $write("-- This file contains ONE tenant's data: clients, users, customers,\n");
    $write("-- quotes, products, calendar appointments, pricing — everything that\n");
    $write("-- carries (or chains back to) this client_id.\n");
    $write("--\n");
    $write("-- LOADING:\n");
    $write("--   Into a FRESH empty database: works as-is. The DROP+CREATE block\n");
    $write("--     gives you a clean schema; the INSERTs populate the one tenant.\n");
    $write("--   Into a DB that ALREADY has the same tables (incl. other tenants):\n");
    $write("--     the DROP TABLE statements will wipe every other tenant too.\n");
    $write("--     For an in-place single-tenant restore, strip the DROP/CREATE\n");
    $write("--     section and only run the INSERTs (after deleting the\n");
    $write("--     tenant's existing rows first).\n");
    $write("\n");
    $write("SET FOREIGN_KEY_CHECKS = 0;\n");
    $write("SET UNIQUE_CHECKS = 0;\n");
    $write("SET @OLD_SQL_MODE = @@SQL_MODE, SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
    $write("\n");

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    // Find which tables have a client_id column — used as the fallback
    // scope for anything not in the explicit map.
    $clientIdCols = [];
    $cidStmt = $pdo->query(
        "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND COLUMN_NAME  = 'client_id'"
    );
    foreach ($cidStmt->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $clientIdCols[$t] = true;
    }

    foreach ($tables as $table) {
        $hasMap     = isset($map[$table]);
        $hasClient  = isset($clientIdCols[$table]);

        $write("\n-- ----------------------------------------\n");
        $write("-- Table: `$table`\n");
        $write("-- ----------------------------------------\n");

        if (!$hasMap && !$hasClient) {
            // System-level table (login_attempts, password_resets, etc.) —
            // not part of one tenant's data. Skip the data but still emit
            // schema so a fresh-DB load gets a complete structure.
            $write("-- (no tenant scope — schema only, no rows)\n");
            $write("DROP TABLE IF EXISTS `$table`;\n");
            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
            $write(($create['Create Table'] ?? '') . ";\n");
            continue;
        }

        // Schema
        $write("DROP TABLE IF EXISTS `$table`;\n");
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
        $write(($create['Create Table'] ?? '') . ";\n\n");

        // Filtered data — explicit scope first, client_id fallback otherwise.
        $whereSql = $hasMap ? $map[$table] : 'client_id = ?';
        $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE $whereSql");
        $stmt->execute([$clientId]);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        $batch   = [];
        $columns = null;
        while ($row = $stmt->fetch()) {
            if ($columns === null) {
                $columns = '`' . implode('`, `', array_keys($row)) . '`';
            }
            $vals = [];
            foreach ($row as $v) {
                if ($v === null) {
                    $vals[] = 'NULL';
                } elseif (is_int($v) || is_float($v)) {
                    $vals[] = (string) $v;
                } else {
                    $vals[] = $pdo->quote((string) $v);
                }
            }
            $batch[] = '(' . implode(', ', $vals) . ')';
            if (count($batch) >= 100) {
                $write("INSERT INTO `$table` ($columns) VALUES\n  "
                    . implode(",\n  ", $batch) . ";\n");
                $batch = [];
            }
        }
        if ($batch) {
            $write("INSERT INTO `$table` ($columns) VALUES\n  "
                . implode(",\n  ", $batch) . ";\n");
        }
    }

    $write("\nSET SQL_MODE = @OLD_SQL_MODE;\n");
    $write("SET UNIQUE_CHECKS = 1;\n");
    $write("SET FOREIGN_KEY_CHECKS = 1;\n");
}

/**
 * Stream a full SQL dump of every table to the given handle.
 * Designed to be `php://output` for downloads or a file path for
 * server-side auto-snapshots.
 */
function pe_stream_dump(PDO $pdo, $handle): void
{
    $write = static function (string $s) use ($handle): void {
        fwrite($handle, $s);
    };

    $write("-- YourBlinds DB backup\n");
    $write('-- Generated: ' . date('c') . "\n");
    $write('-- DB:        ' . (string) $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n");
    $write("--\n");
    $write("-- Restore by re-uploading via /master-admin/backup.php, or\n");
    $write("-- by piping into `mysql <db>` from the command line.\n");
    $write("\n");
    $write("SET FOREIGN_KEY_CHECKS = 0;\n");
    $write("SET UNIQUE_CHECKS = 0;\n");
    $write("SET @OLD_SQL_MODE = @@SQL_MODE, SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
    $write("\n");

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $write("\n-- ----------------------------------------\n");
        $write("-- Table: `$table`\n");
        $write("-- ----------------------------------------\n");
        $write("DROP TABLE IF EXISTS `$table`;\n");

        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
        $write(($create['Create Table'] ?? '') . ";\n\n");

        // Dump data in batches of 100 rows per INSERT — big enough to
        // cut row-by-row overhead, small enough that any single line
        // stays under a sensible max_allowed_packet.
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $batch   = [];
        $columns = null;
        while ($row = $stmt->fetch()) {
            if ($columns === null) {
                $columns = '`' . implode('`, `', array_keys($row)) . '`';
            }
            $vals = [];
            foreach ($row as $v) {
                if ($v === null) {
                    $vals[] = 'NULL';
                } elseif (is_int($v) || is_float($v)) {
                    $vals[] = (string) $v;
                } else {
                    $vals[] = $pdo->quote((string) $v);
                }
            }
            $batch[] = '(' . implode(', ', $vals) . ')';
            if (count($batch) >= 100) {
                $write("INSERT INTO `$table` ($columns) VALUES\n  "
                    . implode(",\n  ", $batch) . ";\n");
                $batch = [];
            }
        }
        if ($batch) {
            $write("INSERT INTO `$table` ($columns) VALUES\n  "
                . implode(",\n  ", $batch) . ";\n");
        }
    }

    $write("\nSET SQL_MODE = @OLD_SQL_MODE;\n");
    $write("SET UNIQUE_CHECKS = 1;\n");
    $write("SET FOREIGN_KEY_CHECKS = 1;\n");
}

/**
 * Split a SQL blob into individual statements. State machine handles
 * single/double-quoted strings (with backslash escapes), -- line
 * comments, and /* ... *​/ block comments. Anything outside those
 * splits on ';'.
 */
function pe_split_sql(string $sql): array
{
    $statements = [];
    $current    = '';
    $len        = strlen($sql);
    $i          = 0;
    $inString   = false;
    $stringCh   = '';

    while ($i < $len) {
        $ch = $sql[$i];

        if ($inString) {
            $current .= $ch;
            if ($ch === '\\' && $i + 1 < $len) {
                $current .= $sql[$i + 1];
                $i += 2;
                continue;
            }
            if ($ch === $stringCh) $inString = false;
            $i++;
            continue;
        }

        // -- single-line comment → consume to newline, drop it
        if ($ch === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
            $eol = strpos($sql, "\n", $i);
            if ($eol === false) break;
            $i = $eol + 1;
            continue;
        }
        // /* block comment */ → consume to closing, drop it
        if ($ch === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
            $end = strpos($sql, '*/', $i + 2);
            if ($end === false) break;
            $i = $end + 2;
            continue;
        }
        // Open string
        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $inString = true;
            $stringCh = $ch;
            $current .= $ch;
            $i++;
            continue;
        }
        // Statement terminator
        if ($ch === ';') {
            if (trim($current) !== '') $statements[] = $current;
            $current = '';
            $i++;
            continue;
        }
        $current .= $ch;
        $i++;
    }
    if (trim($current) !== '') $statements[] = $current;
    return $statements;
}

/**
 * Run a SQL backup file against the current DB. Wraps every statement
 * in FOREIGN_KEY_CHECKS = 0 for the duration so DROP/CREATE order
 * doesn't matter, then turns checks back on.
 */
function pe_restore_file(PDO $pdo, string $path): array
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('Could not read backup file.');
    }
    $stmts   = pe_split_sql($sql);
    $ran     = 0;
    $skipped = 0;

    // Wrap the whole restore in a transaction so a SQL file that
    // errors halfway through rolls back to the pre-restore state.
    // The auto-snapshot taken just before this call is the last-resort
    // safety net; the transaction is the first-resort one. NOTE:
    // statements that DDL the schema (DROP/CREATE TABLE, ALTER) cause
    // MySQL to implicitly commit any open transaction — so for the
    // backup files we generate (which are heavy on DROP+CREATE) the
    // transaction is effectively bracket-only. Still useful for the
    // INSERT-heavy restore patterns and for clarity of intent.
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->beginTransaction();
    try {
        foreach ($stmts as $s) {
            $trim = trim($s);
            if ($trim === '') { $skipped++; continue; }
            $pdo->exec($trim);
            $ran++;
        }
        if ($pdo->inTransaction()) $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
    return ['ran' => $ran, 'skipped' => $skipped, 'total' => count($stmts)];
}
