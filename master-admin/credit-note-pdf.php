<?php
declare(strict_types=1);

/**
 * Stream a wholesale credit note PDF (Beverley → trade account). Super-admin only.
 * Usage: /master-admin/credit-note-pdf.php?id=N[&download=1]
 *
 * Built by ar_credit_note_pdf() — the same bytes ar_send_credit_note() emails.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_ar.php';
require_once __DIR__ . '/../pdf-generator/ar_pdf.php';

requireSuperAdmin();

$pdo     = db();
$factory = ar_factory_id();
$id      = (int) ($_GET['id'] ?? 0);

$doc   = null;
$found = false;
try {
    $chk = $pdo->prepare('SELECT 1 FROM factory_ar_credit_notes WHERE id = ? AND factory_client_id = ? LIMIT 1');
    $chk->execute([$id, $factory]);
    $found = (bool) $chk->fetchColumn();
    if ($found) $doc = ar_credit_note_pdf($pdo, $factory, $id);
} catch (Throwable $e) { /* handled below */ }

if (!$found) { http_response_code(404); header('Content-Type: text/plain'); echo 'Credit note not found.'; exit; }
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
