<?php
declare(strict_types=1);

/**
 * Start a new PayPal subscription for the current tenant.
 *
 * GET/POST: admin-only. Creates a subscription via PayPal API, saves
 * its id locally (without yet changing plan/status — that happens
 * when the user returns from approval, OR via the webhook), then
 * redirects the user to PayPal's hosted approval page.
 *
 * If PayPal config isn't in place yet, sends the user back to the
 * Billing page with a flash error explaining what's missing.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/billing_helpers.php';
require __DIR__ . '/../_partials/paypal.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // GET shouldn't be initiating money-moving flows; bounce.
    header('Location: /billing/index.php');
    exit;
}
csrf_check();

$user     = current_user();
$clientId = (int) $user['client_id'];

if (!paypal_is_configured()) {
    $_SESSION['flash_error'] = 'PayPal isn\'t configured yet. '
        . 'Ask the super-admin to set PAYPAL_CLIENT_ID / PAYPAL_SECRET in .env.';
    header('Location: /billing/index.php');
    exit;
}

$cfg    = paypal_config();
$planId = $cfg['plan_id_accounts'];
if ($planId === '') {
    $_SESSION['flash_error'] = 'PayPal plan id is missing — run /setup_paypal_plan.php '
        . '(super-admin) and add PAYPAL_PLAN_ACCOUNTS to .env.';
    header('Location: /billing/index.php');
    exit;
}

// Absolute return / cancel URLs. APP_URL must be set in .env.
$appBase    = rtrim((string) (env('APP_URL', '') ?? ''), '/');
if ($appBase === '') {
    $_SESSION['flash_error'] = 'APP_URL missing from .env — needed so PayPal knows '
        . 'where to send the customer after approval.';
    header('Location: /billing/index.php');
    exit;
}
$returnUrl = $appBase . '/billing/return.php';
$cancelUrl = $appBase . '/billing/index.php';

try {
    $r = paypal_request('POST', '/v1/billing/subscriptions', [
        'plan_id'  => $planId,
        // custom_id rides on the subscription forever — webhook
        // uses it to map PayPal's subscription back to our tenant
        // even if external_subscription_id wasn't recorded locally
        // for any reason.
        'custom_id' => 'client:' . $clientId,
        'application_context' => [
            'brand_name'          => 'YourBlinds',
            'locale'              => 'en-GB',
            'shipping_preference' => 'NO_SHIPPING',
            'user_action'         => 'SUBSCRIBE_NOW',
            'payment_method'      => [
                'payer_selected'                => 'PAYPAL',
                'payee_preferred'               => 'IMMEDIATE_PAYMENT_REQUIRED',
            ],
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
        ],
    ]);
} catch (Throwable $e) {
    error_log('PayPal subscribe error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Could not start subscription: ' . $e->getMessage();
    header('Location: /billing/index.php');
    exit;
}

$subId = (string) ($r['data']['id'] ?? '');
if ($subId === '') {
    $_SESSION['flash_error'] = 'PayPal returned no subscription id.';
    header('Location: /billing/index.php');
    exit;
}

// Save the subscription id locally — but DON'T yet change plan/status
// to active. We wait until the user has approved on PayPal and we've
// confirmed via the API in return.php (or the webhook fires the
// ACTIVATED event). This way a customer who bails on the PayPal
// approval page doesn't accidentally end up with an "active" sub.
db()->prepare(
    "INSERT INTO tenant_subscriptions
       (client_id, plan_code, status, external_provider, external_subscription_id)
       VALUES (?, ?, ?, 'paypal', ?)
     ON DUPLICATE KEY UPDATE
       external_provider        = 'paypal',
       external_subscription_id = VALUES(external_subscription_id)"
)->execute([$clientId, 'free', 'active', $subId]);
// (plan/status stay whatever they were — we only stash the id.)

// Find the approval URL in PayPal's links array and redirect.
$approveUrl = '';
foreach ((array) ($r['data']['links'] ?? []) as $link) {
    if (($link['rel'] ?? '') === 'approve') {
        $approveUrl = (string) ($link['href'] ?? '');
        break;
    }
}
if ($approveUrl === '') {
    $_SESSION['flash_error'] = 'PayPal didn\'t return an approval URL.';
    header('Location: /billing/index.php');
    exit;
}

header('Location: ' . $approveUrl);
exit;
