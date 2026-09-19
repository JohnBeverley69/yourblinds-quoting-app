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
 * Endpoints are stable and hard-coded (Intuit publishes a discovery doc, but
 * these have not changed in years):
 *   authorize : https://appcenter.intuit.com/connect/oauth2
 *   token     : https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer
 *   revoke    : https://developer.api.intuit.com/v2/oauth2/tokens/revoke
 *   API base  : https://quickbooks.api.intuit.com  (prod)
 *               https://sandbox-quickbooks.api.intuit.com  (sandbox)
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
        $e = strtolower((string) (env('QUICKBOOKS_ENV', 'sandbox') ?? 'sandbox'));
        return in_array($e, ['sandbox', 'production'], true) ? $e : 'sandbox';
    }

    private function clientId(): string     { return (string) (env('QUICKBOOKS_CLIENT_ID', '') ?? ''); }
    private function clientSecret(): string { return (string) (env('QUICKBOOKS_CLIENT_SECRET', '') ?? ''); }

    public function redirectUri(): string
    {
        $explicit = (string) (env('QUICKBOOKS_REDIRECT_URI', '') ?? '');
        if ($explicit !== '') return $explicit;
        // Fall back to APP_URL so the callback path stays in one place.
        $base = rtrim((string) (env('APP_URL', 'https://yourblinds.uk') ?? 'https://yourblinds.uk'), '/');
        return $base . '/admin/accounting/callback.php';
    }

    /** API host for the current environment. */
    public function apiBase(): string
    {
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
        return self::AUTHORIZE_URL . '?' . $q;
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
            ac_http('POST', self::REVOKE_URL, [
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
        $r = ac_http('POST', self::TOKEN_URL, [
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
function qbo_api_get(int $clientId, string $path, array $params = []): array
{
    $token = ac_valid_access_token($clientId, 'quickbooks');
    if ($token === null) throw new RuntimeException('QuickBooks is not connected for this account.');
    $conn = ac_get_connection($clientId, 'quickbooks');
    $realmId = (string) ($conn['realm_id'] ?? '');
    if ($realmId === '') throw new RuntimeException('QuickBooks connection has no company id.');

    /** @var QuickBooksProvider $prov */
    $prov = ac_provider('quickbooks');
    $params['minorversion'] = '73';
    $url = $prov->apiBase() . '/v3/company/' . rawurlencode($realmId) . $path
         . '?' . http_build_query($params);
    $r = ac_http('GET', $url, [
        'headers' => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
    ]);
    if ($r['status'] >= 400) {
        throw new RuntimeException('QuickBooks API GET ' . $path . ' failed (' . $r['status'] . '): ' . $r['raw']);
    }
    return $r;
}
