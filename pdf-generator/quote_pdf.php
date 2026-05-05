<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/pdf.php';

requireLogin();

$user = current_user();
$id   = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(404);
    exit('Quote not found.');
}

if (!class_exists(\Dompdf\Dompdf::class)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('PDF generator is not installed. Run "composer install" to add dompdf/dompdf.');
}

$pdfBytes = pdf_render_quote($id, $user['client_id']);
if ($pdfBytes === null) {
    http_response_code(404);
    exit('Quote not found.');
}

$qStmt = db()->prepare('SELECT quote_number FROM quotes WHERE id = ? AND client_id = ?');
$qStmt->execute([$id, $user['client_id']]);
$quoteNumber = (string) ($qStmt->fetchColumn() ?: '');
$baseName    = $quoteNumber !== '' ? $quoteNumber : ('quote-' . $id);

// Default to inline preview in the browser; ?download=1 forces download.
$disposition = !empty($_GET['download']) ? 'attachment' : 'inline';
$filename    = preg_replace('/[^A-Za-z0-9._-]/', '_', $baseName) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($pdfBytes));
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
header('Cache-Control: private, no-store');
header('Pragma: no-cache');
echo $pdfBytes;
