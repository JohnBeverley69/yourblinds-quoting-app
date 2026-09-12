<?php
declare(strict_types=1);

/**
 * Wholesale A/R document PDFs (Phase 2). Beverley-branded documents to its trade
 * accounts. Modelled on pdf-generator/pdf.php but two-party (Beverley letterhead +
 * account bill-to/deliver-to). Delivery notes carry NO prices. Returns PDF bytes.
 *
 * Requires composer's dompdf (guarded — returns null if unavailable).
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/** Render HTML to PDF bytes with the app's standard Dompdf setup. */
function ar_pdf_bytes(string $html): ?string
{
    if (!class_exists(Dompdf::class)) return null;
    $options = new Options();
    $options->set('isRemoteEnabled',      false);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont',          'helvetica');
    $options->set('chroot',               APP_ROOT);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    return $dompdf->output();
}

/** Embed a web-relative logo path (/uploads/logos/x.png) as a data: URI, or ''. */
function ar_logo_data_uri(?string $logoPath): string
{
    if (!$logoPath) return '';
    $abs = APP_ROOT . '/' . ltrim((string) $logoPath, '/');
    if (!is_file($abs) || !is_readable($abs)) return '';
    $bytes = @file_get_contents($abs);
    $info  = @getimagesize($abs);
    if ($bytes === false || $info === false) return '';
    return '<img src="data:' . $info['mime'] . ';base64,' . base64_encode($bytes)
         . '" alt="" style="max-height:60px;max-width:220px;display:block;margin-bottom:6px;">';
}

/**
 * Beverley's letterhead block (logo + name + address + VAT no), from its own
 * clients row passed as $factory. Shared by every A/R document.
 */
function ar_letterhead_html(array $factory): string
{
    $e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $lines = array_values(array_filter([
        (string) ($factory['address1'] ?? ''),
        (string) ($factory['address2'] ?? ''),
        trim(((string) ($factory['town'] ?? '')) . ' ' . ((string) ($factory['postcode'] ?? ''))),
        (string) ($factory['county'] ?? ''),
    ], static fn ($s) => trim($s) !== ''));
    $html = ar_logo_data_uri($factory['logo_path'] ?? null)
          . '<div style="font-weight:bold;font-size:13px;color:#111827">' . $e($factory['company_name'] ?? '') . '</div>';
    foreach ($lines as $l) $html .= '<div>' . $e($l) . '</div>';
    if (trim((string) ($factory['phone'] ?? '')) !== '') $html .= '<div>' . $e($factory['phone']) . '</div>';
    if (trim((string) ($factory['email'] ?? '')) !== '') $html .= '<div>' . $e($factory['email']) . '</div>';
    if (trim((string) ($factory['vat_number'] ?? '')) !== '') $html .= '<div>VAT No. ' . $e($factory['vat_number']) . '</div>';
    return $html;
}

/**
 * Delivery note PDF (no prices). $ctx: factory (Beverley clients row), dn_number,
 * date, deliver_to (newline block), order_ref, notes. $items: rows with product,
 * system, options[], fabric, band, width_mm, drop_mm, quantity, room, notes.
 */
