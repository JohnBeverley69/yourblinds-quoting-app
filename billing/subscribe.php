<?php
declare(strict_types=1);

/**
 * Start a new PayPal subscription for the current tenant on a
 * specific add-on plan.
 *
 * POST: admin-only. Accepts `plan_code` — any code in
 * /_partials/billing_plans.php that isn't 'free' and has a paypal
 * Plan ID set in /master-admin/pricing.php.
 *
 * Creates a subscription via PayPal API, saves its id locally
 * (without yet changing status — that happens when the user returns
 * from approval, OR via the webhook), then redirects the user to
 * PayPal's hosted approval page.
 *
 * custom_id format: "client:<id>|plan:<plan_code>" — webhook + return
 * handler both parse this back to (clientId, planCode) so we always
 * know which add-on row to update.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/billing_helpers.php';
require __DIR__ . '/../_partials/paypal.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /billing/index.php');
    exit;
}
csrf_check();

$user     = current_user();
$clientId = (int) $user['client_id'];

// plan_code from the form; legacy callers without one default to
// 'accounts' so any browser back-button replay of the old flow still
// works.
$planCode = trim((string) ($_POST['plan_code'] ?? 'accounts'));
$plan     = billing_plan($planCode);
if (!$plan || $planCode === 'free') {
    $_SESSION['flash_error'] = 'Unknown plan: ' . $planCode;
    header('Location: /billing/index.php');
    exit;
}

if (!paypal_is_configured()) {
    $_SESSION['flash_error'] = 'PayPal isn\'t configured yet. '
        . 'Ask the super-admin to set PAYPAL_CLIENT_ID / PAYPAL_SECRET in .env.';
    header('Location: /billing/index.php');
    exit;
}

$paypalPlanId = billing_paypal_plan_id($planCode);
if ($paypalPlanId === '') {
    $_SESSION['flash_error'] = 'No PayPal Plan ID for "' . $planCode . '". '
        . 'Ask the super-admin to click "Create on PayPal" on the Pricing page.';
    header('Location: /billing/index.php');
    exit;
}

// Absolute return / cancel URLs. APP_URL must be set in .env.
$appBase = rtrim((string) (env('APP_URL', '') ?? ''), '/');
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
        'plan_id'   => $paypalPlanId,
        // custom_id rides on the subscription forever — webhook +
        // return handler decode it back to (clientId, planCode).
        'custom_id' => 'client:' . $clientId . '|plan:' . $planCode,
        'application_context' => [
            'brand_name'          => 'YourBlinds',
            'locale'              => 'en-GB',
            'shipping_preference' => 'NO_SHIPPING',
            'user_action'         => 'SUBSCRIBE_NOW',
            'payment_method'      => [
                'payer_selected'  => 'PAYPAL',
                'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED',
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

// Save the subscription id locally — but DON'T yet change status to
// active. That happens in return.php (or via the webhook) so a
// customer who bails on the PayPal approval page doesn't end up
// stuck with an "active" sub. plan_code IS recorded here so we know
// which row to UPSERT against when return.php fires.
//
// One row per (client, plan): UNIQUE(client_id, plan_code) makes this
// an idempotent reschedule if the tenant clicks Subscribe twice.
db()->prepare(
    "INSERT INTO tenant_subscriptions
       (client_id, plan_code, status, external_provider, external_subscription_id)
       VALUES (?, ?, 'past_due', 'paypal', ?)
     ON DUPLICATE KEY UPDATE
       external_provider        = 'paypal',
       external_subscription_id = VALUES(external_subscription_id),
       status                   = CASE
                                    WHEN status IN ('cancelled', 'expired') THEN 'past_due'
                                    ELSE status
                                  END"
)->execute([$clientId, $planCode, $subId]);

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
