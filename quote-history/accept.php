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
    'SELECT id, status, end_customer_name FROM quotes
      WHERE public_token = ? LIMIT 1'
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
    $ip = client_ip();
    $pdo->prepare(
        'UPDATE quotes
            SET status                    = "accepted",
                acceptance_signature_name = ?,
                acceptance_ip             = ?,
                accepted_at               = NOW()
          WHERE id = ?'
    )->execute([substr($name, 0, 150), substr($ip, 0, 45), (int) $quote['id']]);

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
