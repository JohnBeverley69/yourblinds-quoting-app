<?php
declare(strict_types=1);

/**
 * Helpers for the "New order" launcher (master-admin/new-order.php).
 *
 * Creates a Beverley (factory) quote raised FOR a trade account: owned by the
 * factory, customer = the account (details copied off its clients row), and
 * tagged with account_client_id so the quote builder prices it with the
 * account's trade discount (pe_calculate_item's $forAccountId).
 */

require_once __DIR__ . '/../quote-builder/_helpers.php';

/**
 * Create a factory quote for a trade account. Returns ['id','number'].
 * Throws if the account isn't a real, active client other than the factory.
 */
function no_create_account_quote(PDO $pdo, int $factoryClientId, int $accountId, int $userId): array
{
    if ($accountId <= 0 || $accountId === $factoryClientId) {
        throw new RuntimeException('Pick a valid credit account.');
    }

    $st = $pdo->prepare('SELECT * FROM clients WHERE id = ? AND active = 1 LIMIT 1');
    $st->execute([$accountId]);
    $acc = $st->fetch(PDO::FETCH_ASSOC);
    if (!$acc) {
        throw new RuntimeException('That account was not found or is inactive.');
    }

    // Contact email/phone: use the account's own columns if present, else fall
    // back to its admin login's details. All best-effort — blanks are fine.
    $email = trim((string) ($acc['email'] ?? ''));
    $phone = trim((string) ($acc['phone'] ?? ($acc['telephone'] ?? '')));
    if ($email === '') {
        try {
            $us = $pdo->prepare(
                "SELECT email, full_name FROM client_users
                  WHERE client_id = ? AND active = 1 ORDER BY (role = 'admin') DESC, id LIMIT 1"
            );
            $us->execute([$accountId]);
            if ($u = $us->fetch(PDO::FETCH_ASSOC)) {
                $email = trim((string) ($u['email'] ?? ''));
            }
        } catch (Throwable $e) { /* no login — leave blank */ }
    }

    $f = [
        'customer_id'           => 0,
        'end_customer_name'     => (string) ($acc['company_name'] ?? 'Trade account'),
        'end_customer_email'    => $email,
        'end_customer_phone'    => $phone,
        'end_customer_address1' => (string) ($acc['address1'] ?? ''),
        'end_customer_address2' => (string) ($acc['address2'] ?? ''),
        'end_customer_town'     => (string) ($acc['town'] ?? ''),
        'end_customer_county'   => (string) ($acc['county'] ?? ''),
        'end_customer_postcode' => (string) ($acc['postcode'] ?? ''),
        'has_whatsapp'          => 0,
        'notes'                 => '',
    ];

    return qb_create_quote_from_fields($pdo, $factoryClientId, $f, 0, $userId, $accountId);
}
