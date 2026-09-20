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
        'end_customer_mobile'   => trim((string) ($acc['mobile'] ?? '')),
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

/**
 * Find an existing (active) trade account by an exact, case-insensitive company
 * name. Returns the account id, or 0 if none. Used to warn before creating a
 * duplicate account from the manual "save as new trade account" path.
 */
function no_find_account_by_name(PDO $pdo, int $factoryClientId, string $company): int
{
    $company = trim($company);
    if ($company === '') return 0;
    $st = $pdo->prepare(
        'SELECT id FROM clients
          WHERE id <> ? AND active = 1 AND LOWER(company_name) = LOWER(?)
          ORDER BY id LIMIT 1'
    );
    $st->execute([$factoryClientId, $company]);
    return (int) ($st->fetchColumn() ?: 0);
}

/**
 * Create a lightweight, no-portal trade account (a `clients` row Beverley sells
 * to — no login, no catalogue seed, no trial). Mirrors the clients +
 * client_settings inserts from new-client.php's no-portal path, without the
 * tenant-onboarding baggage. Returns the new account id.
 *
 * $fields: company_name (required) + optional contact_name/email/phone/mobile/
 * address1/address2/town/county/postcode.
 */
function no_create_trade_account(PDO $pdo, array $fields): int
{
    $company = trim((string) ($fields['company_name'] ?? ''));
    if ($company === '') {
        throw new RuntimeException('Company name is required to create a trade account.');
    }
    $emptyToNull = static fn (string $k) => trim((string) ($fields[$k] ?? '')) !== ''
        ? trim((string) $fields[$k]) : null;

    // clients.mobile only exists post Phase-2 migration — include it only if so.
    $hasMobile = false;
    try { $pdo->query('SELECT `mobile` FROM clients LIMIT 0'); $hasMobile = true; } catch (Throwable $e) {}

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) $pdo->beginTransaction();
    try {
        $cols = ['company_name', 'contact_name', 'email', 'phone', 'address1', 'address2', 'town', 'county', 'postcode', 'active'];
        $vals = [
            $company,
            $emptyToNull('contact_name'),
            $emptyToNull('email'),
            $emptyToNull('phone'),
            $emptyToNull('address1'),
            $emptyToNull('address2'),
            $emptyToNull('town'),
            $emptyToNull('county'),
            $emptyToNull('postcode'),
            1,
        ];
        if ($hasMobile) {
            array_splice($cols, 4, 0, 'mobile');           // after phone
            array_splice($vals, 4, 0, [$emptyToNull('mobile')]);
        }
        $ph = implode(', ', array_fill(0, count($cols), '?'));
        $pdo->prepare('INSERT INTO clients (' . implode(', ', $cols) . ') VALUES (' . $ph . ')')
            ->execute($vals);
        $newId = (int) $pdo->lastInsertId();

        // Empty settings row so master-admin toggles never create-on-write.
        $pdo->prepare('INSERT INTO client_settings (client_id) VALUES (?)')->execute([$newId]);

        if ($ownTx) $pdo->commit();
        return $newId;
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Create a factory quote for a manually-entered one-off trade customer (no
 * account, standard pricing). The company/contact are snapshotted onto the
 * quote's end_customer_* fields and it's marked sale_type = 'trade' so it's
 * still distinguishable from a plain retail sale downstream.
 */
function no_create_oneoff_trade_quote(PDO $pdo, int $factoryClientId, array $fields, int $userId): array
{
    $company = trim((string) ($fields['company_name'] ?? ''));
    $contact = trim((string) ($fields['contact_name'] ?? ''));
    if ($company === '' && $contact === '') {
        throw new RuntimeException('Enter at least a company or contact name.');
    }
    $displayName = $company !== '' ? $company : $contact;
    if ($company !== '' && $contact !== '') $displayName = $company . ' (' . $contact . ')';

    $f = [
        'customer_id'           => 0,
        'end_customer_name'     => $displayName,
        'end_customer_email'    => trim((string) ($fields['email']    ?? '')),
        'end_customer_phone'    => trim((string) ($fields['phone']    ?? '')),
        'end_customer_mobile'   => trim((string) ($fields['mobile']   ?? '')),
        'end_customer_address1' => trim((string) ($fields['address1'] ?? '')),
        'end_customer_address2' => trim((string) ($fields['address2'] ?? '')),
        'end_customer_town'     => trim((string) ($fields['town']     ?? '')),
        'end_customer_county'   => trim((string) ($fields['county']   ?? '')),
        'end_customer_postcode' => trim((string) ($fields['postcode'] ?? '')),
        'has_whatsapp'          => trim((string) ($fields['mobile'] ?? '')) !== '' && !empty($fields['has_whatsapp']) ? 1 : 0,
        // A trade customer hands you their PO with the order, so take it here
        // rather than making someone go back for it afterwards.
        'customer_reference'    => trim((string) ($fields['customer_reference'] ?? '')),
        'notes'                 => '',
    ];

    // account_client_id = 0 (one-off, standard price), but explicitly sale_type=trade.
    return qb_create_quote_from_fields($pdo, $factoryClientId, $f, 0, $userId, 0, 'trade');
}