function ar_render_delivery_note(array $ctx, array $items): ?string
{
    $e   = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $nl2 = static fn ($s) => nl2br(htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'));
    $mm  = static fn ($v) => ($v === null || $v === '' || (int) $v === 0) ? '—' : (string) ((int) $v) . 'mm';

    $rows = '';
    $n = 0;
    foreach ($items as $it) {
        $n++;
        $fabric = trim(implode(' / ', array_filter([
            (string) ($it['fabric'] ?? ''),
            (string) ($it['colour'] ?? ''),
            (string) ($it['code'] ?? ''),
        ], static fn ($s) => trim($s) !== '')));
        $optsHtml = '';
        foreach ((array) ($it['options'] ?? []) as $opt) {
            if (trim((string) $opt) === '') continue;
            $optsHtml .= '<br><span class="opt">+ ' . $e($opt) . '</span>';
        }
        $rows .= '<tr>'
              . '<td class="num">' . $n . '</td>'
              . '<td><strong>' . $e($it['product'] ?? '') . '</strong>'
              . ((string) ($it['system'] ?? '') !== '' ? '<br><span class="muted">' . $e($it['system']) . '</span>' : '')
              . $optsHtml . '</td>'
              . '<td>' . ($fabric !== '' ? $e($fabric) : '—')
              . ((string) ($it['band'] ?? '') !== '' ? '<br><span class="muted">Band ' . $e($it['band']) . '</span>' : '')
              . '</td>'
              . '<td>' . $e($mm($it['width_mm'] ?? null)) . ' &times; ' . $e($mm($it['drop_mm'] ?? null)) . '</td>'
              . '<td class="num">' . (int) ($it['quantity'] ?? 1) . '</td>'
              . '<td>' . $e($it['room'] ?? '')
              . ((string) ($it['notes'] ?? '') !== '' ? '<br><span class="muted">' . $nl2($it['notes']) . '</span>' : '')
              . '</td></tr>';
    }
    $totalQty = array_sum(array_map(static fn ($it) => (int) ($it['quantity'] ?? 1), $items));

    $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
        . 'body{font-family:helvetica,arial,sans-serif;font-size:11px;color:#1f2937;margin:0}'
        . '.top{width:100%;margin-bottom:10px}.top td{vertical-align:top;padding:0}'
        . '.title{font-size:22px;font-weight:bold;color:#111827;margin:0 0 2px;text-align:right}'
        . '.meta{font-size:11px;color:#374151;text-align:right}'
        . '.cols{width:100%;margin:8px 0 12px}.cols td{vertical-align:top;width:50%;padding:0}'
        . '.box-label{font-size:9px;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;margin-bottom:2px}'
        . '.box{font-size:11px;line-height:1.4}'
        . 'table.items{width:100%;border-collapse:collapse;margin-top:6px}'
        . 'table.items th{background:#1f3b5b;color:#fff;font-size:10px;text-align:left;padding:6px 7px}'
        . 'table.items td{border-bottom:1px solid #e5e7eb;padding:6px 7px;font-size:11px;vertical-align:top}'
        . 'table.items td.num,table.items th.num{text-align:center;width:34px}'
        . '.muted{color:#6b7280;font-size:10px}.opt{color:#1f3b5b;font-size:10px;font-weight:bold}'
        . '.foot{margin-top:14px;font-size:10px;color:#6b7280}'
        . '.sign{margin-top:26px;font-size:10px;color:#374151}'
        . '</style></head><body>'
        . '<table class="top"><tr>'
        . '<td style="width:55%">' . ar_letterhead_html($ctx['factory'] ?? []) . '</td>'
        . '<td style="width:45%"><div class="title">DELIVERY NOTE</div><div class="meta">'
        . ((string) ($ctx['dn_number'] ?? '') !== '' ? '<strong>' . $e($ctx['dn_number']) . '</strong><br>' : '')
        . ((string) ($ctx['date'] ?? '') !== '' ? $e($ctx['date']) . '<br>' : '')
        . ((string) ($ctx['order_ref'] ?? '') !== '' ? 'Order ref: ' . $e($ctx['order_ref']) : '')
        . '</div></td></tr></table>'
        . '<table class="cols"><tr>'
        . '<td><div class="box-label">Deliver to</div><div class="box">'
        . ((string) ($ctx['deliver_to'] ?? '') !== '' ? $nl2($ctx['deliver_to']) : '<span class="muted">— no address —</span>')
        . '</div></td><td></td></tr></table>'
        . '<table class="items"><thead><tr>'
        . '<th class="num">#</th><th>Product</th><th>Fabric / colour / code</th>'
        . '<th>Size (W &times; D)</th><th class="num">Qty</th><th>Room / notes</th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<div class="foot">' . count($items) . ' line(s), ' . (int) $totalQty . ' item(s) total.'
        . ((string) ($ctx['notes'] ?? '') !== '' ? ' &nbsp; ' . $e($ctx['notes']) : '') . '</div>'
        . '<div class="sign">Received by: ______________________________ &nbsp;&nbsp; Date: ______________</div>'
        . '</body></html>';

    return ar_pdf_bytes($html);
}

/**
 * Invoice / credit-note PDF (priced, VAT). $ctx: factory (Beverley clients row),
 * doc_title ('INVOICE'|'CREDIT NOTE'), doc_number, issue_date, due_date (invoice),
 * bill_to (newline block), order_ref, vat_percent, notes, bank (newline block),
 * watermark (e.g. 'VOID'). $items: rows with description, width_mm, drop_mm,
 * quantity, unit_net, line_net. $totals: [subtotal, vat, total]. Credit notes pass
 * positive amounts and doc_title 'CREDIT NOTE'.
 */
function ar_render_invoice(array $ctx, array $items, array $totals): ?string
{
    $e   = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $nl2 = static fn ($s) => nl2br(htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'));
    $mm  = static fn ($v) => ($v === null || $v === '' || (int) $v === 0) ? '' : (string) ((int) $v) . 'mm';
    $money = static fn ($n) => '&pound;' . number_format((float) $n, 2);
    $title = strtoupper((string) ($ctx['doc_title'] ?? 'INVOICE'));

    $rows = '';
    $n = 0;
    foreach ($items as $it) {
        $n++;
        $size = trim($mm($it['width_mm'] ?? null) . (($it['drop_mm'] ?? null) ? ' &times; ' . $mm($it['drop_mm']) : ''));
        $rows .= '<tr>'
              . '<td class="num">' . $n . '</td>'
              . '<td>' . $e($it['description'] ?? '') . ($size !== '' ? '<br><span class="muted">' . $size . '</span>' : '') . '</td>'
              . '<td class="num">' . (int) ($it['quantity'] ?? 1) . '</td>'
              . '<td class="rt">' . $money($it['unit_net'] ?? 0) . '</td>'
              . '<td class="rt">' . $money($it['line_net'] ?? 0) . '</td>'
              . '</tr>';
    }

    $vatPct = rtrim(rtrim(number_format((float) ($ctx['vat_percent'] ?? 20), 2), '0'), '.');
    $bank   = trim((string) ($ctx['bank'] ?? ''));

    $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
        . 'body{font-family:helvetica,arial,sans-serif;font-size:11px;color:#1f2937;margin:0}'
        . '.top{width:100%;margin-bottom:10px}.top td{vertical-align:top;padding:0}'
        . '.title{font-size:22px;font-weight:bold;color:#111827;margin:0 0 2px;text-align:right}'
        . '.meta{font-size:11px;color:#374151;text-align:right;line-height:1.5}'
        . '.cols{width:100%;margin:8px 0 12px}.cols td{vertical-align:top;width:50%;padding:0}'
        . '.box-label{font-size:9px;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;margin-bottom:2px}'
        . '.box{font-size:11px;line-height:1.4}'
        . 'table.items{width:100%;border-collapse:collapse;margin-top:6px}'
        . 'table.items th{background:#1f3b5b;color:#fff;font-size:10px;text-align:left;padding:6px 7px}'
        . 'table.items td{border-bottom:1px solid #e5e7eb;padding:6px 7px;font-size:11px;vertical-align:top}'
        . 'table.items td.num,table.items th.num{text-align:center;width:30px}'
        . 'table.items td.rt,table.items th.rt{text-align:right;width:76px}'
        . '.muted{color:#6b7280;font-size:10px}'
        . 'table.tot{width:46%;margin-left:54%;margin-top:8px;border-collapse:collapse}'
        . 'table.tot td{padding:4px 7px;font-size:11px}table.tot td.rt{text-align:right}'
        . 'table.tot tr.grand td{font-weight:bold;font-size:13px;border-top:2px solid #1f3b5b}'
        . '.foot{margin-top:16px;font-size:10px;color:#374151;line-height:1.5}'
        . '.wm{position:fixed;top:44%;left:0;width:100%;text-align:center;font-size:90px;font-weight:bold;color:#f3d0d0;transform:rotate(-20deg);z-index:-1}'
        . '</style></head><body>'
        . ((string) ($ctx['watermark'] ?? '') !== '' ? '<div class="wm">' . $e($ctx['watermark']) . '</div>' : '')
        . '<table class="top"><tr>'
        . '<td style="width:55%">' . ar_letterhead_html($ctx['factory'] ?? []) . '</td>'
        . '<td style="width:45%"><div class="title">' . $e($title) . '</div><div class="meta">'
        . ((string) ($ctx['doc_number'] ?? '') !== '' ? '<strong>' . $e($ctx['doc_number']) . '</strong><br>' : '')
        . 'Date: ' . $e($ctx['issue_date'] ?? '') . '<br>'
        . ((string) ($ctx['due_date'] ?? '') !== '' ? 'Due: ' . $e($ctx['due_date']) . '<br>' : '')
        . ((string) ($ctx['order_ref'] ?? '') !== '' ? 'Order ref: ' . $e($ctx['order_ref']) : '')
        . '</div></td></tr></table>'
        . '<table class="cols"><tr>'
        . '<td><div class="box-label">' . ($title === 'CREDIT NOTE' ? 'Credit to' : 'Bill to') . '</div><div class="box">'
        . ((string) ($ctx['bill_to'] ?? '') !== '' ? $nl2($ctx['bill_to']) : '<span class="muted">— no address —</span>')
        . '</div></td><td></td></tr></table>'
        . '<table class="items"><thead><tr>'
        . '<th class="num">#</th><th>Description</th><th class="num">Qty</th><th class="rt">Unit (net)</th><th class="rt">Net</th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<table class="tot">'
        . '<tr><td>Subtotal (net)</td><td class="rt">' . $money($totals['subtotal'] ?? 0) . '</td></tr>'
        . '<tr><td>VAT @ ' . $vatPct . '%</td><td class="rt">' . $money($totals['vat'] ?? 0) . '</td></tr>'
        . '<tr class="grand"><td>Total</td><td class="rt">' . $money($totals['total'] ?? 0) . '</td></tr>'
        . '</table>'
        . '<div class="foot">'
        . ((string) ($ctx['notes'] ?? '') !== '' ? $e($ctx['notes']) . '<br>' : '')
        . ($bank !== '' ? '<strong>Payment</strong><br>' . $nl2($bank) : '')
        . '</div>'
        . '</body></html>';

    return ar_pdf_bytes($html);
}

/**
 * Render an account STATEMENT — Beverley letterhead + a ledger of the account's
 * invoices (charges), credit notes and payments (credits) with a running balance,
 * opening and closing. $ctx: factory, bill_to, statement_date, from, to, watermark,
 * bank, notes. $data: opening, rows[{date,type,ref,charge,credit,balance}], closing.
 */
function ar_render_statement(array $ctx, array $data): ?string
{
    $e     = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $nl2   = static fn ($s) => nl2br(htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'));
    $money = static fn ($n) => '&pound;' . number_format((float) $n, 2);
    $fmtD  = static function ($d) { $t = $d ? strtotime((string) $d) : false; return $t ? date('j M Y', $t) : ''; };

    $opening = (float) ($data['opening'] ?? 0);
    $closing = (float) ($data['closing'] ?? 0);
    $bank    = trim((string) ($ctx['bank'] ?? ''));

    $rows = '<tr class="op"><td>' . $e($fmtD($ctx['from'] ?? '')) . '</td><td></td><td>Opening balance</td>'
          . '<td class="rt"></td><td class="rt"></td><td class="rt">' . $money($opening) . '</td></tr>';
    foreach (($data['rows'] ?? []) as $r) {
        $rows .= '<tr>'
              . '<td>' . $e($fmtD($r['date'])) . '</td>'
              . '<td>' . $e($r['ref'] ?? '') . '</td>'
              . '<td>' . $e($r['type'] ?? '') . '</td>'
              . '<td class="rt">' . (($r['charge'] ?? 0) > 0 ? $money($r['charge']) : '') . '</td>'
              . '<td class="rt">' . (($r['credit'] ?? 0) > 0 ? $money($r['credit']) : '') . '</td>'
              . '<td class="rt">' . $money($r['balance'] ?? 0) . '</td>'
              . '</tr>';
    }

    $period = ((string) ($ctx['from'] ?? '') !== '' ? $e($fmtD($ctx['from'])) . ' &ndash; ' : 'To ')
            . $e($fmtD($ctx['to'] ?? ''));

    $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
        . 'body{font-family:helvetica,arial,sans-serif;font-size:11px;color:#1f2937;margin:0}'
        . '.top{width:100%;margin-bottom:10px}.top td{vertical-align:top;padding:0}'
        . '.title{font-size:22px;font-weight:bold;color:#111827;margin:0 0 2px;text-align:right}'
        . '.meta{font-size:11px;color:#374151;text-align:right;line-height:1.5}'
        . '.cols{width:100%;margin:8px 0 12px}.cols td{vertical-align:top;width:50%;padding:0}'
        . '.box-label{font-size:9px;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;margin-bottom:2px}'
        . '.box{font-size:11px;line-height:1.4}'
        . 'table.items{width:100%;border-collapse:collapse;margin-top:6px}'
        . 'table.items th{background:#1f3b5b;color:#fff;font-size:10px;text-align:left;padding:6px 7px}'
        . 'table.items td{border-bottom:1px solid #e5e7eb;padding:6px 7px;font-size:11px;vertical-align:top}'
        . 'table.items td.rt,table.items th.rt{text-align:right;width:74px}'
        . 'table.items tr.op td{background:#f3f6fa;font-style:italic}'
        . 'table.due{width:46%;margin-left:54%;margin-top:8px;border-collapse:collapse}'
        . 'table.due td{padding:5px 7px;font-size:13px;font-weight:bold;border-top:2px solid #1f3b5b}'
        . 'table.due td.rt{text-align:right}'
        . '.foot{margin-top:16px;font-size:10px;color:#374151;line-height:1.5}'
        . '.wm{position:fixed;top:44%;left:0;width:100%;text-align:center;font-size:90px;font-weight:bold;color:#f3d0d0;transform:rotate(-20deg);z-index:-1}'
        . '</style></head><body>'
        . ((string) ($ctx['watermark'] ?? '') !== '' ? '<div class="wm">' . $e($ctx['watermark']) . '</div>' : '')
        . '<table class="top"><tr>'
        . '<td style="width:55%">' . ar_letterhead_html($ctx['factory'] ?? []) . '</td>'
        . '<td style="width:45%"><div class="title">STATEMENT</div><div class="meta">'
        . 'Date: ' . $e($fmtD($ctx['statement_date'] ?? date('Y-m-d'))) . '<br>'
        . 'Period: ' . $period
        . '</div></td></tr></table>'
        . '<table class="cols"><tr>'
        . '<td><div class="box-label">Account</div><div class="box">'
        . ((string) ($ctx['bill_to'] ?? '') !== '' ? $nl2($ctx['bill_to']) : '<span class="box-label">— no address —</span>')
        . '</div></td><td></td></tr></table>'
        . '<table class="items"><thead><tr>'
        . '<th>Date</th><th>Reference</th><th>Type</th><th class="rt">Charges</th><th class="rt">Payments / credits</th><th class="rt">Balance</th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<table class="due"><tr><td>Balance due</td><td class="rt">' . $money($closing) . '</td></tr></table>'
        . '<div class="foot">'
        . ((string) ($ctx['notes'] ?? '') !== '' ? $e($ctx['notes']) . '<br>' : '')
        . ($bank !== '' ? '<strong>Payment</strong><br>' . $nl2($bank) : '')
        . '</div>'
        . '</body></html>';

    return ar_pdf_bytes($html);
}

/* ── Open-item statement (BM style) + combined run ──────────────────────── */

/** Shared <style> inner CSS for the open-item statement (single + combined). */
function ar_statement_bm_styles(): string
{
    return 'body{font-family:helvetica,arial,sans-serif;font-size:11px;color:#1f2937;margin:0}'
        . '.stmt-page{padding:0 0 4px}'
        . '.top{width:100%;margin-bottom:10px}.top td{vertical-align:top;padding:0}'
        . '.title{font-size:22px;font-weight:bold;color:#111827;margin:0 0 2px;text-align:right}'
        . '.meta{font-size:11px;color:#374151;text-align:right;line-height:1.6}'
        . '.cols{width:100%;margin:8px 0 12px}.cols td{vertical-align:top;padding:0}'
        . '.box-label{font-size:9px;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;margin-bottom:2px}'
        . '.box{font-size:11px;line-height:1.4}'
        . 'table.items{width:100%;border-collapse:collapse;margin-top:4px}'
        . 'table.items th{background:#1f3b5b;color:#fff;font-size:9.5px;text-align:left;padding:6px 7px}'
        . 'table.items td{border-bottom:1px solid #e5e7eb;padding:5px 7px;font-size:10.5px;vertical-align:top}'
        . 'table.items td.rt,table.items th.rt{text-align:right}'
        . 'table.items tr.tot td{font-weight:bold;border-top:2px solid #1f3b5b;border-bottom:none;background:#f3f6fa}'
        . 'table.age{width:100%;border-collapse:collapse;margin-top:14px}'
        . 'table.age th{background:#e8edf3;color:#1f3b5b;font-size:9px;text-transform:uppercase;letter-spacing:.4px;text-align:right;padding:5px 7px;border:1px solid #cdd7e3}'
        . 'table.age th.first{text-align:left}'
        . 'table.age td{text-align:right;padding:6px 7px;font-size:11px;border:1px solid #cdd7e3;font-weight:bold}'
        . 'table.age td.first{text-align:left;font-weight:normal;color:#6b7280;font-size:9px;text-transform:uppercase;letter-spacing:.4px}'
        . 'table.age td.od{color:#b91c1c}'
        . '.foot{margin-top:16px;font-size:10px;color:#374151;line-height:1.5}';
}

/**
 * Build ONE account's open-item statement as an inner-body fragment (no <html>).
 * $ctx: factory, account_name, acc_ref, bill_to, statement_date, to.
 * $data: invoices[{issue_date,due_date,inv_number,order_ref,customer_ref,total,paid,outstanding}],
 *        total_outstanding, aging{current,d30,d60,d90,d90plus,total}.
 * $break = start on a new page (for the stacked combined run).
 */
function ar_statement_bm_body(array $ctx, array $data, bool $break = false): string
{
    $e     = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $nl2   = static fn ($s) => nl2br(htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'));
    $money = static fn ($n) => '&pound;' . number_format((float) $n, 2);
    $fmtD  = static function ($d) { $t = $d ? strtotime((string) $d) : false; return $t ? date('j M Y', $t) : ''; };

    $inv = $data['invoices'] ?? [];
    $rows = '';
    if (!$inv) {
        $rows = '<tr><td colspan="7" style="color:#6b7280;font-style:italic">No outstanding invoices.</td></tr>';
    } else {
        foreach ($inv as $r) {
            $rows .= '<tr>'
                . '<td>' . $e($fmtD($r['issue_date'] ?? '')) . '</td>'
                . '<td>' . $e($r['inv_number'] ?? '') . '</td>'
                . '<td>' . $e($r['order_ref'] ?? '') . '</td>'
                . '<td>' . $e($r['customer_ref'] ?? '') . '</td>'
                . '<td class="rt">' . $money($r['total'] ?? 0) . '</td>'
                . '<td class="rt">' . $money($r['paid'] ?? 0) . '</td>'
                . '<td class="rt">' . $money($r['outstanding'] ?? 0) . '</td>'
                . '</tr>';
        }
    }
    $rows .= '<tr class="tot"><td colspan="6" class="rt">Total outstanding</td>'
           . '<td class="rt">' . $money($data['total_outstanding'] ?? 0) . '</td></tr>';

    $a = $data['aging'] ?? [];
    $ageCell = static function ($v) use ($money) {
        $od = (float) $v > 0.004;
        return '<td class="' . ($od ? 'od' : '') . '">' . ($od ? $money($v) : '&mdash;') . '</td>';
    };

    $style = $break ? ' style="page-break-before:always"' : '';
    return '<div class="stmt-page"' . $style . '>'
        . '<table class="top"><tr>'
        . '<td style="width:55%">' . ar_letterhead_html($ctx['factory'] ?? []) . '</td>'
        . '<td style="width:45%"><div class="title">STATEMENT</div><div class="meta">'
        . 'As at: ' . $e($fmtD($ctx['statement_date'] ?? date('Y-m-d')))
        . '</div></td></tr></table>'
        . '<table class="cols"><tr>'
        . '<td style="width:60%"><div class="box-label">To</div><div class="box">'
        . ((string) ($ctx['bill_to'] ?? '') !== '' ? $nl2($ctx['bill_to']) : '<span class="box-label">— no address —</span>')
        . '</div></td>'
        . '<td style="width:40%;text-align:right"><div class="box-label">Account ref</div>'
        . '<div class="box">' . $e($ctx['acc_ref'] ?? '') . '</div></td>'
        . '</tr></table>'
        . '<table class="items"><thead><tr>'
        . '<th>Invoice Date</th><th>Invoice No</th><th>Order Ref</th><th>Your Reference</th>'
        . '<th class="rt">Amount</th><th class="rt">Paid</th><th class="rt">Outstanding</th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<table class="age"><thead><tr>'
        . '<th class="first">Aged (days overdue)</th><th>Current</th><th>1&ndash;30</th><th>31&ndash;60</th><th>61&ndash;90</th><th>90+</th><th>Total</th>'
        . '</tr></thead><tbody><tr>'
        . '<td class="first">Outstanding</td>'
        . $ageCell($a['current'] ?? 0)
        . $ageCell($a['d30'] ?? 0)
        . $ageCell($a['d60'] ?? 0)
        . $ageCell($a['d90'] ?? 0)
        . $ageCell($a['d90plus'] ?? 0)
        . '<td>' . $money($a['total'] ?? 0) . '</td>'
        . '</tr></tbody></table>'
        . '<div class="foot">'
        . ((string) ($ctx['notes'] ?? '') !== '' ? $e($ctx['notes']) . '<br>' : '')
        . (trim((string) ($ctx['bank'] ?? '')) !== '' ? '<strong>Payment</strong><br>' . $nl2($ctx['bank']) : '')
        . '</div>'
        . '</div>';
}

/** One account's open-item statement as a complete PDF. */
function ar_render_statement_bm(array $ctx, array $data): ?string
{
    $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
        . ar_statement_bm_styles() . '</style></head><body>'
        . ar_statement_bm_body($ctx, $data, false)
        . '</body></html>';
    return ar_pdf_bytes($html);
}

/**
 * Every account's statement stacked into ONE PDF (the run), one per page.
 * $statements = [['ctx'=>[...], 'data'=>[...]], …] (from ar_statement_bundle()).
 */
function ar_render_statements_bm_combined(array $statements): ?string
{
    $body = '';
    $first = true;
    foreach ($statements as $s) {
        $body .= ar_statement_bm_body($s['ctx'] ?? [], $s['data'] ?? [], !$first);
        $first = false;
    }
    if ($body === '') $body = '<div class="stmt-page" style="padding:20px;color:#6b7280">No accounts with an outstanding balance.</div>';
    $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
        . ar_statement_bm_styles() . '</style></head><body>'
        . $body
        . '</body></html>';
    return ar_pdf_bytes($html);
}

/**
 * Render a COMMISSION STATEMENT for a sales consultant (internal). Beverley
 * letterhead + a table of (account, product, invoiced turnover, rate, commission)
 * and the total due. $ctx: factory, consultant_name, from, to, statement_date,
 * watermark. $data: rows[{account_name,product_name,turnover,percent,commission}], total.
 */
function ar_render_commission(array $ctx, array $data): ?string
{
    $e     = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $money = static fn ($n) => '&pound;' . number_format((float) $n, 2);
    $pct   = static fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.') . '%';
    $fmtD  = static function ($d) { $t = $d ? strtotime((string) $d) : false; return $t ? date('j M Y', $t) : ''; };

    $rows = '';
    foreach (($data['rows'] ?? []) as $r) {
        $rows .= '<tr>'
              . '<td>' . $e($r['account_name'] ?? '') . '</td>'
              . '<td>' . $e($r['product_name'] ?? '') . '</td>'
              . '<td class="rt">' . $money($r['turnover'] ?? 0) . '</td>'
              . '<td class="rt">' . $pct($r['percent'] ?? 0) . '</td>'
              . '<td class="rt">' . $money($r['commission'] ?? 0) . '</td>'
              . '</tr>';
    }
    if ($rows === '') $rows = '<tr><td colspan="5" style="color:#6b7280">No commission rules or turnover in this period.</td></tr>';

    $period = ((string) ($ctx['from'] ?? '') !== '' ? $e($fmtD($ctx['from'])) . ' &ndash; ' : 'To ') . $e($fmtD($ctx['to'] ?? ''));

    $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
        . 'body{font-family:helvetica,arial,sans-serif;font-size:11px;color:#1f2937;margin:0}'
        . '.top{width:100%;margin-bottom:10px}.top td{vertical-align:top;padding:0}'
        . '.title{font-size:20px;font-weight:bold;color:#111827;margin:0 0 2px;text-align:right}'
        . '.meta{font-size:11px;color:#374151;text-align:right;line-height:1.5}'
        . '.box-label{font-size:9px;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;margin:8px 0 2px}'
        . 'table.items{width:100%;border-collapse:collapse;margin-top:6px}'
        . 'table.items th{background:#1f3b5b;color:#fff;font-size:10px;text-align:left;padding:6px 7px}'
        . 'table.items td{border-bottom:1px solid #e5e7eb;padding:6px 7px;font-size:11px;vertical-align:top}'
        . 'table.items td.rt,table.items th.rt{text-align:right;width:82px}'
        . 'table.due{width:46%;margin-left:54%;margin-top:8px;border-collapse:collapse}'
        . 'table.due td{padding:5px 7px;font-size:13px;font-weight:bold;border-top:2px solid #1f3b5b}'
        . 'table.due td.rt{text-align:right}'
        . '.foot{margin-top:16px;font-size:10px;color:#6b7280;line-height:1.5}'
        . '</style></head><body>'
        . '<table class="top"><tr>'
        . '<td style="width:55%">' . ar_letterhead_html($ctx['factory'] ?? []) . '</td>'
        . '<td style="width:45%"><div class="title">COMMISSION STATEMENT</div><div class="meta">'
        . 'Date: ' . $e($fmtD($ctx['statement_date'] ?? date('Y-m-d'))) . '<br>'
        . 'Period: ' . $period
        . '</div></td></tr></table>'
        . '<div class="box-label">Sales consultant</div>'
        . '<div style="font-size:13px;font-weight:bold">' . $e($ctx['consultant_name'] ?? '') . '</div>'
        . '<table class="items"><thead><tr>'
        . '<th>Account</th><th>Product</th><th class="rt">Turnover (net)</th><th class="rt">Rate</th><th class="rt">Commission</th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<table class="due"><tr><td>Total commission</td><td class="rt">' . $money($data['total'] ?? 0) . '</td></tr></table>'
        . '<div class="foot">Commission is calculated on invoiced net turnover (ex VAT) in the period shown.</div>'
        . '</body></html>';

    return ar_pdf_bytes($html);
}
