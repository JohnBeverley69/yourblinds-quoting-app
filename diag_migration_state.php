<?php
declare(strict_types=1);

/**
 * Diagnose: which migrate_*.php migrations have been applied to the LIVE DB?
 *
 * Hit /diag_migration_state.php while logged in as a super-admin.
 *
 * Read-only. Makes NO schema changes — it only introspects information_schema
 * for the columns/tables each migration adds and reports APPLIED / PENDING /
 * PARTIAL per migration. Use it to decide which migrate_*.php scripts still
 * need running (each is idempotent; run with ?apply=1 where applicable).
 *
 * The live DB is only reachable from the server (DB_HOST=localhost), so this
 * has to run on production. Delete this file once the state is confirmed.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth/middleware.php';
requireSuperAdmin();

header('Content-Type: text/plain; charset=utf-8');

$pdo = db();

/** Does a column exist on the current schema? */
function col_exists(PDO $pdo, string $table, string $col): bool
{
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $st->execute([$table, $col]);
    return (bool) $st->fetchColumn();
}

/** Does a table exist on the current schema? */
function table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $st->execute([$table]);
    return (bool) $st->fetchColumn();
}

/** Fetch a column's SQL type (e.g. 'varchar(20)', "enum('a','b')"), or '' if absent. */
function col_type(PDO $pdo, string $table, string $col): string
{
    $st = $pdo->prepare(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $st->execute([$table, $col]);
    return (string) ($st->fetchColumn() ?: '');
}

/**
 * Each migration maps to a list of "checks". A check returns [label, bool ok].
 * A migration is APPLIED when all checks pass, PENDING when none pass, and
 * PARTIAL otherwise (half-run — worth a closer look).
 */
$migrations = [
    'migrate_auto_receipt.php' => [
        ['quotes.receipt_sent_at',              col_exists($pdo, 'quotes', 'receipt_sent_at')],
        ['client_settings.feature_auto_receipt', col_exists($pdo, 'client_settings', 'feature_auto_receipt')],
    ],
    'migrate_bank_details.php' => [
        ['client_settings.bank_account_name',    col_exists($pdo, 'client_settings', 'bank_account_name')],
        ['client_settings.bank_sort_code',       col_exists($pdo, 'client_settings', 'bank_sort_code')],
        ['client_settings.bank_account_number',  col_exists($pdo, 'client_settings', 'bank_account_number')],
        ['client_settings.payment_instructions', col_exists($pdo, 'client_settings', 'payment_instructions')],
    ],
    'migrate_help_videos.php' => [
        ['table help_videos', table_exists($pdo, 'help_videos')],
    ],
    'migrate_line_charge.php' => [
        ['products.line_charge', col_exists($pdo, 'products', 'line_charge')],
    ],
    'migrate_parent_match_all.php' => [
        ['product_extras.parent_match_all', col_exists($pdo, 'product_extras', 'parent_match_all')],
    ],
    'migrate_pricing_basis.php' => [
        ['client_settings.pricing_basis', col_exists($pdo, 'client_settings', 'pricing_basis')],
    ],
    'migrate_quote_status_column.php' => [
        // Applied = status is VARCHAR (the migration widened it from ENUM).
        ['quotes.status is VARCHAR (not ENUM)',
            stripos(col_type($pdo, 'quotes', 'status'), 'varchar') === 0],
    ],
    'migrate_supplier_request_email.php' => [
        ['supplier_requests.email', col_exists($pdo, 'supplier_requests', 'email')],
    ],
    'migrate_wt_charge.php' => [
        ['quotes.wt_amount',           col_exists($pdo, 'quotes', 'wt_amount')],
        ['client_settings.feature_wt', col_exists($pdo, 'client_settings', 'feature_wt')],
    ],
];

echo "Migration-state diagnostic (live DB)\n";
echo "Read-only — nothing is modified.\n";
echo "Schema: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n";
echo str_repeat('-', 62) . "\n\n";

$pending = [];
$partial = [];

foreach ($migrations as $file => $checks) {
    $passed = 0;
    foreach ($checks as $c) { if ($c[1]) $passed++; }
    $total = count($checks);

    if ($passed === $total)      { $status = 'APPLIED ✓'; }
    elseif ($passed === 0)       { $status = 'PENDING  ✗'; $pending[] = $file; }
    else                         { $status = 'PARTIAL  !'; $partial[] = $file; }

    echo str_pad($file, 38) . $status . "\n";
    // Show per-object detail only when not cleanly applied, to keep it scannable.
    if ($passed !== $total) {
        foreach ($checks as $c) {
            echo "    " . ($c[1] ? '[present] ' : '[MISSING] ') . $c[0] . "\n";
        }
    }
}

echo "\n" . str_repeat('-', 62) . "\n";
if (!$pending && !$partial) {
    echo "RESULT: all 9 migrations are APPLIED on this database. ✓\n";
} else {
    if ($pending) {
        echo "PENDING (not yet run) — run each on production:\n";
        foreach ($pending as $f) echo "  • /$f\n";
    }
    if ($partial) {
        echo "\nPARTIAL (half-applied — investigate before re-running):\n";
        foreach ($partial as $f) echo "  • /$f\n";
    }
    echo "\nAll migrate_*.php scripts are idempotent; the line_charge one applies\n";
    echo "with ?apply=1, the others apply on plain load. Re-running an already-\n";
    echo "applied migration is a safe no-op.\n";
}
