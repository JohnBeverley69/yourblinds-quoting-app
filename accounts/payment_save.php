<?php
declare(strict_types=1);

/**
 * Record a new payment, or update an existing one. POST + CSRF.
 *
 * Posted fields:
 *   id            optional — present = UPDATE, absent = INSERT
 *   quote_id      optional — link to a quote (typical case)
 *   customer_id   optional — link to a customer (auto-set from
 *                            quote if quote_id provided)
 *   amount        required
 *   received_at   required (YYYY-MM-DD)
 *   method        required (one of acct_methods())
 *   reference     optional
 *   notes         optional
 *   return_to     optional — URL to redirect back to after save
 *
 * Tenant-scoped via the trailing client_id placeholder in every
 * statement.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../quote-builder/_helpers.php';   // qb_settle_if_paid

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

csrf_check();

$user     = current_user();
$clientId = (int) $user['client_id'];
$pdo      = db();

// Paid add-on — gate at the handler too, not just the UI.
acct_require_feature($clientId);

$id        = (int) ($_POST['id']          ?? 0);
$quoteId   = (int) ($_POST['quote_id']    ?? 0) ?: null;
$amountRaw = trim((string) ($_POST['amount']      ?? ''));
$dateRaw   = trim((string) ($_POST['received_at'] ?? ''));
$method    = (string) ($_POST['method']      ?? 'bank_transfer');
$reference = trim((string) ($_POST['reference']   ?? '')) ?: null;
$notes     = trim((string) ($_POST['notes']       ?? '')) ?: null;
// Who the payment came from. Most useful for standalone payments with no
// linked order/customer. Only persisted if the column exists (migration).
$payerName = trim((string) ($_POST['payer_name']  ?? '')) ?: null;
$hasPayer  = acct_has_payer_column();
$returnTo  = safe_local_redirect(
    (string) ($_POST['return_to'] ?? ''), '/accounts/index.php'
);

// Validation.
$error = null;
if (!is_numeric($amountRaw) || (float) $amountRaw == 0) {
    $error = 'Amount must be a non-zero number.';
} elseif ($dateRaw === ''
    || !DateTimeImmutable::createFromFormat('!Y-m-d', $dateRaw)) {
    $error = 'Received date is required (YYYY-MM-DD).';
} elseif (!array_key_exists($method, acct_methods())) {
    $error = 'Unknown payment method.';
}

if ($error) {
    $_SESSION['flash_error'] = $error;
    header('Location: ' . $returnTo);
    exit;
}

$amount     = round((float) $amountRaw, 2);
$receivedAt = $dateRaw;

// If quote_id was provided, sanity-check it belongs to this tenant
// AND grab the customer_id off the quote so payments are also
// indexed by customer (handy for future per-customer reports).
$customerId = null;
if ($quoteId) {
    $qSt = $pdo->prepare(
        'SELECT customer_id FROM quotes WHERE id = ? AND client_id = ? LIMIT 1'
    );
    $qSt->execute([$quoteId, $clientId]);
    $row = $qSt->fetch();
    if (!$row) {
        $_SESSION['flash_error'] = 'Quote not found for this tenant.';
        header('Location: ' . $returnTo);
        exit;
    }
    $customerId = $row['customer_id'] !== null ? (int) $row['customer_id'] : null;
}

if ($id > 0) {
    // UPDATE — tenant-scoped via client_id in WHERE.
    $st = $pdo->prepare(
        'UPDATE payments
            SET amount      = ?,
                received_at = ?,
                method      = ?,
                reference   = ?,
                notes       = ?'
        . ($hasPayer ? ',
                payer_name  = ?' : '') . '
          WHERE id = ? AND client_id = ?'
    );
    $params = [$amount, $receivedAt, $method, $reference, $notes];
    if ($hasPayer) $params[] = $payerName;
    $params[] = $id;
    $params[] = $clientId;
    $st->execute($params);
    $_SESSION['flash_success'] = 'Payment updated.';
} else {
    $cols = 'client_id, quote_id, customer_id, amount, received_at, method, reference, notes'
          . ($hasPayer ? ', payer_name' : '');
    $vals = $hasPayer ? '?, ?, ?, ?, ?, ?, ?, ?, ?' : '?, ?, ?, ?, ?, ?, ?, ?';
    $st = $pdo->prepare("INSERT INTO payments ($cols) VALUES ($vals)");
    $params = [
        $clientId, $quoteId, $customerId,
        $amount, $receivedAt, $method, $reference, $notes,
    ];
    if ($hasPayer) $params[] = $payerName;
    $st->execute($params);
    $_SESSION['flash_success'] = 'Payment recorded: ' . acct_fmt_money($amount) . '.';
}

// Auto-mark the quote paid once the balance is settled (or un-settle if this
// was an edit that reduced it back below the total).
if ($quoteId) {
    qb_settle_if_paid($pdo, (int) $quoteId, $clientId);
}

header('Location: ' . $returnTo);
exit;
