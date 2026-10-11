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
 * WHICH QuickBooks company. A match ("this trade account is customer 58") and
 * a sent record ("invoice X is QuickBooks invoice 183") only mean anything in
 * the company they were made in — the test company's ids are not Annette's.
 * So both tables carry the company's realm id, and everything reads and writes
 * only the rows for the company connected now. Switching from the test company
 * to the real one starts with no matches and nothing sent; switching back finds
 * the test company's rows again.
 *
 * Adds the columns on first use (idempotent); rows made before this are
 * stamped with the company connected at that moment (they were all made in it).
 */
function qbc_realm_schema(PDO $pdo, int $clientId): void
{
    static $done = [];
    if (isset($done[$clientId])) return;
    $cols = static function (string $t) use ($pdo): array {
        $s = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $s->execute([$t]);
        return $s->fetchAll(PDO::FETCH_COLUMN);
    };
    $realm = qbc_realm($clientId);
    foreach ([
        'accounting_customer_links' => ['uq_aclink', '(client_id, provider, realm_id, account_client_id)'],
        'accounting_sync'           => ['uq_acsync', '(client_id, provider, realm_id, entity, local_id)'],
    ] as $t => [$key, $def]) {
        $have = $cols($t);
        if (!$have) throw new RuntimeException('Run setup/migrations/migrate_accounting_sync.php first.');
        if (!in_array('realm_id', $have, true)) {
            $pdo->exec("ALTER TABLE $t ADD COLUMN realm_id VARCHAR(40) NULL AFTER provider");
            try { $pdo->exec("ALTER TABLE $t DROP INDEX $key"); } catch (Throwable $e) { /* already gone */ }
            $pdo->exec("ALTER TABLE $t ADD UNIQUE KEY $key $def");
        }
        if ($realm !== '') {
            $pdo->prepare("UPDATE $t SET realm_id = ? WHERE client_id = ? AND provider = 'quickbooks' AND realm_id IS NULL")
                ->execute([$realm, $clientId]);
        }
    }
    $done[$clientId] = true;
}

/** The connected QuickBooks company's id ('' when not connected). */
function qbc_realm(int $clientId): string
{
    return (string) (ac_get_connection($clientId, 'quickbooks')['realm_id'] ?? '');
}

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
                // Only a typo or a missing space: "Hilarys"/"Hillarys", "ShadeTech"/"Shade Tech".
                // Never short codes or initials — "BCS Blinds" is not "ABC Blinds".
                $score = qbc_near_spelling($mine, $theirs) ? 88.0 : 0.0;
            }
            if (!$c['active']) $score -= 5;
            if ($score > $bestScore) { $bestScore = $score; $best = $c['id']; }
        }
    }
    if ($best === null || $bestScore < 82) return null;
    return ['id' => $best, 'strength' => $bestScore >= 99 ? 'exact' : 'likely'];
}

/**
 * Same name apart from a small typo in a LONG word, or spaces: word by word,
 * each pair is equal, or both are 5+ letters and one or two letters apart.
 * Short words (ABC, BCS, D&D…) must match exactly.
 */
function qbc_near_spelling(string $a, string $b): bool
{
    if (str_replace(' ', '', $a) === str_replace(' ', '', $b)) return true;   // ShadeTech / Shade Tech
    $wa = explode(' ', $a); $wb = explode(' ', $b);
    if (count($wa) !== count($wb)) return false;
    $typos = 0;
    foreach ($wa as $i => $w) {
        if ($w === $wb[$i]) continue;
        if (mb_strlen($w) < 5 || mb_strlen($wb[$i]) < 5) return false;
        if (levenshtein($w, $wb[$i]) > 2) return false;
        $typos++;
    }
    return $typos === 1;
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

/** Saved links in the connected QuickBooks company: account_client_id => row. */
function qbc_links(int $clientId): array
{
    qbc_realm_schema(db(), $clientId);
    $st = db()->prepare('SELECT * FROM accounting_customer_links WHERE client_id = ? AND provider = ? AND realm_id = ?');
    $st->execute([$clientId, 'quickbooks', qbc_realm($clientId)]);
    $out = [];
    foreach ($st->fetchAll() as $r) $out[(int) $r['account_client_id']] = $r;
    return $out;
}

/** How many trade accounts are linked in the connected company. */
function qbc_link_count(int $clientId): int
{
    return count(qbc_links($clientId));
}

/** The QuickBooks customer Id a trade account is linked to (connected company), or null. Used by the push. */
function qbc_customer_id(int $clientId, int $accountId): ?string
{
    qbc_realm_schema(db(), $clientId);
    $st = db()->prepare('SELECT external_id FROM accounting_customer_links WHERE client_id = ? AND provider = ? AND realm_id = ? AND account_client_id = ?');
    $st->execute([$clientId, 'quickbooks', qbc_realm($clientId), $accountId]);
    $v = $st->fetchColumn();
    return $v === false ? null : (string) $v;
}

/** Link (or re-link) a trade account to a QuickBooks customer in the connected company. */
function qbc_link(int $clientId, int $accountId, string $externalId, string $externalName, ?int $userId): void
{
    qbc_realm_schema(db(), $clientId);
    $realm = qbc_realm($clientId);
    if ($realm === '') throw new RuntimeException('QuickBooks is not connected.');
    db()->prepare(
        'INSERT INTO accounting_customer_links (client_id, provider, realm_id, account_client_id, external_id, external_name, linked_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE external_id = VALUES(external_id), external_name = VALUES(external_name),
                                 linked_by = VALUES(linked_by), linked_at = CURRENT_TIMESTAMP'
    )->execute([$clientId, 'quickbooks', $realm, $accountId, $externalId, mb_substr($externalName, 0, 190), $userId]);
}

function qbc_unlink(int $clientId, int $accountId): void
{
    qbc_realm_schema(db(), $clientId);
    db()->prepare('DELETE FROM accounting_customer_links WHERE client_id = ? AND provider = ? AND realm_id = ? AND account_client_id = ?')
        ->execute([$clientId, 'quickbooks', qbc_realm($clientId), $accountId]);
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
