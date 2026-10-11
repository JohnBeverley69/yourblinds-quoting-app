<?php
declare(strict_types=1);

/**
 * Invoices export shaped for Sage Accounting (UK, the cloud product):
 * Sales → Quick entries → Import. Included by accounts/export.php
 * (?type=sage) after it has loaded $orders for the date window — never run
 * on its own.
 *
 * Sage quick entries are ONE ROW PER INVOICE (no line detail) and each row
 * must name a customer by its Sage reference — a customer Sage doesn't
 * already have makes the import fail. So this is a .zip of:
 *   1-customers.csv  — every customer in the period with a stable reference
 *                      (YB + customer number, or YBQ + order number for a
 *                      one-off customer). Import once: Contacts → Import.
 *   2-invoices.csv   — the quick entries.
 *   READ ME.txt      — the two steps, in order.
 *
 * UNVERIFIED (Oct 2026): Sage no longer publishes the column list — its help
 * says to download the template from inside Sage. The headings below are the
 * last published layout (Type "Inv", DD/MM/YYYY, ledger 4000, VAT rate by
 * name). Check them against a live Sage template and adjust here.
 */

if (!isset($orders, $openCsv, $gbDate, $slug)) {
    http_response_code(404);
    exit;
}

const SAGE_LEDGER = '4000';   // Sales Type A — Sage's standard sales ledger

$sageRef = static fn (array $o): string => !empty($o['customer_id'])
    ? 'YB' . str_pad((string) (int) $o['customer_id'], 6, '0', STR_PAD_LEFT)
    : 'YBQ' . str_pad((string) (int) $o['id'], 5, '0', STR_PAD_LEFT);

$sageVat = static function ($vat): string {
    $v = round((float) $vat, 1);
    if ($v <= 0)            return 'Zero Rated';
    if (abs($v - 5) < .01)  return 'Lower Rate';
    return 'Standard';
};

$customers = [];
$invoices  = [];
foreach ($orders as $o) {
    $total = round((float) $o['total'], 2);
    if (abs($total) < 0.005) continue;                 // Sage rejects zero rows
    $net = isset($o['subtotal']) ? round((float) $o['subtotal'], 2) : $total;
    $ref = $sageRef($o);
    $customers[$ref] ??= [
        $ref, (string) $o['customer_name'], (string) ($o['addr1'] ?? ''), (string) ($o['addr2'] ?? ''),
        (string) ($o['town'] ?? ''), (string) ($o['county'] ?? ''), (string) ($o['postcode'] ?? ''),
        (string) $o['customer_email'], (string) ($o['phone'] ?? ''),
    ];
    $invoices[] = [
        'Inv', $ref, $gbDate($o['accepted_at'] ?: $o['created_at']), (string) $o['quote_number'], SAGE_LEDGER,
        'Order ' . $o['quote_number'],
        number_format($net, 2, '.', ''), $sageVat($o['vat_percent'] ?? 0),
        number_format(round($total - $net, 2), 2, '.', ''), number_format($total, 2, '.', ''),
        '', '', '',
    ];
}

$csv = static function (array $header, array $rows): string {
    $mem = fopen('php://temp', 'w+b');
    fwrite($mem, "\xEF\xBB\xBF");
    fputcsv_safe($mem, $header);
    foreach ($rows as $r) fputcsv_safe($mem, $r);
    rewind($mem);
    $s = (string) stream_get_contents($mem);
    fclose($mem);
    return $s;
};

$custCsv = $csv(['Customer Reference', 'Customer Name', 'Address Line 1', 'Address Line 2', 'Town/City',
                 'County', 'Postcode', 'Email', 'Telephone'], array_values($customers));
$invCsv  = $csv(['Type', 'Customer Reference', 'Date', 'Reference', 'Ledger Account', 'Details',
                 'Net', 'VAT Rate', 'VAT', 'Total', 'Analysis Type 1', 'Analysis Type 2', 'Analysis Type 3'], $invoices);
$readMe  = "Getting these invoices into Sage Accounting\r\n\r\n"
    . "1. Customers first (Sage refuses an invoice for a customer it doesn't know):\r\n"
    . "   Contacts > Import > Customers, and choose 1-customers.csv.\r\n"
    . "   Each customer has a reference starting YB. If they're already in Sage under\r\n"
    . "   another reference, change the reference in 2-invoices.csv to match yours.\r\n\r\n"
    . "2. Then the invoices: Sales > Quick entries > the arrow beside New Quick Entry >\r\n"
    . "   Import Quick Entries, and choose 2-invoices.csv.\r\n\r\n"
    . "Each order comes in as one invoice line (Sage quick entries don't carry the\r\n"
    . "individual blinds) to sales ledger 4000, with VAT as Standard / Lower Rate / Zero Rated.\r\n\r\n"
    . "If Sage says a column doesn't match, download its own template from that same\r\n"
    . "Import screen and copy these columns across: Sage occasionally renames them.\r\n";

$base = $slug . '-sage-invoices-' . date('Y-m-d');

if (!class_exists('ZipArchive')) {
    // No zip on this server: invoices only (customers must already be in Sage).
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $base . '.csv"');
    header('Cache-Control: no-store');
    echo $invCsv;
    exit;
}

$tmp = tempnam(sys_get_temp_dir(), 'ybsage');
$zip = new ZipArchive();
$zip->open($tmp, ZipArchive::OVERWRITE);
$zip->addFromString('1-customers.csv', $custCsv);
$zip->addFromString('2-invoices.csv', $invCsv);
$zip->addFromString('READ ME.txt', $readMe);
$zip->close();
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $base . '.zip"');
header('Content-Length: ' . (string) filesize($tmp));
header('Cache-Control: no-store');
readfile($tmp);
@unlink($tmp);
exit;
