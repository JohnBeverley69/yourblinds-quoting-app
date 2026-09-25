<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../quote-builder/_helpers.php';
require __DIR__ . '/pdf.php';

requireLogin();

$user = current_user();
$id   = (int) ($_GET['id'] ?? 0);

// Same gate as the quote editor: a restricted user (fitter) only gets PDFs
// of the orders they're assigned to, not every quote in the tenant.
qb_require_quote_access(
    qb_load_quote_or_404($id, (int) $user['client_id']),
    $user,
    current_user_permissions()
);

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
