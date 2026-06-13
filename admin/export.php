<?php
declare(strict_types=1);

/**
 * Client data export — a tenant admin downloads their OWN quotes + orders
 * (with line items) as a human-readable file. NOT a SQL dump — businesses
 * want something they can open.
 *
 *   ?format=xlsx  → multi-sheet Excel (Quotes & Orders + Line items). The
 *                   full backup; opens in Excel / Google Sheets / Numbers.
 *   ?format=pdf   → a one-page-per-batch printable summary list of orders.
 *
 * Admin-gated, tenant-scoped (current user's client_id only).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/payments_ledger.php';

requireAdmin();

@set_time_limit(0);
@ini_set('memory_limit', '512M');

$user     = current_user();
$clientId = (int) $user['client_id'];
$pdo      = db();
$format   = strtolower((string) ($_GET['format'] ?? 'xlsx'));
if (!in_array($format, ['xlsx', 'pdf'], true)) $format = 'xlsx';

// Company name — filename + report title.
$cStmt = $pdo->prepare('SELECT company_name FROM clients WHERE id = ? LIMIT 1');
$cStmt->execute([$clientId]);
$company = trim((string) ($cStmt->fetchColumn() ?: '')) ?: 'YourBlinds';

// Quotes + orders (everything in the funnel) for this tenant.
$qStmt = $pdo->prepare(
    "SELECT q.id, q.quote_number, q.status, q.total,
            q.deposit_amount, q.deposit_paid_at, q.created_at, q.accepted_at,
            COALESCE(c.name, q.end_customer_name, '')         AS customer_name,
            COALESCE(c.postcode, q.end_customer_postcode, '') AS postcode
       FROM quotes q
       LEFT JOIN customers c ON c.id = q.customer_id
      WHERE q.client_id = ?
      ORDER BY COALESCE(q.accepted_at, q.created_at) DESC, q.id DESC"
);
$qStmt->execute([$clientId]);
$quotes = $qStmt->fetchAll();

// Payments per quote (for received / outstanding). Optional table.
$payByQuote = [];
try {
    $pStmt = $pdo->prepare(
        'SELECT quote_id, COALESCE(SUM(amount), 0) AS s
           FROM payments WHERE client_id = ? GROUP BY quote_id'
    );
    $pStmt->execute([$clientId]);
    foreach ($pStmt->fetchAll() as $r) $payByQuote[(int) $r['quote_id']] = (float) $r['s'];
} catch (Throwable $e) { /* no payments table — figures default to 0 */ }

// Derive received / outstanding (deposit_extra_for keeps it correct before
// AND after the deposit-ledger migration).
$qmeta = [];
foreach ($quotes as &$q) {
    $paid     = $payByQuote[(int) $q['id']] ?? 0.0;
    $received = round($paid + deposit_extra_for($q['deposit_paid_at'] ?? null, $q['deposit_amount'] ?? null), 2);
    $q['received']     = $received;
    $q['outstanding']  = round((float) $q['total'] - $received, 2);
    $q['deposit_paid'] = !empty($q['deposit_paid_at']) ? (float) ($q['deposit_amount'] ?? 0) : 0.0;
    $qmeta[(int) $q['id']] = $q;
}
unset($q);

// Line items across all of the tenant's quotes.
$lineItems = [];
$iStmt = $pdo->prepare(
    "SELECT qi.quote_id, qi.line_no, qi.room_name,
            qi.product_name_snapshot, qi.system_name_snapshot,
            qi.fabric_name_snapshot, qi.fabric_colour_snapshot,
            qi.width_mm, qi.drop_mm, qi.quantity, qi.line_total
       FROM quote_items qi
       JOIN quotes q ON q.id = qi.quote_id
      WHERE q.client_id = ?
      ORDER BY qi.quote_id, qi.line_no"
);
$iStmt->execute([$clientId]);
$lineItems = $iStmt->fetchAll();

$slug = trim((string) (preg_replace('/[^a-z0-9]+/i', '-', $company) ?? ''), '-');
if ($slug === '') $slug = 'yourblinds';
$dateStr = date('Y-m-d');

