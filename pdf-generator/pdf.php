<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../_partials/legal_text.php';

/**
 * YourBlinds — customer-facing quote PDF rendering.
 *
 * The output is intentionally **size-free** — the customer sees product,
 * fabric, colour, room, extras, quantity, price. Width and drop are NOT
 * rendered (business rule: trade companies don't want customers shopping
 * the same blinds elsewhere with dimensions in hand).
 *
 * Loads a quote (scoped by client_id), builds an HTML document, and uses
 * Dompdf to produce A4 PDF bytes. Returns null if the quote does not
 * exist or Dompdf is not installed (logged).
 */
function pdf_render_quote(int $quoteId, int $clientId): ?string
{
    if (!class_exists(Dompdf::class)) {
        error_log('[YourBlinds] Dompdf not installed — run "composer install" to enable PDF rendering.');
        return null;
    }

    $pdo = db();

    // Quote header + the trade company's branding fields, all in one trip.
    $qstmt = $pdo->prepare(
        'SELECT q.*,
                c.company_name AS trade_company_name,
                c.address1     AS trade_addr1,
                c.address2     AS trade_addr2,
                c.town         AS trade_town,
                c.county       AS trade_county,
                c.postcode     AS trade_postcode,
                c.email        AS trade_email,
                c.phone        AS trade_phone,
                c.vat_number   AS trade_vat_number,
                c.logo_path    AS trade_logo,
                cs.quote_footer
           FROM quotes q
           JOIN clients          c  ON c.id        = q.client_id
           LEFT JOIN client_settings cs ON cs.client_id = q.client_id
          WHERE q.id = ? AND q.client_id = ?
          LIMIT 1'
    );
    $qstmt->execute([$quoteId, $clientId]);
    $quote = $qstmt->fetch();
    if (!$quote) {
        return null;
    }

    // Terms & Conditions + Privacy Policy (optional columns). Loaded with a
    // separate guarded query — kept out of the main SELECT so the PDF still
    // renders if migrate_terms_conditions.php hasn't been run yet.
    $quote['terms_conditions'] = null;
    $quote['privacy_policy']   = null;
    $legalAvailable = false;
    try {
        $lstmt = $pdo->prepare(
            'SELECT terms_conditions, privacy_policy FROM client_settings WHERE client_id = ? LIMIT 1'
        );
        $lstmt->execute([$clientId]);
        $legalAvailable = true;
        if ($lrow = $lstmt->fetch()) {
            $quote['terms_conditions'] = $lrow['terms_conditions'];
            $quote['privacy_policy']   = $lrow['privacy_policy'];
        }
    } catch (Throwable $e) { /* columns not present yet — skip */ }
    // NULL / no settings row → standard template (live by default).
    if ($legalAvailable) {
        $quote['terms_conditions'] = legal_effective_terms($quote['terms_conditions'] ?? null);
        $quote['privacy_policy']   = legal_effective_privacy($quote['privacy_policy'] ?? null);
    }

    // Items, in line-no order. We use the snapshot fields (frozen at quote
    // time) rather than current product names, so re-rendering an old quote
    // shows what was sold then, not what the catalogue says now.
    $istmt = $pdo->prepare(
        'SELECT * FROM quote_items WHERE quote_id = ? ORDER BY line_no, id'
    );
    $istmt->execute([$quoteId]);
    $items = $istmt->fetchAll();

    // Per-item extras. One query, IN clause, fold into a map so the HTML
    // builder can pull them out per row without N+1.
    $extrasByItem = [];
    if ($items) {
        $itemIds = array_map(static fn ($r) => (int) $r['id'], $items);
        $ph = implode(',', array_fill(0, count($itemIds), '?'));
        // user_value column may not exist yet — fall back to column-less.
        try {
            $est = $pdo->prepare(
                "SELECT quote_item_id, extra_name_snapshot, choice_label_snapshot,
                        amount_applied, user_value
                   FROM quote_item_extras
                  WHERE quote_item_id IN ($ph)
                  ORDER BY id"
            );
            $est->execute($itemIds);
            $rows = $est->fetchAll();
        } catch (Throwable $e) {
            $est = $pdo->prepare(
                "SELECT quote_item_id, extra_name_snapshot, choice_label_snapshot, amount_applied
                   FROM quote_item_extras
                  WHERE quote_item_id IN ($ph)
                  ORDER BY id"
            );
            $est->execute($itemIds);
            $rows = $est->fetchAll();
            foreach ($rows as &$r) $r['user_value'] = null;
            unset($r);
        }
        foreach ($rows as $r) {
            $extrasByItem[(int) $r['quote_item_id']][] = $r;
        }
    }

    $html = pdf_quote_html($quote, $items, $extrasByItem);

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

/**
 * Render a SUPPLIER purchase-order PDF (A4) — bytes, for emailing to a
 * supplier. Unlike the customer quote, this DOES show dimensions and is
 * spec-only (no customer prices). $ctx carries the buyer/company + delivery
 * details; $items is the spec list for this one supplier. Returns null if
 * Dompdf isn't installed.
 */
function pdf_render_supplier_order(array $ctx, array $items): ?string
{
    if (!class_exists(Dompdf::class)) {
        error_log('[YourBlinds] Dompdf not installed — cannot render supplier order PDF.');
        return null;
    }

    $e   = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $mm  = static fn ($v) => ($v === null || $v === '' || (int) $v === 0) ? '—' : number_format((int) $v) . ' mm';
    $nl2 = static fn ($s) => nl2br(htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'));

    $rows = '';
    $n = 0;
    foreach ($items as $it) {
        $n++;
        $fabric = trim(implode(' / ', array_filter([
            (string) ($it['fabric'] ?? ''),
            (string) ($it['colour'] ?? ''),
            (string) ($it['code'] ?? ''),
        ], static fn ($s) => trim($s) !== '')));
        // Options / extras (tilt, mid rail, offsets + measurements, …) — the
        // supplier needs these to make the item. Listed under the product.
        $optsHtml = '';
        if (!empty($it['options']) && is_array($it['options'])) {
            foreach ($it['options'] as $opt) {
                if (trim((string) $opt) === '') continue;
                $optsHtml .= '<br><span class="opt">+ ' . $e($opt) . '</span>';
            }
        }
        $rows .= '<tr>'
              . '<td class="num">' . $n . '</td>'
              . '<td><strong>' . $e($it['product'] ?? '') . '</strong>'
              . ((string) ($it['system'] ?? '') !== '' ? '<br><span class="muted">' . $e($it['system']) . '</span>' : '')
              . $optsHtml
              . '</td>'
              . '<td>' . ($fabric !== '' ? $e($fabric) : '—')
              . ((string) ($it['band'] ?? '') !== '' ? '<br><span class="muted">Band ' . $e($it['band']) . '</span>' : '')
              . '</td>'
              . '<td>' . $e($mm($it['width_mm'] ?? null)) . ' &times; ' . $e($mm($it['drop_mm'] ?? null)) . '</td>'
              . '<td class="num">' . (int) ($it['quantity'] ?? 1) . '</td>'
              . '<td>' . $e($it['room'] ?? '')
              . ((string) ($it['notes'] ?? '') !== '' ? '<br><span class="muted">' . $nl2($it['notes']) . '</span>' : '')
              . '</td>'
              . '</tr>';
    }

    $totalQty = array_sum(array_map(static fn ($it) => (int) ($it['quantity'] ?? 1), $items));

    $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
        . 'body{font-family:helvetica,arial,sans-serif;font-size:11px;color:#1f2937;margin:0}'
        . '.head{display:block;margin-bottom:14px}'
        . '.title{font-size:20px;font-weight:bold;color:#111827;margin:0 0 2px}'
        . '.meta{font-size:11px;color:#374151}'
        . '.cols{width:100%;margin:10px 0 14px}'
        . '.cols td{vertical-align:top;width:50%;padding:0}'
        . '.box-label{font-size:9px;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;margin-bottom:2px}'
        . '.box{font-size:11px;line-height:1.4}'
        . 'table.items{width:100%;border-collapse:collapse;margin-top:6px}'
        . 'table.items th{background:#1f3b5b;color:#fff;font-size:10px;text-align:left;padding:6px 7px}'
        . 'table.items td{border-bottom:1px solid #e5e7eb;padding:6px 7px;font-size:11px;vertical-align:top}'
        . 'table.items td.num,table.items th.num{text-align:center;width:34px}'
        . '.muted{color:#6b7280;font-size:10px}'
        . '.opt{color:#1f3b5b;font-size:10px;font-weight:bold}'
        . '.foot{margin-top:14px;font-size:10px;color:#6b7280}'
        . '</style></head><body>'
        . '<div class="head">'
        . '<div class="title">PURCHASE ORDER</div>'
        . '<div class="meta"><strong>' . $e($ctx['company_name'] ?? '') . '</strong>'
        . ((string) ($ctx['po_ref'] ?? '') !== '' ? ' &nbsp;|&nbsp; Ref: <strong>' . $e($ctx['po_ref']) . '</strong>' : '')
        . ((string) ($ctx['date'] ?? '') !== '' ? ' &nbsp;|&nbsp; ' . $e($ctx['date']) : '')
        . '</div></div>'
        . '<table class="cols"><tr>'
        . '<td><div class="box-label">Supplier</div><div class="box"><strong>' . $e($ctx['supplier_name'] ?? '') . '</strong>'
        . ((string) ($ctx['account_number'] ?? '') !== '' ? '<br><span class="muted">Account no: ' . $e($ctx['account_number']) . '</span>' : '')
        . '</div></td>'
        . '<td><div class="box-label">Deliver to</div><div class="box">'
        . ((string) ($ctx['delivery_address'] ?? '') !== '' ? $nl2($ctx['delivery_address']) : '<span class="muted">— no delivery address set —</span>')
        . '</div></td>'
        . '</tr><tr>'
        . '<td style="padding-top:10px"><div class="box-label">Ordered by</div><div class="box">'
        . $e($ctx['company_name'] ?? '')
        . ((string) ($ctx['company_email'] ?? '') !== '' ? '<br>' . $e($ctx['company_email']) : '')
        . ((string) ($ctx['company_phone'] ?? '') !== '' ? '<br>' . $e($ctx['company_phone']) : '')
        . '</div></td><td></td>'
        . '</tr></table>'
        . '<table class="items"><thead><tr>'
        . '<th class="num">#</th><th>Product</th><th>Fabric / colour / code</th>'
        . '<th>Size (W &times; D)</th><th class="num">Qty</th><th>Room / notes</th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<div class="foot">' . count($items) . ' line(s), ' . (int) $totalQty . ' item(s) total. '
        . 'Please confirm receipt and lead time to the contact above.</div>'
        . '</body></html>';

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

/**
 * Build the printable HTML for one quote — customer-facing version.
 * Inline CSS — Dompdf has isRemoteEnabled = false (intentionally), so
 * external stylesheets won't load.
 */
function pdf_quote_html(array $quote, array $items, array $extrasByItem): string
{
    $money   = static fn ($n)         => '&pound;' . number_format((float) $n, 2);
    $fmtDate = static function (?string $dt): string {
        if (!$dt) return '&mdash;';
        $ts = strtotime($dt);
        return $ts ? date('j F Y', $ts) : '&mdash;';
    };

    // Trade company logo — embedded as a data URI so Dompdf doesn't need
    // to chase a remote URL or worry about chroot. Skipped silently if the
    // file's missing or unreadable (the text branding still appears below).
    $logoTag = '';
    if (!empty($quote['trade_logo'])) {
        // logo_path is stored as a web-relative path like /uploads/logos/X.png
        $rel  = ltrim((string) $quote['trade_logo'], '/');
        $abs  = APP_ROOT . '/' . $rel;
        if (is_file($abs) && is_readable($abs)) {
            $bytes = file_get_contents($abs);
            $info  = @getimagesize($abs);
            if ($bytes !== false && $info !== false) {
                $mime = $info['mime'];
                $logoTag = '<img src="data:' . $mime . ';base64,' . base64_encode($bytes)
                         . '" alt="" style="max-height:64px;max-width:240px;display:block;margin-bottom:8px;">';
            }
        }
    }

    // Trade company address block (the branding panel at the top-left).
    $tradeLines = array_values(array_filter([
        (string) ($quote['trade_addr1']    ?? ''),
        (string) ($quote['trade_addr2']    ?? ''),
        trim(((string) ($quote['trade_town'] ?? '')) . ' ' . ((string) ($quote['trade_postcode'] ?? ''))),
        (string) ($quote['trade_county']   ?? ''),
    ], static fn ($s) => trim((string) $s) !== ''));

    // End-customer address block.
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
.brand { font-size: 22px; font-weight: bold; color: #1f3b5b; line-height: 1; margin-bottom: 6px; }
.trade-block { font-size: 10px; color: #6b7280; line-height: 1.55; }
.trade-block .name { color: #111827; font-size: 13px; font-weight: bold; }
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
.items .room { font-weight: 600; color: #111827; font-size: 11.5px; }
.items .desc { color: #4b5563; font-size: 10px; margin-top: 3px; line-height: 1.45; }
.items .extras { color: #6b7280; font-size: 10px; margin-top: 3px; }
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
.legal { margin-top: 18px; page-break-before: always; }
.legal h3 { margin: 0 0 6px; font-size: 11px; color: #111827; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.04em; }
.legal p  { margin: 0; white-space: pre-line; font-size: 8px; line-height: 1.5; color: #374151; }
</style>
</head>
<body>

<table class="layout">
<tr>
<td width="55%">
<?= $logoTag /* already-safe HTML built above; data URI image */ ?>
<div class="brand"><?= e((string) ($quote['trade_company_name'] ?? '')) ?></div>
<div class="trade-block">
<?php foreach ($tradeLines as $line): ?>
<?= e((string) $line) ?><br>
<?php endforeach; ?>
<?php if (!empty($quote['trade_phone'])): ?>
<?= e((string) $quote['trade_phone']) ?><br>
<?php endif; ?>
<?php if (!empty($quote['trade_email'])): ?>
<?= e((string) $quote['trade_email']) ?><br>
<?php endif; ?>
<?php if (!empty($quote['trade_vat_number'])): ?>
VAT No. <?= e((string) $quote['trade_vat_number']) ?>
<?php endif; ?>
</div>
</td>
<td class="quote-block">
<h2>Quote <?= e((string) $quote['quote_number']) ?></h2>
<div class="meta">
<span class="lbl">Date</span> <span class="val"><?= $fmtDate($quote['created_at'] ?? null) ?></span><br>
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
<th class="num" width="40">Qty</th>
<th class="num" width="75">Unit</th>
<th class="num" width="80">Total</th>
</tr>
</thead>
<tbody>
<?php if (empty($items)): ?>
<tr><td colspan="5" style="text-align:center; padding:24px; color:#9ca3af;">No line items.</td></tr>
<?php else: foreach ($items as $i => $item):
    // Build the description block from snapshot fields. We deliberately
    // do NOT render width_mm / drop_mm / matrix_* anywhere — that's the
    // size-rule the trade business asked for.
    $descBits = [];
    if (!empty($item['product_name_snapshot'])) {
        $descBits[] = (string) $item['product_name_snapshot']
            . (!empty($item['system_name_snapshot']) ? ' — ' . (string) $item['system_name_snapshot'] : '');
    }
    $fabricBits = array_filter([
        (string) ($item['fabric_supplier_snapshot'] ?? ''),
        (string) ($item['fabric_name_snapshot']     ?? ''),
        (string) ($item['fabric_colour_snapshot']   ?? ''),
    ], static fn ($s) => $s !== '');
    if ($fabricBits) {
        $descBits[] = implode(' / ', $fabricBits);
    }
?>
<tr>
<td><?= (int) ($item['line_no'] ?? ($i + 1)) ?></td>
<td>
<?php if (!empty($item['room_name'])): ?>
<div class="room"><?= e((string) $item['room_name']) ?></div>
<?php endif; ?>
<?php if ($descBits): ?>
<div class="desc"><?= e(implode("\n", $descBits)) ?></div>
<?php endif; ?>
<?php $exs = $extrasByItem[(int) $item['id']] ?? []; ?>
<?php if ($exs): ?>
<div class="extras">
<?php foreach ($exs as $ex): ?>
+ <?= e((string) $ex['extra_name_snapshot']) ?><?php if (($ex['choice_label_snapshot'] ?? '') !== ''): ?>: <?= e((string) $ex['choice_label_snapshot']) ?><?php endif;
    if (isset($ex['user_value']) && $ex['user_value'] !== null && (float) $ex['user_value'] > 0):
        echo ' &mdash; ' . e(rtrim(rtrim(number_format((float) $ex['user_value'], 2, '.', ''), '0'), '.')) . 'mm';
    endif;
?><br>
<?php endforeach; ?>
</div>
<?php endif; ?>
</td>
<td class="num"><?= (int) $item['quantity'] ?></td>
<td class="num"><?= $money($item['sell_price']) ?></td>
<td class="num"><?= $money($item['line_total']) ?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
<tfoot>
<?php if ((float) ($quote['vat_percent'] ?? 0) > 0): ?>
<tr><td colspan="3"></td><td class="label">Subtotal</td><td class="val"><?= $money($quote['subtotal']) ?></td></tr>
<tr><td colspan="3"></td><td class="label">VAT (<?= e($vatPct) ?>%)</td><td class="val"><?= $money($quote['vat']) ?></td></tr>
<?php endif; ?>
<tr class="grand"><td colspan="3"></td><td class="label">Total</td><td class="val"><?= $money($quote['total']) ?></td></tr>
</tfoot>
</table>

<?php if (!empty($quote['notes'])): ?>
<div class="notes">
<h3>Notes</h3>
<p><?= e((string) $quote['notes']) ?></p>
</div>
<?php endif; ?>

<?php $tcText = trim((string) ($quote['terms_conditions'] ?? '')); ?>
<?php if ($tcText !== ''): ?>
<div class="legal">
<h3>Terms &amp; Conditions</h3>
<p><?= e(legal_render_tokens($tcText, $quote)) ?></p>
</div>
<?php endif; ?>

<?php $ppText = trim((string) ($quote['privacy_policy'] ?? '')); ?>
<?php if ($ppText !== ''): ?>
<div class="legal">
<h3>Privacy Policy</h3>
<p><?= e(legal_render_tokens($ppText, $quote)) ?></p>
</div>
<?php endif; ?>

<?php if (!empty($quote['quote_footer'])): ?>
<div class="footer"><?= e((string) $quote['quote_footer']) ?></div>
<?php else: ?>
<div class="footer"><?= e((string) ($quote['trade_company_name'] ?? '')) ?> &middot; Quote <?= e((string) $quote['quote_number']) ?></div>
<?php endif; ?>

</body>
</html>
<?php
    return (string) ob_get_clean();
}
