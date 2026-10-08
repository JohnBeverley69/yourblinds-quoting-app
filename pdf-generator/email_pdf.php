<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../mailer.php';
require __DIR__ . '/../quote-builder/_helpers.php';
require __DIR__ . '/pdf.php';
require_once __DIR__ . '/../_partials/send_quota.php';
require_once __DIR__ . '/../_partials/quote_expiry.php';
require_once __DIR__ . '/../_partials/tenant_mail.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Location: /orders/index.php');
    exit;
}

csrf_check();

$user     = current_user();
$id       = (int) ($_POST['id'] ?? $_POST['quote_id'] ?? 0);
$quote    = qb_load_quote_or_404($id, (int) $user['client_id']);
qb_require_quote_access($quote, $user, current_user_permissions());
$backUrl  = '/quote-builder/edit.php?id=' . $id;

// An email queued on a tablet with no signal carries a one-off client_ref. If the
// signal dropped after we sent it but before the tablet heard back, the tablet
// sends it again: don't email the customer twice.
$clientRef = substr(preg_replace('/[^A-Za-z0-9-]/', '', (string) ($_POST['client_ref'] ?? '')), 0, 64);
if ($clientRef !== '' && in_array($clientRef, $_SESSION['qb_sent_refs'] ?? [], true)) {
    qb_flash_redirect($backUrl, 'success', 'Quote PDF already emailed.');
}

if (!class_exists(\Dompdf\Dompdf::class)) {
    qb_flash_redirect(
        $backUrl,
        'error',
        'PDF generator not installed. Run "composer install" to add dompdf/dompdf.'
    );
}

$to = trim((string) ($_POST['to'] ?? ($quote['end_customer_email'] ?? '')));
if (!send_quota_allows((int) $user['client_id'])) {
    qb_flash_redirect($backUrl, 'error', 'Daily email limit reached for this account — please try again tomorrow, or contact support if you need more.');
}
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    qb_flash_redirect($backUrl, 'error', 'Please provide a valid recipient email address.');
}

$pdfBytes = pdf_render_quote($id, (int) $user['client_id']);
if ($pdfBytes === null) {
    qb_flash_redirect($backUrl, 'error', 'Could not render the quote PDF.');
}

$customMessage = trim((string) ($_POST['message'] ?? ''));

$subject = sprintf(
    'Your quote %s from %s',
    (string) $quote['quote_number'],
    (string) $user['company_name']
);

$greeting = (string) $quote['end_customer_name'] !== ''
    ? (string) $quote['end_customer_name'] : 'there';

// Build the absolute accept-link URL from APP_URL. Same defensive pattern as
// auth/forgot_password.php — never derive from $_SERVER['HTTP_HOST'].
$appUrl    = trim((string) (env('APP_URL', '') ?? ''));
$publicUrl = $appUrl !== ''
    ? rtrim($appUrl, '/') . '/quote-history/public.php?token=' . urlencode((string) $quote['public_token'])
    : '/quote-history/public.php?token=' . urlencode((string) $quote['public_token']);

$body  = "Hello {$greeting},\n\n";
$body .= "Please find your quote ({$quote['quote_number']}) attached as a PDF.\n";
if ($customMessage !== '') {
    $body .= "\n" . $customMessage . "\n";
}
$body .= "\nYou can also view it online and accept it here:\n";
$body .= $publicUrl . "\n";
$body .= "\nIf you have any questions please reply to this email.\n\n";
$body .= "Kind regards,\n";
$body .= (string) $user['company_name'];

$filename = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $quote['quote_number']) . '.pdf';

$ok = mailer_send(
    $to,
    $subject,
    $body,
    [
        'content'  => $pdfBytes,
        'filename' => $filename,
        'mime'     => 'application/pdf',
    ],
    null,
    tenant_mail_opts(db(), (int) $user['client_id'])
);

if ($ok) {
    send_quota_record((int) $user['client_id'], (int) $user['user_id'], 'quote');
    if ($clientRef !== '') {
        $_SESSION['qb_sent_refs'] = array_slice(array_merge($_SESSION['qb_sent_refs'] ?? [], [$clientRef]), -100);
    }
}
if (!$ok) {
    qb_flash_redirect(
        $backUrl,
        'error',
        'Could not send the email. Check SMTP credentials in .env and the PHP error log.'
    );
}

// Promote draft → sent on first successful send + stamp sent_at.
if ((string) $quote['status'] === 'draft') {
    db()->prepare('UPDATE quotes SET status = "sent", sent_at = NOW() WHERE id = ?')
        ->execute([$id]);
} elseif (empty($quote['sent_at']) || quote_is_expired($quote)) {
    // No send date yet, or re-sending an EXPIRED quote: that's a fresh issue,
    // so restart its acceptance window from today.
    db()->prepare('UPDATE quotes SET sent_at = NOW() WHERE id = ?')
        ->execute([$id]);
}

qb_flash_redirect($backUrl, 'success', 'Quote PDF emailed to ' . $to . '.');
