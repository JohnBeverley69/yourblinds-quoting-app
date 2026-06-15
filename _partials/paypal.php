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
        // Buyer approved, but PayPal hasn't flipped it to ACTIVE yet
        // (activation is often async). NOT a payment failure — it's pending
        // activation; the webhook flips it to active shortly.
        'APPROVED', 'APPROVAL_PENDING' => 'pending',
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
 * Update an existing PayPal Plan's pricing scheme — i.e. change the
 * monthly fee. Existing subscribers don't get re-billed at the new
 * rate immediately; PayPal applies the new price at each subscriber's
 * next billing cycle automatically. New subscribers get the new price
 * straight away.
 *
 * Used by /master-admin/pricing.php when the admin saves a price
 * change. The plan_pricing row is updated in our DB regardless of
 * whether this call succeeds — the admin sees a warning in that case
 * but the local source of truth stays consistent.
 *
 * Throws RuntimeException on API error (caller catches).
 *
 * NB: PayPal's plan-update API replaces ALL existing billing cycles
 * for the plan, so we have to pull the current ones, swap in the new
 * price, and PUT them back. Subscription plans typically only have
 * one regular cycle (no trial); we handle that common case plus the
 * trial-cycle case defensively.
 */
function paypal_update_plan_price(string $planId, float $newPriceGbp): void
{
    if ($planId === '') {
        throw new RuntimeException('Plan id required.');
    }
    if ($newPriceGbp < 0) {
        throw new RuntimeException('Price must be non-negative.');
    }

    // Fetch the current plan to see what cycles exist.
    $r = paypal_request('GET', '/v1/billing/plans/' . rawurlencode($planId));
    $cycles = (array) ($r['data']['billing_cycles'] ?? []);
    if (!$cycles) {
        throw new RuntimeException('Plan has no billing cycles to update.');
    }

    $newCycles = [];
    foreach ($cycles as $cycle) {
        $seq    = (int) ($cycle['sequence'] ?? 1);
        $type   = (string) ($cycle['tenure_type'] ?? 'REGULAR');
        // Trial cycles are usually free; we only update the regular
        // (paid) one. If a plan is genuinely tiered we'd need a UI for
        // that — out of scope for v1.
        if ($type === 'REGULAR') {
            $newCycles[] = [
                'sequence'      => $seq,
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value'         => number_format($newPriceGbp, 2, '.', ''),
                        'currency_code' => 'GBP',
                    ],
                ],
            ];
        }
    }

    if (!$newCycles) {
        throw new RuntimeException('No REGULAR billing cycle found to reprice.');
    }

    paypal_request(
        'POST',
        '/v1/billing/plans/' . rawurlencode($planId) . '/update-pricing-schemes',
        ['pricing_schemes' => $newCycles]
    );
}

/**
 * Create a PayPal Product + Plan from scratch and return the new
 * Plan ID. Used by /master-admin/pricing.php when an admin types a
 * price for a plan that has no PayPal Plan ID yet (e.g. a brand new
 * paid plan was just added to the static registry).
 *
 * Returns the new plan_id (e.g. "P-XXXXXXXXX").
 */
function paypal_create_plan(string $planCode, string $name, string $description, float $priceGbp): string
{
    if ($priceGbp <= 0) {
        throw new RuntimeException('Cannot create a PayPal plan with zero or negative price.');
    }

    // 1. Product.
    $prodId = 'yb-' . preg_replace('/[^a-z0-9_-]/i', '', $planCode) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    $r = paypal_request('POST', '/v1/catalogs/products', [
        'id'          => $prodId,
        'name'        => $name,
        'description' => $description ?: $name,
        'type'        => 'SERVICE',
        'category'    => 'SOFTWARE',
    ]);
    $productId = (string) ($r['data']['id'] ?? $prodId);

    // 2. Plan — monthly billing, no trial, in GBP, fixed price, auto-renewing.
    $r2 = paypal_request('POST', '/v1/billing/plans', [
        'product_id'   => $productId,
        'name'         => $name,
        'description'  => $description ?: $name,
        'status'       => 'ACTIVE',
        'billing_cycles' => [[
            'frequency'      => ['interval_unit' => 'MONTH', 'interval_count' => 1],
            'tenure_type'    => 'REGULAR',
            'sequence'       => 1,
            'total_cycles'   => 0,   // 0 = infinite
            'pricing_scheme' => [
                'fixed_price' => [
                    'value'         => number_format($priceGbp, 2, '.', ''),
                    'currency_code' => 'GBP',
                ],
            ],
        ]],
        'payment_preferences' => [
            'auto_bill_outstanding'     => true,
            'setup_fee'                 => [
                'value' => '0', 'currency_code' => 'GBP',
            ],
            'setup_fee_failure_action'  => 'CONTINUE',
            'payment_failure_threshold' => 3,
        ],
    ]);
    $planId = (string) ($r2['data']['id'] ?? '');
    if ($planId === '') {
        throw new RuntimeException('PayPal created a plan but returned no id.');
    }
    return $planId;
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
