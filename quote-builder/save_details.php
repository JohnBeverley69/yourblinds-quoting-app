<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

csrf_check();

$user     = current_user();
$clientId = (int) $user['client_id'];
$quoteId  = (int) ($_POST['quote_id'] ?? 0);
$quote    = qb_load_quote_or_404($quoteId, $clientId);
qb_require_quote_access($quote, $user, current_user_permissions());

if (!qb_is_editable($quote)) {
    qb_flash_redirect(
        '/quote-builder/edit.php?id=' . $quoteId,
        'error',
        'Quote is locked (status: ' . $quote['status'] . '). Reopen it to edit.'
    );
}

// Trade order (raised FOR a trade account): the customer IS the linked
// account, whose details are managed on the account itself — this panel only
// captures the per-order references. Save just those and stop; don't touch the
// end_customer_* snapshot or demand a customer name that isn't on this form.
if ((int) ($quote['account_client_id'] ?? 0) > 0) {
    $emptyToNull = static function (string $k): ?string {
        $v = trim((string) ($_POST[$k] ?? ''));
        return $v === '' ? null : mb_substr($v, 0, 100);
    };
    db()->prepare(
        'UPDATE quotes SET customer_reference = ?, additional_reference = ?, notes = ?
          WHERE id = ? AND client_id = ?'
    )->execute([
        $emptyToNull('customer_reference'),
        $emptyToNull('additional_reference'),
        (function () { $v = trim((string) ($_POST['notes'] ?? '')); return $v === '' ? null : $v; })(),
        $quoteId,
        $clientId,
    ]);
    qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'success', 'Order details saved.');
}

$name = trim((string) ($_POST['end_customer_name'] ?? ''));
if ($name === '') {
    qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'error', 'Customer name is required.');
}
if (strlen($name) > 150) {
    qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'error', 'Customer name is too long (max 150 chars).');
}

$customerId = (int) ($_POST['customer_id'] ?? 0);
// Only link a customer that belongs to THIS tenant — don't trust the
// posted id. Keeps quotes.customer_id referentially sound within the
// tenant (mirrors the check in new.php). Unknown id → leave unlinked.
if ($customerId > 0) {
    $cs = db()->prepare('SELECT 1 FROM customers WHERE id = ? AND client_id = ? LIMIT 1');
    $cs->execute([$customerId, $clientId]);
    if (!$cs->fetchColumn()) {
        $customerId = 0;
    }
}
$emptyToNull = static function (string $k): ?string {
    $v = trim((string) ($_POST[$k] ?? ''));
    return $v === '' ? null : $v;
};

$hasWhatsapp = !empty($_POST['has_whatsapp']) ? 1 : 0;

$u = db()->prepare(
    'UPDATE quotes
        SET customer_id = ?,
            end_customer_name = ?, end_customer_email = ?, end_customer_phone = ?, end_customer_mobile = ?,
            has_whatsapp = ?,
            end_customer_address1 = ?, end_customer_address2 = ?,
            end_customer_town = ?, end_customer_county = ?, end_customer_postcode = ?,
            notes = ?
      WHERE id = ? AND client_id = ?'
);
$u->execute([
    $customerId > 0 ? $customerId : null,
    $name,
    $emptyToNull('end_customer_email'),
    $emptyToNull('end_customer_phone'),
    $emptyToNull('end_customer_mobile'),
    $hasWhatsapp,
    $emptyToNull('end_customer_address1'),
    $emptyToNull('end_customer_address2'),
    $emptyToNull('end_customer_town'),
    $emptyToNull('end_customer_county'),
    $emptyToNull('end_customer_postcode'),
    $emptyToNull('notes'),
    $quoteId,
    $clientId,
]);

qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'success', 'Customer details saved.');
