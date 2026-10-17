<?php
declare(strict_types=1);

/**
 * CLEAN SLATE — clear every account's quotes, orders and customers, with their
 * whole trail, ready for go-live (John, 2026-10-17).
 *
 * CLEARED, for EVERY account (Beverley, white-label factories, trade accounts):
 *   - quotes / orders, their lines and options, and their floor, factory and
 *     supplier records (jobs, blind jobs, streams, scan logs, purchase-order
 *     sends, remakes, factory parts, offline resend markers);
 *   - paperwork and money: factory invoices, delivery notes, credit notes,
 *     payments and allocations, statement-email log, retail payments and
 *     deposits, and the QuickBooks sync rows and log for them;
 *   - customers (with their own discounts and markups), EVERY calendar
 *     appointment (fittings, measures, everything), online bookings, and every
 *     office-calendar entry (John: "all booking of fits, calendar entries");
 *   - the text/email log (Messages) and the send-usage log.
 *   Quote numbers restart at 0001 by themselves: they are worked out from the
 *   highest number still on file.
 *
 * KEPT (John: "all of the price tables and the options… all products are to
 * remain"): accounts, users, settings, the whole catalogue (products, systems,
 * fabrics, options, price tables), trade discounts and markups, suppliers, build
 * rules and worksheets, QuickBooks connections and customer matches, bought text
 * bundles, PayPal and support records, calendar feed links and online-booking settings.
 *
 * Bank feed: lines are KEPT (the feed would only fetch them again) but unmatched
 * and moved to Ignored, so nothing points at a deleted payment. "Unignore" on
 * Master Admin → Bank brings one back.
 *
 * Safety: super-admin; GET shows a dry run with counts and changes nothing. It
 * only clears with &apply=1&confirm=CLEAR-EVERYTHING, and refuses unless a full
 * backup was taken in the last 24 hours (Settings → Backup). One transaction:
 * all of it or none of it.
 *
 *   /setup/tools/clean_slate.php                                   → dry run
 *   /setup/tools/clean_slate.php?apply=1&confirm=CLEAR-EVERYTHING  → clear
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

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$apply = !empty($_GET['apply']) && (string) ($_GET['confirm'] ?? '') === 'CLEAR-EVERYTHING';

$tableExists = static function (string $t) use ($pdo): bool {
    $s = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    $s->execute([$t]);
    return (bool) $s->fetchColumn();
};
$colExists = static function (string $t, string $c) use ($pdo): bool {
    $s = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
    $s->execute([$t, $c]);
    return (bool) $s->fetchColumn();
};

// What gets cleared: [table, WHERE (''=every row), plain-English name].
// Children before parents; every table is cleared in full unless a WHERE says otherwise.
$plan = [
    ['factory_blind_streams',          '', 'floor progress per blind'],
    ['factory_scan_log',               '', 'scanner log'],
    ['factory_blind_jobs',             '', 'blinds on the floor'],
    ['factory_jobs',                   '', 'factory order records'],
    ['order_factory_parts',            '', 'per-factory order parts'],
    ['supplier_orders',                '', 'purchase orders sent to suppliers'],
    ['factory_remake_items',           '', 'remake lines'],
    ['factory_remakes',                '', 'remakes'],
    ['factory_ar_payment_allocations', '', 'payment allocations'],
    ['factory_ar_credit_note_lines',   '', 'credit note lines'],
    ['factory_ar_credit_notes',        '', 'credit notes'],
    ['factory_ar_invoice_lines',       '', 'invoice lines'],
    ['factory_ar_invoice_orders',      '', 'invoice ↔ order links'],
    ['factory_ar_invoices',            '', 'factory invoices'],
    ['factory_ar_delivery_note_lines', '', 'delivery note lines'],
    ['factory_ar_delivery_notes',      '', 'delivery notes'],
    ['factory_ar_payments',            '', 'factory payments received'],
    ['factory_ar_statement_emails',    '', 'statement-email log'],
    ['accounting_sync',                '', 'QuickBooks sent-invoice/payment records'],
    ['accounting_log',                 '', 'QuickBooks log'],
    ['payments',                       '', 'retail payments and deposits'],
    ['online_bookings',                '', 'online bookings'],
    ['appointments',                   '', 'calendar appointments (all)'],
    ['factory_office_entries',         '', 'office-calendar entries (all)'],
    ['quote_item_extras',              '', 'quote line options'],
    ['quote_items',                    '', 'quote lines'],
    ['quotes',                         '', 'quotes and orders'],
    ['offline_refs',                   '', 'offline resend markers'],
    ['messages',                       '', 'texts and emails log (Messages)'],
    ['tenant_send_log',                '', 'send-usage log'],
    ['customer_discounts',             '', 'customer discounts'],
    ['customer_markups',               '', 'customer markups'],
    ['customers',                      '', 'customers'],
];

echo $apply ? "CLEAN SLATE — CLEARING\n\n" : "CLEAN SLATE — DRY RUN (nothing is changed)\n\n";

// The backup gate (the go-live checklist reads the same column).
$fac = function_exists('factory_client_id') ? (int) factory_client_id() : 3;
$lastBackup = null;
try {
    $s = $pdo->prepare('SELECT last_backup_at FROM clients WHERE id = ? LIMIT 1');
    $s->execute([$fac]);
    $lastBackup = $s->fetchColumn() ?: null;
} catch (Throwable $e) { /* column absent */ }
$backupFresh = $lastBackup !== null && strtotime((string) $lastBackup) >= time() - 86400;
echo 'Last full backup: ' . ($lastBackup ? date('j M Y H:i', strtotime((string) $lastBackup)) : 'never recorded')
   . ($backupFresh ? "  ✓ within 24 hours\n\n" : "  ✗ take a fresh one first (Settings → Backup)\n\n");

