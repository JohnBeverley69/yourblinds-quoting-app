<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../_partials/legal_text.php';

/**
 * YourBlinds — customer-facing quote PDF rendering.
 *
 * Each line shows product, fabric, colour, room, extras, quantity, price. The
 * blind SIZE (width × drop) is shown only when the tenant's show_line_sizes
 * setting is on (Settings → Quoting) — trade quotes show sizes, retail hide them.
 * Per-blind prices are likewise gated by show_line_prices.
 *
 * Loads a quote (scoped by client_id), builds an HTML document, and uses
 * Dompdf to produce A4 PDF bytes. Returns null if the quote does not
 * exist or Dompdf is not installed (logged).
 */
function pdf_render_quote(int $quoteId, int $clientId, string $docLabel = 'Quote'): ?string
{
    if (!class_exists(Dompdf::class)) {
        error_log('[YourBlinds] Dompdf not installed — run "composer install" to enable PDF rendering.');
        return null;
    }
    require_once __DIR__ . '/../_partials/calendar_money.php';

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

    // Trade order? The "for" block should be the linked trade account (its live
    // Contact / Company / address), not the retail end_customer_* snapshot.
    // Retail quotes (no account link) are untouched.
    $pdfTradeAccount = null;
    if ((int) ($quote['account_client_id'] ?? 0) > 0) {
        try {
            $accSt = $pdo->prepare(
                'SELECT company_name, contact_name, email, phone,
                        address1, address2, town, county, postcode
                   FROM clients WHERE id = ? LIMIT 1'
            );
            $accSt->execute([(int) $quote['account_client_id']]);
            $pdfTradeAccount = $accSt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) { $pdfTradeAccount = null; }
    }

    // Money received so far + balance, via the shared helper so these match
    // the calendar / Payments panel exactly (deposit isn't double-counted).
    $pdfMoney   = function_exists('calendar_money_for_quotes')
        ? calendar_money_for_quotes($pdo, $clientId, [$quoteId]) : [];
    $received   = isset($pdfMoney[$quoteId]) ? (float) $pdfMoney[$quoteId]['received'] : 0.0;
    $balanceDue = isset($pdfMoney[$quoteId]) ? (float) $pdfMoney[$quoteId]['balance']  : (float) $quote['total'];

    // Terms & Conditions + Privacy Policy (optional columns). Loaded with a
    // separate guarded query — kept out of the main SELECT so the PDF still
    // renders if migrate_terms_conditions.php hasn't been run yet.
    $quote['terms_conditions']       = null;
    $quote['trade_terms_conditions'] = null;
    $quote['privacy_policy']         = null;
    $legalAvailable = false;
    try {
        // trade_terms_conditions is a later migration; try it first, fall back.
        try {
            $lstmt = $pdo->prepare(
                'SELECT terms_conditions, trade_terms_conditions, privacy_policy FROM client_settings WHERE client_id = ? LIMIT 1'
            );
            $lstmt->execute([$clientId]);
        } catch (Throwable $eCol) {
            $lstmt = $pdo->prepare(
                'SELECT terms_conditions, privacy_policy FROM client_settings WHERE client_id = ? LIMIT 1'
            );
            $lstmt->execute([$clientId]);
        }
        $legalAvailable = true;
        if ($lrow = $lstmt->fetch()) {
            $quote['terms_conditions']       = $lrow['terms_conditions'] ?? null;
            $quote['trade_terms_conditions'] = $lrow['trade_terms_conditions'] ?? null;
            $quote['privacy_policy']         = $lrow['privacy_policy'] ?? null;
        }
    } catch (Throwable $e) { /* columns not present yet — skip */ }
    // NULL / no settings row → standard template (live by default).
    if ($legalAvailable) {
        $quote['terms_conditions']       = legal_effective_terms($quote['terms_conditions'] ?? null);
        $quote['trade_terms_conditions'] = legal_effective_trade_terms($quote['trade_terms_conditions'] ?? null);
        $quote['privacy_policy']         = legal_effective_privacy($quote['privacy_policy'] ?? null);
    }

    // Per-blind price visibility (Settings → Quoting). Carried on $quote so the
    // HTML builder can read it. Guarded so a pre-migration DB (column absent)
    // defaults to SHOWING prices — the historic behaviour.
    $quote['show_line_prices'] = 1;
    try {
        $spstmt = $pdo->prepare('SELECT show_line_prices FROM client_settings WHERE client_id = ? LIMIT 1');
        $spstmt->execute([$clientId]);
        $spVal = $spstmt->fetchColumn();
        if ($spVal !== false && $spVal !== null) $quote['show_line_prices'] = (int) $spVal;
    } catch (Throwable $e) { /* column not migrated yet — show */ }

    // Per-blind SIZE visibility (sibling of show_line_prices). Guarded so a
    // pre-migration DB defaults to SHOWING sizes — the new trade default.
    $quote['show_line_sizes'] = 1;
    try {
        $ssstmt = $pdo->prepare('SELECT show_line_sizes FROM client_settings WHERE client_id = ? LIMIT 1');
        $ssstmt->execute([$clientId]);
        $ssVal = $ssstmt->fetchColumn();
        if ($ssVal !== false && $ssVal !== null) $quote['show_line_sizes'] = (int) $ssVal;
    } catch (Throwable $e) { /* column not migrated yet — show */ }

    // Bank details for the "How to pay" block. Carried on $quote; guarded so a
    // pre-migration DB (columns absent) simply omits the block.
    $quote['bank_account_name'] = $quote['bank_sort_code'] = '';
    $quote['bank_account_number'] = $quote['payment_instructions'] = '';
    try {
        $bkstmt = $pdo->prepare(
            'SELECT bank_account_name, bank_sort_code, bank_account_number, payment_instructions
               FROM client_settings WHERE client_id = ? LIMIT 1'
        );
        $bkstmt->execute([$clientId]);
        if ($bkRow = $bkstmt->fetch(PDO::FETCH_ASSOC)) {
            $quote['bank_account_name']    = trim((string) ($bkRow['bank_account_name']    ?? ''));
            $quote['bank_sort_code']       = trim((string) ($bkRow['bank_sort_code']       ?? ''));
            $quote['bank_account_number']  = trim((string) ($bkRow['bank_account_number']  ?? ''));
            $quote['payment_instructions'] = trim((string) ($bkRow['payment_instructions'] ?? ''));
        }
    } catch (Throwable $e) { /* columns not migrated yet — no block */ }

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

    $html = pdf_quote_html(
        $quote, $items, $extrasByItem,
        $docLabel, $clientId, $pdfTradeAccount, $received, $balanceDue
    );

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
 *
 * Everything this needs is passed in. It used to read $docLabel, $clientId,
 * $pdfTradeAccount, $received and $balanceDue straight out of the air: they are
 * locals of pdf_render_quote(), and a function does not inherit its caller's
 * scope (unlike an include). So they were all null here, which meant the
 * document label vanished, the Paid/Balance-due rows never rendered, the linked
 * trade account was ignored, the legal links came out as ?c=0 — and, because
 * this file is strict_types=1, strtolower($docLabel) on the terms line threw a
 * TypeError and killed PDF generation outright for any tenant with terms text.
 */
function pdf_quote_html(
    array $quote,
    array $items,
    array $extrasByItem,
    string $docLabel,
    int $clientId,
    ?array $pdfTradeAccount,
    float $received,
    float $balanceDue
): string {
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

    // End-customer address block. For a trade order this is the linked
    // account's address; for retail it's the quote's end_customer_* snapshot.
    if ($pdfTradeAccount !== null) {
        $custLines = array_values(array_filter([
            (string) ($pdfTradeAccount['address1'] ?? ''),
            (string) ($pdfTradeAccount['address2'] ?? ''),
            trim(((string) ($pdfTradeAccount['town'] ?? '')) . ' ' . ((string) ($pdfTradeAccount['postcode'] ?? ''))),
            (string) ($pdfTradeAccount['county']   ?? ''),
        ], static fn ($s) => trim((string) $s) !== ''));
    } else {
        $custLines = array_values(array_filter([
            (string) ($quote['end_customer_address1'] ?? ''),
            (string) ($quote['end_customer_address2'] ?? ''),
            trim(((string) ($quote['end_customer_town'] ?? '')) . ' ' . ((string) ($quote['end_customer_postcode'] ?? ''))),
            (string) ($quote['end_customer_county']   ?? ''),
        ], static fn ($s) => trim((string) $s) !== ''));
    }

    // The "for" name + contact line — trade order shows Contact — Company,
    // retail shows the end customer with their email/phone.
    if ($pdfTradeAccount !== null) {
        $pdfCustName = trim((string) ($pdfTradeAccount['contact_name'] ?? '')) !== ''
            ? trim((string) $pdfTradeAccount['contact_name']) . ' — ' . (string) ($pdfTradeAccount['company_name'] ?? '')
            : (string) ($pdfTradeAccount['company_name'] ?? '');
        $pdfCustEmail = (string) ($pdfTradeAccount['email'] ?? '');
        $pdfCustPhone = (string) ($pdfTradeAccount['phone'] ?? '');
        $pdfCustRef   = trim((string) ($quote['customer_reference'] ?? ''));
    } else {
        $pdfCustName  = (string) ($quote['end_customer_name'] ?? '');
        $pdfCustEmail = (string) ($quote['end_customer_email'] ?? '');
        $pdfCustPhone = (string) ($quote['end_customer_phone'] ?? '');
        $pdfCustRef   = '';
    }

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
/* Laid out as a small right-aligned table so the label and value share a
   baseline and the two rows line up in columns — Dompdf mis-aligns
   inline-block against adjacent text, which left the labels sitting high. */
.quote-block .meta { font-size: 10.5px; border-collapse: collapse; }
.quote-block .meta td { padding: 0 0 3px; vertical-align: baseline; white-space: nowrap; }
.quote-block .meta td.lbl { color: #6b7280; text-align: right; padding-right: 8px; }
.quote-block .meta td.val { color: #111827; font-weight: 600; text-align: right; }
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
.items .size { color: #111827; font-size: 10.5px; font-weight: 600; margin-top: 3px; }
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
.legal-links { margin-top: 16px; padding-top: 10px; border-top: 1px solid #e5e7eb;
               font-size: 9px; line-height: 1.6; color: #6b7280; }
.legal-links a { color: #1f3b5b; text-decoration: none; }
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
<h2><?= e($docLabel) ?> <?= e((string) $quote['quote_number']) ?></h2>
<table class="meta" align="right">
<tr><td class="lbl">Date</td><td class="val"><?= $fmtDate($quote['created_at'] ?? null) ?></td></tr>
<tr><td class="lbl">Status</td><td class="val" style="text-transform:capitalize;"><?= e((string) $quote['status']) ?></td></tr>
</table>
</td>
</tr>
</table>

<div class="customer">
<div class="label"><?= e($docLabel) ?> for</div>
<div class="name"><?= e($pdfCustName) ?></div>
<?php if ($custLines): ?>
<div class="addr"><?= e(implode("\n", $custLines)) ?></div>
<?php endif; ?>
<?php if ($pdfCustEmail !== '' || $pdfCustPhone !== ''): ?>
<div class="contact">
<?php if ($pdfCustEmail !== ''): ?><?= e($pdfCustEmail) ?><?php endif; ?>
<?php if ($pdfCustEmail !== '' && $pdfCustPhone !== ''): ?> &middot; <?php endif; ?>
<?php if ($pdfCustPhone !== ''): ?><?= e($pdfCustPhone) ?><?php endif; ?>
</div>
<?php endif; ?>
<?php if ($pdfCustRef !== ''): ?>
<div class="contact">Ref: <?= e($pdfCustRef) ?></div>
<?php endif; ?>
</div>

<?php
$showLinePrices = ((int) ($quote['show_line_prices'] ?? 1)) === 1;
$showLineSizes  = ((int) ($quote['show_line_sizes'] ?? 1)) === 1;
// A trade quote (raised for an account) gets an extra Discount column between
// Unit and Total; retail quotes are unchanged.
$isTradeQuote = $showLinePrices && (int) ($quote['account_client_id'] ?? 0) > 0;
$colCount = $showLinePrices ? ($isTradeQuote ? 6 : 5) : 3;

// Human size string for a line (W × D mm), honouring width-only / per-slat lines.
$lineSizeStr = static function (array $item): string {
    $w = (int) ($item['width_mm'] ?? 0);
    $d = (int) ($item['drop_mm'] ?? 0);
    if ($w > 0 && $d > 0) return $w . ' × ' . $d . ' mm';
    if ($w > 0) return $w . ' mm wide';
    if ($d > 0) return 'Drop ' . $d . ' mm';
    return '';
};

// WT (internal surcharge) is folded into the stored subtotal. When per-blind
// prices are shown, spread it proportionally across the line totals so the
// lines still reconcile to the subtotal — it is NEVER shown as its own line.
$wt = round((float) ($quote['wt_amount'] ?? 0), 2);
$wtShare = [];   // item id => £ added to that line
if ($wt > 0.0049 && $showLinePrices && !empty($items)) {
    $base = 0.0;
    foreach ($items as $it) $base += (float) $it['line_total'];
    if ($base > 0.0049) {
        $acc = 0.0; $n = count($items); $k = 0;
        foreach ($items as $it) {
            $k++;
            if ($k < $n) { $s = round($wt * (float) $it['line_total'] / $base, 2); $acc += $s; }
            else         { $s = round($wt - $acc, 2); }   // remainder on the last line
            $wtShare[(int) $it['id']] = $s;
        }
    }
}
?>
<table class="items">
<thead>
<tr>
<th width="30">#</th>
<th>Description</th>
<th class="num" width="40">Qty</th>
<?php if ($showLinePrices): ?>
<th class="num" width="75">Unit</th>
<?php if ($isTradeQuote): ?><th class="num" width="60">Discount</th><?php endif; ?>
<th class="num" width="80">Total</th>
<?php endif; ?>
</tr>
</thead>
<tbody>
<?php if (empty($items)): ?>
<tr><td colspan="<?= $colCount ?>" style="text-align:center; padding:24px; color:#9ca3af;">No line items.</td></tr>
<?php else: foreach ($items as $i => $item):
    // Build the description block from snapshot fields. The blind SIZE
    // (width × drop) is shown only when show_line_sizes is on (a Settings →
    // Quoting toggle) — trade quotes show sizes, retail quotes hide them.
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

    // Trade quote line: the account discount comes off the BASE only
    // (pricing_engine.php), so the list unit and discount % are derived from the
    // stored figures — no re-save of existing quotes needed. $__unit is the price
    // shown in the Unit column (list price when discounted, else the net), and
    // $__disc the Discount-column text (null = no discount, shown as "—").
    // A markup-inflated line yields no positive base discount here, so it simply
    // reads as no discount rather than anything misleading.
    $__unit = (float) ($item['sell_price'] ?? 0);   // net unit (default)
    $__disc = null;
    if ($isTradeQuote) {
        $__base   = (float) ($item['base_price']   ?? 0);
        $__extras = (float) ($item['extras_total'] ?? 0);
        $__net    = (float) ($item['sell_price']   ?? 0);
        $__list   = round($__base + $__extras, 2);
        $__off    = round($__list - $__net, 2);
        if ($__base > 0 && $__off >= 0.01) {
            $__unit = $__list;   // Unit column shows the list price
            // 1dp, trailing zeros trimmed — deriving the % from penny-rounded
            // prices gives 15.01/14.99; 1dp reads back as a clean 15%.
            $__disc = rtrim(rtrim(number_format($__off / $__base * 100, 1, '.', ''), '0'), '.') . '%';
        }
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
<?php if ($showLineSizes && ($__sz = $lineSizeStr($item)) !== ''): ?>
<div class="size"><?= e($__sz) ?></div>
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
<?php if ($showLinePrices): ?>
<?php $__share = $wtShare[(int) $item['id']] ?? 0.0; ?>
<?php if ($__share > 0.0049): /* line bumped by its WT share; no separate line */
    $__qty = max(1, (int) $item['quantity']);
    $__lt  = (float) $item['line_total'] + $__share; ?>
<td class="num"><?= $money(round($__lt / $__qty, 2)) ?></td>
<?php if ($isTradeQuote): ?><td class="num"><?= $__disc !== null ? e($__disc) : '&mdash;' ?></td><?php endif; ?>
<td class="num"><?= $money($__lt) ?></td>
<?php else: ?>
<td class="num"><?= $money($__unit) ?></td>
<?php if ($isTradeQuote): ?><td class="num"><?= $__disc !== null ? e($__disc) : '&mdash;' ?></td><?php endif; ?>
<td class="num"><?= $money($item['line_total']) ?></td>
<?php endif; ?>
<?php endif; ?>
</tr>
<?php endforeach; endif; ?>
</tbody>
<tfoot>
<?php $spacer = $colCount - 2; ?>
<?php
// Agreed-price override → a customer-facing "Discount" bringing the natural line
// prices (which include WT) down to the pinned total. Derived, so it always
// reconciles with the Subtotal/Total below.
$olPreNet = $wt;
foreach ($items as $__it) $olPreNet += (float) $__it['line_total'];
$olDiscount = round($olPreNet - (float) $quote['subtotal'], 2);
?>
<?php if ($showLinePrices && abs($olDiscount) >= 0.01): ?>
<tr><td colspan="<?= $spacer ?>"></td><td class="label"><?= $olDiscount >= 0 ? 'Discount' : 'Price adjustment' ?></td><td class="val"><?= ($olDiscount >= 0 ? '&minus;' : '+') . $money(abs($olDiscount)) ?></td></tr>
<?php endif; ?>
<?php if ((float) ($quote['vat_percent'] ?? 0) > 0): ?>
<tr><td colspan="<?= $spacer ?>"></td><td class="label">Subtotal</td><td class="val"><?= $money($quote['subtotal']) ?></td></tr>
<tr><td colspan="<?= $spacer ?>"></td><td class="label">VAT (<?= e($vatPct) ?>%)</td><td class="val"><?= $money($quote['vat']) ?></td></tr>
<?php endif; ?>
<tr class="grand"><td colspan="<?= $spacer ?>"></td><td class="label">Total</td><td class="val"><?= $money($quote['total']) ?></td></tr>
<?php if ($received > 0.0049): ?>
<tr><td colspan="<?= $spacer ?>"></td><td class="label">Paid</td><td class="val"><?= $money($received) ?></td></tr>
<tr class="grand"><td colspan="<?= $spacer ?>"></td><td class="label">Balance due</td><td class="val"><?= $money($balanceDue) ?></td></tr>
<?php endif; ?>
</tfoot>
</table>

<?php if (!empty($quote['notes'])): ?>
<div class="notes">
<h3>Notes</h3>
<p><?= e((string) $quote['notes']) ?></p>
</div>
<?php endif; ?>

<?php
$bName  = trim((string) ($quote['bank_account_name']    ?? ''));
$bSort  = trim((string) ($quote['bank_sort_code']       ?? ''));
$bAcc   = trim((string) ($quote['bank_account_number']  ?? ''));
$bInstr = trim((string) ($quote['payment_instructions'] ?? ''));
?>
<?php if ($bName !== '' || $bAcc !== ''): ?>
<div style="margin-top:12px;border:1px solid #d1d5db;border-radius:6px;padding:8px 12px;background:#f9fafb;font-size:11px">
<strong>How to pay &mdash; bank transfer</strong><br>
<?php if ($bName !== ''): ?>Account name: <strong><?= e($bName) ?></strong><br><?php endif; ?>
<?php if ($bSort !== ''): ?>Sort code: <strong><?= e($bSort) ?></strong><br><?php endif; ?>
<?php if ($bAcc !== ''): ?>Account number: <strong><?= e($bAcc) ?></strong><br><?php endif; ?>
<?php if ($bInstr !== ''): ?><span style="color:#6b7280"><?= e($bInstr) ?></span><br><?php endif; ?>
<span style="color:#6b7280">Please use <strong><?= e((string) ($quote['quote_number'] ?? '')) ?></strong> as your payment reference.</span>
</div>
<?php endif; ?>

<?php
// Terms & Conditions and Privacy Policy are LINKED, not printed in full — the
// customer clicks through to the always-current online copy (saves printing many
// pages on every quote). A trade quote (raised for an account) links to the
// TRADE terms; a retail quote to the retail terms. We only show a link when that
// document actually has content (a tenant can blank it to switch it off).
$legalDoc  = ((int) ($quote['account_client_id'] ?? 0) > 0) ? 'trade' : 'retail';
$legalText = trim((string) ($legalDoc === 'trade'
    ? ($quote['trade_terms_conditions'] ?? '')
    : ($quote['terms_conditions'] ?? '')));
$ppText    = trim((string) ($quote['privacy_policy'] ?? ''));
$legalHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
$legalBase = 'https://' . ($legalHost !== '' ? $legalHost : 'yourblinds.uk');
$termsUrl  = $legalBase . '/legal/view.php?c=' . (int) $clientId . '&doc=' . $legalDoc;
$privUrl   = $legalBase . '/legal/view.php?c=' . (int) $clientId . '&doc=privacy';
?>
<?php if ($legalText !== '' || $ppText !== ''): ?>
<div class="legal-links">
<?php if ($legalText !== ''): ?>
This <?= e($docLabel === 'Quote' ? 'quotation' : strtolower($docLabel)) ?> is subject to our Terms &amp; Conditions of sale: <a href="<?= e($termsUrl) ?>"><?= e($termsUrl) ?></a>.<br>
<?php endif; ?>
<?php if ($ppText !== ''): ?>
Privacy Policy: <a href="<?= e($privUrl) ?>"><?= e($privUrl) ?></a>.
<?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($quote['quote_footer'])): ?>
<div class="footer"><?= e((string) $quote['quote_footer']) ?></div>
<?php else: ?>
<div class="footer"><?= e((string) ($quote['trade_company_name'] ?? '')) ?> &middot; <?= e($docLabel) ?> <?= e((string) $quote['quote_number']) ?></div>
<?php endif; ?>

</body>
</html>
<?php
    return (string) ob_get_clean();
}
