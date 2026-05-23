<?php
declare(strict_types=1);

/**
 * PayPal webhook receiver.
 *
 * Receives event POSTs from PayPal and applies them to the matching
 * tenant_subscriptions row (located by external_subscription_id, which
 * is per PayPal subscription and therefore per add-on). Source of
 * truth for long-running state — the user-facing subscribe/return/
 * cancel flows are immediate UX niceties; this is what keeps state
 * correct over weeks/months as payments succeed, fail, retry, etc.
 *
 * Auth: cryptographic via paypal_verify_webhook(). No CSRF (no
 * browser context) — the signature IS the auth.
 *
 * Always responds 200 to events we successfully process (or
 * deliberately ignore), so PayPal doesn't retry. Responds 400 only
 * if verification fails — that's PayPal's signal to alert us via
 * the developer dashboard, not to retry.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/billing_helpers.php';
require __DIR__ . '/../_partials/paypal.php';

// NOTE: no requireLogin() — PayPal isn't logged in. The signature
// verification below is the auth.

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$cfg = paypal_config();
if ($cfg['webhook_id'] === '') {
    error_log('PayPal webhook arrived but PAYPAL_WEBHOOK_ID is unset in .env');
    http_response_code(200);
    exit('Webhook id not configured');
}

$payload = (string) file_get_contents('php://input');
$headers = paypal_request_headers();

// Log every webhook attempt — even ones that fail verification, so we
// can see attempted forgeries / mis-configured endpoints in the
// audit trail. Anything written here is best-effort; if the log
// table is missing (migration not run) we silently skip.
//
// The closure is defined upfront so each code path can fire-and-forget
// without worrying about row uniqueness or sequence.
$pwl_log = static function (
    string $eventType,
    ?string $eventId,
    ?string $subId,
    ?int $clientId,
    ?string $planCode,
    bool $verified,
    bool $processed,
    ?string $outcome,
    string $payloadExcerpt
): void {
    try {
        db()->prepare(
            'INSERT INTO paypal_webhook_log
              (event_type, event_id, subscription_id, client_id, plan_code,
               verified, processed, outcome, payload_excerpt)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $eventType ?: 'unknown',
            $eventId,
            $subId,
            $clientId,
            $planCode,
            $verified ? 1 : 0,
            $processed ? 1 : 0,
            $outcome,
            mb_substr($payloadExcerpt, 0, 4000),
        ]);
    } catch (Throwable $e) {
        // Table missing or DB hiccup — don't let logging break event
        // processing. Note in PHP error log instead.
        error_log('paypal_webhook_log insert failed: ' . $e->getMessage());
    }
};

if (!paypal_verify_webhook($payload, $headers, $cfg['webhook_id'])) {
    error_log('PayPal webhook signature verification FAILED. Headers: '
        . json_encode($headers) . ' Body: ' . substr($payload, 0, 500));
    // Try to extract the event type even from an unverified payload so
    // the operator can see "spoof attempt" with context.
    $maybe = json_decode($payload, true);
    $pwl_log(
        is_array($maybe) ? (string) ($maybe['event_type'] ?? 'unknown') : 'unknown',
        is_array($maybe) ? (string) ($maybe['id'] ?? null) : null,
        null,
        null,
        null,
        false,
        false,
        'verification_failed',
        $payload
    );
    http_response_code(400);
    exit('Verification failed');
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    $pwl_log('unknown', null, null, null, null, true, false, 'bad_json', $payload);
    http_response_code(200);
    exit('Bad JSON');
}

$type     = (string) ($event['event_type'] ?? '');
$eventId  = (string) ($event['id']         ?? '');
$resource = (array)  ($event['resource']   ?? []);
$subId    = (string) ($resource['id']      ?? '');

// Some events (e.g. PAYMENT.SALE.COMPLETED) carry the subscription id
// inside billing_agreement_id rather than id. Cover both.
if ($subId === '' || str_starts_with($subId, 'PAY-')) {
    $subId = (string) ($resource['billing_agreement_id'] ?? $subId);
}

// custom_id format: "client:N|plan:CODE" (new); legacy "client:N"
// is interpreted as plan=accounts for pre-multi-add-on subs.
$customId = (string) ($resource['custom_id'] ?? $resource['custom'] ?? '');
$customClient = 0;
$customPlan   = '';
if (preg_match('/^client:(\d+)\|plan:([a-z0-9_]+)$/i', $customId, $m)) {
    $customClient = (int) $m[1];
    $customPlan   = strtolower($m[2]);
} elseif (preg_match('/^client:(\d+)$/', $customId, $m)) {
    $customClient = (int) $m[1];
    $customPlan   = 'accounts';
}

if ($subId === '' && $customId === '') {
    error_log('PayPal webhook had no usable subscription id (event: ' . $type . ')');
    $pwl_log($type, $eventId ?: null, null, null, null, true, false, 'no_subscription_id', $payload);
    http_response_code(200);
    exit('No id');
}

$pdo = db();

// Find the local row by subscription_id first (most reliable), then
// fall back to (client, plan) from custom_id.
$local = null;
if ($subId !== '') {
    $st = $pdo->prepare(
        'SELECT * FROM tenant_subscriptions
          WHERE external_subscription_id = ? LIMIT 1'
    );
    $st->execute([$subId]);
    $local = $st->fetch() ?: null;
}
if (!$local && $customClient > 0 && $customPlan !== '') {
    $st = $pdo->prepare(
        'SELECT * FROM tenant_subscriptions
          WHERE client_id = ? AND plan_code = ? LIMIT 1'
    );
    $st->execute([$customClient, $customPlan]);
    $local = $st->fetch() ?: null;
}
if (!$local) {
    error_log('PayPal webhook: no matching local subscription for ' . $subId
        . ' / ' . $customId . ' (event: ' . $type . ')');
    $pwl_log(
        $type, $eventId ?: null, $subId ?: null,
        $customClient ?: null, $customPlan ?: null,
        true, false, 'no_matching_tenant', $payload
    );
    http_response_code(200);
    exit('No matching tenant');
}

$clientId = (int) $local['client_id'];
$planCode = (string) $local['plan_code'];

// Period-end pulled from the resource where possible.
$periodEnd = null;
$nbt = $resource['billing_info']['next_billing_time']
    ?? $resource['agreement_details']['next_billing_date']
    ?? null;
if (is_string($nbt) && strlen($nbt) >= 10) {
    $periodEnd = substr($nbt, 0, 10);
}

switch ($type) {

    case 'BILLING.SUBSCRIPTION.ACTIVATED':
    case 'BILLING.SUBSCRIPTION.RE-ACTIVATED':
    case 'BILLING.SUBSCRIPTION.UPDATED':
        // Activated, reactivated, or details changed. Use the API to
        // fetch the canonical current state rather than trust the
        // event body's snapshot.
        try {
            $r = paypal_request('GET', '/v1/billing/subscriptions/' . rawurlencode((string) $local['external_subscription_id']));
            $full = $r['data'];
            $newStatus = paypal_map_status((string) ($full['status'] ?? ''));
            $nbt2 = $full['billing_info']['next_billing_time'] ?? null;
            $pe2  = (is_string($nbt2) && strlen($nbt2) >= 10) ? substr($nbt2, 0, 10) : null;
            $pdo->prepare(
                "UPDATE tenant_subscriptions
                    SET status = ?,
                        current_period_end = ?,
                        cancelled_at = CASE WHEN ? = 'active' THEN NULL ELSE cancelled_at END
                  WHERE client_id = ? AND plan_code = ?"
            )->execute([$newStatus, $pe2, $newStatus, $clientId, $planCode]);
            billing_sync_feature_flags_force($clientId);
        } catch (Throwable $e) {
            error_log('PayPal webhook activation lookup failed: ' . $e->getMessage());
        }
        break;

    case 'BILLING.SUBSCRIPTION.CANCELLED':
    case 'BILLING.SUBSCRIPTION.EXPIRED':
        $newStatus = $type === 'BILLING.SUBSCRIPTION.EXPIRED' ? 'expired' : 'cancelled';
        $pdo->prepare(
            "UPDATE tenant_subscriptions
                SET status = ?,
                    cancelled_at = NOW()
              WHERE client_id = ? AND plan_code = ?"
        )->execute([$newStatus, $clientId, $planCode]);
        billing_sync_feature_flags_force($clientId);
        break;

    case 'BILLING.SUBSCRIPTION.SUSPENDED':
    case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
        // Payment failure — PayPal will retry. Mark past_due so the UI
        // can warn, but DON'T turn off features yet (give them a few
        // days to fix it).
        $pdo->prepare(
            "UPDATE tenant_subscriptions
                SET status = 'past_due'
              WHERE client_id = ? AND plan_code = ?"
        )->execute([$clientId, $planCode]);
        // Deliberately NOT syncing flags — keep features on through
        // the retry window. CANCELLED/EXPIRED kills them.
        break;

    case 'PAYMENT.SALE.COMPLETED':
        // Renewal succeeded. Bump period end.
        try {
            $r = paypal_request('GET', '/v1/billing/subscriptions/' . rawurlencode((string) $local['external_subscription_id']));
            $full = $r['data'];
            $nbt2 = $full['billing_info']['next_billing_time'] ?? null;
            $pe2  = (is_string($nbt2) && strlen($nbt2) >= 10) ? substr($nbt2, 0, 10) : null;
            $pdo->prepare(
                "UPDATE tenant_subscriptions
                    SET status = 'active',
                        current_period_end = ?
                  WHERE client_id = ? AND plan_code = ?"
            )->execute([$pe2, $clientId, $planCode]);
            billing_sync_feature_flags_force($clientId);
        } catch (Throwable $e) {
            error_log('PayPal renewal lookup failed: ' . $e->getMessage());
        }
        break;

    case 'PAYMENT.SALE.DENIED':
    case 'PAYMENT.SALE.REFUNDED':
    case 'PAYMENT.SALE.REVERSED':
        // Money didn't make it / got reversed. Mark past_due; don't
        // immediately disable features (they may retry).
        $pdo->prepare(
            "UPDATE tenant_subscriptions
                SET status = 'past_due'
              WHERE client_id = ? AND plan_code = ?"
        )->execute([$clientId, $planCode]);
        break;

    default:
        error_log('PayPal webhook: unhandled event type ' . $type);
        $pwl_log(
            $type, $eventId ?: null, $subId ?: null,
            $clientId ?: null, $planCode ?: null,
            true, false, 'unhandled_event_type', $payload
        );
        http_response_code(200);
        exit('ok (unhandled)');
}

// All handled-event paths fall through to here. One log row per
// successfully-processed event — that's what the health dashboard
// reads ("last event received: 4 minutes ago").
$pwl_log(
    $type, $eventId ?: null, $subId ?: null,
    $clientId, $planCode,
    true, true, 'processed', $payload
);

http_response_code(200);
exit('ok');
