<?php
declare(strict_types=1);

/**
 * Migration: one live invoice per order, enforced by the database.
 *
 * ar_create_invoice() used to rely on its callers checking "is this order
 * already invoiced?" first. Two requests at once (a double-click on the Dispatch
 * tray's "Raise invoices", or the tray and Wholesale together) could both pass
 * the check and raise two invoices for one order.
 *
 * Adds two columns to factory_ar_invoice_orders:
 *   factory_client_id — the invoicing factory (copied from the invoice)
 *   live_quote_id     — = quote_id while the invoice is live, NULL once voided
 * and a UNIQUE KEY on (live_quote_id, factory_client_id). NULLs never collide in
 * a unique index, so voided invoices (and re-issues after a void) are untouched;
 * a credited invoice is still live, matching ar_order_invoice_number().
 *
 * If live duplicates already exist they are listed and the key is NOT added —
 * void the spare invoice(s) on Wholesale and run this again.
 *
 * Additive + idempotent. Web-runnable: /setup/migrations/migrate_ar_invoice_one_per_order.php
 * (super-admin).
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

$tbl = 'factory_ar_invoice_orders';
$has = static function (string $sql, array $args) use ($pdo): bool {
    $s = $pdo->prepare($sql);
    $s->execute($args);
    return (int) $s->fetchColumn() > 0;
};
if (!$has("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$tbl])) {
    echo "$tbl not found — run migrate_ar_invoices.php first.\n";
    exit;
}
$colSql = "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?";

foreach (['factory_client_id' => 'INT NULL AFTER quote_id', 'live_quote_id' => 'INT NULL AFTER factory_client_id'] as $col => $def) {
    if ($has($colSql, [$tbl, $col])) {
        echo "$col already present — skipped.\n";
    } else {
        $pdo->exec("ALTER TABLE $tbl ADD COLUMN $col $def");
        echo "Added $tbl.$col.\n";
    }
}

// Backfill from the invoice header (re-runnable: sets the same values again).
$n = $pdo->exec(
    "UPDATE $tbl io JOIN factory_ar_invoices i ON i.id = io.invoice_id
        SET io.factory_client_id = i.factory_client_id,
            io.live_quote_id     = IF(i.status = 'void', NULL, io.quote_id)"
);
echo "Backfilled $n link row(s).\n";

if ($has("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = 'uq_io_live'", [$tbl])) {
    echo "uq_io_live already present — skipped.\n";
} else {
    $dups = $pdo->query(
        "SELECT io.live_quote_id, io.factory_client_id, GROUP_CONCAT(i.inv_number ORDER BY i.id SEPARATOR ', ') AS invs
           FROM $tbl io JOIN factory_ar_invoices i ON i.id = io.invoice_id
          WHERE io.live_quote_id IS NOT NULL
       GROUP BY io.live_quote_id, io.factory_client_id
         HAVING COUNT(*) > 1"
    )->fetchAll(PDO::FETCH_ASSOC);
    if ($dups) {
        echo "\nNOT adding the unique key — these orders already have more than one live invoice:\n";
        foreach ($dups as $d) echo "  order id {$d['live_quote_id']}: {$d['invs']}\n";
        echo "Void the spare invoice(s) on Wholesale, then run this again.\n";
        exit;
    }
    $pdo->exec("ALTER TABLE $tbl ADD UNIQUE KEY uq_io_live (live_quote_id, factory_client_id)");
    echo "Added unique key uq_io_live (live_quote_id, factory_client_id).\n";
}

echo "\nDone. An order can now have only one live (non-void) invoice per factory;\n";
echo "a second attempt is reported as \"already invoiced\".\n";
