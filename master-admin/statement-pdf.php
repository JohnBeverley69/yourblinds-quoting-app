<?php
declare(strict_types=1);

/**
 * Stream an account statement PDF (Beverley → trade account). Super-admin only.
 * Usage: /master-admin/statement-pdf.php?account_id=N[&from=&to=][&download=1]
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_ar.php';
require_once __DIR__ . '/../pdf-generator/ar_pdf.php';

requireSuperAdmin();

$pdo       = db();
$factory   = ar_factory_id();
$accountId = (int) ($_GET['account_id'] ?? 0);
$from      = trim((string) ($_GET['from'] ?? ''));
$to        = trim((string) ($_GET['to'] ?? ''));

$acStmt = $pdo->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
$acStmt->execute([$accountId]);
$acc = $acStmt->fetch(PDO::FETCH_ASSOC);
if (!$acc || $accountId === $factory) {
    http_response_code(404); header('Content-Type: text/plain'); echo 'Account not found.'; exit;
}

$fac = [];
try {
    $fs = $pdo->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
    $fs->execute([$factory]);
    $fac = $fs->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { /* letterhead degrades gracefully */ }

$data   = ar_statement($pdo, $factory, $accountId, $from, $to);
$billTo = ar_account_address_block($acc);
if (($acc['vat_number'] ?? '') !== '') $billTo .= "\nVAT No. " . $acc['vat_number'];

$ctx = [
    'factory'        => $fac,
    'bill_to'        => $billTo,
    'statement_date' => date('Y-m-d'),
    'from'           => $data['from'],
    'to'             => $data['to'],
];

$pdf = ar_render_statement($ctx, $data);
if ($pdf === null) { http_response_code(500); header('Content-Type: text/plain'); echo 'PDF engine unavailable.'; exit; }

$disposition = !empty($_GET['download']) ? 'attachment' : 'inline';
$namePart    = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($acc['company_name'] ?? 'account'));
$filename    = 'Statement-' . $namePart . '-' . date('Y-m-d') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($pdf));
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
header('Cache-Control: private, no-store');
header('Pragma: no-cache');
echo $pdf;
