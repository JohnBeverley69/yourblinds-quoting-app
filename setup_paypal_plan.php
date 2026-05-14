<?php
declare(strict_types=1);

/**
 * One-off setup script — creates the PayPal Product + Plan needed
 * for subscriptions. Run ONCE after the migration. Outputs the
 * Plan ID; add it to /.env as PAYPAL_PLAN_ACCOUNTS.
 *
 * Idempotent in spirit: if you re-run it you'll get duplicate
 * Products / Plans in PayPal (PayPal doesn't dedupe by name). Delete
 * this script after first successful run so it can't accidentally
 * be re-executed.
 *
 * Super-admin only.
 *
 * Prereqs in /.env on the server:
 *   PAYPAL_ENV=sandbox
 *   PAYPAL_CLIENT_ID=...
 *   PAYPAL_SECRET=...
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
require_once __DIR__ . '/_partials/paypal.php';
require_once __DIR__ . '/_partials/billing_helpers.php';

requireSuperAdmin();

ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

set_exception_handler(function (Throwable $e) {
    if (!headers_sent()) header('Content-Type: text/plain; charset=utf-8');
    echo "PAYPAL SETUP FAILED\n===================\n\n";
    echo 'Error: ' . $e->getMessage() . "\n";
    echo 'In:    ' . $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
});

$cfg = paypal_config();
if (!paypal_is_configured()) {
    throw new RuntimeException(
        'PAYPAL_CLIENT_ID and/or PAYPAL_SECRET are missing from .env. '
      . 'Add them before running this script.'
    );
}
echo "Environment: " . $cfg['env'] . "\n";
echo "API base:    " . $cfg['api_base_url'] . "\n\n";

// --- 1. Create Product -------------------------------------------------
echo "Creating Product...\n";
$plans = billing_plans();
$accountsPlan = $plans['accounts'] ?? null;
if (!$accountsPlan) {
    throw new RuntimeException('billing_plans()["accounts"] not found');
}

$productResp = paypal_request('POST', '/v1/catalogs/products', [
    'name'        => 'YourBlinds — ' . $accountsPlan['name'],
    'description' => $accountsPlan['description'],
    'type'        => 'SERVICE',
    'category'    => 'SOFTWARE',
]);
$productId = (string) ($productResp['data']['id'] ?? '');
if ($productId === '') {
    throw new RuntimeException('Product creation returned no id: '
        . json_encode($productResp['data']));
}
echo "  Product created: $productId\n\n";

// --- 2. Create Plan ----------------------------------------------------
echo "Creating Plan...\n";
$price = number_format((float) $accountsPlan['price_gbp_monthly'], 2, '.', '');

$planResp = paypal_request('POST', '/v1/billing/plans', [
    'product_id'  => $productId,
    'name'        => $accountsPlan['name'] . ' — £' . $price . '/month',
    'description' => 'Monthly billing for ' . $accountsPlan['name'],
    'status'      => 'ACTIVE',
    'billing_cycles' => [[
        'frequency' => [
            'interval_unit'  => 'MONTH',
            'interval_count' => 1,
        ],
        'tenure_type' => 'REGULAR',
        'sequence'    => 1,
        'total_cycles' => 0,   // 0 = infinite recurring
        'pricing_scheme' => [
            'fixed_price' => [
                'value'         => $price,
                'currency_code' => 'GBP',
            ],
        ],
    ]],
    'payment_preferences' => [
        'auto_bill_outstanding'     => true,
        'setup_fee_failure_action'  => 'CONTINUE',
        'payment_failure_threshold' => 3,
    ],
]);
$planId = (string) ($planResp['data']['id'] ?? '');
if ($planId === '') {
    throw new RuntimeException('Plan creation returned no id: '
        . json_encode($planResp['data']));
}
echo "  Plan created: $planId\n\n";

// --- 3. Done — give the operator the env line they need ----------------
echo "============================================================\n";
echo "DONE. Add this line to /.env on the server:\n";
echo "\n";
echo "  PAYPAL_PLAN_ACCOUNTS=$planId\n";
echo "\n";
echo "Then DELETE this file (setup_paypal_plan.php) from the web root\n";
echo "so it can't be re-run by accident (running it again would\n";
echo "create a duplicate Product + Plan in PayPal).\n";
echo "\n";
echo "Next steps:\n";
echo "  1. Save PAYPAL_PLAN_ACCOUNTS in .env.\n";
echo "  2. Register the webhook URL in PayPal's developer dashboard:\n";
echo "       Apps & Credentials -> your app -> Webhooks -> Add\n";
echo "       URL:   " . rtrim((string) env('APP_URL', 'https://yourblinds.uk'), '/') . "/billing/paypal_webhook.php\n";
echo "       Events: BILLING.SUBSCRIPTION.ACTIVATED,\n";
echo "               BILLING.SUBSCRIPTION.CANCELLED,\n";
echo "               BILLING.SUBSCRIPTION.SUSPENDED,\n";
echo "               BILLING.SUBSCRIPTION.EXPIRED,\n";
echo "               BILLING.SUBSCRIPTION.UPDATED,\n";
echo "               PAYMENT.SALE.COMPLETED,\n";
echo "               PAYMENT.SALE.DENIED\n";
echo "     PayPal will give you a webhook ID — add that as\n";
echo "     PAYPAL_WEBHOOK_ID in .env.\n";
echo "  3. Test with a sandbox buyer account.\n";
