<?php
declare(strict_types=1);

/**
 * QuickBooks customer matching — which customer in the company's QuickBooks
 * each trade account is. Matched ONCE, confirmed by the accounts person,
 * stored in accounting_customer_links; the paid-invoice push (next step)
 * looks the link up and never creates a duplicate customer.
 *
 * Annette's QuickBooks already has a customer for every trade account, so
 * the default is LINK, not create. Creating a QuickBooks customer is a
 * separate, confirmed action for a genuinely new account.
 *
 * Suggestions (qbc_suggest) compare the trade account's company name and Acc
 * Ref with each QuickBooks customer's display name and company name:
 *   'exact'    same name once case, punctuation and Ltd/Limited are ignored
 *   'likely'   one name contains the other, or they're very close
 * Nothing is linked from a suggestion until someone saves it.
 */

require_once __DIR__ . '/accounting.php';
require_once __DIR__ . '/quickbooks.php';

/**
 * Every customer in the connected QuickBooks company (active and inactive —
 * an inactive one is shown, flagged, so it isn't recreated by mistake).
 * Pages through QuickBooks' 1,000-row query limit.
 *
 * @return array<string, array{id:string, name:string, company:string, email:string, active:bool}> keyed by Id
 */
function qbc_list(int $clientId): array
{
    $out = [];
    for ($start = 1; $start < 20000; $start += 1000) {
        $resp = qbo_query($clientId,
            "SELECT Id, DisplayName, CompanyName, PrimaryEmailAddr, Active FROM Customer WHERE Active IN (true, false)"
            . " ORDERBY DisplayName STARTPOSITION $start MAXRESULTS 1000");
        $rows = (array) ($resp['Customer'] ?? []);
        foreach ($rows as $c) {
            $id = (string) ($c['Id'] ?? '');
            if ($id === '') continue;
            $out[$id] = [
                'id'      => $id,
                'name'    => (string) ($c['DisplayName'] ?? ''),
                'company' => (string) ($c['CompanyName'] ?? ''),
                'email'   => (string) ($c['PrimaryEmailAddr']['Address'] ?? ''),
                'active'  => !isset($c['Active']) || $c['Active'] !== false,
            ];
        }
        if (count($rows) < 1000) break;
    }
    return $out;
}

/** Name reduced for comparing: lower case, no punctuation, no Ltd/Limited/The. */
function qbc_norm(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = str_replace('&', ' and ', $s);
    $s = preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $s) ?? $s;
    $s = preg_replace('/\b(ltd|limited|plc|llp|the|co|uk)\b/u', ' ', $s) ?? $s;
    return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
}

/**
 * Best QuickBooks customer for one trade account, or null.
 *
 * @param array{company_name:string, account_ref:?string} $account
 * @param array<string, array> $customers from qbc_list()
 * @return array{id:string, strength:string}|null
 */
function qbc_suggest(array $account, array $customers): ?array
{
    $mine = qbc_norm((string) ($account['company_name'] ?? ''));
    $ref  = qbc_norm((string) ($account['account_ref'] ?? ''));
    if ($mine === '' && $ref === '') return null;

    $best = null; $bestScore = 0.0;
    foreach ($customers as $c) {
        foreach ([$c['name'], $c['company']] as $theirsRaw) {
            $theirs = qbc_norm($theirsRaw);
            if ($theirs === '') continue;
            if ($theirs === $mine || ($ref !== '' && $theirs === $ref)) {
                // An exact match on an ACTIVE customer wins outright.
                if ($c['active']) return ['id' => $c['id'], 'strength' => 'exact'];
                $score = 99.0;
            } elseif ($mine === '' || preg_replace('/\D+/', '', $mine) !== preg_replace('/\D+/', '', $theirs)) {
                // Different numbers ("Blinds 2 U" / "Blinds 4 U", "Unit 3" / "Unit 7") are different firms.
                continue;
            } elseif (substr_count($mine, ' ') >= 1 && substr_count($theirs, ' ') >= 1
                      && (qbc_words_within($mine, $theirs) || qbc_words_within($theirs, $mine))) {
                // Two words or more on both sides, so "Blinds" alone never matches "Vesta Blinds".
                $score = 90.0;   // every word of one name appears in the other
            } else {
                similar_text($mine, $theirs, $pct);
                $score = (float) $pct >= 88 ? (float) $pct : 0.0;   // only a near-identical spelling
            }
            if (!$c['active']) $score -= 5;
            if ($score > $bestScore) { $bestScore = $score; $best = $c['id']; }
        }
    }
    if ($best === null || $bestScore < 82) return null;
    return ['id' => $best, 'strength' => $bestScore >= 99 ? 'exact' : 'likely'];
}

