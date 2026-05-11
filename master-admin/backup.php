<?php
declare(strict_types=1);

/**
 * Database backup + restore tool. Super-admin only.
 *
 * Three operations driven by POST _action:
 *   - download : stream a full SQL dump as a downloadable .sql file
 *   - restore  : auto-snapshot the current DB, then restore from
 *                an uploaded .sql file
 *   - delete-snapshot : prune one server-side auto-snapshot
 *
 * Storage:
 *   /_backups/ inside the web root, with an .htaccess Deny-from-all
 *   so the files aren't downloadable directly. Auto-snapshots taken
 *   before a restore live here as auto-prerestore-<ts>.sql. Manual
 *   downloads stream directly to the browser and are NOT stored
 *   server-side.
 *
 * NOTE: file uploads (logos, choice images) live on the filesystem,
 * not in the DB. This tool backs up DATA ONLY — keep a separate copy
 * of /uploads if those matter for a full disaster restore.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

// Restores and big dumps can take time on shared hosting.
@set_time_limit(0);
@ini_set('memory_limit', '512M');

$user      = current_user();
$clientId  = (int) $user['client_id'];
$activeNav = 'backup';

$backupDir = __DIR__ . '/../_backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0775, true);
    // Block direct web access to the dumps. Mod_rewrite-friendly hosts
    // honour this; if .htaccess is ignored, the files still require
    // guessing the timestamped filename — but better to also leave
    // these outside the web root if your host supports it.
    @file_put_contents($backupDir . '/.htaccess', "Require all denied\nDeny from all\n");
    @file_put_contents($backupDir . '/index.html', '');
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

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

// =============================================================
//  Actions
// =============================================================

$pdo    = db();
$action = (string) ($_POST['_action'] ?? '');

if ($action === 'download') {
    csrf_check();
    $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $name   = 'yourblinds-' . $dbName . '-' . date('Y-m-d-His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    $fh = fopen('php://output', 'wb');
    pe_stream_dump($pdo, $fh);
    fclose($fh);
    exit;
}

if ($action === 'download-tenant') {
    csrf_check();
    $targetClient = (int) ($_POST['client_id'] ?? 0);
    if ($targetClient <= 0) {
        $_SESSION['flash_error'] = 'Pick a tenant to export.';
        header('Location: /master-admin/backup.php');
        exit;
    }
    // Verify the client exists + grab its name for the filename and the
    // dump header comment.
    $cStmt = $pdo->prepare('SELECT id, company_name FROM clients WHERE id = ? LIMIT 1');
    $cStmt->execute([$targetClient]);
    $clientRow = $cStmt->fetch();
    if (!$clientRow) {
        $_SESSION['flash_error'] = 'Tenant not found.';
        header('Location: /master-admin/backup.php');
        exit;
    }

    $companyLabel = (string) $clientRow['company_name'];
    // Build a filesystem-friendly slug for the filename.
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $companyLabel)) ?: 'tenant';
    $slug = trim($slug, '-');
    $name = 'yourblinds-tenant-' . $slug . '-' . date('Y-m-d-His') . '.sql';

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    $fh = fopen('php://output', 'wb');
    pe_stream_tenant_dump($pdo, $fh, $targetClient, $companyLabel);
    fclose($fh);
    exit;
}

if ($action === 'restore') {
    csrf_check();

    if (empty($_POST['confirm']) || $_POST['confirm'] !== 'yes') {
        $_SESSION['flash_error'] = 'You must tick the confirmation box to restore.';
        header('Location: /master-admin/backup.php');
        exit;
    }

    if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        $_SESSION['flash_error'] = 'No backup file was uploaded.';
        header('Location: /master-admin/backup.php');
        exit;
    }

    try {
        // Pre-restore auto-snapshot so a bad restore is never destructive.
        $autoPath = $backupDir . '/auto-prerestore-' . date('Y-m-d-His') . '.sql';
        $autoFh   = fopen($autoPath, 'wb');
        if ($autoFh === false) {
            throw new RuntimeException('Could not open auto-snapshot file for writing.');
        }
        pe_stream_dump($pdo, $autoFh);
        fclose($autoFh);

        // Run the upload.
        $result = pe_restore_file($pdo, $_FILES['file']['tmp_name']);
        $_SESSION['flash_success'] =
            'Restore complete. Ran ' . $result['ran'] . ' of ' . $result['total']
            . ' statements. Auto-snapshot saved as ' . basename($autoPath) . '.';
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Restore failed: ' . $e->getMessage()
            . ' (an auto-snapshot was taken just before — use it to roll back if needed)';
    }

    header('Location: /master-admin/backup.php');
    exit;
}

if ($action === 'delete-snapshot') {
    csrf_check();
    $name = (string) ($_POST['name'] ?? '');
    // Whitelist filename pattern so we can't be tricked into deleting
    // anything outside the backups dir.
    if ($name !== '' && preg_match('/^auto-prerestore-[\d\-]+\.sql$/', $name)) {
        $full = $backupDir . '/' . $name;
        if (is_file($full)) @unlink($full);
        $_SESSION['flash_success'] = 'Snapshot ' . $name . ' deleted.';
    } else {
        $_SESSION['flash_error'] = 'Invalid snapshot name.';
    }
    header('Location: /master-admin/backup.php');
    exit;
}

// =============================================================
//  GET — render the UI
// =============================================================

// List of server-side auto-snapshots (most recent first).
$snapshots = [];
foreach (glob($backupDir . '/auto-prerestore-*.sql') ?: [] as $path) {
    $snapshots[] = [
        'name'  => basename($path),
        'size'  => (int) @filesize($path),
        'mtime' => (int) @filemtime($path),
    ];
}
usort($snapshots, static fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

// Lightweight stats so the page tells you what'd be in a backup.
$tableCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
)->fetchColumn();
$rowApprox = (int) $pdo->query(
    "SELECT IFNULL(SUM(TABLE_ROWS), 0)
       FROM INFORMATION_SCHEMA.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
)->fetchColumn();

// All tenants, for the per-tenant export dropdown.
$tenants = $pdo->query(
    'SELECT id, company_name, active FROM clients ORDER BY company_name'
)->fetchAll();
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Backup &amp; Restore &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .danger-zone {
            border: 1px solid #fecaca; background: #fef2f2;
            border-radius: 10px; padding: 1rem 1.125rem;
        }
        .danger-zone legend {
            font-size: 0.8125rem; font-weight: 700; color: #991b1b;
            text-transform: uppercase; letter-spacing: 0.05em;
            padding: 0 0.5rem;
        }
        .check-row {
            display: flex; align-items: center; gap: 0.5rem;
            margin: 0.75rem 0;
        }
        .check-row input { width: 18px; height: 18px; }
        .snapshot-list {
            width: 100%; border-collapse: collapse; font-size: 0.9375rem;
        }
        .snapshot-list th, .snapshot-list td {
            text-align: left; padding: 0.5rem 0.625rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .snapshot-list th {
            font-size: 0.75rem; text-transform: uppercase;
            letter-spacing: 0.04em; color: #6b7280; font-weight: 600;
        }
        .snapshot-list code { font-size: 0.8125rem; color: #1f3b5b; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Backup &amp; Restore</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/index.php">&larr; Master Admin</a>
                </p>
            </div>
        </div>

        <?php if ($flashMsg): ?>
            <div class="alert alert-success" role="status"><?= e($flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
            <div class="alert alert-error" role="alert"><?= e($flashErr) ?></div>
        <?php endif; ?>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Download backup</h2>
            </div>
            <p style="color:#6b7280;font-size:0.9375rem;margin:0 0 0.75rem">
                Streams a full SQL dump of the database
                (<strong><?= $tableCount ?></strong> tables,
                ~<strong><?= number_format($rowApprox) ?></strong> rows)
                as a downloadable <code>.sql</code> file. Restore by
                re-uploading below, or by piping it into
                <code>mysql &lt;db&gt;</code> from the command line.
            </p>
            <p style="color:#6b7280;font-size:0.8125rem;margin:0 0 0.75rem">
                Backs up <strong>data only</strong> — file uploads
                (logos, fabric / option images) live in
                <code>/uploads</code> and aren't included. Keep a
                separate copy of that folder if you want a true
                disaster-recovery snapshot.
            </p>
            <form method="post" action="/master-admin/backup.php">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="download">
                <button type="submit" class="btn btn-primary">Download backup now</button>
            </form>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Per-tenant export</h2>
            </div>
            <p style="color:#6b7280;font-size:0.9375rem;margin:0 0 0.75rem">
                Exports <strong>one tenant's data only</strong> — their users,
                customers, quotes, calendar appointments, products, fabrics,
                price tables, markups. Useful for handing a tenant their own
                data takeout, migrating one tenant to a new instance, or
                investigating one tenant's records in isolation.
            </p>
            <p style="color:#6b7280;font-size:0.8125rem;margin:0 0 0.75rem">
                Loads cleanly into a <strong>fresh empty database</strong>.
                Loading it into a DB that already has these tables would
                wipe other tenants — strip the DROP/CREATE block first if
                you're doing an in-place single-tenant restore (the file's
                header comment explains).
            </p>
            <form method="post" action="/master-admin/backup.php"
                  style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="download-tenant">
                <label for="tenant-select" class="visually-hidden">Tenant</label>
                <select id="tenant-select" name="client_id" required
                        style="flex:1;min-width:240px;padding:0.5rem 0.625rem;
                               border:1px solid #d1d5db;border-radius:8px;
                               font:inherit;background:#fff">
                    <option value="">— Choose a tenant —</option>
                    <?php foreach ($tenants as $t): ?>
                        <option value="<?= (int) $t['id'] ?>">
                            <?= e((string) $t['company_name']) ?>
                            <?php if (!(int) $t['active']): ?> (inactive)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Download tenant export</button>
            </form>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Restore from backup</h2>
            </div>
            <fieldset class="danger-zone">
                <legend>Danger zone</legend>
                <p style="color:#991b1b;font-size:0.9375rem;margin:0 0 0.75rem">
                    Restoring <strong>drops every table and recreates
                    them from the file</strong>. Any data added since
                    the backup will be lost.
                </p>
                <p style="color:#6b7280;font-size:0.875rem;margin:0 0 0.75rem">
                    For safety, an automatic snapshot is taken just
                    before the restore runs — listed under "Auto-snapshots"
                    below. If a restore goes wrong, download that
                    snapshot and restore <em>it</em> to roll back.
                </p>
                <form method="post" action="/master-admin/backup.php"
                      enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="restore">
                    <div class="form-group" style="margin:0 0 0.5rem">
                        <label for="file" style="font-weight:600;display:block;margin-bottom:0.25rem">
                            Backup file (.sql)
                        </label>
                        <input id="file" name="file" type="file" accept=".sql,application/sql,text/plain" required>
                    </div>
                    <label class="check-row">
                        <input type="checkbox" name="confirm" value="yes" required>
                        <span>I understand this overwrites the current database.</span>
                    </label>
                    <button type="submit" class="btn btn-danger">Restore database</button>
                </form>
            </fieldset>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Auto-snapshots</h2>
            </div>
            <p style="color:#6b7280;font-size:0.875rem;margin:0 0 0.5rem">
                Snapshots taken automatically just before each restore.
                Stored in <code>/_backups/</code> on the server (blocked
                from direct download). Delete them once you're sure the
                restore worked.
            </p>
            <?php if (!$snapshots): ?>
                <p style="color:#9ca3af;font-style:italic">
                    No auto-snapshots yet. One will be created the
                    first time you run a restore.
                </p>
            <?php else: ?>
                <table class="snapshot-list">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th style="width:10rem">Taken</th>
                            <th style="width:6rem;text-align:right">Size</th>
                            <th style="width:6rem"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($snapshots as $s): ?>
                            <tr>
                                <td><code><?= e($s['name']) ?></code></td>
                                <td><?= e(date('Y-m-d H:i', $s['mtime'])) ?></td>
                                <td style="text-align:right">
                                    <?= number_format($s['size'] / 1024, 0) ?> KB
                                </td>
                                <td>
                                    <form method="post" action="/master-admin/backup.php"
                                          style="margin:0"
                                          onsubmit="return confirm('Delete this snapshot?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_action" value="delete-snapshot">
                                        <input type="hidden" name="name" value="<?= e($s['name']) ?>">
                                        <button type="submit" class="btn btn-secondary"
                                                style="padding:0.25rem 0.625rem;font-size:0.8125rem">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
