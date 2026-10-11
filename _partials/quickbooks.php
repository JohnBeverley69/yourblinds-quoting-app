<?php
declare(strict_types=1);

/**
 * QuickBooks Online provider — implements AccountingProvider.
 *
 * Platform-level app credentials come from .env (one Intuit app serves every
 * tenant; the per-tenant part is the OAuth connection stored in
 * accounting_connections):
 *   QUICKBOOKS_ENV            sandbox | production   (default: sandbox)
 *   QUICKBOOKS_CLIENT_ID      Intuit "Keys & credentials"
 *   QUICKBOOKS_CLIENT_SECRET  Intuit "Keys & credentials"
 *   QUICKBOOKS_REDIRECT_URI   must EXACTLY match the Redirect URI registered
 *                             at Intuit (default: https://yourblinds.uk/admin/accounting/callback.php)
 *
 * OAuth2 authorization-code flow. On connect Intuit returns ?code, ?state and
 * ?realmId (the company id, needed on every API call). Access token ~1h,
 * refresh token ~101 days (rolls forward on each refresh).
 *
 * OAuth endpoints come from Intuit's discovery document (as Intuit asks),
 * cached for a day in platform_config; the constants below are the fallback
 * if it can't be fetched (they're what the document has said for years):
 *   authorize : https://appcenter.intuit.com/connect/oauth2
 *   token     : https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer
 *   revoke    : https://developer.api.intuit.com/v2/oauth2/tokens/revoke
 *   API base  : https://quickbooks.api.intuit.com  (prod)
 *               https://sandbox-quickbooks.api.intuit.com  (sandbox)
 *
 * All API calls go through qbo_request(): on a 401 it refreshes the token
 * and retries once; a refresh token Intuit refuses (invalid_grant) marks the
 * connection 'needs_reconnect' so the screens ask the company to reconnect;
 * every failure is written to accounting_log with Intuit's intuit_tid.
 */

require_once __DIR__ . '/accounting.php';

class QuickBooksProvider implements AccountingProvider
{
    private const AUTHORIZE_URL = 'https://appcenter.intuit.com/connect/oauth2';
    private const TOKEN_URL     = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';
    private const REVOKE_URL    = 'https://developer.api.intuit.com/v2/oauth2/tokens/revoke';
    private const SCOPE         = 'com.intuit.quickbooks.accounting';
    private const MINOR_VERSION = '73';

    public function key(): string   { return 'quickbooks'; }
    public function label(): string { return 'QuickBooks Online'; }

    public function environment(): string
    {
        $e = strtolower((string) (pc_get('QUICKBOOKS_ENV', 'sandbox') ?? 'sandbox'));
        return in_array($e, ['sandbox', 'production'], true) ? $e : 'sandbox';
    }

    /**
     * An endpoint from Intuit's discovery document (authorization_endpoint,
     * token_endpoint, revocation_endpoint), fetched at most once a day and
     * cached in platform_config. Falls back to $fallback if unavailable.
     */
    private function endpoint(string $key, string $fallback): string
    {
        // Local testing: the stand-in server named by QBO_API_BASE also issues tokens.
        $test = (string) (env('QBO_API_BASE', '') ?? '');
        if ($test !== '' && $key === 'token_endpoint' && $this->environment() !== 'production') {
            return rtrim($test, '/') . '/token';
        }
        static $doc = null;
        if ($doc === null) {
            $doc = [];
            $cacheKey = 'QBO_DISCOVERY_' . strtoupper($this->environment());
            $cached = json_decode((string) (pc_get($cacheKey, '') ?? ''), true);
            if (is_array($cached) && (int) ($cached['_fetched'] ?? 0) > time() - 86400) {
                $doc = $cached;
            } else {
                try {
                    $url = $this->environment() === 'production'
                        ? 'https://developer.api.intuit.com/.well-known/openid_configuration'
                        : 'https://developer.api.intuit.com/.well-known/openid_sandbox_configuration';
                    $r = ac_http('GET', $url, ['headers' => ['Accept: application/json']]);
                    if ($r['status'] === 200 && !empty($r['data']['token_endpoint'])) {
                        $doc = $r['data'] + ['_fetched' => time()];
                        try { pc_set($cacheKey, json_encode($doc)); } catch (Throwable $e) { /* cache optional */ }
                    } elseif (is_array($cached)) {
                        $doc = $cached;   // stale beats nothing
                    }
                } catch (Throwable $e) {
                    if (is_array($cached)) $doc = $cached;
                }
            }
        }
        $v = (string) ($doc[$key] ?? '');
        return str_starts_with($v, 'https://') ? $v : $fallback;
    }