/** True when every word of $a (normalised) is a whole word in $b. */
function qbc_words_within(string $a, string $b): bool
{
    $bw = array_flip(explode(' ', $b));
    foreach (explode(' ', $a) as $w) {
        if ($w !== '' && !isset($bw[$w])) return false;
    }
    return true;
}

/** Saved links for a company: account_client_id => row. */
function qbc_links(int $clientId): array
{
    $st = db()->prepare('SELECT * FROM accounting_customer_links WHERE client_id = ? AND provider = ?');
    $st->execute([$clientId, 'quickbooks']);
    $out = [];
    foreach ($st->fetchAll() as $r) $out[(int) $r['account_client_id']] = $r;
    return $out;
}

/** The QuickBooks customer Id a trade account is linked to, or null. Used by the push. */
function qbc_customer_id(int $clientId, int $accountId): ?string
{
    $st = db()->prepare('SELECT external_id FROM accounting_customer_links WHERE client_id = ? AND provider = ? AND account_client_id = ?');
    $st->execute([$clientId, 'quickbooks', $accountId]);
    $v = $st->fetchColumn();
    return $v === false ? null : (string) $v;
}

/** Link (or re-link) a trade account to a QuickBooks customer. */
function qbc_link(int $clientId, int $accountId, string $externalId, string $externalName, ?int $userId): void
{
    db()->prepare(
        'INSERT INTO accounting_customer_links (client_id, provider, account_client_id, external_id, external_name, linked_by)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE external_id = VALUES(external_id), external_name = VALUES(external_name),
                                 linked_by = VALUES(linked_by), linked_at = CURRENT_TIMESTAMP'
    )->execute([$clientId, 'quickbooks', $accountId, $externalId, mb_substr($externalName, 0, 190), $userId]);
}

function qbc_unlink(int $clientId, int $accountId): void
{
    db()->prepare('DELETE FROM accounting_customer_links WHERE client_id = ? AND provider = ? AND account_client_id = ?')
        ->execute([$clientId, 'quickbooks', $accountId]);
}

/**
 * Create a NEW customer in QuickBooks for a trade account that genuinely
 * isn't there yet, and link it. QuickBooks refuses a display name that's
 * already in use (by a customer, supplier or employee) — that error comes
 * back as-is, which is the protection against a duplicate.
 *
 * @param array $account a clients row
 * @return array{id:string, name:string}
 */
function qbc_create(int $clientId, array $account, ?int $userId): array
{
    $name = trim((string) ($account['company_name'] ?? ''));
    if ($name === '') throw new RuntimeException('This account has no company name to create in QuickBooks.');

    $body = ['DisplayName' => mb_substr($name, 0, 100), 'CompanyName' => mb_substr($name, 0, 100)];
    $email = trim((string) ($account['email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) $body['PrimaryEmailAddr'] = ['Address' => $email];
    $phone = trim((string) ($account['phone'] ?? ''));
    if ($phone !== '') $body['PrimaryPhone'] = ['FreeFormNumber' => mb_substr($phone, 0, 30)];
    $addr = array_filter([
        'Line1'      => trim((string) ($account['address1'] ?? '')),
        'Line2'      => trim((string) ($account['address2'] ?? '')),
        'City'       => trim((string) ($account['town'] ?? '')),
        'CountrySubDivisionCode' => trim((string) ($account['county'] ?? '')),
        'PostalCode' => trim((string) ($account['postcode'] ?? '')),
    ], static fn ($v) => $v !== '');
    if ($addr) $body['BillAddr'] = $addr;

    $ref = (string) ($account['account_ref'] ?? '');
    $r = qbo_api_post($clientId, '/customer', $body, 'create customer');
    $c = $r['data']['Customer'] ?? null;
    if (!$c || empty($c['Id'])) throw new RuntimeException('QuickBooks did not return the new customer.');

    $id = (string) $c['Id'];
    $dn = (string) ($c['DisplayName'] ?? $name);
    qbc_link($clientId, (int) $account['id'], $id, $dn, $userId);
    ac_log($clientId, 'quickbooks', 'create customer', 'ok', 'Created "' . $dn . '" (QuickBooks id ' . $id . ')',
           (int) $r['status'], $r['tid'] ?? null, $ref !== '' ? $ref : null);
    return ['id' => $id, 'name' => $dn];
}
