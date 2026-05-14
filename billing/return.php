<?php
declare(strict_types=1);

/**
 * Landing page after the user approves a subscription on PayPal.
 *
 * PayPal redirects them here with ?subscription_id=I-xxx. We:
 *   1. Look the subscription up via PayPal's API.
 *   2. Verify it belongs to this tenant via the custom_id we set
 *      during subscribe.php (defence against approval-URL tampering).
 *   3. Map PayPal status → our local enum + save.
 *   4. Sync feature flags so Accounts goes live immediately if
 *      status === ACTIVE.
 *
 * The webhook (paypal_webhook.php) is the source of truth long-term,
 * but this return handler gives the user immediate feedback without
 * waiting for the webhook to land.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/billing_helpers.php';
require __DIR__ . '/../_partials/paypal.php';

requireAdmin();

$user     = current_user();
$clientId = (int) $user['client_id'];

// Log everything PayPal sent us — invaluable when something looks
// odd later (different param names between sandbox/live, order
// tokens sneaking into subscription_id, etc.).
error_log('PayPal return.php hit. $_GET=' . json_encode($_GET));

$subId = trim((string) ($_GET['subscription_id'] ?? ''));

// Sanity-check the format. PayPal Subscription IDs are
// "I-" followed by ~13 alphanumeric chars. Order tokens (which
// PayPal sometimes accidentally returns) look like
// "5EU12928FA5744921" — 17 alphanumerics, no prefix. Calling
// /v1/billing/subscriptions/<order-token> gives a confusing 400
// "request not well-formed" — surface a clearer message and bail.
if ($subId === '' || !preg_match('/^I-[A-Z0-9]+$/i', $subId)) {
    error_log('PayPal return.php: bad subscription_id "' . $subId . '". $_GET=' . json_encode($_GET));
    $_SESSION['flash_error'] = $subId === ''
        ? 'No subscription id returned by PayPal.'
        : 'PayPal returned an unexpected id (' . $subId . '). Try Subscribe again, '
        . 'or contact support if it keeps happening.';
    header('Location: /billing/index.php');
    exit;
}

try {
    $r = paypal_request('GET', '/v1/billing/subscriptions/' . rawurlencode($subId));
} catch (Throwable $e) {
    error_log('PayPal return lookup error for ' . $subId . ': ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Could not verify subscription with PayPal: ' . $e->getMessage();
    header('Location: /billing/index.php');
    exit;
}

$sub = $r['data'];

// Verify custom_id matches our tenant. If it doesn't, someone has
// fed us a different tenant's subscription_id via URL tampering —
// refuse to update anything.
$customId = (string) ($sub['custom_id'] ?? '');
if ($customId !== 'client:' . $clientId) {
    $_SESSION['flash_error'] = 'Subscription does not match your account.';
    header('Location: /billing/index.php');
    exit;
}

$paypalStatus = strtoupper((string) ($sub['status'] ?? ''));
$localStatus  = paypal_map_status($paypalStatus);

// Pull period end from billing_info.next_billing_time (when active).
$periodEnd = null;
$nbt = $sub['billing_info']['next_billing_time'] ?? null;
if (is_string($nbt) && strlen($nbt) >= 10) {
    $periodEnd = substr($nbt, 0, 10);   // YYYY-MM-DD
}

$pdo = db();
// Also clear any "Backfilled from feature_accounts=…" note left by
// migrate_tenant_subscriptions — once a real PayPal subscription is
// in place, the migration's audit blurb is stale + visible to the
// tenant on their Billing page, which looks odd. Admin-set notes
// (anything not starting with "Backfilled") are preserved.
$pdo->prepare(
    "INSERT INTO tenant_subscriptions
       (client_id, plan_code, status,
        external_provider, external_subscription_id,
        current_period_end)
       VALUES (?, 'accounts', ?, 'paypal', ?, ?)
     ON DUPLICATE KEY UPDATE
       plan_code                = 'accounts',
       status                   = VALUES(status),
       external_provider        = 'paypal',
       external_subscription_id = VALUES(external_subscription_id),
       current_period_end       = VALUES(current_period_end),
       cancelled_at             = NULL,
       notes                    = CASE
                                    WHEN notes LIKE 'Backfilled%' THEN NULL
                                    ELSE notes
                                  END"
)->execute([$clientId, $localStatus, $subId, $periodEnd]);

billing_sync_feature_flags($clientId);

if ($localStatus === 'active') {
    $_SESSION['flash_success'] =
        '✓ Subscription active — the Accounts add-on is now enabled. '
        . 'Thanks!';
} else {
    $_SESSION['flash_error'] =
        'Subscription saved but PayPal reports status "' . $paypalStatus . '". '
        . 'It may activate shortly — refresh in a moment, or check your PayPal account.';
}

header('Location: /billing/index.php');
exit;
