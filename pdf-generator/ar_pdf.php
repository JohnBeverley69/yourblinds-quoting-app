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
