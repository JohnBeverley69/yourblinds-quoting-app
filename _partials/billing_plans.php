<?php
declare(strict_types=1);

/**
 * Single source of truth for billing plans — a TIER LADDER.
 *
 * Bronze (free) → Silver → Gold → Platinum. Each tier is CUMULATIVE: it lists
 * every feature flag it includes, so a Gold subscriber automatically gets
 * Silver's features too (the grant engine in billing_helpers.php switches on a
 * flag if ANY active plan lists it — so listing them on each tier "just works").
 *
 * A tenant is on exactly ONE paid tier at a time. Switching tier creates a new
 * PayPal subscription and the old tier's subscription is cancelled on activation
 * (see billing/return.php). The 'free' plan is the implicit baseline, always
 * granted, no subscription needed.
 *
 * Each plan has:
 *   name              — short label.
 *   description       — one-line summary for the Billing page.
 *   price_gbp_monthly — DEFAULT; the live figure is plan_pricing.price_gbp_monthly
 *                       (edited on /master-admin/pricing.php).
 *   features          — feature-flag column names this tier grants (cumulative).
 *   tier              — ladder position (0 = Bronze/free, higher = more).
 *   term_months       — minimum contract length in months (null = none). Platinum
 *                       is a 12-month contract billed monthly: the PayPal plan is
 *                       an ordinary monthly plan, and the commitment is enforced
 *                       app-side (commitment_end_at + a cancel guard).
 *
 * To change a tier's price: edit it on /master-admin/pricing.php, then click
 * "Create on PayPal" (or update the PayPal plan). To add a tier: add an entry
 * here, run /migrate_billing_tiers.php to seed its price, create its PayPal plan.
 */

return [
    'free' => [
        'name'              => 'Bronze',
        'description'       => 'Create and send quotes, plus the core platform — calendar, customers, orders and products. Free, forever.',
        'price_gbp_monthly' => 0,
        'features'          => [],
        'tier'              => 0,
        'term_months'       => null,
    ],
    'silver' => [
        'name'              => 'Silver',
        'description'       => 'Everything in Bronze, plus Maps (run optimiser, customer-pin map, "Let\'s go" links) and Postcode lookup.',
        'price_gbp_monthly' => 20,
        'features'          => ['feature_maps', 'feature_postcode_lookup'],
        'tier'              => 1,
        'term_months'       => null,
    ],
    'gold' => [
        'name'              => 'Gold',
        'description'       => 'Everything in Silver, plus Accounts — payment tracking, outstanding balances and the account summary.',
        'price_gbp_monthly' => 40,
        'features'          => ['feature_maps', 'feature_postcode_lookup', 'feature_accounts'],
        'tier'              => 2,
        'term_months'       => null,
    ],
    'platinum' => [
        'name'              => 'Platinum',
        'description'       => 'Everything in Gold, plus Price updates — the Supplier Price-List Library. 12-month contract, billed monthly.',
        'price_gbp_monthly' => 60,
        'features'          => ['feature_maps', 'feature_postcode_lookup', 'feature_accounts', 'feature_price_library'],
        'tier'              => 3,
        'term_months'       => 12,
    ],
];