// ───────────────────────────────────────────────────────── PDF (summary)
if ($format === 'pdf') {
    require_once __DIR__ . '/../vendor/autoload.php';
    $e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $m = static fn ($n) => '£' . number_format((float) $n, 2);

    $rows = '';
    foreach ($quotes as $q) {
        $rows .= '<tr>'
              . '<td>' . $e($q['quote_number']) . '</td>'
              . '<td>' . $e($q['customer_name']) . '</td>'
              . '<td>' . $e($q['postcode']) . '</td>'
              . '<td>' . $e(ucfirst((string) $q['status'])) . '</td>'
              . '<td>' . $e($q['accepted_at'] ? date('j M Y', strtotime((string) $q['accepted_at']))
                          : ($q['created_at'] ? date('j M Y', strtotime((string) $q['created_at'])) : '')) . '</td>'
              . '<td class="num">' . $e($m($q['total'])) . '</td>'
              . '<td class="num">' . $e($m($q['received'])) . '</td>'
              . '<td class="num">' . $e($m($q['outstanding'])) . '</td>'
              . '</tr>';
    }
    if ($rows === '') $rows = '<tr><td colspan="8" style="text-align:center;color:#6b7280">No quotes or orders yet.</td></tr>';

    $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
        . 'body{font-family:helvetica,arial,sans-serif;font-size:10px;color:#1f2937}'
        . 'h1{font-size:16px;margin:0 0 2px}.sub{color:#6b7280;font-size:10px;margin:0 0 10px}'
        . 'table{width:100%;border-collapse:collapse}'
        . 'th{background:#1f3b5b;color:#fff;text-align:left;padding:5px 6px;font-size:9px}'
        . 'td{border-bottom:1px solid #e5e7eb;padding:4px 6px}'
        . '.num{text-align:right}'
        . '</style></head><body>'
        . '<h1>' . $e($company) . ' — Quotes &amp; Orders</h1>'
        . '<p class="sub">' . count($quotes) . ' record' . (count($quotes) === 1 ? '' : 's')
            . ' &middot; generated ' . $e(date('j M Y')) . '</p>'
        . '<table><thead><tr>'
        . '<th>Quote #</th><th>Customer</th><th>Postcode</th><th>Status</th><th>Date</th>'
        . '<th class="num">Total</th><th class="num">Received</th><th class="num">Outstanding</th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table></body></html>';

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', false);
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $slug . '-orders-' . $dateStr . '.pdf"');
    header('Cache-Control: no-store');
    echo $dompdf->output();
    exit;
}

// ───────────────────────────────────────────────────────── XLSX (full)
require_once __DIR__ . '/../vendor/autoload.php';

$ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

// Sheet 1 — Quotes & Orders summary.
$s1 = $ss->getActiveSheet();
$s1->setTitle('Quotes & Orders');
$s1->fromArray(
    ['Quote #', 'Customer', 'Postcode', 'Status', 'Created', 'Accepted',
     'Total £', 'Deposit paid £', 'Received £', 'Outstanding £'],
    null, 'A1'
);
$r = 2;
foreach ($quotes as $q) {
    $s1->fromArray([
        (string) $q['quote_number'],
        (string) $q['customer_name'],
        (string) $q['postcode'],
        ucfirst((string) $q['status']),
        $q['created_at']  ? date('Y-m-d', strtotime((string) $q['created_at']))  : '',
        $q['accepted_at'] ? date('Y-m-d', strtotime((string) $q['accepted_at'])) : '',
        (float) $q['total'],
        (float) $q['deposit_paid'],
        (float) $q['received'],
        (float) $q['outstanding'],
    ], null, 'A' . $r);
    $r++;
}
$s1->getStyle('A1:J1')->getFont()->setBold(true);
foreach (range('A', 'J') as $col) $s1->getColumnDimension($col)->setAutoSize(true);

// Sheet 2 — Line items.
$s2 = $ss->createSheet();
$s2->setTitle('Line items');
$s2->fromArray(
    ['Quote #', 'Customer', 'Line', 'Room', 'Product', 'System',
     'Fabric', 'Colour', 'Width (mm)', 'Drop (mm)', 'Qty', 'Line total £'],
    null, 'A1'
);
$r = 2;
foreach ($lineItems as $li) {
    $meta = $qmeta[(int) $li['quote_id']] ?? [];
    $s2->fromArray([
        (string) ($meta['quote_number'] ?? ''),
        (string) ($meta['customer_name'] ?? ''),
        (int) $li['line_no'],
        (string) $li['room_name'],
        (string) $li['product_name_snapshot'],
        (string) $li['system_name_snapshot'],
        (string) $li['fabric_name_snapshot'],
        (string) $li['fabric_colour_snapshot'],
        (int) $li['width_mm'],
        (int) $li['drop_mm'],
        (int) $li['quantity'],
        (float) $li['line_total'],
    ], null, 'A' . $r);
    $r++;
}
$s2->getStyle('A1:L1')->getFont()->setBold(true);
foreach (range('A', 'L') as $col) $s2->getColumnDimension($col)->setAutoSize(true);

$ss->setActiveSheetIndex(0);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $slug . '-orders-' . $dateStr . '.xlsx"');
header('Cache-Control: no-store');
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
$writer->save('php://output');
exit;
