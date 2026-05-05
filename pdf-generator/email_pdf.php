<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../mailer.php';
require __DIR__ . '/../quote-builder/_helpers.php';
require __DIR__ . '/pdf.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Location: /quote-history/index.php');
    exit;
}

csrf_check();

$user     = current_user();
$id       = (int) ($_POST['id'] ?? 0);
$quote    = qb_load_quote_or_404($id, $user['client_id']);
$backUrl  = '/quote-history/view.php?id=' . $id;

if (!class_exists(\Dompdf\Dompdf::class)) {
    qb_flash_redirect(
        $backUrl,
        'error',
        'PDF generator not installed. Run "composer install" to add dompdf/dompdf.'
    );
}

$to = trim((string) ($_POST['to'] ?? ($quote['end_customer_email'] ?? '')));
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    qb_flash_redirect($backUrl, 'error', 'Please provide a valid recipient email address.');
}

$pdfBytes = pdf_render_quote($id, $user['client_id']);
if ($pdfBytes === null) {
    qb_flash_redirect($backUrl, 'error', 'Could not render the quote PDF.');
}

$customMessage = trim((string) ($_POST['message'] ?? ''));

$subject = sprintf(
    'Your quote %s from %s',
    (string) $quote['quote_number'],
    (string) $user['company_name']
);

$greeting = (string) ($quote['end_customer_name'] !== '' ? $quote['end_customer_name'] : 'there');

$body = "Hello {$greeting},\n\n"
      . "Please find your quote ({$quote['quote_number']}) attached as a PDF.\n";
if ($customMessage !== '') {
    $body .= "\n" . $customMessage . "\n";
}
$body .= "\nIf you have any questions please reply to this email.\n\n"
       . 'Kind regards,' . "\n"
       . (string) $user['company_name'];

$filename = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $quote['quote_number']) . '.pdf';

$ok = mailer_send(
    $to,
    $subject,
    $body,
    [
        'content'  => $pdfBytes,
        'filename' => $filename,
        'mime'     => 'application/pdf',
    ]
);

if (!$ok) {
    qb_flash_redirect(
        $backUrl,
        'error',
        'Could not send the email. Check SMTP credentials in .env and the PHP error log.'
    );
}

// Promote draft -> sent on first successful send.
if ((string) $quote['status'] === 'draft') {
    db()->prepare('UPDATE quotes SET status = "sent" WHERE id = ?')->execute([$id]);
}

qb_flash_redirect($backUrl, 'success', 'Quote PDF emailed to ' . $to . '.');