    private function clientId(): string     { return (string) (pc_get('QUICKBOOKS_CLIENT_ID', '') ?? ''); }
    private function clientSecret(): string { return (string) (pc_get('QUICKBOOKS_CLIENT_SECRET', '') ?? ''); }

    public function redirectUri(): string
    {
        $explicit = (string) (pc_get('QUICKBOOKS_REDIRECT_URI', '') ?? '');
        if ($explicit !== '') return $explicit;
        // Fall back to APP_URL so the callback path stays in one place.
        $base = rtrim((string) (env('APP_URL', 'https://yourblinds.uk') ?? 'https://yourblinds.uk'), '/');
        return $base . '/admin/accounting/callback.php';
    }

    /** API host for the current environment. */
    public function apiBase(): string
    {
        // QBO_API_BASE in .env points at a stand-in server for local testing.
        $override = (string) (env('QBO_API_BASE', '') ?? '');
        if ($override !== '' && $this->environment() !== 'production') return rtrim($override, '/');
        return $this->environment() === 'production'
            ? 'https://quickbooks.api.intuit.com'
            : 'https://sandbox-quickbooks.api.intuit.com';
    }

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function authorizeUrl(string $state): string
    {
        $q = http_build_query([
            'client_id'     => $this->clientId(),
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'redirect_uri'  => $this->redirectUri(),
            'state'         => $state,
        ]);
        return $this->endpoint('authorization_endpoint', self::AUTHORIZE_URL) . '?' . $q;
    }

    /**
     * Exchange the authorization code (from Intuit's redirect) for tokens.
     * Intuit also hands us realmId on the query string.
     */
    public function handleCallback(array $query): array
    {
        $code    = (string) ($query['code'] ?? '');
        $realmId = (string) ($query['realmId'] ?? '');
        if ($code === '')    throw new RuntimeException('QuickBooks returned no authorization code.');
        if ($realmId === '') throw new RuntimeException('QuickBooks returned no company id (realmId).');

        $tok = $this->tokenRequest([
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => $this->redirectUri(),
        ]);

        $fields = $this->tokensToFields($tok);
        $fields['realm_id']     = $realmId;
        $fields['environment']  = $this->environment();
        $fields['company_name'] = $this->fetchCompanyName($realmId, $fields['access_token']) ?? ('QuickBooks company ' . $realmId);
        $fields['status']       = 'connected';
        $fields['last_error']   = null;
        $fields['connected_at'] = date('Y-m-d H:i:s');
        return $fields;
    }

    public function refresh(array $connPlain): array
    {
        $rt = (string) ($connPlain['refresh_token'] ?? '');
        if ($rt === '') throw new RuntimeException('No refresh token stored.');
        $tok = $this->tokenRequest([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $rt,
        ]);
        return $this->tokensToFields($tok);
    }

    public function disconnect(array $connPlain): void
    {
        $rt = (string) ($connPlain['refresh_token'] ?? '');
        if ($rt === '' || !$this->isConfigured()) return;
        try {
            ac_http('POST', $this->endpoint('revocation_endpoint', self::REVOKE_URL), [
                'userpwd' => $this->clientId() . ':' . $this->clientSecret(),
                'headers' => ['Accept: application/json', 'Content-Type: application/json'],
                'body'    => json_encode(['token' => $rt]),
            ]);
        } catch (Throwable $e) {
            error_log('QuickBooks revoke failed: ' . $e->getMessage());
        }
    }

    /* -------------------------------------------------------------- *
     *  Internals
     * -------------------------------------------------------------- */

