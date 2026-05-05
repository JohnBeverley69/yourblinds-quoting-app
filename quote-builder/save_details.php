<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Location: /quote-history/index.php');
    exit;
}

csrf_check();

$user     = current_user();
$clientId = $user['client_id'];
$quoteId  = (int) ($_POST['quote_id'] ?? 0);

$quote = qb_load_quote_or_404($quoteId, $clientId);

$customerIdSel = (int) ($_POST['customer_id'] ?? 0);
if ($customerIdSel > 0) {
    $stmt = db()->prepare('SELECT id FROM customers WHERE id = ? AND client_id = ? LIMIT 1');
    $stmt->execute([$customerIdSel, $clientId]);
    if (!$stmt->fetch()) {
        $customerIdSel = 0;
    }
}

$name = trim((string) ($_POST['end_customer_name'] ?? ''));
if ($name === '') {
    qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'error', 'Customer name is required.');
}

$fields = [
    'end_customer_name'     => $name,
    'end_customer_email'    => trim((string) ($_POST['end_customer_email']    ?? '')) ?: null,
    'end_customer_phone'    => trim((string) ($_POST['end_customer_phone']    ?? '')) ?: null,
    'end_customer_address1' => trim((string) ($_POST['end_customer_address1'] ?? '')) ?: null,
    'end_customer_address2' => trim((string) ($_POST['end_customer_address2'] ?? '')) ?: null,
    'end_customer_town'     => trim((string) ($_POST['end_customer_town']     ?? '')) ?: null,
    'end_customer_county'   => trim((string) ($_POST['end_customer_county']   ?? '')) ?: null,
    'end_customer_postcode' => trim((string) ($_POST['end_customer_postcode'] ?? '')) ?: null,
    'notes'                 => trim((string) ($_POST['notes']                 ?? '')) ?: null,
    'customer_id'           => $customerIdSel > 0 ? $customerIdSel : null,
];

$stmt = db()->prepare(
    'UPDATE quotes
        SET customer_id           = :customer_id,
            end_customer_name     = :end_customer_name,
            end_customer_email    = :end_customer_email,
            end_customer_phone    = :end_customer_phone,
            end_customer_address1 = :end_customer_address1,
            end_customer_address2 = :end_customer_address2,
            end_customer_town     = :end_customer_town,
            end_customer_county   = :end_customer_county,
            end_customer_postcode = :end_customer_postcode,
            notes                 = :notes
      WHERE id = :id AND client_id = :client_id'
);
$stmt->execute(array_merge($fields, [
    'id'        => $quoteId,
    'client_id' => $clientId,
]));

qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'success', 'Customer details saved.');
