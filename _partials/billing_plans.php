<?php
declare(strict_types=1);

/**
 * Single source of truth for billing plans.
 *
 * Each plan has:
 *   name          — short label shown to users.
 *   description   — one-line summary for the Billing page.
 *   price_gbp_monthly — DEFAULT, overridden by the plan_pricing DB
 *                       table once migrate_plan_pricing.php has run.
 *                       The DB row is what /master-admin/pricing.php
 *                       edits.
 *   features      — array of feature-flag column names (matches
 *                   _partials/feature_flags.php keys). When a tenant
 *                   has an active subscription (or comp) on this plan,
 *                   those flags are set to 1. When the subscription
 *                   lapses and there's no comp they're set back to 0.
 *
 * Tenants can subscribe to any number of add-on plans independently —
 * one PayPal subscription per plan. The 'free' plan is the implicit
 * baseline and is always granted; it doesn't need a subscription.
 *
 * To add a new paid plan:
 *   1. Add an entry here.
 *   2. (Optional) Add new feature flag columns via SQL + the
 *      _partials/feature_flags.php registry.
 *   3. Re-run /migrate_plan_pricing.php so the plan_pricing table
 *      picks up the new code with its registry default price.
 *   4. From /master-admin/pricing.php, click "Create on PayPal" to
 *      create the matching PayPal Product + Plan. That's it — the
 *      add-on becomes subscribable from the tenant Billing page
 *      immediately.
 */

return [
    'free' => [
        'name'              => 'Free',
        'description'       => 'Calendar, quotes, customers, orders, products — the core platform.',
        'price_gbp_monthly' => 0,
        'features'          => [],
    ],
    'maps' => [
        'name'              => 'Maps add-on',
        'description'       => 'Drag-to-reorder run optimiser, customer-pin map view, "Let\'s go" link to Google Maps for each appointment.',
        'price_gbp_monthly' => 3,
        'features'          => ['feature_maps'],
    ],
    'postcode_lookup' => [
        'name'              => 'Postcode lookup add-on',
        'description'       => 'Auto-fill address from postcode when adding customers and creating quotes — fewer typos, faster data entry.',
        'price_gbp_monthly' => 2,
        'features'          => ['feature_postcode_lookup'],
    ],
    'accounts' => [
        'name'              => 'Accounts add-on',
        'description'       => 'Payment tracking — record balance payments at install, see outstanding per order, account summary dashboard.',
        'price_gbp_monthly' => 10,
        'features'          => ['feature_accounts'],
    ],
];
