<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * YourBlinds — quote PDF rendering.
 *
 * Loads a quote (scoped by client_id), builds an HTML document, and uses
 * Dompdf to produce A4 PDF bytes. Returns null if the quote does not exist
 * or Dompdf is not installed (logged).
 */
function pdf_render_quote(int $quoteId, int $clientId): ?string
{
    if (!class_exists(Dompdf::class)) {
        error_log('[YourBlinds] Dompdf not installed — run "composer install" to enable PDF rendering.');
        return null;
    }

    $pdo = db();

    $qstmt = $pdo->prepare(
        'SELECT q.*,
                c.company_name, c.address1 AS client_addr1, c.address2 AS client_addr2,
                c.town AS client_town, c.county AS client_county, c.postcode AS client_postcode,
                c.email AS client_email, c.phone AS client_phone,
                cs.vat_percent, cs.quote_footer
           FROM quotes q
           JOIN clients c        ON c.id        = q.client_id
           LEFT JOIN client_settings cs ON cs.client_id = q.client_id
          WHERE q.id = ? AND q.client_id = ?
          LIMIT 1'
    );
    $qstmt->execute([$quoteId, $clientId]);
    $quote = $qstmt->fetch();
    if (!$quote) {
        return null;
    }

    $istmt = $pdo->prepare(
        'SELECT qi.*, p.name AS product_name
           FROM quote_items qi
           LEFT JOIN products p ON p.id = qi.product_id
          WHERE qi.quote_id = ?
          ORDER BY qi.line_no, qi.id'
    );
    $istmt->execute([$quoteId]);
    $items = $istmt->fetchAll();

    $html = pdf_quote_html($quote, $items);

    $options = new Options();
    $options->set('isRemoteEnabled',          false);
    $options->set('isHtml5ParserEnabled',     true);
    $options->set('defaultFont',              'helvetica');
    $options->set('chroot',                   APP_ROOT);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

/**
 * Build the printable HTML for one quote. Inline CSS — Dompdf doesn't load
 * external stylesheets when isRemoteEnabled is off (intentionally).
 */
function pdf_quote_html(array $quote, array $items): string
{
    $money   = static fn ($n)         => '&pound;' . number_format((float) $n, 2);
    $fmtDate = static function (?string $dt): string {
        if (!$dt) {
            return '&mdash;';
        }
        $ts = strtotime($dt);
        return $ts ? date('j F Y', $ts) : '&mdash;';
    };
    $fmtSize = static fn (float $v) => number_format($v, 1, '.', '');

    $clientLines = array_values(array_filter([
        (string) ($quote['client_addr1']   ?? ''),
        (string) ($quote['client_addr2']   ?? ''),
        trim(((string) ($quote['client_town'] ?? '')) . ' ' . ((string) ($quote['client_postcode'] ?? ''))),
        (string) ($quote['client_county']  ?? ''),
    ], static fn ($s) => trim((string) $s) !== ''));

    $custLines = array_values(array_filter([
        (string) ($quote['end_customer_address1'] ?? ''),
        (string) ($quote['end_customer_address2'] ?? ''),
        trim(((string) ($quote['end_customer_town'] ?? '')) . ' ' . ((string) ($quote['end_customer_postcode'] ?? ''))),
        (string) ($quote['end_customer_county']   ?? ''),
    ], static fn ($s) => trim((string) $s) !== ''));

    $vatPct = $quote['vat_percent'] !== null
        ? rtrim(rtrim(number_format((float) $quote['vat_percent'], 2, '.', ''), '0'), '.')
        : '20';

    ob_start();
    ?><!doctype html>
<html>
<head>
<meta charset="UTF-8">
<style>
@page { margin: 50px 50px 60px 50px; }
body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2937; line-height: 1.5; margin: 0; }
table { border-collapse: collapse; }
.layout { width: 100%; }
.layout td { vertical-align: top; padding: 0; }
.brand { font-size: 26px; font-weight: bold; color: #1f3b5b; line-height: 1; }
.brand .accent { color: #2563eb; }
.client-block { margin-top: 8px; font-size: 10px; color: #6b7280; line-height: 1.55; }
.client-block .name { color: #111827; font-size: 12px; font-weight: bold; }
.quote-block { text-align: right; }
.quote-block h2 { font-size: 18px; color: #111827; margin: 0 0 10px; font-weight: bold; }
.quote-block .meta { font-size: 10.5px; }
.quote-block .meta .lbl { color: #6b7280; display: inline-block; min-width: 78px; }
.quote-block .meta .val { color: #111827; font-weight: 600; }
.customer { margin: 28px 0 22px; padding: 12px 14px; background: #f9fafb; border-left: 3px solid #1f3b5b; }
.customer .label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.06em; }
.customer .name { font-weight: bold; font-size: 13px; color: #111827; margin-top: 2px; }
.customer .addr { font-size: 10.5px; color: #374151; margin-top: 4px; white-space: pre-line; }
.customer .contact { font-size: 10.5px; color: #374151; margin-top: 3px; }
.items { width: 100%; margin-top: 4px; }
.items thead th { background: #1f3b5b; color: #fff; padding: 9px 8px; text-align: left;
                  font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 600; }
.items tbody td { padding: 9px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
.items tbody tr:last-child td { border-bottom: 1px solid #d1d5db; }
.items td.num, .items th.num { text-align: right; }
.items .room { font-weight: 600; color: #111827; }
.items .desc { white-space: pre-line; color: #4b5563; font-size: 10px; margin-top: 3px; }
.items tfoot td { padding: 6px 8px; font-size: 11px; }
.items tfoot td.label { text-align: right; color: #6b7280; }
.items tfoot td.val   { text-align: right; font-weight: 600; }
.items tfoot tr.grand td { font-size: 13px; color: #111827; padding-top: 10px; }
.items tfoot tr.grand td.label { color: #111827; font-weight: 600; }
.notes { margin-top: 22px; padding: 12px 14px; background: #fffbeb; border-left: 3px solid #f59e0b; }
.notes h3 { margin: 0 0 6px; font-size: 11px; color: #92400e; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
.notes p  { margin: 0; white-space: pre-line; }
.footer { margin-top: 28px; padding-top: 12px; border-top: 1px solid #e5e7eb;
          font-size: 9px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>

<table class="layout">
<tr>
<td width="55%">
<div class="brand">Your<span class="accent">Blinds</span></div>
<div class="client-block">
<div class="name"><?= e((string) $quote['company_name']) ?></div>
<?php foreach ($clientLines as $line): ?>
<?= e((string) $line) ?><br>
<?php endforeach; ?>
<?php if (!empty($quote['client_phone'])): ?>
<?= e((string) $quote['client_phone']) ?><br>
<?php endif; ?>
<?php if (!empty($quote['client_email'])): ?>
<?= e((string) $quote['client_email']) ?>
<?php endif; ?>
</div>
</td>
<td class="quote-block">
<h2>Quote <?= e((string) $quote['quote_number']) ?></h2>
<div class="meta">
<span class="lbl">Date</span> <span class="val"><?= $fmtDate($quote['quote_date'] ?? $quote['created_at']) ?></span><br>
<span class="lbl">Valid until</span> <span class="val"><?= $fmtDate($quote['valid_until'] ?? null) ?></span><br>
<span class="lbl">Status</span> <span class="val" style="text-transform:capitalize;"><?= e((string) $quote['status']) ?></span>
</div>
</td>
</tr>
</table>

<div class="customer">
<div class="label">Quote for</div>
<div class="name"><?= e((string) $quote['end_customer_name']) ?></div>
<?php if ($custLines): ?>
<div class="addr"><?= e(implode("\n", $custLines)) ?></div>
<?php endif; ?>
<?php if (!empty($quote['end_customer_email']) || !empty($quote['end_customer_phone'])): ?>
<div class="contact">
<?php if (!empty($quote['end_customer_email'])): ?><?= e((string) $quote['end_customer_email']) ?><?php endif; ?>
<?php if (!empty($quote['end_customer_email']) && !empty($quote['end_customer_phone'])): ?> &middot; <?php endif; ?>
<?php if (!empty($quote['end_customer_phone'])): ?><?= e((string) $quote['end_customer_phone']) ?><?php endif; ?>
</div>
<?php endif; ?>
</div>

<table class="items">
<thead>
<tr>
<th width="30">#</th>
<th>Description</th>
<th width="80">Size</th>
<th class="num" width="40">Qty</th>
<th class="num" width="70">Unit</th>
<th class="num" width="75">Total</th>
</tr>
</thead>
<tbody>
<?php if (empty($items)): ?>
<tr><td colspan="6" style="text-align:center; padding:24px; color:#9ca3af;">No line items.</td></tr>
<?php else: foreach ($items as $i => $item): ?>
<tr>
<td><?= (int) ($item['line_no'] ?? ($i + 1)) ?></td>
<td>
<?php if (!empty($item['room_name'])): ?>
<div class="room"><?= e((string) $item['room_name']) ?></div>
<?php endif; ?>
<div class="desc"><?= e((string) $item['description_text']) ?></div>
</td>
<td>
<?php if (!empty($item['width']) && !empty($item['drop_value'])): ?>
<?= $fmtSize((float) $item['width']) ?>&times;<?= $fmtSize((float) $item['drop_value']) ?>m
<?php endif; ?>
</td>
<td class="num"><?= (int) $item['quantity'] ?></td>
<td class="num"><?= $money($item['sell_price']) ?></td>
<td class="num"><?= $money($item['line_total']) ?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
<tfoot>
<tr><td colspan="4"></td><td class="label">Subtotal</td><td class="val"><?= $money($quote['subtotal']) ?></td></tr>
<tr><td colspan="4"></td><td class="label">VAT (<?= e($vatPct) ?>%)</td><td class="val"><?= $money($quote['vat']) ?></td></tr>
<tr class="grand"><td colspan="4"></td><td class="label">Total</td><td class="val"><?= $money($quote['total']) ?></td></tr>
</tfoot>
</table>

<?php if (!empty($quote['notes'])): ?>
<div class="notes">
<h3>Notes</h3>
<p><?= e((string) $quote['notes']) ?></p>
</div>
<?php endif; ?>

<?php if (!empty($quote['quote_footer'])): ?>
<div class="footer"><?= e((string) $quote['quote_footer']) ?></div>
<?php else: ?>
<div class="footer"><?= e((string) $quote['company_name']) ?> &middot; Quote <?= e((string) $quote['quote_number']) ?></div>
<?php endif; ?>

</body>
</html>
<?php
    return (string) ob_get_clean();
}
