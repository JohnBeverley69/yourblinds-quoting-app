<?php
declare(strict_types=1);

/**
 * Accounts → CSV export for accounting software (Xero / QuickBooks / Sage).
 *
 *   ?type=invoices  → one row per order LINE ITEM, in Xero's sales-invoice
 *                     import shape (rows sharing an InvoiceNumber become one
 *                     invoice). UnitAmount is the NET (ex-VAT) line price; the
 *                     accounting package recomputes the tax from TaxType.
 *   ?type=payments  → one row per payment received (the payments ledger).
 *
 *   ?from=YYYY-MM-DD&to=YYYY-MM-DD  → optional date window. Invoices filter on
 *                     order date (accepted, else created); payments on the date
 *                     received.
 *
 * Admin-gated, behind the Accounts paid add-on, tenant-scoped.
 *
 * NOTE on codes: AccountCode defaults to 200 (Xero's standard Sales account)
 * and TaxType to the UK 20% rate name. Those are org-specific — the importer
 * lets you remap them, and the Accounts page spells this out.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';

requireAdmin();

$user     = current_user();
$clientId = (int) $user['client_id'];

// Paid add-on gate (same as the rest of the module).
acct_require_feature($clientId);

@set_time_limit(0);
@ini_set('memory_limit', '512M');

$pdo  = db();
$type = (string) ($_GET['type'] ?? 'invoices');
if (!in_array($type, ['invoices', 'payments'], true)) $type = 'invoices';

// Date window (optional). Bad/garbled dates are ignored rather than erroring.
$validDate = static fn (string $s): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
$from = trim((string) ($_GET['from'] ?? ''));
$to   = trim((string) ($_GET['to']   ?? ''));

// Defaults a UK Xero org will recognise; adjustable on import.
$ACCOUNT_CODE   = '200';                       // Xero "Sales"
$TAX_STANDARD   = '20% (VAT on Income)';       // UK standard-rated output
$TAX_ZERO       = 'No VAT';
$DUE_DAYS       = 14;                          // invoice terms

// Company name → filename slug.
$company = (string) ($pdo->query('SELECT company_name FROM clients WHERE id = ' . (int) $clientId)->fetchColumn() ?: 'yourblinds');
$slug    = trim((string) (preg_replace('/[^a-z0-9]+/i', '-', $company) ?? ''), '-') ?: 'yourblinds';

/** Open a CSV download stream with a UTF-8 BOM so Excel reads £ / accents. */
$openCsv = static function (string $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    $fh = fopen('php://output', 'wb');
    fwrite($fh, "\xEF\xBB\xBF");   // BOM
    return $fh;
};

$gbDate = static fn (?string $sqlDate): string =>
    ($sqlDate && strtotime((string) $sqlDate)) ? date('d/m/Y', strtotime((string) $sqlDate)) : '';

