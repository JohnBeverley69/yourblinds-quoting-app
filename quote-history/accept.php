<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';  // for csrf_check
require __DIR__ . '/../mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Location: /quote-history/public.php');
    exit;
}

csrf_check();

$token   = (string) ($_POST['token'] ?? '');
$backUrl = '/quote-history/public.php?token=' . urlencode($token);

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    $_SESSION['flash_error'] = 'Invalid quote link.';
    header('Location: ' . $backUrl);
    exit;
}

$pdo = db();

// Atomic accept: only succeeds if the quote is in an acceptable state.
// Race-safe — two clicks can't both flip status because rowCount on the second
// will be 0 (accepted_at is now set, so the IS NULL guard fails).
$stmt = $pdo->prepare(
    'UPDATE quotes
        SET status      = "accepted",
            accepted_at = NOW()
      WHERE public_token = ?
        AND accepted_at  IS NULL
        AND status       IN ("sent", "draft")
        AND (valid_until IS NULL OR valid_until >= CURDATE())'
);
$stmt->execute([$token]);

if ($stmt->rowCount() === 0) {
    $_SESSION['flash_error'] =
        'This quote could not be accepted — it may already be accepted, expired, or in a different state.';
    header('Location: ' . $backUrl);
    exit;
}

// Look up the now-accepted quote so we can notify the trade business.
$qStmt = $pdo->prepare(
    'SELECT q.*,
            c.company_name,
            c.email              AS client_email,
            c.office_quote_email AS client_office_email
       FROM quotes q
       JOIN clients c ON c.id = q.client_id
      WHERE q.public_token = ?
      LIMIT 1'
);
$qStmt->execute([$token]);
$quote = $qStmt->fetch();

if ($quote) {
    $notifyTo = trim((string) ($quote['client_office_email'] ?? '')) !== ''
        ? (string) $quote['client_office_email']
        : (string) ($quote['client_email'] ?? '');

    if ($notifyTo !== '' && filter_var($notifyTo, FILTER_VALIDATE_EMAIL)) {
        $subject = sprintf(
            'Quote %s accepted by %s',
            (string) $quote['quote_number'],
            (string) $quote['end_customer_name']
        );

        $body = "Good news — your customer has accepted the quote.\n\n"
              . 'Quote:    ' . (string) $quote['quote_number']     . "\n"
              . 'Customer: ' . (string) $quote['end_customer_name'] . "\n"
              . (!empty($quote['end_customer_email'])
                  ? 'Email:    ' . (string) $quote['end_customer_email'] . "\n" : '')
              . (!empty($quote['end_customer_phone'])
                  ? 'Phone:    ' . (string) $quote['end_customer_phone'] . "\n" : '')
              . 'Total:    £' . number_format((float) $quote['total'], 2) . "\n"
              . 'Accepted: ' . date('j M Y H:i') . "\n\n"
              . 'View this quote in YourBlinds: '
              . '/quote-history/view.php?id=' . (int) $quote['id'] . "\n\n"
              . '— YourBlinds';

        mailer_send($notifyTo, $subject, $body);
        // mailer_send returns false on SMTP misconfiguration / failure; we
        // intentionally don't surface that to the public visitor.
    }
}

$_SESSION['flash_success'] =
    'Thanks! Your acceptance has been recorded. The supplier has been notified.';
header('Location: ' . $backUrl);
exit;