    /** POST to the token endpoint (Basic auth = client_id:secret). */
    private function tokenRequest(array $params): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('QuickBooks credentials missing (QUICKBOOKS_CLIENT_ID / QUICKBOOKS_CLIENT_SECRET).');
        }
        $r = ac_http('POST', $this->endpoint('token_endpoint', self::TOKEN_URL), [
            'userpwd' => $this->clientId() . ':' . $this->clientSecret(),
            'headers' => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            'body' => http_build_query($params),
        ]);
        if ($r['status'] !== 200 || empty($r['data']['access_token'])) {
            $msg = $r['data']['error_description'] ?? $r['data']['error'] ?? $r['raw'];
            throw new RuntimeException('QuickBooks token request failed (' . $r['status'] . '): ' . $msg);
        }
        return $r['data'];
    }

    /** Map a token response to accounting_connections fields (plaintext tokens). */
    private function tokensToFields(array $tok): array
    {
        $now = time();
        $accessTtl  = (int) ($tok['expires_in'] ?? 3600);
        $refreshTtl = (int) ($tok['x_refresh_token_expires_in'] ?? 8640000); // ~100 days
        return [
            'access_token'       => (string) ($tok['access_token'] ?? ''),
            // Intuit returns a fresh refresh token on refresh — always store it.
            'refresh_token'      => (string) ($tok['refresh_token'] ?? ''),
            'access_expires_at'  => date('Y-m-d H:i:s', $now + $accessTtl),
            'refresh_expires_at' => date('Y-m-d H:i:s', $now + $refreshTtl),
        ];
    }

    /** Friendly company name for the Settings screen (best-effort). */
    private function fetchCompanyName(string $realmId, string $accessToken): ?string
    {
        try {
            $r = $this->apiGetRaw($realmId, $accessToken, '/companyinfo/' . rawurlencode($realmId));
            $name = $r['data']['CompanyInfo']['CompanyName'] ?? null;
            return $name ? (string) $name : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Raw GET against the QBO API using an explicit token (used during connect,
     * before the row is saved). Post-connect, callers use ac_valid_access_token()
     * + qbo_api_get().
     */
    private function apiGetRaw(string $realmId, string $accessToken, string $path, array $params = []): array
    {
        $params['minorversion'] = self::MINOR_VERSION;
        $url = $this->apiBase() . '/v3/company/' . rawurlencode($realmId) . $path
             . '?' . http_build_query($params);
        $r = ac_http('GET', $url, [
            'headers' => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
            ],
        ]);
        if ($r['status'] >= 400) {
            throw new RuntimeException('QuickBooks API GET ' . $path . ' failed (' . $r['status'] . '): ' . $r['raw']);
        }
        return $r;
    }
}

/**
 * Connected API GET for QuickBooks — resolves a fresh token for the client and
 * calls the QBO API. Returns decoded ['status','data','raw']. Throws if the
 * client isn't connected. (Phase 2+ mapping/push will build on this.)
 */
/**
 * One QuickBooks API call for a connected company. GET/POST against
 * /v3/company/{realm}{path}. On a 401 the token is refreshed and the call
 * retried once (an expired token mid-session is normal); if Intuit then
 * refuses the refresh, the connection is marked 'needs_reconnect' (see
 * ac_valid_access_token) and this throws "please reconnect". Every failure
 * is logged to accounting_log with Intuit's intuit_tid.
 *
 * @return array{status:int, data:array, raw:string, tid:?string}
 */