// =====================================================================
//  INVOICES — one row per order line item (Xero sales-invoice shape)
// =====================================================================
if ($type === 'invoices') {
    $where  = ["q.client_id = ?", "q.status IN ('accepted','ordered','fitted','invoiced','paid')"];
    $params = [$clientId];
    $dateExpr = 'COALESCE(q.accepted_at, q.created_at)';
    if ($validDate($from)) { $where[] = "$dateExpr >= ?"; $params[] = $from . ' 00:00:00'; }
    if ($validDate($to))   { $where[] = "$dateExpr < ?";  $params[] = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'; }
    $whereSql = implode(' AND ', $where);

    $orders = $pdo->prepare(
        "SELECT q.id, q.quote_number, q.vat_percent, q.total, q.accepted_at, q.created_at,
                COALESCE(c.name, q.end_customer_name, 'Customer') AS customer_name,
                COALESCE(c.email, '')                              AS customer_email
           FROM quotes q
           LEFT JOIN customers c ON c.id = q.customer_id
          WHERE $whereSql
          ORDER BY $dateExpr, q.id"
    );
    $orders->execute($params);
    $orders = $orders->fetchAll(PDO::FETCH_ASSOC);

    // Line items for those orders, in one trip.
    $linesByQuote = [];
    if ($orders) {
        $ids = array_map(static fn ($o) => (int) $o['id'], $orders);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $li  = $pdo->prepare(
            "SELECT quote_id, line_no, room_name, product_name_snapshot, system_name_snapshot,
                    fabric_name_snapshot, fabric_colour_snapshot, quantity, line_total
               FROM quote_items WHERE quote_id IN ($ph) ORDER BY quote_id, line_no"
        );
        $li->execute($ids);
        foreach ($li->fetchAll(PDO::FETCH_ASSOC) as $r) $linesByQuote[(int) $r['quote_id']][] = $r;
    }

    $fh = $openCsv($slug . '-invoices-' . date('Y-m-d') . '.csv');
    fputcsv($fh, [
        'ContactName', 'EmailAddress', 'InvoiceNumber', 'InvoiceDate', 'DueDate',
        'Description', 'Quantity', 'UnitAmount', 'AccountCode', 'TaxType',
    ]);

    foreach ($orders as $o) {
        $invDate = $o['accepted_at'] ?: $o['created_at'];
        $invGb   = $gbDate($invDate);
        $dueGb   = ($invDate && strtotime((string) $invDate))
            ? date('d/m/Y', strtotime((string) $invDate . ' +' . $DUE_DAYS . ' days'))
            : '';
        $taxType = ((float) ($o['vat_percent'] ?? 0) > 0) ? $TAX_STANDARD : $TAX_ZERO;
        $lines   = $linesByQuote[(int) $o['id']] ?? [];

        if (!$lines) {
            // Order with no captured lines — emit a single summary line so the
            // invoice total still lands.
            fputcsv($fh, [
                $o['customer_name'], $o['customer_email'], $o['quote_number'], $invGb, $dueGb,
                'Order ' . $o['quote_number'], 1, number_format((float) $o['total'], 2, '.', ''),
                $ACCOUNT_CODE, $taxType,
            ]);
            continue;
        }

        foreach ($lines as $l) {
            $qty  = (int) $l['quantity'] > 0 ? (int) $l['quantity'] : 1;
            $unit = round((float) $l['line_total'] / $qty, 2);

            $bits = [];
            $prod = trim((string) ($l['product_name_snapshot'] ?? ''));
            $sys  = trim((string) ($l['system_name_snapshot']  ?? ''));
            if ($prod !== '') $bits[] = $prod . ($sys !== '' ? ' — ' . $sys : '');
            $fab = trim(implode(' ', array_filter([
                (string) ($l['fabric_name_snapshot']   ?? ''),
                (string) ($l['fabric_colour_snapshot'] ?? ''),
            ], static fn ($s) => $s !== '')));
            if ($fab !== '')                       $bits[] = $fab;
            if (trim((string) ($l['room_name'] ?? '')) !== '') $bits[] = '(' . trim((string) $l['room_name']) . ')';
            $desc = $bits ? implode(' / ', $bits) : ('Line ' . (int) $l['line_no']);

            fputcsv($fh, [
                $o['customer_name'], $o['customer_email'], $o['quote_number'], $invGb, $dueGb,
                $desc, $qty, number_format($unit, 2, '.', ''), $ACCOUNT_CODE, $taxType,
            ]);
        }
    }
    fclose($fh);
    exit;
}

// =====================================================================
//  PAYMENTS — one row per payment received (the ledger)
// =====================================================================
$where  = ['p.client_id = ?'];
$params = [$clientId];
if ($validDate($from)) { $where[] = 'p.received_at >= ?'; $params[] = $from; }
if ($validDate($to))   { $where[] = 'p.received_at <= ?'; $params[] = $to; }
$whereSql = implode(' AND ', $where);

$depSel = payments_has_is_deposit() ? 'p.is_deposit' : '0 AS is_deposit';
$rows = $pdo->prepare(
    "SELECT p.received_at, p.amount, p.method, p.reference, $depSel,
            COALESCE(c.name, 'Customer') AS customer_name,
            COALESCE(qq.quote_number, '') AS quote_number
       FROM payments p
       LEFT JOIN customers c  ON c.id  = p.customer_id
       LEFT JOIN quotes    qq ON qq.id = p.quote_id
      WHERE $whereSql
      ORDER BY p.received_at, p.id"
);
$rows->execute($params);

$fh = $openCsv($slug . '-payments-' . date('Y-m-d') . '.csv');
fputcsv($fh, ['Date', 'InvoiceNumber', 'Customer', 'Amount', 'Method', 'Reference', 'Type']);
foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $p) {
    fputcsv($fh, [
        $gbDate($p['received_at'] ?? null),
        $p['quote_number'],
        $p['customer_name'],
        number_format((float) $p['amount'], 2, '.', ''),
        acct_method_label((string) ($p['method'] ?? '')),
        (string) ($p['reference'] ?? ''),
        ((int) ($p['is_deposit'] ?? 0) === 1) ? 'Deposit' : 'Payment',
    ]);
}
fclose($fh);
exit;
