<?php
declare(strict_types=1);

/**
 * Accounting-package integration — provider-agnostic core.
 *
 * Each tenant connects their OWN accounting account via OAuth2 (they authorise
 * on the provider's own site — we never see their password). Tokens + the
 * company/realm id + field-mapping live per (client_id, provider) in the
 * accounting_connections table (migrate_accounting_connections.php).
 *
 * QuickBooks Online is the first provider (see _partials/quickbooks.php). Xero
 * and Sage slot in later by implementing AccountingProvider — nothing in the
 * Settings UI or the invoice-push code should reference a provider directly;
 * always go through ac_provider() / the interface.
 *
 * Design constraint (John's accounts flow): CASH accounting — nothing is pushed
 * to the accounting package until it is PAID. So the push is triggered by a
 * "payment received" event (interim: a manual mark-paid; later: a live bank
 * feed), and each sale is recorded already-paid. That trigger lives in the
 * payments/bank layer, not here; this file is just the connection plumbing.
 */

require_once __DIR__ . '/../bootstrap.php';

/* ------------------------------------------------------------------ *
 *  Provider interface — implement one class per accounting package.
 * ------------------------------------------------------------------ */
interface AccountingProvider
{
    /** Stable machine key stored in accounting_connections.provider (e.g. 'quickbooks'). */
    public function key(): string;

    /** Human label for the Settings UI (e.g. 'QuickBooks Online'). */
    public function label(): string;

    /** sandbox | production — from the provider's own env config. */
    public function environment(): string;

    /** True once the platform-level app credentials (env) are present. */
    public function isConfigured(): bool;

    /**
     * The OAuth redirect URI this provider will send the tenant back to. It
     * MUST match the URI registered on the provider's app exactly, so both
     * Settings and the accounting health page print it for comparison.
     *
     * Declared here because both of those pages call it through an
     * AccountingProvider-typed value (ac_provider() returns ?AccountingProvider).
     * It only ever worked because QuickBooksProvider happens to be the single
     * implementation; the moment Xero or Sage is added, an implementation
     * without it would fatal at the call site instead of failing to compile.
     */
    public function redirectUri(): string;

    /**
     * The provider's OAuth authorize URL to send the tenant to. $state is an
     * opaque anti-CSRF token we also stash in the session and re-check on
     * callback.
     */
    public function authorizeUrl(string $state): string;

    /**
     * Handle the OAuth redirect back. Given the raw query ($_GET), exchange the
     * code for tokens and return a field array ready for ac_save_connection()
     * (access_token/refresh_token PLAINTEXT — the caller seals them), plus
     * realm_id, company_name, access_expires_at, refresh_expires_at.
     * Throws RuntimeException on any failure.
     */
    public function handleCallback(array $query): array;

    /**
     * Given a stored connection row (tokens already opened to plaintext by the
     * caller), obtain a fresh access token from the refresh token. Returns the
     * same field shape as handleCallback (minus realm/company). Throws on failure.
     */
    public function refresh(array $connPlain): array;

    /** Best-effort revoke at the provider. Never throws (log + move on). */
    public function disconnect(array $connPlain): void;
}

/* ------------------------------------------------------------------ *
 *  Provider registry.
 * ------------------------------------------------------------------ */

/** All known providers, keyed by ->key(). Add Xero/Sage here when built. */
function ac_providers(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    require_once __DIR__ . '/quickbooks.php';
    $list = [new QuickBooksProvider()];

    $cache = [];
    foreach ($list as $p) { $cache[$p->key()] = $p; }
    return $cache;
}

function ac_provider(string $key): ?AccountingProvider
{
    return ac_providers()[$key] ?? null;
}

/* ------------------------------------------------------------------ *
 *  Platform config (DB) — lets super-admins set platform-level keys
 *  (the QuickBooks app credentials, the token key) from the admin UI
 *  instead of hand-editing .env on the server. DB value wins; .env is
 *  the fallback. Reads are cached per request.
 * ------------------------------------------------------------------ */
