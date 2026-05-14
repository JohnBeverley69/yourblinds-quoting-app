<?php
declare(strict_types=1);

/**
 * PayPal REST API helpers — used by:
 *   billing/subscribe.php       (create subscription)
 *   billing/return.php          (verify post-approval status)
 *   billing/cancel.php          (cancel subscription)
 *   billing/paypal_webhook.php  (event receipt)
 *   setup_paypal_plan.php       (one-off Product + Plan creation)
 *
 * Configuration comes from .env (gitignored):
 *   PAYPAL_ENV               sandbox | live  (default: sandbox)
 *   PAYPAL_CLIENT_ID         from PayPal Apps & Credentials
 *   PAYPAL_SECRET            from PayPal Apps & Credentials
 *   PAYPAL_PLAN_ACCOUNTS     populated by setup_paypal_plan.php
 *   PAYPAL_WEBHOOK_ID        populated after registering the webhook
 *                            in PayPal's dashboard
 */

/**
 * Returns the PayPal API config from environment variables. No
 * fallback to live mode — defaults to sandbox so an accidental
 * deploy can't process real money before .env is set up.
 */
function paypal_config(): array
{
    $envMode = strtolower((string) (env('PAYPAL_ENV', 'sandbox') ?? 'sandbox'));
    if (!in_array($envMode, ['sandbox', 'live'], true)) {
        $envMode = 'sandbox';
    }
    return [
        'env'              => $envMode,
        'api_base_url'     => $envMode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com',
        'web_base_url'     => $envMode === 'live'
            ? 'https://www.paypal.com'
            : 'https://www.sandbox.paypal.com',
        'client_id'        => (string) (env('PAYPAL_CLIENT_ID', '') ?? ''),
        'secret'           => (string) (env('PAYPAL_SECRET', '')    ?? ''),
        'plan_id_accounts' => (string) (env('PAYPAL_PLAN_ACCOUNTS', '') ?? ''),
        'webhook_id'       => (string) (env('PAYPAL_WEBHOOK_ID', '')   ?? ''),
    ];
}

/**
 * True if PayPal config is sufficient to attempt API calls. Used by
 * the Billing page to decide whether to show "Subscribe" or a
 * "Not yet configured" placeholder.
 */
function paypal_is_configured(): bool
{
    $cfg = paypal_config();
    return $cfg['client_id'] !== '' && $cfg['secret'] !== '';
}

/**
 * OAuth2 client-credentials grant. Caches the token in a class-like
 * static for the lifetime of the request — every API call within
 * one request reuses the same token.
 *
 * Throws on auth failure so the caller's try/catch can bubble a
 * clean error to the user.
 */
function paypal_access_token(): string
{
    static $cached     = null;
    static $expiresAt  = 0;
    if ($cached !== null && time() < $expiresAt) return $cached;

    $cfg = paypal_config();
    if ($cfg['client_id'] === '' || $cfg['secret'] === '') {
        throw new RuntimeException('PayPal credentials missing (PAYPAL_CLIENT_ID / PAYPAL_SECRET).');
    }

    $ch = curl_init($cfg['api_base_url'] . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
        CURLOPT_USERPWD        => $cfg['client_id'] . ':' . $cfg['secret'],
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        throw new RuntimeException('PayPal auth network error: ' . $err);
    }
    $data = json_decode((string) $resp, true);
    if ($code !== 200 || empty($data['access_token'])) {
        throw new RuntimeException('PayPal auth failed (' . $code . '): ' . (string) $resp);
    }

    $cached    = (string) $data['access_token'];
    // Refresh ~1 minute before actual expiry to avoid using a
    // just-expired token across long requests.
    $expiresAt = time() + max(60, ((int) ($data['expires_in'] ?? 3600)) - 60);
    return $cached;
}

/**
 * Generic PayPal REST call. Pass the path with the leading slash
 * (e.g. '/v1/billing/subscriptions'). Body is JSON-encoded if not
 * null. Throws on any non-2xx response.
 *
 * Returns ['status' => int, 'data' => array].
 */