// Per-account picture.
echo "Per account (quotes/orders · customers):\n";
$acc = $pdo->query(
    "SELECT c.id, c.company_name,
            (SELECT COUNT(*) FROM quotes q WHERE q.client_id = c.id) AS q,
            (SELECT COUNT(*) FROM customers u WHERE u.client_id = c.id) AS cu
       FROM clients c
     HAVING q > 0 OR cu > 0
   ORDER BY q DESC, c.company_name"
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($acc as $a) {
    printf("  %-40s %5d · %5d\n", mb_substr((string) $a['company_name'], 0, 40), (int) $a['q'], (int) $a['cu']);
}
if (!$acc) echo "  (none — already clear)\n";

echo "\nWhat would be cleared:\n";
$total = 0;
foreach ($plan as [$t, $where, $label]) {
    if (!$tableExists($t)) { printf("  %-36s —  (no table here)\n", $label); continue; }
    if ($where !== '' && str_contains($where, 'quote_id') && !$colExists($t, 'quote_id')) { printf("  %-36s —  (no order link here)\n", $label); continue; }
    $n = (int) $pdo->query("SELECT COUNT(*) FROM `$t`" . ($where !== '' ? " WHERE $where" : ''))->fetchColumn();
    $total += $n;
    printf("  %-36s %6d\n", $label, $n);
}
$bankN = 0;
if ($tableExists('factory_bank_transactions')) {
    $bankN = (int) $pdo->query("SELECT COUNT(*) FROM factory_bank_transactions WHERE status <> 'ignored' OR payment_id IS NOT NULL")->fetchColumn();
    printf("  %-36s %6d  (kept, unmatched → Ignored)\n", 'bank feed lines', $bankN);
}
echo "\nKept: accounts, users, settings, products, systems, fabrics, options, price tables,\n"
   . "trade discounts/markups, suppliers, build rules, worksheets, QuickBooks connections and\n"
   . "customer matches, text bundles bought, PayPal/support records, online-booking settings.\n";

if (!$apply) {
    echo "\n" . number_format($total) . " rows would be cleared. Nothing has changed.\n"
       . "To clear: take a full backup, then add &apply=1&confirm=CLEAR-EVERYTHING to this address.\n";
    exit;
}
if (!$backupFresh) {
    echo "\nREFUSED: no full backup in the last 24 hours. Take one under Settings → Backup, then run this again.\n";
    exit;
}

// An extra safety snapshot, on top of the backup rule above: the nightly backup's own dump code, to a .part file that is
// renamed only once complete. No snapshot = no clear.
require_once dirname(__DIR__, 2) . '/_partials/db_backup.php';
@set_time_limit(0);
@ini_set('memory_limit', '512M');
$backupDir = dirname(__DIR__, 2) . '/_backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0775, true);
    @file_put_contents($backupDir . '/.htaccess', "Require all denied\nDeny from all\n");
    @file_put_contents($backupDir . '/index.html', '');
}
$snap = $backupDir . '/pre-clean-slate-' . date('Y-m-d-His') . '.sql';
$fh   = @fopen($snap . '.part', 'wb');
try {
    if (!$fh) throw new RuntimeException('could not open the snapshot file for writing');
    pe_stream_dump($pdo, $fh);
    fclose($fh);
    if (!@rename($snap . '.part', $snap)) throw new RuntimeException('could not finish the snapshot file');
    $snapSize = (int) @filesize($snap);
    if ($snapSize < 10240) throw new RuntimeException('the snapshot came out far too small (' . $snapSize . ' bytes)');
} catch (Throwable $e) {
    if (is_resource($fh)) fclose($fh);
    @unlink($snap . '.part');
    echo "\nREFUSED — the safety snapshot failed, so nothing was cleared: " . $e->getMessage() . "\n";
    exit;
}
echo "\nSafety snapshot saved: _backups/" . basename($snap) . ' (' . number_format($snapSize / 1048576, 2) . " MB).\n";

$done = [];
$pdo->beginTransaction();
try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');   // every linked row goes in this same run
    foreach ($plan as [$t, $where, $label]) {
        if (!$tableExists($t)) continue;
        if ($where !== '' && str_contains($where, 'quote_id') && !$colExists($t, 'quote_id')) continue;
        $done[$label] = $pdo->exec("DELETE FROM `$t`" . ($where !== '' ? " WHERE $where" : ''));
    }
    if ($tableExists('factory_bank_transactions')) {
        $done['bank feed lines unmatched'] = $pdo->exec(
            "UPDATE factory_bank_transactions SET payment_id = NULL, status = 'ignored'
              WHERE status <> 'ignored' OR payment_id IS NOT NULL"
        );
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "\nFAILED — nothing was cleared (rolled back): " . $e->getMessage() . "\n";
    exit;
}

echo "\nCleared:\n";
foreach ($done as $label => $n) printf("  %-36s %6d\n", $label, (int) $n);
$left = (int) $pdo->query('SELECT COUNT(*) FROM quotes')->fetchColumn()
      + (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
echo "\n" . ($left === 0 ? "Done — no quotes, orders or customers left on any account.\n"
                         : "WARNING: $left quote/customer rows still present — tell Claude.\n");
