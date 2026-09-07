<?php
declare(strict_types=1);

/**
 * Stream a wholesale credit note PDF (Beverley → trade account). Super-admin only.
 * Usage: /master-admin/credit-note-pdf.php?id=N[&download=1]
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_ar.php';
require_once __DIR__ . '/../pdf-generator/ar_pdf.php';

requireSuperAdmin();

$pdo     = db();
$factory = ar_factory_id();
$id      = (int) ($_GET['id'] ?? 0);

$cn = null;
try {
    $st = $pdo->prepare('SELECT * FROM factory_ar_credit_notes WHERE id = ? AND factory_client_id = ? LIMIT 1');
    $st->execute([$id, $factory]);
    $cn = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) { /* handled below */ }

if (!$cn) { http_response_code(404); header('Content-Type: text/plain'); echo 'Credit note not found.'; exit; }

$lines = [];
try {
    $ls = $pdo->prepare('SELECT * FROM factory_ar_credit_note_lines WHERE credit_note_id = ? ORDER BY sort_order, id');
    $ls->execute([$id]);
    $lines = $ls->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* none */ }

$fac = [];
try {
    $fs = $pdo->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
    $fs->execute([$factory]);
    $fac = $fs->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { /* letterhead degrades */ }

// The invoice this credits (for the reference line).
$againstRef = '';
if (!empty($cn['against_invoice_id'])) {
    try {
        $q = $pdo->prepare('SELECT inv_number FROM factory_ar_invoices WHERE id = ? LIMIT 1');
        $q->execute([(int) $cn['against_invoice_id']]);
        $againstRef = (string) ($q->fetchColumn() ?: '');
    } catch (Throwable $e) { /* optional */ }
}

$notes = trim((string) ($cn['reason'] ?? ''));
if ($againstRef !== '') $notes = ($notes !== '' ? $notes . ' · ' : '') . 'Credit against ' . $againstRef;

$ctx = [
    'factory'     => $fac,
    'doc_title'   => 'CREDIT NOTE',
    'doc_number'  => (string) $cn['cn_number'],
    'issue_date'  => $cn['issue_date'] ? date('j F Y', strtotime((string) $cn['issue_date'])) : date('j F Y', strtotime((string) $cn['created_at'])),
    'due_date'    => '',
    'bill_to'     => (string) ($cn['bill_to_snapshot'] ?? ''),
    'order_ref'   => $againstRef,
    'vat_percent' => (float) $cn['vat_percent'],
    'notes'       => $notes,
    'bank'        => '',
    'watermark'   => $cn['status'] === 'void' ? 'VOID' : '',
];
$totals = ['subtotal' => (float) $cn['subtotal'], 'vat' => (float) $cn['vat'], 'total' => (float) $cn['total']];

$pdf = ar_render_invoice($ctx, $lines, $totals);
if ($pdf === null) { http_response_code(500); header('Content-Type: text/plain'); echo 'PDF engine unavailable.'; exit; }

$disposition = !empty($_GET['download']) ? 'attachment' : 'inline';
$filename    = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $cn['cn_number']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($pdf));
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
header('Cache-Control: private, no-store');
header('Pragma: no-cache');
echo $pdf;
