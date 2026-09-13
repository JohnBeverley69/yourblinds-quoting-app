<?php
declare(strict_types=1);

/**
 * Send ONE supplier's purchase order: spec PDF + plain-text body, emailed to the
 * supplier, then logged to supplier_orders. Shared by the tenant "Send to
 * suppliers" (quote-builder/order_suppliers.php) and the factory one
 * (factory/order-suppliers.php) so the PO format + logging stay identical.
 *
 * The CALLER decides scope (which supplier the line goes to, whose email/address,
 * the buyer identity, and whether it's a tenant or factory send) and passes it in.
 *
 * $ctx      header for the PO: company_name/company_email/company_phone (the
 *           BUYER), supplier_name, account_number, delivery_address,
 *           quote_number, po_ref, date.
 * $pdfItems the mapped spec rows (product/system/fabric/colour/code/band/
 *           width_mm/drop_mm/quantity/room/notes/options[]).
 * $logMeta  client_id, quote_id, supplier_name, item_count, sent_by_user_id,
 *           and optional ordered_by_factory_id (set for a FACTORY send).
 *
 * Returns true on a successful send.
 */
function supplier_send_group(PDO $pdo, array $ctx, array $pdfItems, string $email, array $mailOpts, array $logMeta): bool
{
    if (!function_exists('pdf_render_supplier_order')) { require_once __DIR__ . '/../pdf-generator/pdf.php'; }
    if (!function_exists('mailer_send'))               { require_once __DIR__ . '/../mailer.php'; }

    $company = (string) ($ctx['company_name'] ?? '');
    $account = (string) ($ctx['account_number'] ?? '');
    $delivery = (string) ($ctx['delivery_address'] ?? '');
    $qNum     = (string) ($ctx['quote_number'] ?? '');

    $pdf = function_exists('pdf_render_supplier_order') ? pdf_render_supplier_order($ctx, $pdfItems) : null;

    $bodyLines = [
        'Hello,',
        '',
        'Please supply the following order from ' . ($company !== '' ? $company : 'us')
            . ' (ref ' . $qNum . '). Full specification is attached as a PDF.',
        '',
    ];
    if ($account !== '') {
        $bodyLines[] = 'Our account number with you: ' . $account;
        $bodyLines[] = '';
    }
    foreach ($pdfItems as $pi) {
        $fab = implode(' / ', array_filter([
            (string) ($pi['fabric'] ?? ''), (string) ($pi['colour'] ?? ''), (string) ($pi['code'] ?? ''),
        ], static fn ($s) => trim((string) $s) !== ''));
        $sz = ((int) ($pi['width_mm'] ?? 0)) . ' x ' . ((int) ($pi['drop_mm'] ?? 0)) . ' mm';
        $bodyLines[] = '- ' . (int) ($pi['quantity'] ?? 1) . ' x ' . ($pi['product'] ?? '')
            . ($fab !== '' ? ' — ' . $fab : '') . ' — ' . $sz
            . ((string) ($pi['room'] ?? '') !== '' ? ' (' . $pi['room'] . ')' : '');
        foreach (($pi['options'] ?? []) as $opt) {
            $bodyLines[] = '    • ' . $opt;
        }
    }
    $bodyLines[] = '';
    if (trim($delivery) !== '') {
        $bodyLines[] = 'Deliver to:';
        $bodyLines[] = $delivery;
        $bodyLines[] = '';
    }
    $bodyLines[] = 'Any queries, please reply to '
        . ((string) ($ctx['company_email'] ?? '') ?: 'us')
        . ((string) ($ctx['company_phone'] ?? '') !== '' ? ' / ' . $ctx['company_phone'] : '') . '.';
    $bodyLines[] = '';
    $bodyLines[] = $company;

    $safeName   = preg_replace('/[^A-Za-z0-9]+/', '-', (string) ($ctx['supplier_name'] ?? '')) ?: 'supplier';
    $attachment = ($pdf !== null) ? [
        'content'  => $pdf,
        'filename' => 'PO-' . $qNum . '-' . $safeName . '.pdf',
        'mime'     => 'application/pdf',
    ] : null;

    $ok = mailer_send(
        $email,
        ($company !== '' ? $company : 'Order') . ' — Purchase Order ' . $qNum,
        implode("\n", $bodyLines),
        $attachment,
        null,
        $mailOpts
    );

    if ($ok) {
        try {
            $hasFac = false;
            try { $pdo->query('SELECT `ordered_by_factory_id` FROM supplier_orders LIMIT 0'); $hasFac = true; } catch (Throwable $e) {}
            if ($hasFac) {
                $pdo->prepare(
                    'INSERT INTO supplier_orders
                        (client_id, quote_id, supplier_name, email, item_count, sent_by_user_id, ordered_by_factory_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    (int) ($logMeta['client_id'] ?? 0), (int) ($logMeta['quote_id'] ?? 0),
                    (string) ($logMeta['supplier_name'] ?? ''), $email, (int) ($logMeta['item_count'] ?? 0),
                    (int) ($logMeta['sent_by_user_id'] ?? 0) ?: null,
                    isset($logMeta['ordered_by_factory_id']) ? (int) $logMeta['ordered_by_factory_id'] : null,
                ]);
            } else {
                $pdo->prepare(
                    'INSERT INTO supplier_orders
                        (client_id, quote_id, supplier_name, email, item_count, sent_by_user_id)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([
                    (int) ($logMeta['client_id'] ?? 0), (int) ($logMeta['quote_id'] ?? 0),
                    (string) ($logMeta['supplier_name'] ?? ''), $email, (int) ($logMeta['item_count'] ?? 0),
                    (int) ($logMeta['sent_by_user_id'] ?? 0) ?: null,
                ]);
            }
        } catch (Throwable $e) { error_log('supplier_orders log failed: ' . $e->getMessage()); }
    }
    return $ok;
}