function qbo_request(int $clientId, string $method, string $path, array $params = [], ?array $body = null,
                     string $action = ''): array
{
    $conn = ac_get_connection($clientId, 'quickbooks');
    $realmId = (string) ($conn['realm_id'] ?? '');
    if ($realmId === '') throw new RuntimeException('QuickBooks is not connected for this account.');
    /** @var QuickBooksProvider $prov */
    $prov = ac_provider('quickbooks');
    $params['minorversion'] = '73';
    // RFC 3986 (%20, not +) — the query endpoint is fussy about spaces.
    $url = $prov->apiBase() . '/v3/company/' . rawurlencode($realmId) . $path . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $action = $action !== '' ? $action : strtolower($method) . ' ' . strtok($path, '?');

    $r = null;
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $token = ac_valid_access_token($clientId, 'quickbooks', $attempt > 0);
        if ($token === null) {
            $why = ac_needs_reconnect(ac_get_connection($clientId, 'quickbooks'))
                ? 'QuickBooks needs reconnecting — open the QuickBooks settings and click Reconnect.'
                : 'QuickBooks is not connected for this account.';
            ac_log($clientId, 'quickbooks', $action, 'failed', $why);
            throw new RuntimeException($why);
        }
        $headers = ['Authorization: Bearer ' . $token, 'Accept: application/json'];
        $opts = ['headers' => $headers];
        if ($body !== null) {
            $opts['headers'][] = 'Content-Type: application/json';
            $opts['body'] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $r = ac_http($method, $url, $opts);
        if ($r['status'] !== 401) break;   // only an auth failure is worth a refresh + retry
    }

    if ($r['status'] >= 400) {
        $fault = $r['data']['Fault']['Error'][0] ?? null;
        $msg   = $fault ? trim(($fault['Message'] ?? '') . ' — ' . ($fault['Detail'] ?? ''), ' —') : mb_substr($r['raw'], 0, 500);
        ac_log($clientId, 'quickbooks', $action, 'failed', $msg, $r['status'], $r['tid']);
        throw new RuntimeException('QuickBooks said: ' . $msg . ' (' . $r['status'] . ($r['tid'] ? ', ref ' . $r['tid'] : '') . ')');
    }
    return $r;
}

/** Connected API GET (kept for existing callers). */
function qbo_api_get(int $clientId, string $path, array $params = []): array
{
    return qbo_request($clientId, 'GET', $path, $params);
}

/** Connected API POST of a JSON body, e.g. qbo_api_post($c, '/invoice', [...]). */
function qbo_api_post(int $clientId, string $path, array $body, string $action = ''): array
{
    return qbo_request($clientId, 'POST', $path, [], $body, $action);
}

/**
 * Run a QuickBooks SQL-ish query and return the QueryResponse array
 * (e.g. ['Item' => [...]], ['TaxCode' => [...]], ['Account' => [...]]).
 */
function qbo_query(int $clientId, string $sql): array
{
    $r = qbo_request($clientId, 'GET', '/query', ['query' => $sql], null, 'query');
    return $r['data']['QueryResponse'] ?? [];
}

/** Active service/other items usable on invoice + sales-receipt lines. */
function qbo_list_items(int $clientId): array
{
    $resp = qbo_query($clientId, "SELECT Id, Name, Type FROM Item WHERE Active = true ORDERBY Name MAXRESULTS 500");
    return array_map(static fn ($i) => [
        'id'   => (string) ($i['Id'] ?? ''),
        'name' => (string) ($i['Name'] ?? ''),
        'type' => (string) ($i['Type'] ?? ''),
    ], (array) ($resp['Item'] ?? []));
}

/** VAT / sales-tax codes. */
function qbo_list_tax_codes(int $clientId): array
{
    $resp = qbo_query($clientId, "SELECT Id, Name, Active FROM TaxCode MAXRESULTS 500");
    $out = [];
    foreach ((array) ($resp['TaxCode'] ?? []) as $t) {
        if (isset($t['Active']) && $t['Active'] === false) continue;
        $out[] = ['id' => (string) ($t['Id'] ?? ''), 'name' => (string) ($t['Name'] ?? '')];
    }
    return $out;
}

/**
 * Accounts, split by role: 'income' (where sales post) and 'deposit'
 * (Bank + Undeposited Funds — where a paid sale's money lands).
 */
function qbo_list_accounts(int $clientId): array
{
    $resp = qbo_query($clientId, "SELECT Id, Name, AccountType, AccountSubType FROM Account WHERE Active = true ORDERBY Name MAXRESULTS 1000");
    $income = []; $deposit = [];
    foreach ((array) ($resp['Account'] ?? []) as $a) {
        $row = ['id' => (string) ($a['Id'] ?? ''), 'name' => (string) ($a['Name'] ?? '')];
        $type = (string) ($a['AccountType'] ?? '');
        $sub  = (string) ($a['AccountSubType'] ?? '');
        if ($type === 'Income') $income[] = $row;
        if ($type === 'Bank' || $sub === 'UndepositedFunds') $deposit[] = $row;
    }
    return ['income' => $income, 'deposit' => $deposit];
}
