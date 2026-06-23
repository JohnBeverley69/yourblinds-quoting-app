<?php
declare(strict_types=1);

/**
 * Send invoice to the customer.
 *
 * Emails the customer an INVOICE (the order PDF, rendered with an "Invoice"
 * heading + Paid / Balance-due lines), with the balance due stated in the
 * email, then advances the job to "Invoiced" — so the pipeline + calendar
 * reflect it. Mirrors the "Send to suppliers" flow on the order side.
 *
 *   POST id (quote id)
 *
 * Available once the job is an order (ordered → fitted → invoiced → paid).
 * Order-side action: admins + can_create_orders only.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../mailer.php';
require __DIR__ . '/../quote-builder/_helpers.php';
require __DIR__ . '/../_partials/calendar_money.php';
require __DIR__ . '/pdf.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Location: /orders/index.php');
    exit;
}

csrf_check();

$user     = current_user();
$isAdmin  = ($user['role'] ?? '') === 'admin';
$_perms   = current_user_permissions();
$clientId = (int) $user['client_id'];

$id      = (int) ($_POST['id'] ?? $_POST['quote_id'] ?? 0);
$quote   = qb_load_quote_or_404($id, $clientId);
$backUrl = '/quote-builder/edit.php?id=' . $id;

// Invoicing is an order-side action.
if (!$isAdmin && empty($_perms['can_create_orders'])) {
    qb_flash_redirect($backUrl, 'error', 'You don\'t have permission to invoice orders.');
}

// Only once the job is an order (ordered onward). Accepted-but-not-ordered
// jobs aren't invoiceable yet — place the order first.
if (!in_array((string) $quote['status'], ['ordered', 'fitted', 'invoiced', 'paid'], true)) {
    qb_flash_redirect($backUrl, 'error', 'You can invoice once the job is ordered — move it to Ordered first.');
}

if (!class_exists(\Dompdf\Dompdf::class)) {
    qb_flash_redirect($backUrl, 'error', 'PDF generator not installed. Run "composer install" to add dompdf/dompdf.');
}

$to = trim((string) ($_POST['to'] ?? ($quote['end_customer_email'] ?? '')));
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    qb_flash_redirect($backUrl, 'error', 'No valid customer email on this order — add one on the customer, then try again.');
}

// Render the order PDF as an INVOICE (heading + Paid / Balance-due lines).
$pdfBytes = pdf_render_quote($id, $clientId, 'Invoice');
if ($pdfBytes === null) {
    qb_flash_redirect($backUrl, 'error', 'Could not render the invoice PDF.');
}

// Balance due for the email wording — same basis as the document.
$money = calendar_money_for_quotes(db(), $clientId, [$id]);
$bal   = isset($money[$id]) ? (float) $money[$id]['balance'] : (float) $quote['total'];
$fmt   = static fn ($n) => '£' . number_format((float) $n, 2);

$subject = sprintf(
    'Invoice %s from %s',
    (string) $quote['quote_number'],
    (string) $user['company_name']
);

$greeting = (string) $quote['end_customer_name'] !== ''
    ? (string) $quote['end_customer_name'] : 'there';

$appUrl    = trim((string) (env('APP_URL', '') ?? ''));
$publicUrl = $appUrl !== ''
    ? rtrim($appUrl, '/') . '/quote-history/public.php?token=' . urlencode((string) $quote['public_token'])
    : '';

$customMessage = trim((string) ($_POST['message'] ?? ''));

$body  = "Hello {$greeting},\n\n";
$body .= "Please find your invoice ({$quote['quote_number']}) attached as a PDF.\n";
if ($bal > 0.0049) {
    $body .= "\nBalance due: " . $fmt($bal) . ". Payment details are on the invoice.\n";
} else {
    $body .= "\nThis invoice is fully paid — thank you.\n";
}
if ($customMessage !== '') {
    $body .= "\n" . $customMessage . "\n";
}
if ($publicUrl !== '') {
    $body .= "\nYou can also view it online here:\n" . $publicUrl . "\n";
}
$body .= "\nIf you have any questions please reply to this email.\n\n";
$body .= "Kind regards,\n";
$body .= (string) $user['company_name'];

$filename = 'Invoice_'
    . preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $quote['quote_number']) . '.pdf';

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
        'Could not send the invoice email. Check SMTP credentials in .env and the PHP error log.'
    );
}

// Advance to Invoiced — but only FROM ordered / fitted, so a job already
// invoiced / paid is never pulled back. SQL-guarded for race safety.
$advanced = false;
try {
    $adv = db()->prepare(
        "UPDATE quotes SET status = 'invoiced'
          WHERE id = ? AND client_id = ? AND status IN ('ordered', 'fitted')"
    );
    $adv->execute([$id, $clientId]);
    $advanced = $adv->rowCount() > 0;
} catch (Throwable $e) {
    error_log('send_invoice: advance to invoiced failed: ' . $e->getMessage());
}

qb_flash_redirect(
    $backUrl,
    'success',
    'Invoice emailed to ' . $to . '.' . ($advanced ? ' Marked as Invoiced.' : '')
);
