<?php
declare(strict_types=1);

/**
 * PayPal webhook receiver.
 *
 * Receives event POSTs from PayPal and applies them to the matching
 * tenant_subscriptions row. Source of truth for long-running state —
 * the user-facing subscribe/return/cancel flows are immediate UX
 * niceties; this is what keeps state correct over weeks/months as
 * payments succeed, fail, retry, expire, etc.
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
    // Misconfigured — log loudly but don't bounce PayPal into a
    // retry storm. Acknowledge the event and move on.
    error_log('PayPal webhook arrived but PAYPAL_WEBHOOK_ID is unset in .env');
    http_response_code(200);
    exit('Webhook id not configured');
}

$payload = (string) file_get_contents('php://input');
$headers = paypal_request_headers();

if (!paypal_verify_webhook($payload, $headers, $cfg['webhook_id'])) {
    error_log('PayPal webhook signature verification FAILED. Headers: '
        . json_encode($headers) . ' Body: ' . substr($payload, 0, 500));
    http_response_code(400);
    exit('Verification failed');
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    http_response_code(200);
    exit('Bad JSON');
}

$type     = (string) ($event['event_type'] ?? '');
$resource = (array)  ($event['resource']   ?? []);
$subId    = (string) ($resource['id']      ?? '');

// Some events (e.g. PAYMENT.SALE.COMPLETED) carry the subscription id
// inside billing_agreement_id rather than id. Cover both.
if ($subId === '' || str_starts_with($subId, 'PAY-')) {
    $subId = (string) ($resource['billing_agreement_id'] ?? $subId);
}

// custom_id is the fallback — we set it during subscribe.php so we
// can still find our tenant if the subscription_id link is somehow
// broken.
$customId = (string) ($resource['custom_id'] ?? $resource['custom'] ?? '');

if ($subId === '' && $customId === '') {
    error_log('PayPal webhook had no usable subscription id (event: ' . $type . ')');
    http_response_code(200);
    exit('No id');
}

$pdo = db();

// Find the local row.
$local = null;
if ($subId !== '') {
    $st = $pdo->prepare(
        'SELECT * FROM tenant_subscriptions
          WHERE external_subscription_id = ? LIMIT 1'
    );
    $st->execute([$subId]);
    $local = $st->fetch() ?: null;
}
if (!$local && str_starts_with($customId, 'client:')) {
    $cidGuess = (int) substr($customId, strlen('client:'));
    if ($cidGuess > 0) {
        $st = $pdo->prepare('SELECT * FROM tenant_subscriptions WHERE client_id = ? LIMIT 1');
        $st->execute([$cidGuess]);
        $local = $st->fetch() ?: null;
    }
}
if (!$local) {
    error_log('PayPal webhook: no matching local subscription for ' . $subId
        . ' / ' . $customId . ' (event: ' . $type . ')');
    http_response_code(200);
    exit('No matching tenant');
}

$clientId = (int) $local['client_id'];

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
        // Activated, reactivated after suspension, or a plan/details
        // change. Use the API to fetch the canonical current state
        // rather than trust the event body's snapshot, since UPDATED
        // events can be partial.
        try {
            $r = paypal_request('GET', '/v1/billing/subscriptions/' . rawurlencode($subId ?: (string) $local['external_subscription_id']));
            $full = $r['data'];
            $newStatus = paypal_map_status((string) ($full['status'] ?? ''));
            $nbt2 = $full['billing_info']['next_billing_time'] ?? null;
            $pe2  = (is_string($nbt2) && strlen($nbt2) >= 10) ? substr($nbt2, 0, 10) : null;
            $pdo->prepare(
                "UPDATE tenant_subscriptions
                    SET plan_code = 'accounts',
                        status = ?,
                        current_period_end = ?,
                        cancelled_at = CASE WHEN ? = 'active' THEN NULL ELSE cancelled_at END
                  WHERE client_id = ?"
            )->execute([$newStatus, $pe2, $newStatus, $clientId]);
            billing_sync_feature_flags($clientId);
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
              WHERE client_id = ?"
        )->execute([$newStatus, $clientId]);
        billing_sync_feature_flags($clientId);
        break;

    case 'BILLING.SUBSCRIPTION.SUSPENDED':
    case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
        // Payment failure — PayPal will retry up to the failure
        // threshold we set on the plan. Mark past_due so the UI can
        // show a warning, but DON'T turn off features yet (give them
        // a few days to fix it).
        $pdo->prepare(
            "UPDATE tenant_subscriptions
                SET status = 'past_due'
              WHERE client_id = ?"
        )->execute([$clientId]);
        // Note: not calling billing_sync_feature_flags here on
        // purpose. past_due is non-active, so sync would turn
        // features OFF — too aggressive. Keep features on through
        // the retry window; CANCELLED/EXPIRED events kill them.
        break;

    case 'PAYMENT.SALE.COMPLETED':
        // Renewal payment succeeded. Bump current_period_end via API
        // (same approach as ACTIVATED).
        try {
            $r = paypal_request('GET', '/v1/billing/subscriptions/' . rawurlencode($subId ?: (string) $local['external_subscription_id']));
            $full = $r['data'];
            $nbt2 = $full['billing_info']['next_billing_time'] ?? null;
            $pe2  = (is_string($nbt2) && strlen($nbt2) >= 10) ? substr($nbt2, 0, 10) : null;
            $pdo->prepare(
                "UPDATE tenant_subscriptions
                    SET status = 'active',
                        current_period_end = ?
                  WHERE client_id = ?"
            )->execute([$pe2, $clientId]);
            billing_sync_feature_flags($clientId);
        } catch (Throwable $e) {
            error_log('PayPal renewal lookup failed: ' . $e->getMessage());
        }
        break;

    case 'PAYMENT.SALE.DENIED':
    case 'PAYMENT.SALE.REFUNDED':
    case 'PAYMENT.SALE.REVERSED':
        // Money didn't make it / got reversed. Mark past_due; we don't
        // immediately disable features (they may retry).
        $pdo->prepare(
            "UPDATE tenant_subscriptions
                SET status = 'past_due'
              WHERE client_id = ?"
        )->execute([$clientId]);
        break;

    default:
        // Unhandled but recognised — ack with 200 so PayPal doesn't
        // retry. Logged so we can spot new event types we should
        // care about.
        error_log('PayPal webhook: unhandled event type ' . $type);
        break;
}

http_response_code(200);
exit('ok');
