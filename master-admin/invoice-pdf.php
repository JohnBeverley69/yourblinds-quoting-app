<?php
declare(strict_types=1);

/**
 * Stream a wholesale invoice PDF (Beverley → trade account). Super-admin only.
 * Usage: /master-admin/invoice-pdf.php?id=N[&download=1]
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_ar.php';
require_once __DIR__ . '/../pdf-generator/ar_pdf.php';

requireSuperAdmin();

$pdo     = db();
$factory = ar_factory_id();
$id      = (int) ($_GET['id'] ?? 0);

$inv = null;
try {
    $st = $pdo->prepare('SELECT * FROM factory_ar_invoices WHERE id = ? AND factory_client_id = ? LIMIT 1');
    $st->execute([$id, $factory]);
    $inv = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) { /* handled below */ }

if (!$inv) { http_response_code(404); header('Content-Type: text/plain'); echo 'Invoice not found.'; exit; }

$lines = [];
try {
    $ls = $pdo->prepare('SELECT * FROM factory_ar_invoice_lines WHERE invoice_id = ? ORDER BY sort_order, id');
    $ls->execute([$id]);
    $lines = $ls->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* none */ }

// Beverley (factory) letterhead + bank details.
$fac = [];
$bank = '';
try {
    $fs = $pdo->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
    $fs->execute([$factory]);
    $fac = $fs->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { /* letterhead degrades */ }
try {
    $bs = $pdo->prepare('SELECT bank_account_name, bank_sort_code, bank_account_number, payment_instructions FROM client_settings WHERE client_id = ? LIMIT 1');
    $bs->execute([$factory]);
    if ($b = $bs->fetch(PDO::FETCH_ASSOC)) {
        $bankLines = array_values(array_filter([
            ($b['bank_account_name'] ?? '') !== '' ? 'Account: ' . $b['bank_account_name'] : '',
            ($b['bank_sort_code'] ?? '') !== '' ? 'Sort code: ' . $b['bank_sort_code'] : '',
            ($b['bank_account_number'] ?? '') !== '' ? 'Account no: ' . $b['bank_account_number'] : '',
            (string) ($b['payment_instructions'] ?? ''),
        ], static fn ($s) => trim((string) $s) !== ''));
        $bank = implode("\n", $bankLines);
    }
} catch (Throwable $e) { /* no bank details */ }

// Order ref(s) from the invoice↔order link.
$orderRef = '';
try {
    $os = $pdo->prepare(
        'SELECT q.quote_number FROM factory_ar_invoice_orders io
           JOIN quotes q ON q.id = io.quote_id WHERE io.invoice_id = ? ORDER BY io.id'
    );
    $os->execute([$id]);
    $orderRef = implode(', ', array_filter(array_column($os->fetchAll(PDO::FETCH_ASSOC), 'quote_number')));
} catch (Throwable $e) { /* optional */ }

$ctx = [
    'factory'     => $fac,
    'doc_title'   => 'INVOICE',
    'doc_number'  => (string) $inv['inv_number'],
    'issue_date'  => $inv['issue_date'] ? date('j F Y', strtotime((string) $inv['issue_date'])) : date('j F Y', strtotime((string) $inv['created_at'])),
    'due_date'    => $inv['due_date'] ? date('j F Y', strtotime((string) $inv['due_date'])) : '',
    'bill_to'     => (string) ($inv['bill_to_snapshot'] ?? ''),
    'order_ref'   => $orderRef,
    'vat_percent' => (float) $inv['vat_percent'],
    'notes'       => (string) ($inv['notes'] ?? ''),
    'bank'        => $bank,
    'watermark'   => $inv['status'] === 'void' ? 'VOID' : '',
];
$totals = ['subtotal' => (float) $inv['subtotal'], 'vat' => (float) $inv['vat'], 'total' => (float) $inv['total']];

$pdf = ar_render_invoice($ctx, $lines, $totals);
if ($pdf === null) { http_response_code(500); header('Content-Type: text/plain'); echo 'PDF engine unavailable.'; exit; }

$disposition = !empty($_GET['download']) ? 'attachment' : 'inline';
$filename    = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $inv['inv_number']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($pdf));
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
header('Cache-Control: private, no-store');
header('Pragma: no-cache');
echo $pdf;
