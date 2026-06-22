<?php
declare(strict_types=1);

/**
 * Migration: make quotes.status accept every status the app uses.
 *
 * THE BUG THIS FIXES
 * ------------------
 * The app's status lifecycle is:
 *     draft → sent → accepted → declined → ordered → fitted → invoiced → paid
 * Every one of these is used in code (transitions, pipeline columns, calendar
 * colours, the orders filter). But `quotes.status` was an ENUM that never had
 * 'fitted' added to it. On a non-strict MySQL, writing an out-of-range ENUM
 * value stores '' (empty string) SILENTLY — no error. So clicking
 * "Mark as fitted" set the status to '', which then:
 *   - vanished from the Pipeline (no column matches ''),
 *   - lost its calendar colour,
 *   - showed a blank read-only banner ("in  state"),
 *   - flipped $quoteIsOrder false → hid the deposit panel, the supplier
 *     button, AND the "Record a new payment" form (so the balance couldn't
 *     be taken).
 *
 * THE FIX
 * -------
 * Convert quotes.status to VARCHAR(20). The application already validates
 * every transition (qb_allowed_transitions), so the DB-level ENUM was the
 * only thing rejecting 'fitted' — and being an ENUM is exactly what made the
 * failure SILENT. VARCHAR(20) stores any of the eight statuses and can never
 * silently blank a value again. Then repair any rows already blanked by the
 * bug: status '' / NULL → 'fitted' (the action that caused it).
 *
 * Idempotent. Run via web: /migrate_quote_status_column.php (super-admin).
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

$ops = [];
set_exception_handler(function (Throwable $e) use (&$ops) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Migration FAILED: " . $e->getMessage() . "\n\n";
    echo "Steps completed before failure:\n";
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

echo "Migrating: quotes.status → VARCHAR(20) (so 'fitted' et al. can be stored)…\n\n";

// Report the current column definition so the before/after is visible.
$col = $pdo->query(
    "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
       FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'quotes'
        AND COLUMN_NAME  = 'status'"
)->fetch(PDO::FETCH_ASSOC);

if (!$col) {
    echo "quotes.status column not found — nothing to do (Phase-2 schema?).\n";
    exit(0);
}

echo "Current definition: {$col['COLUMN_TYPE']} "
   . ($col['IS_NULLABLE'] === 'YES' ? 'NULL' : 'NOT NULL')
   . " default " . var_export($col['COLUMN_DEFAULT'], true) . "\n\n";

// 1. Widen to a nullable VARCHAR first (no value can be rejected during the
//    conversion — an out-of-range ENUM value carries over as its '' string).
$pdo->exec("ALTER TABLE quotes MODIFY status VARCHAR(20) NULL DEFAULT 'draft'");
$ops[] = "quotes.status → VARCHAR(20) NULL DEFAULT 'draft'.";

// 2. Repair rows the bug blanked. '' / NULL was a failed 'Mark as fitted'.
$rep = $pdo->prepare("UPDATE quotes SET status = 'fitted' WHERE status = '' OR status IS NULL");
$rep->execute();
$fixed = $rep->rowCount();
$ops[] = $fixed > 0
    ? "Repaired $fixed quote(s) with a blank status → 'fitted'."
    : "No blank-status quotes to repair.";

// 3. Lock it back to NOT NULL now that no blanks/NULLs remain.
$pdo->exec("ALTER TABLE quotes MODIFY status VARCHAR(20) NOT NULL DEFAULT 'draft'");
$ops[] = "quotes.status → NOT NULL DEFAULT 'draft'.";

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\n'Mark as fitted' (and every other status) now saves correctly — the job\n";
echo "will land in the Fitted pipeline column, recolour on the calendar, and the\n";
echo "'Record a new payment' form will appear so the balance can be taken.\n";
if ($fixed > 0) {
    echo "\nNote: $fixed stuck job(s) were set to 'fitted'. If any of those wasn't\n";
    echo "actually fitted, open it and use its status buttons to set the right one.\n";
}
