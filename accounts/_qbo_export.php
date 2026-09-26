<?php
declare(strict_types=1);

/**
 * Invoices export shaped for QuickBooks Online (UK): Settings ⚙ → Import data
 * → Invoices. Included by accounts/export.php (?type=quickbooks) after it has
 * loaded $orders / $linesByQuote for the date window — never run on its own.
 *
 * Why a separate shape (checked against Intuit's UK help, Sep 2026):
 *   - QuickBooks refuses NEGATIVE lines, so the "Discount — agreed price" /
 *     "Adjustment" row the Xero file carries is folded into the real lines
 *     instead: every line is scaled so the invoice still totals exactly the
 *     order's agreed net, with any penny of rounding on the largest line.
 *   - It wants a line AMOUNT (and a rate), its own VAT code names
 *     ("20.0% S", "5.0% R", "No VAT"), and a Product/Service item — we fill
 *     "Blinds" (make that item in QuickBooks, or tick "add new
 *     products/services" on import).
 *   - It takes at most 100 invoices / 1,000 rows per file, so a bigger period
 *     comes as a .zip of numbered CSVs to import one after another.
 * Column headings follow QuickBooks' own example file so the mapping step
 * matches them automatically. Dates are DD/MM/YYYY; prices are before VAT.
 */

if (!isset($orders, $linesByQuote, $openCsv, $gbDate, $slug)) {
    http_response_code(404);
    exit;
}

const QBO_MAX_INVOICES = 100;
const QBO_MAX_ROWS     = 1000;
const QBO_ITEM         = 'Blinds';

$qboTaxCode = static function ($vat): string {
    $v = round((float) $vat, 1);
    if ($v <= 0)          return 'No VAT';
    if (abs($v - 20) < .01) return '20.0% S';
    if (abs($v - 5) < .01)  return '5.0% R';
    return number_format($v, 1) . '% S';
};

// ---- Build each invoice's rows, with no negative lines ------------------
$invoices = [];   // each: list of CSV rows
foreach ($orders as $o) {
    $invDate = $o['accepted_at'] ?: $o['created_at'];
    $invGb   = $gbDate($invDate);
    $dueGb   = ($invDate && strtotime((string) $invDate))
        ? date('d/m/Y', strtotime((string) $invDate . ' +' . (int) ($DUE_DAYS ?? 14) . ' days'))
        : '';
    $tax  = $qboTaxCode($o['vat_percent'] ?? 0);
    $cust = (string) $o['customer_name'];
    $no   = (string) $o['quote_number'];

    // Natural lines: [description, qty, net amount]
    $lines = [];
    foreach ($linesByQuote[(int) $o['id']] ?? [] as $l) {
        $qty = (int) $l['quantity'] > 0 ? (int) $l['quantity'] : 1;
        $bits = [];
        $prod = trim((string) ($l['product_name_snapshot'] ?? ''));
        $sys  = trim((string) ($l['system_name_snapshot']  ?? ''));
        if ($prod !== '') $bits[] = $prod . ($sys !== '' ? ' — ' . $sys : '');
        $fab = trim(implode(' ', array_filter([
            (string) ($l['fabric_name_snapshot']   ?? ''),
            (string) ($l['fabric_colour_snapshot'] ?? ''),
        ], static fn ($s) => $s !== '')));
        if ($fab !== '') $bits[] = $fab;
        if (trim((string) ($l['room_name'] ?? '')) !== '') $bits[] = '(' . trim((string) $l['room_name']) . ')';
        $lines[] = [$bits ? implode(' / ', $bits) : ('Line ' . (int) $l['line_no']), $qty, round((float) $l['line_total'], 2)];
    }

    $orderNet = isset($o['subtotal']) ? round((float) $o['subtotal'], 2) : null;
    if (!$lines) {
        $lines[] = ['Order ' . $no, 1, $orderNet ?? round((float) $o['total'], 2)];
    } elseif ($orderNet !== null) {
        // Fold any agreed-price difference into the lines (never a minus line).
        $sum = round(array_sum(array_column($lines, 2)), 2);
        if (abs($orderNet - $sum) >= 0.01 && $sum > 0) {
            $factor = $orderNet / $sum;
            foreach ($lines as &$ln) { $ln[2] = round($ln[2] * $factor, 2); }
            unset($ln);
            $rem = round($orderNet - array_sum(array_column($lines, 2)), 2);
            if (abs($rem) >= 0.01) {
                // Put the rounding penny on the biggest line, preferring qty 1.
                $best = null;
                foreach ($lines as $i => $ln) {
                    if ($best === null
                        || ($ln[1] === 1 && $lines[$best][1] !== 1)
                        || ($ln[1] === $lines[$best][1] && $ln[2] > $lines[$best][2])) {
                        $best = $i;
                    }
                }
                $lines[$best][2] = round($lines[$best][2] + $rem, 2);
            }
        }
    }

    $rows = [];
    foreach ($lines as [$desc, $qty, $amt]) {
        $rows[] = [
            $no, $cust, $invGb, $dueGb, QBO_ITEM, $desc, $qty,
            rtrim(rtrim(number_format($amt / $qty, 4, '.', ''), '0'), '.'),
            number_format($amt, 2, '.', ''), $tax,
        ];
    }
    $invoices[] = $rows;
}

// ---- Split into files QuickBooks will accept ----------------------------
$files = [[]];
$nInv = 0; $nRows = 0;
foreach ($invoices as $rows) {
    if ($nInv >= QBO_MAX_INVOICES || ($nRows + count($rows) > QBO_MAX_ROWS && $nInv > 0)) {
        $files[] = [];
        $nInv = 0; $nRows = 0;
    }
    $files[count($files) - 1][] = $rows;
    $nInv++; $nRows += count($rows);
}

$header = ['InvoiceNo', 'Customer', 'InvoiceDate', 'DueDate', 'Item(Product/Service)',
           'ItemDescription', 'ItemQuantity', 'ItemRate', 'ItemAmount', 'ItemTaxCode'];
$writeCsv = static function ($fh, array $invs) use ($header): void {
    fputcsv_safe($fh, $header);
    foreach ($invs as $rows) foreach ($rows as $r) fputcsv_safe($fh, $r);
};

$base = $slug . '-quickbooks-invoices-' . date('Y-m-d');

if (count($files) === 1 || !class_exists('ZipArchive')) {
    $fh = $openCsv($base . '.csv');
    $writeCsv($fh, array_merge(...$files));
    fclose($fh);
    exit;
}

// More than QuickBooks takes in one go: a zip of numbered parts.
$tmp = tempnam(sys_get_temp_dir(), 'ybqbo');
$zip = new ZipArchive();
$zip->open($tmp, ZipArchive::OVERWRITE);
foreach ($files as $i => $invs) {
    $mem = fopen('php://temp', 'w+b');
    fwrite($mem, "\xEF\xBB\xBF");
    $writeCsv($mem, $invs);
    rewind($mem);
    $zip->addFromString(sprintf('%s-part-%d-of-%d.csv', $base, $i + 1, count($files)), (string) stream_get_contents($mem));
    fclose($mem);
}
$zip->addFromString('READ ME.txt',
    "QuickBooks Online imports at most 100 invoices / 1,000 rows per file, so this period is split into "
    . count($files) . " files.\r\nImport them one after another: Settings > Import data > Invoices.\r\n");
$zip->close();
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $base . '.zip"');
header('Content-Length: ' . (string) filesize($tmp));
header('Cache-Control: no-store');
readfile($tmp);
@unlink($tmp);
exit;
