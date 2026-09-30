<?php
declare(strict_types=1);

/**
 * Stream a wholesale invoice PDF (Beverley → trade account). Super-admin only.
 * Usage: /master-admin/invoice-pdf.php?id=N[&download=1]
 *
 * The document itself is built by ar_invoice_pdf() — the same bytes that
 * ar_send_invoice() emails to the account.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_ar.php';
require_once __DIR__ . '/../pdf-generator/ar_pdf.php';

requireFactoryOffice();

$pdo     = db();
$factory = ar_factory_id();
$id      = (int) ($_GET['id'] ?? 0);

$doc   = null;
$found = false;
try {
    $chk = $pdo->prepare('SELECT 1 FROM factory_ar_invoices WHERE id = ? AND factory_client_id = ? LIMIT 1');
    $chk->execute([$id, $factory]);
    $found = (bool) $chk->fetchColumn();
    if ($found) $doc = ar_invoice_pdf($pdo, $factory, $id);
} catch (Throwable $e) { /* handled below */ }

if (!$found) { http_response_code(404); header('Content-Type: text/plain'); echo 'Invoice not found.'; exit; }
if ($doc === null) { http_response_code(500); header('Content-Type: text/plain'); echo 'PDF engine unavailable.'; exit; }

$pdf         = $doc['pdf'];
$disposition = !empty($_GET['download']) ? 'attachment' : 'inline';
$filename    = $doc['filename'];
header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($pdf));
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
header('Cache-Control: private, no-store');
header('Pragma: no-cache');
echo $pdf;