function paypal_request(string $method, string $path, ?array $body = null, array $extraHeaders = []): array
{
    $cfg   = paypal_config();
    $url   = $cfg['api_base_url'] . $path;
    $token = paypal_access_token();

    $headers = array_merge([
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
    ], $extraHeaders);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        throw new RuntimeException("PayPal $method $path network error: $err");
    }
    $data = $resp === '' ? [] : (json_decode((string) $resp, true) ?: ['raw' => $resp]);
    if ($code >= 400) {
        $msg = $data['message'] ?? $data['error_description'] ?? 'Unknown error';
        throw new RuntimeException("PayPal $method $path failed ($code): $msg");
    }
    return ['status' => $code, 'data' => $data];
}

/**
 * Map a PayPal subscription status to our local enum.
 *
 *   APPROVAL_PENDING / APPROVED → past_due (created but not yet active)
 *   ACTIVE                      → active
 *   SUSPENDED                   → past_due (payment failed, retrying)
 *   CANCELLED                   → cancelled
 *   EXPIRED                     → expired
 */
function paypal_map_status(string $paypalStatus): string
{
    return match (strtoupper($paypalStatus)) {
        'ACTIVE'                       => 'active',
        'APPROVED', 'APPROVAL_PENDING' => 'past_due',
        'SUSPENDED'                    => 'past_due',
        'CANCELLED'                    => 'cancelled',
        'EXPIRED'                      => 'expired',
        default                        => 'past_due',
    };
}

/**
 * Verify a PayPal webhook callback. Calls back to PayPal's
 * verify-webhook-signature endpoint with the cryptographic headers +
 * raw payload — PayPal confirms it really sent the event.
 *
 * Returns true on SUCCESS, false otherwise. Does NOT throw — the
 * caller logs + responds with the appropriate HTTP code.
 *
 * Headers: case-insensitive. We accept either header name format
 * because PHP's getallheaders() and $_SERVER conventions differ.
 */
function paypal_verify_webhook(string $rawPayload, array $headers, string $webhookId): bool
{
    if ($webhookId === '') return false;

    // Normalise header keys to lower-case for the lookup.
    $h = [];
    foreach ($headers as $k => $v) {
        $h[strtolower((string) $k)] = (string) $v;
    }
    $event = json_decode($rawPayload, true);
    if (!is_array($event)) return false;

    try {
        $r = paypal_request('POST', '/v1/notifications/verify-webhook-signature', [
            'auth_algo'         => $h['paypal-auth-algo']        ?? '',
            'cert_url'          => $h['paypal-cert-url']         ?? '',
            'transmission_id'   => $h['paypal-transmission-id']  ?? '',
            'transmission_sig'  => $h['paypal-transmission-sig'] ?? '',
            'transmission_time' => $h['paypal-transmission-time']?? '',
            'webhook_id'        => $webhookId,
            'webhook_event'     => $event,
        ]);
        return strtoupper((string) ($r['data']['verification_status'] ?? '')) === 'SUCCESS';
    } catch (Throwable $e) {
        error_log('PayPal webhook verify error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Pull a `Bearer`-ish set of HTTP headers off $_SERVER, since the
 * standard getallheaders() isn't always present (mod_php on some
 * shared hosts, FPM with weird configs). Returns name => value with
 * keys reconstructed from HTTP_FOO_BAR to "Foo-Bar".
 */
function paypal_request_headers(): array
{
    if (function_exists('getallheaders')) {
        $h = getallheaders();
        if (is_array($h) && $h !== []) return $h;
    }
    $out = [];
    foreach ($_SERVER as $k => $v) {
        if (str_starts_with($k, 'HTTP_')) {
            $name = strtolower(str_replace('_', '-', substr($k, 5)));
            $out[$name] = (string) $v;
        }
    }
    return $out;
}
