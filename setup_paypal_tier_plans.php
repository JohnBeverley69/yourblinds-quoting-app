<?php
declare(strict_types=1);

/**
 * One-shot: create the LIVE PayPal subscription plans for the tier ladder
 * (Silver / Gold / Platinum) and save each Plan ID straight into
 * plan_pricing.paypal_plan_id — so the Master Admin → Pricing page picks them
 * up with no copy-paste.
 *
 * Use it when switching PayPal sandbox → live:
 *   1. Put the LIVE PAYPAL_ENV / CLIENT_ID / SECRET in /.env first.
 *   2. Visit /setup_paypal_tier_plans.php  → shows what it WILL do (dry run).
 *   3. Visit /setup_paypal_tier_plans.php?confirm=create  → creates + saves.
 *   4. DELETE this file afterwards (re-running makes DUPLICATE plans in
 *      PayPal — PayPal doesn't dedupe by name).
 *
 * Super-admin only. Creates one Product, then one Plan per paid tier at that
 * tier's current monthly price.
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
    echo "PAYPAL TIER-PLAN SETUP FAILED\n=============================\n\n";
    echo 'Error: ' . $e->getMessage() . "\n";
    echo 'In:    ' . $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
});

$cfg = paypal_config();
if (!paypal_is_configured()) {
    throw new RuntimeException(
        'PAYPAL_CLIENT_ID and/or PAYPAL_SECRET are missing from .env. Add the LIVE values first.'
    );
}

$confirm = (string) ($_GET['confirm'] ?? '') === 'create';

echo "YourBlinds — create PayPal tier plans\n";
echo "=====================================\n\n";
echo 'Environment: ' . strtoupper((string) $cfg['env']) . "\n";
echo 'API base:    ' . $cfg['api_base_url'] . "\n\n";

if (strtolower((string) $cfg['env']) !== 'live') {
    echo "⚠  PAYPAL_ENV is NOT 'live'. You can still run this (to (re)build\n";
    echo "   sandbox plans), but for go-live set PAYPAL_ENV=live in .env first.\n\n";
}

// The paid tiers, in order, with their current monthly price + existing id.
$pdo  = db();
$paid = billing_paid_plans();   // silver / gold / platinum
$rows = [];
foreach ($paid as $code => $plan) {
    $rows[] = [
        'code'  => $code,
        'name'  => (string) $plan['name'],
        'price' => number_format((float) billing_plan_price($code), 2, '.', ''),
        'cur'   => (string) ($plan['paypal_plan_id'] ?? ''),
    ];
}

echo "Tiers to set up:\n";
foreach ($rows as $r) {
    echo sprintf("  %-9s £%-7s  current id: %s\n", $r['name'], $r['price'], $r['cur'] !== '' ? $r['cur'] : '(none)');
}
echo "\n";

if (!$confirm) {
    echo "DRY RUN — nothing created.\n\n";
    echo "To actually create these plans in the " . strtoupper((string) $cfg['env']) . " PayPal account and\n";
    echo "save their ids into the Pricing page, re-open this URL with ?confirm=create :\n\n";
    echo "    /setup_paypal_tier_plans.php?confirm=create\n\n";
    echo "NOTE: each existing id above (if any) is from the OLD environment and\n";
    echo "will be replaced. Run this ONCE — re-running creates duplicate plans.\n";
    exit;
}

// --- Create one shared Product ----------------------------------------
echo "Creating Product...\n";
$productResp = paypal_request('POST', '/v1/catalogs/products', [
    'name'        => 'YourBlinds Subscription',
    'description' => 'YourBlinds plan subscription',
    'type'        => 'SERVICE',
    'category'    => 'SOFTWARE',
]);
$productId = (string) ($productResp['data']['id'] ?? '');
if ($productId === '') {
    throw new RuntimeException('Product creation returned no id: ' . json_encode($productResp['data']));
}
echo "  Product: $productId\n\n";

// --- Create a Plan per tier + save the id -----------------------------
$upd = $pdo->prepare('UPDATE plan_pricing SET paypal_plan_id = ? WHERE plan_code = ?');
$done = [];
foreach ($rows as $r) {
    echo "Creating plan: {$r['name']} (£{$r['price']}/month)...\n";
    $planResp = paypal_request('POST', '/v1/billing/plans', [
        'product_id'  => $productId,
        'name'        => 'YourBlinds ' . $r['name'] . ' — £' . $r['price'] . '/month',
        'description' => 'Monthly billing for YourBlinds ' . $r['name'],
        'status'      => 'ACTIVE',
        'billing_cycles' => [[
            'frequency'      => ['interval_unit' => 'MONTH', 'interval_count' => 1],
            'tenure_type'    => 'REGULAR',
            'sequence'       => 1,
            'total_cycles'   => 0,
            'pricing_scheme' => ['fixed_price' => ['value' => $r['price'], 'currency_code' => 'GBP']],
        ]],
        'payment_preferences' => [
            'auto_bill_outstanding'     => true,
            'setup_fee_failure_action'  => 'CONTINUE',
            'payment_failure_threshold' => 3,
        ],
    ]);
    $planId = (string) ($planResp['data']['id'] ?? '');
    if ($planId === '') {
        throw new RuntimeException("Plan creation for {$r['name']} returned no id: " . json_encode($planResp['data']));
    }
    $upd->execute([$planId, $r['code']]);
    $done[$r['name']] = $planId;
    echo "  Saved: $planId  →  plan_pricing[{$r['code']}]\n\n";
}

echo "============================================================\n";
echo "DONE. Plans created in " . strtoupper((string) $cfg['env']) . " PayPal and saved to the Pricing page:\n\n";
foreach ($done as $name => $id) echo sprintf("  %-9s %s\n", $name, $id);
echo "\nNext:\n";
echo "  1. Master Admin → Pricing — the ids above now show against each tier.\n";
echo "  2. Register the webhook in PayPal (Apps & Credentials → your LIVE app → Webhooks):\n";
echo "       URL:    " . rtrim((string) env('APP_URL', 'https://yourblinds.uk'), '/') . "/billing/paypal_webhook.php\n";
echo "       Events: BILLING.SUBSCRIPTION.ACTIVATED / CANCELLED / SUSPENDED / EXPIRED / UPDATED,\n";
echo "               PAYMENT.SALE.COMPLETED / DENIED\n";
echo "     Put the Webhook ID it gives you in .env as PAYPAL_WEBHOOK_ID.\n";
echo "  3. DELETE this file (setup_paypal_tier_plans.php) so it can't be re-run.\n";
