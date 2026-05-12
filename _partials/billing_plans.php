<?php
declare(strict_types=1);

/**
 * Single source of truth for billing plans.
 *
 * Each plan has:
 *   name          — short label shown to users.
 *   description   — one-line summary for the Billing page.
 *   price_gbp_monthly — display only; PayPal is the actual biller
 *                       once Phase 2 lands.
 *   features      — array of feature-flag column names (matches
 *                   _partials/feature_flags.php keys). When a tenant
 *                   has an active subscription on this plan, those
 *                   flags are set to 1. When the subscription lapses
 *                   they're set back to 0.
 *
 * To add a new paid plan:
 *   1. Add an entry here.
 *   2. (Optional) Add new feature flag columns via SQL + the
 *      _partials/feature_flags.php registry.
 *   3. PayPal subscription plan ID gets attached in Phase 2 — kept
 *      out of this file so the same plan definition works in
 *      sandbox AND live without code changes.
 */

return [
    'free' => [
        'name'              => 'Free',
        'description'       => 'Calendar, quotes, customers, orders, products — the core platform.',
        'price_gbp_monthly' => 0,
        'features'          => [],
    ],
    'accounts' => [
        'name'              => 'Accounts add-on',
        'description'       => 'Everything in Free plus payment tracking — record balance payments at install, see outstanding per order, account summary dashboard.',
        'price_gbp_monthly' => 10,
        'features'          => ['feature_accounts'],
    ],
];
