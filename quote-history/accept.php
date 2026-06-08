<?php
declare(strict_types=1);

/**
 * Public accept handler — receives the typed-name confirmation from
 * /quote-history/public.php.
 *
 * Auth model: the public_token in the POST is the auth (64+ hex chars,
 * matched against the same token on the quote row). The trade business
 * who created the quote already trusts whoever has the link.
 *
 * Status transitions:
 *   sent  → accepted   (with signature recorded)
 *   sent  → declined   (no signature required)
 *   anything else → leave alone (idempotent — refresh-on-success won't double-accept)
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../quote-builder/_helpers.php';
require __DIR__ . '/../mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$token  = trim((string) ($_POST['token']  ?? ''));
$action = trim((string) ($_POST['action'] ?? ''));
$name   = trim((string) ($_POST['signature_name'] ?? ''));

if ($token === '' || !preg_match('/^[a-f0-9]{40,128}$/i', $token)) {
    http_response_code(404);
    exit('Quote not found.');
}

$pdo = db();

$qStmt = $pdo->prepare(
    'SELECT q.id, q.status, q.end_customer_name, q.client_id,
            q.end_customer_email, q.quote_number, q.public_token,
            c.company_name AS trade_company_name
       FROM quotes q
       JOIN clients c ON c.id = q.client_id
      WHERE q.public_token = ? LIMIT 1'
);
$qStmt->execute([$token]);
$quote = $qStmt->fetch();

if (!$quote) {
    http_response_code(404);
    exit('Quote not found.');
}

$publicUrl = '/quote-history/public.php?token=' . urlencode($token);

if ((string) $quote['status'] !== 'sent') {
    // Already moved on — or never sent. Don't transition anything; just bounce
    // back to the public page where the appropriate state will render.
    $_SESSION['flash_error'] = 'This quote is no longer awaiting your response.';
    header('Location: ' . $publicUrl);
    exit;
}

if ($action === 'accept') {
    if ($name === '') {
        $_SESSION['flash_error'] = 'Please type your full name to confirm acceptance.';
        header('Location: ' . $publicUrl);
        exit;
    }
    // If the trade business has Terms & Conditions configured, the customer
    // must tick the "I have read and understood" box. Enforced server-side
    // too, not just via the HTML `required` checkbox. Guarded so it's a no-op
    // on schemas where migrate_terms_conditions.php hasn't run.
    $hasTerms = false;
    try {
        $tStmt = $pdo->prepare(
            'SELECT terms_conditions FROM client_settings WHERE client_id = ? LIMIT 1'
        );
        $tStmt->execute([(int) $quote['client_id']]);
        $hasTerms = trim((string) ($tStmt->fetchColumn() ?: '')) !== '';
    } catch (Throwable $e) { /* column missing — treat as no terms */ }
    if ($hasTerms && empty($_POST['agree_terms'])) {
        $_SESSION['flash_error'] = 'Please confirm you have read and understood the Terms & Conditions before accepting.';
        header('Location: ' . $publicUrl);
        exit;
    }
    $ip = client_ip();
    $pdo->prepare(
        'UPDATE quotes
            SET status                    = "accepted",
                acceptance_signature_name = ?,
                acceptance_ip             = ?,
                accepted_at               = NOW()
          WHERE id = ?'
    )->execute([substr($name, 0, 150), substr($ip, 0, 45), (int) $quote['id']]);

    // Auto-create the installation appointment so the trade business
    // sees the job land on their calendar the moment the customer
    // accepts. Idempotent — repeat accepts don't multiply appointments.
    qb_create_appointment_from_quote($pdo, (int) $quote['id']);

    // Thank-you email to the customer — best-effort, never blocks acceptance
    // (mailer_send logs its own failures). Sent once: a second submit bounces
    // at the status guard above before reaching here.
    $custEmail = trim((string) ($quote['end_customer_email'] ?? ''));
    if ($custEmail !== '' && filter_var($custEmail, FILTER_VALIDATE_EMAIL)) {
        $company  = trim((string) ($quote['trade_company_name'] ?? '')) ?: 'your supplier';
        $greeting = trim((string) ($quote['end_customer_name'] ?? '')) !== ''
            ? (string) $quote['end_customer_name'] : 'there';
        $appUrl   = trim((string) (env('APP_URL', '') ?? ''));
        $viewUrl  = ($appUrl !== '' ? rtrim($appUrl, '/') : '')
            . '/quote-history/public.php?token=' . urlencode((string) $quote['public_token']);

        $subject = sprintf('Thank you for accepting quote %s', (string) $quote['quote_number']);
        $body  = "Hello {$greeting},\n\n";
        $body .= "Thank you for accepting your quote ({$quote['quote_number']}) — we really appreciate "
               . "your business and are delighted to have you as a customer.\n\n";
        $body .= "We'll be in touch shortly to arrange the next steps. If you have any questions in the "
               . "meantime, just reply to this email.\n\n";
        $body .= "You can view your quote any time here:\n{$viewUrl}\n\n";
        $body .= "With thanks,\n{$company}";

        mailer_send($custEmail, $subject, $body);
    }

    $_SESSION['flash_success'] = 'Quote accepted. Thanks!';
    header('Location: ' . $publicUrl);
    exit;
}

if ($action === 'decline') {
    $pdo->prepare(
        'UPDATE quotes SET status = "declined" WHERE id = ?'
    )->execute([(int) $quote['id']]);

    $_SESSION['flash_success'] = 'Quote declined. Your supplier has been notified.';
    header('Location: ' . $publicUrl);
    exit;
}

$_SESSION['flash_error'] = 'Unknown action.';
header('Location: ' . $publicUrl);
exit;