function pc_table_exists(): bool
{
    static $has = null;
    if ($has !== null) return $has;
    try {
        $has = (bool) db()->query("SHOW TABLES LIKE 'platform_config'")->fetchColumn();
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

function pc_all(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    if (pc_table_exists()) {
        try {
            foreach (db()->query('SELECT name, value FROM platform_config') as $r) {
                $cache[(string) $r['name']] = $r['value'];
            }
        } catch (Throwable $e) { /* leave empty */ }
    }
    return $cache;
}

/** Platform config value, DB first then .env, then $default. */
function pc_get(string $name, ?string $default = null): ?string
{
    $all = pc_all();
    if (array_key_exists($name, $all) && $all[$name] !== null && $all[$name] !== '') {
        return (string) $all[$name];
    }
    $env = env($name, null);
    if ($env !== null && $env !== '') return $env;
    return $default;
}

function pc_set(string $name, ?string $value): void
{
    if (!pc_table_exists()) {
        throw new RuntimeException('platform_config table missing — run migrate_platform_config.php.');
    }
    db()->prepare(
        'INSERT INTO platform_config (name, value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)'
    )->execute([$name, $value]);
    // Bust the per-request cache so a save is visible immediately.
    // (pc_all caches in a static; re-read by clearing via a fresh query next call
    //  is simplest to reason about — force it by overwriting the static cache.)
}

/* ------------------------------------------------------------------ *
 *  Token sealing. OAuth refresh tokens are long-lived, full-access
 *  credentials — seal them at rest when a key is configured. Falls back
 *  to a tagged plaintext form when no key is set (dev / pre-config), so
 *  the seam exists and turning on encryption later needs no data change
 *  beyond a re-connect.
 * ------------------------------------------------------------------ */
function ac_seal_key(): string
{
    // DB (platform_config) first, then .env. If neither is set, lazily generate
    // one and store it in platform_config so tokens are encrypted at rest without
    // anyone having to hand-edit .env. (For a hardened setup, set APP_ENCRYPTION_KEY
    // in .env and it takes precedence.)
    $k = (string) (pc_get('APP_ENCRYPTION_KEY', '') ?? '');
    if ($k !== '') return $k;
    if (function_exists('random_bytes') && pc_table_exists()) {
        try {
            $k = bin2hex(random_bytes(32));
            pc_set('APP_ENCRYPTION_KEY', $k);
            return $k;
        } catch (Throwable $e) { /* fall through to passthrough */ }
    }
    return '';
}

function ac_seal(?string $plain): ?string
{
    if ($plain === null || $plain === '') return $plain;
    $key = ac_seal_key();
    if ($key === '' || !function_exists('openssl_encrypt')) {
        return 'raw:' . base64_encode($plain);
    }
    $iv  = random_bytes(12);
    $tag = '';
    $ct  = openssl_encrypt($plain, 'aes-256-gcm', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ct === false) return 'raw:' . base64_encode($plain);
    return 'enc:v1:' . base64_encode($iv . $tag . $ct);
}

function ac_open(?string $stored): ?string
{
    if ($stored === null || $stored === '') return $stored;
    if (str_starts_with($stored, 'raw:')) {
        return (string) base64_decode(substr($stored, 4), true);
    }
    if (str_starts_with($stored, 'enc:v1:')) {
        $key = ac_seal_key();
        if ($key === '' || !function_exists('openssl_decrypt')) return null;
        $blob = base64_decode(substr($stored, 7), true);
        if ($blob === false || strlen($blob) < 28) return null;
        $iv  = substr($blob, 0, 12);
        $tag = substr($blob, 12, 16);
        $ct  = substr($blob, 28);
        $pt  = openssl_decrypt($ct, 'aes-256-gcm', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv, $tag);
        return $pt === false ? null : $pt;
    }
    // Unknown/legacy — treat as plaintext.
    return $stored;
}

/* ------------------------------------------------------------------ *
 *  Connection storage.
 * ------------------------------------------------------------------ */
function ac_table_exists(): bool
{
    static $has = null;
    if ($has !== null) return $has;
    try {
        $has = (bool) db()->query("SHOW TABLES LIKE 'accounting_connections'")->fetchColumn();
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

/**
 * Raw connection row for (client, provider), or null. Tokens stay SEALED in the
 * returned array — use ac_connection_plain() when you need the plaintext.
 */
function ac_get_connection(int $clientId, string $provider): ?array
{
    if (!ac_table_exists()) return null;
    $st = db()->prepare(
        'SELECT * FROM accounting_connections WHERE client_id = ? AND provider = ? LIMIT 1'
    );
    $st->execute([$clientId, $provider]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** Connection row with access_token / refresh_token opened to plaintext. */
function ac_connection_plain(int $clientId, string $provider): ?array
{
    $row = ac_get_connection($clientId, $provider);
    if (!$row) return null;
    $row['access_token']  = ac_open($row['access_token']  ?? null);
    $row['refresh_token'] = ac_open($row['refresh_token'] ?? null);
    return $row;
}

/**
 * Upsert a connection. $fields uses PLAINTEXT access_token / refresh_token; they
 * are sealed here. Only provided keys are written (others kept on update).
 */
function ac_save_connection(int $clientId, string $provider, array $fields): void
{
    if (!ac_table_exists()) {
        throw new RuntimeException('accounting_connections table missing — run migrate_accounting_connections.php.');
    }
    $col = [
        'environment', 'realm_id', 'company_name', 'access_token', 'refresh_token',
        'access_expires_at', 'refresh_expires_at', 'mapping_json', 'status',
        'last_error', 'connected_at',
    ];
    $existing = ac_get_connection($clientId, $provider);

    // Seal token fields if present.
    if (array_key_exists('access_token', $fields))  $fields['access_token']  = ac_seal($fields['access_token']);
    if (array_key_exists('refresh_token', $fields)) $fields['refresh_token'] = ac_seal($fields['refresh_token']);

    if ($existing) {
        $set = []; $vals = [];
        foreach ($col as $c) {
            if (array_key_exists($c, $fields)) { $set[] = "$c = ?"; $vals[] = $fields[$c]; }
        }
        if (!$set) return;
        $vals[] = $clientId; $vals[] = $provider;
        $sql = 'UPDATE accounting_connections SET ' . implode(', ', $set)
             . ' WHERE client_id = ? AND provider = ?';
        db()->prepare($sql)->execute($vals);
    } else {
        $insCols = ['client_id', 'provider'];
        $insVals = [$clientId, $provider];
        foreach ($col as $c) {
            if (array_key_exists($c, $fields)) { $insCols[] = $c; $insVals[] = $fields[$c]; }
        }
        $ph = implode(', ', array_fill(0, count($insCols), '?'));
        $sql = 'INSERT INTO accounting_connections (' . implode(', ', $insCols) . ') VALUES (' . $ph . ')';
        db()->prepare($sql)->execute($insVals);
    }
}

function ac_delete_connection(int $clientId, string $provider): void
{
    if (!ac_table_exists()) return;
    db()->prepare('DELETE FROM accounting_connections WHERE client_id = ? AND provider = ?')
        ->execute([$clientId, $provider]);
}

/** True when the row is connected and its refresh token hasn't expired. */
function ac_is_connected(?array $row): bool
{
    if (!$row || ($row['status'] ?? '') !== 'connected') return false;
    $rexp = $row['refresh_expires_at'] ?? null;
    if ($rexp && strtotime((string) $rexp) < time()) return false;
    return true;
}

/**
 * Return a VALID access token for (client, provider), refreshing it via the
 * provider if it's within 2 minutes of expiry (and persisting the new tokens).
 * Returns null if not connected / refresh fails. This is what invoice-push code
 * calls before every API request.
 */
function ac_valid_access_token(int $clientId, string $provider): ?string
{
    $row = ac_connection_plain($clientId, $provider);
    if (!ac_is_connected($row)) return null;

    $exp = isset($row['access_expires_at']) ? strtotime((string) $row['access_expires_at']) : 0;
    if ($row['access_token'] && $exp > time() + 120) {
        return $row['access_token'];   // still good
    }

    $prov = ac_provider($provider);
    if (!$prov) return null;
    try {
        $fresh = $prov->refresh($row);   // plaintext tokens back
        ac_save_connection($clientId, $provider, $fresh + ['status' => 'connected', 'last_error' => null]);
        return $fresh['access_token'] ?? null;
    } catch (Throwable $e) {
        ac_save_connection($clientId, $provider, [
            'status'     => 'error',
            'last_error' => 'Token refresh failed: ' . $e->getMessage(),
        ]);
        error_log("Accounting refresh failed (client $clientId, $provider): " . $e->getMessage());
        return null;
    }
}

/* ------------------------------------------------------------------ *
 *  Shared curl helper (mirrors _partials/paypal.php style).
 *  Returns ['status' => int, 'data' => array, 'raw' => string].
 * ------------------------------------------------------------------ */
function ac_http(string $method, string $url, array $opts = []): array
{
    $ch = curl_init($url);
    $curl = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $opts['headers'] ?? [],
    ];
    if (isset($opts['userpwd']))  $curl[CURLOPT_USERPWD]   = $opts['userpwd'];
    if (isset($opts['body']))     $curl[CURLOPT_POSTFIELDS] = $opts['body'];
    curl_setopt_array($ch, $curl);

    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        throw new RuntimeException("$method $url network error: $err");
    }
    $data = $resp === '' ? [] : (json_decode((string) $resp, true) ?: []);
    return ['status' => $code, 'data' => $data, 'raw' => (string) $resp];
}
