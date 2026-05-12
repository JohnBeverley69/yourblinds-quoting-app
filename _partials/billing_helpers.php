<?php
declare(strict_types=1);

/**
 * Billing helpers — used by:
 *   - master-admin/subscriptions.php   (state management)
 *   - billing/index.php                (tenant-side display)
 *   - acct_feature_enabled() / sidebar (downstream feature gates,
 *                                       once they switch to reading
 *                                       through this)
 *
 * Phase 1 uses these against the tenant_subscriptions table. Phase 2
 * (PayPal) adds webhook-driven state changes that flow through the
 * same SQL the helpers read, so consumers don't change.
 */

/**
 * Return the plan registry from _partials/billing_plans.php as an
 * associative array keyed by plan_code.
 */
function billing_plans(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = require __DIR__ . '/billing_plans.php';
    }
    return $cache;
}

/**
 * Look up a plan by its code. Returns null if the code isn't known.
 */
function billing_plan(string $code): ?array
{
    $plans = billing_plans();
    return $plans[$code] ?? null;
}

/**
 * Fetch the subscription row for a tenant. Per-request cached, so
 * the page + sidebar + handler all hitting it in one render only
 * costs one DB round-trip. Returns null if the tenant has no row
 * (shouldn't happen after the migration backfill, but defensively
 * handled).
 */
function billing_subscription_for(int $clientId): ?array
{
    static $cache = [];
    if (array_key_exists($clientId, $cache)) return $cache[$clientId];
    try {
        $st = db()->prepare(
            'SELECT * FROM tenant_subscriptions WHERE client_id = ? LIMIT 1'
        );
        $st->execute([$clientId]);
        $cache[$clientId] = $st->fetch() ?: null;
    } catch (Throwable $e) {
        // Table not present yet (migration hasn't run) — fail open
        // by treating the tenant as on the free plan.
        $cache[$clientId] = null;
    }
    return $cache[$clientId];
}

/**
 * "Is this subscription in a state where features should be granted?"
 * Trial and active count; everything else doesn't. If a period_end
 * is set and in the past, treat as expired regardless of the stored
 * status — defensive against forgotten manual entries.
 */
function billing_subscription_is_active(?array $sub): bool
{
    if (!$sub) return false;
    $status = (string) ($sub['status'] ?? '');
    if (!in_array($status, ['trial', 'active'], true)) return false;
    if (!empty($sub['current_period_end'])) {
        $end = strtotime((string) $sub['current_period_end']);
        $today = strtotime('today');
        if ($end !== false && $today !== false && $end < $today) return false;
    }
    return true;
}

/**
 * "Does this tenant's active subscription grant this feature flag?"
 * Used by feature gates that need to short-circuit if billing has
 * lapsed (e.g. acct_feature_enabled).
 */
function billing_feature_active_for(int $clientId, string $featureFlag): bool
{
    $sub = billing_subscription_for($clientId);
    if (!billing_subscription_is_active($sub)) return false;
    $plan = billing_plan((string) $sub['plan_code']);
    return $plan && in_array($featureFlag, $plan['features'] ?? [], true);
}

/**
 * Sync the legacy feature flags on client_settings to match the
 * tenant's current subscription. Called when an admin saves a
 * subscription change in master-admin so feature_accounts (and any
 * future paid flag) lines up with what the plan grants. Idempotent.
 *
 * Only touches flags listed in $managed (currently feature_accounts).
 * Manual paid flags that aren't yet plan-managed (feature_maps,
 * feature_postcode_lookup as of writing) are left alone — admins
 * still tick those by hand on the master-admin index until they
 * become plans of their own.
 */
function billing_sync_feature_flags(int $clientId): void
{
    $sub = billing_subscription_for($clientId);
    $active = billing_subscription_is_active($sub);
    $plan = $sub ? billing_plan((string) $sub['plan_code']) : null;
    $granted = ($active && $plan) ? ($plan['features'] ?? []) : [];

    $managed = ['feature_accounts'];   // expand as new plans add flags

    $pdo = db();
    foreach ($managed as $flag) {
        $shouldBe = in_array($flag, $granted, true) ? 1 : 0;
        try {
            $st = $pdo->prepare(
                "UPDATE client_settings SET $flag = ? WHERE client_id = ?"
            );
            $st->execute([$shouldBe, $clientId]);
        } catch (Throwable $e) {
            // Column missing (e.g. feature_accounts on a DB that
            // hasn't run migrate_feature_accounts) — skip silently.
        }
    }
    // Caller is the master-admin save handler which redirects after
    // this returns, so the stale per-request cache in
    // billing_subscription_for doesn't bite anyone — the next page
    // load re-runs from scratch.
}

/**
 * Display labels for the status ENUM, in canonical order.
 */
function billing_status_labels(): array
{
    return [
        'trial'     => 'Trial',
        'active'    => 'Active',
        'past_due'  => 'Past due',
        'cancelled' => 'Cancelled',
        'expired'   => 'Expired',
    ];
}
