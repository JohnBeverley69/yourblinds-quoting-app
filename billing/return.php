<?php
declare(strict_types=1);

/**
 * Landing page after the user approves a subscription on PayPal.
 *
 * PayPal redirects them here with ?subscription_id=I-xxx. We:
 *   1. Look the subscription up via PayPal's API.
 *   2. Verify it belongs to this tenant via the custom_id we set
 *      during subscribe.php ("client:N|plan:CODE"). custom_id is
 *      also where we get the plan_code — guarantees the UPSERT lands
 *      on the correct (client_id, plan_code) row.
 *   3. Map PayPal status → our local enum + save.
 *   4. Sync feature flags so the relevant add-on goes live immediately
 *      if status === ACTIVE.
 *
 * The webhook (paypal_webhook.php) is the source of truth long-term;
 * this is just for immediate UX feedback.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/billing_helpers.php';
require __DIR__ . '/../_partials/paypal.php';

requireAdmin();

$user     = current_user();
$clientId = (int) $user['client_id'];

error_log('PayPal return.php hit. $_GET=' . json_encode($_GET));

$subId = trim((string) ($_GET['subscription_id'] ?? ''));

// PayPal Subscription IDs are "I-" followed by alphanumerics. Reject
// anything else with a clear error message rather than calling the
// API on garbage.
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

// Decode custom_id back to (clientId, planCode). Format:
// "client:N|plan:CODE" (new), or legacy "client:N" (old subscriptions
// that pre-date multi-add-on; treat those as 'accounts').
$customId = (string) ($sub['custom_id'] ?? '');
$claimedClient = 0;
$planCode = 'accounts';
if (preg_match('/^client:(\d+)\|plan:([a-z0-9_]+)$/i', $customId, $m)) {
    $claimedClient = (int) $m[1];
    $planCode      = strtolower($m[2]);
} elseif (preg_match('/^client:(\d+)$/', $customId, $m)) {
    $claimedClient = (int) $m[1];
    // legacy: pre-multi-add-on subscriptions were always Accounts.
    $planCode = 'accounts';
}

if ($claimedClient !== $clientId) {
    $_SESSION['flash_error'] = 'Subscription does not match your account.';
    header('Location: /billing/index.php');
    exit;
}

// Defence-in-depth: only accept plan codes we actually know about.
if (!billing_plan($planCode) || $planCode === 'free') {
    $_SESSION['flash_error'] = 'PayPal returned an unknown plan code: ' . $planCode;
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
// UPSERT on the (client_id, plan_code) row. Also clear any
// "Backfilled from feature_*=…" note from the historical migration
// — once a real PayPal sub is in place, that audit blurb is stale.
$pdo->prepare(
    "INSERT INTO tenant_subscriptions
       (client_id, plan_code, status,
        external_provider, external_subscription_id,
        current_period_end)
       VALUES (?, ?, ?, 'paypal', ?, ?)
     ON DUPLICATE KEY UPDATE
       status                   = VALUES(status),
       external_provider        = 'paypal',
       external_subscription_id = VALUES(external_subscription_id),
       current_period_end       = VALUES(current_period_end),
       cancelled_at             = NULL,
       notes                    = CASE
                                    WHEN notes LIKE 'Backfilled%' THEN NULL
                                    ELSE notes
                                  END"
)->execute([$clientId, $planCode, $localStatus, $subId, $periodEnd]);

billing_sync_feature_flags_force($clientId);

$planName = (string) (billing_plan($planCode)['name'] ?? $planCode);
if ($localStatus === 'active') {
    $_SESSION['flash_success'] =
        '✓ Subscription active — the ' . $planName . ' is now enabled. Thanks!';
} else {
    $_SESSION['flash_error'] =
        'Subscription saved but PayPal reports status "' . $paypalStatus . '". '
        . 'It may activate shortly — refresh in a moment, or check your PayPal account.';
}

header('Location: /billing/index.php');
exit;
