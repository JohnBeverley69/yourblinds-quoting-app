<?php
declare(strict_types=1);

/**
 * Billing helpers — used by:
 *   - master-admin/subscriptions.php   (state management)
 *   - master-admin/pricing.php         (price + PayPal-plan-id editing)
 *   - billing/index.php                (tenant-side display)
 *   - billing/subscribe.php            (plan id lookup at PayPal time)
 *   - acct_feature_enabled() / sidebar (downstream feature gates)
 *
 * Model overview
 * --------------
 * - billing_plans.php (PHP file) defines plan STRUCTURE: name,
 *   description, granted feature columns. Ships with the code.
 *
 * - plan_pricing (DB) holds the live price + PayPal Plan ID per plan
 *   code. Editable from /master-admin/pricing.php.
 *
 * - tenant_subscriptions (DB) is per-(tenant, plan) — one row per
 *   PayPal subscription. A tenant can be subscribed to multiple
 *   add-on plans simultaneously (Maps + Postcode + Accounts is three
 *   PayPal subs, three rows).
 *
 * - client_plan_overrides (DB) is per-(tenant, plan) for comps —
 *   admin-granted free access independent of PayPal.
 *
 * A tenant gets a feature if EITHER:
 *   (a) they have an active subscription to a plan that grants it, OR
 *   (b) they have an active comp on a plan that grants it.
 */

/**
 * Return the plan registry merged with live pricing.
 *
 * Source of name/description/features = _partials/billing_plans.php.
 * Source of price + paypal_plan_id    = plan_pricing table (with the
 *                                       registry value as fallback if
 *                                       the table hasn't been seeded
 *                                       for this plan yet).
 *
 * Cached per-request.
 */
function billing_plans(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $registry = require __DIR__ . '/billing_plans.php';

    $live = [];
    try {
        $rows = db()->query(
            'SELECT plan_code, price_gbp_monthly, paypal_plan_id
               FROM plan_pricing'
        )->fetchAll();
        foreach ($rows as $r) {
            $live[(string) $r['plan_code']] = [
                'price_gbp_monthly' => (float) $r['price_gbp_monthly'],
                'paypal_plan_id'    => (string) ($r['paypal_plan_id'] ?? ''),
            ];
        }
    } catch (Throwable $e) {
        $live = [];
    }

    foreach ($registry as $code => &$plan) {
        if (isset($live[$code])) {
            $plan['price_gbp_monthly'] = $live[$code]['price_gbp_monthly'];
            $plan['paypal_plan_id']    = $live[$code]['paypal_plan_id'];
        } else {
            $plan['paypal_plan_id'] = '';
        }
    }
    unset($plan);

    $cache = $registry;
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
 * The paid plans (everything except 'free'). Useful for iteration
 * over "things tenants might subscribe to."
 */
function billing_paid_plans(): array
{
    $out = [];
    foreach (billing_plans() as $code => $p) {
        if ($code === 'free') continue;
        $out[$code] = $p;
    }
    return $out;
}

/**
 * Live price for a plan, in GBP/month.
 */
function billing_plan_price(string $code): float
{
    $p = billing_plan($code);
    return $p ? (float) ($p['price_gbp_monthly'] ?? 0) : 0.0;
}

/**
 * PayPal Plan ID for a plan code. Reads from plan_pricing first; for
 * the legacy 'accounts' plan falls back to the PAYPAL_PLAN_ACCOUNTS
 * env var so a half-migrated install keeps working.
 */
function billing_paypal_plan_id(string $code): string
{
    $p = billing_plan($code);
    $id = $p ? (string) ($p['paypal_plan_id'] ?? '') : '';
    if ($id === '' && $code === 'accounts') {
        $id = (string) (env('PAYPAL_PLAN_ACCOUNTS', '') ?? '');
    }
    return $id;
}

/**
 * Map of clientId => planCode => override-row-shape for currently-
 * active comps + trials. Cached per-request.
 *
 *   billing_comp_map()[42]['accounts'] = [
 *       'type'        => 'comp' | 'trial',
 *       'expires_at'  => 'YYYY-MM-DD' or null,
 *       'days_left'   => int | null     (only for trials)
 *   ]
 *
 * Both override types are treated equivalently as "free access" while
 * within their validity window. The difference is purely informational
 * — trials surface a countdown on the tenant Billing page and an
 * expiry column on the admin Pricing page. The SQL filter excludes any
 * trial whose expires_at is in the past, so the helper returns the
 * truth: who actually has access right now.
 */
function billing_comp_map(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    try {
        // The expires_at column may not exist yet (migrate_trials.php
        // not run); fall back to a simpler query in that case.
        $rows = db()->query(
            "SELECT client_id, plan_code, override_type, expires_at
               FROM client_plan_overrides
              WHERE active = 1
                AND (expires_at IS NULL OR expires_at >= CURDATE())"
        )->fetchAll();
    } catch (Throwable $e) {
        try {
            $rows = db()->query(
                "SELECT client_id, plan_code,
                        'comp'   AS override_type,
                        NULL     AS expires_at
                   FROM client_plan_overrides
                  WHERE active = 1"
            )->fetchAll();
        } catch (Throwable $e2) {
            $rows = [];
        }
    }
    foreach ($rows as $r) {
        $cid    = (int)    $r['client_id'];
        $code   = (string) $r['plan_code'];
        $type   = (string) ($r['override_type'] ?? 'comp');
        $expiry = $r['expires_at'] ?? null;
        $daysLeft = null;
        if ($type === 'trial' && $expiry) {
            $t = strtotime((string) $expiry);
            $today = strtotime('today');
            if ($t !== false && $today !== false) {
                $daysLeft = (int) ceil(($t - $today) / 86400);
            }
        }
        $cache[$cid][$code] = [
            'type'       => $type,
            'expires_at' => $expiry,
            'days_left'  => $daysLeft,
        ];
    }
    return $cache;
}

/**
 * Does this client have a free-access override (comp OR active trial)
 * for this plan right now?
 */
function billing_client_has_comp(int $clientId, string $planCode): bool
{
    $map = billing_comp_map();
    return !empty($map[$clientId][$planCode]);
}

/**
 * Trial info for this (client, plan), or null if no active trial.
 * Returns ['type' => 'trial', 'expires_at' => 'YYYY-MM-DD', 'days_left' => int]
 *
 * A 'comp' (free forever) returns null here — comps and trials are
 * distinguished so the UI can show a countdown vs. a permanent badge.
 */
function billing_client_trial_for(int $clientId, string $planCode): ?array
{
    $entry = billing_comp_map()[$clientId][$planCode] ?? null;
    if (!$entry) return null;
    return ($entry['type'] ?? 'comp') === 'trial' ? $entry : null;
}

/**
 * All subscription rows for a tenant, keyed by plan_code.
 * Cached per-request.
 *
 *   billing_subscriptions_for(42) =>
 *     [
 *       'accounts'        => ['status' => 'active', ...],
 *       'maps'            => ['status' => 'cancelled', ...],
 *       // 'postcode_lookup' missing if they never subscribed
 *     ]
 */
function billing_subscriptions_for(int $clientId): array
{
    static $cache = [];
    if (array_key_exists($clientId, $cache)) return $cache[$clientId];
    $out = [];
    try {
        $st = db()->prepare('SELECT * FROM tenant_subscriptions WHERE client_id = ?');
        $st->execute([$clientId]);
        foreach ($st->fetchAll() as $row) {
            $out[(string) $row['plan_code']] = $row;
        }
    } catch (Throwable $e) {
        $out = [];
    }
    $cache[$clientId] = $out;
    return $out;
}

/**
 * Subscription row for one (tenant, plan) pair, or null.
 */
function billing_subscription_for_plan(int $clientId, string $planCode): ?array
{
    return billing_subscriptions_for($clientId)[$planCode] ?? null;
}

/**
 * LEGACY shim. Kept so any caller still expecting "the tenant's one
 * subscription" doesn't break. Picks the 'accounts' row if present
 * (where it was historically anchored), else the first active row,
 * else any row, else null.
 *
 * New code should call billing_subscription_for_plan() with an
 * explicit plan_code.
 */
function billing_subscription_for(int $clientId): ?array
{
    $all = billing_subscriptions_for($clientId);
    if (isset($all['accounts'])) return $all['accounts'];
    foreach ($all as $row) {
        if (billing_subscription_is_active($row)) return $row;
    }
    return $all ? array_values($all)[0] : null;
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
 * Does this tenant currently have an active subscription for this
 * specific plan? (Subscription only — does NOT include comp.)
 */
function billing_has_active_subscription_for(int $clientId, string $planCode): bool
{
    return billing_subscription_is_active(billing_subscription_for_plan($clientId, $planCode));
}

/**
 * "Is this tenant entitled to this plan right now?" — i.e. active
 * subscription OR active comp. Use for UI gating where you don't
 * care which path granted access.
 */
function billing_plan_active_for(int $clientId, string $planCode): bool
{
    return billing_has_active_subscription_for($clientId, $planCode)
        || billing_client_has_comp($clientId, $planCode);
}

/** Ladder position of a plan (0 = Bronze/free). */
function billing_tier_order(string $planCode): int
{
    return (int) (billing_plan($planCode)['tier'] ?? 0);
}

/** Minimum-term length of a plan in months, or null if no contract. */
function billing_plan_term_months(string $planCode): ?int
{
    $t = billing_plan($planCode)['term_months'] ?? null;
    return $t === null ? null : (int) $t;
}

/**
 * The tenant's current tier code — the HIGHEST-ladder plan that's active for
 * them (by subscription or comp). 'free' (Bronze) if nothing paid is active.
 */
function billing_current_tier_code(int $clientId): string
{
    $best = 'free';
    $bestOrder = 0;
    foreach (billing_plans() as $code => $plan) {
        $order = (int) ($plan['tier'] ?? 0);
        if ($order <= $bestOrder) continue;
        if (billing_plan_active_for($clientId, $code)) {
            $best = $code;
            $bestOrder = $order;
        }
    }
    return $best;
}

/**
 * Is this tenant inside a minimum-term commitment for this plan? (i.e. a
 * term plan with commitment_end_at today or later — can't cancel in-app yet.)
 * Returns the commitment end date (Y-m-d) if so, else null.
 */
function billing_commitment_end(int $clientId, string $planCode): ?string
{
    if (billing_plan_term_months($planCode) === null) return null;
    $sub = billing_subscription_for_plan($clientId, $planCode);
    $end = $sub ? (string) ($sub['commitment_end_at'] ?? '') : '';
    if ($end === '') return null;
    $endTs = strtotime($end);
    $today = strtotime('today');
    if ($endTs === false || $today === false) return null;
    return $endTs >= $today ? substr($end, 0, 10) : null;
}

/**
 * "Does this tenant get this feature flag right now?"
 *
 * Returns true if ANY plan that grants the flag is active (by sub or
 * comp) for the tenant.
 */
function billing_feature_active_for(int $clientId, string $featureFlag): bool
{
    foreach (billing_plans() as $code => $plan) {
        if (!in_array($featureFlag, $plan['features'] ?? [], true)) continue;
        if (billing_plan_active_for($clientId, $code)) return true;
    }
    return false;
}

/**
 * Sync the legacy feature flags on client_settings to match the
 * tenant's current entitlements (subscriptions ∪ comps). Called from
 * subscribe-return, cancel, webhook events, and the master-admin
 * pages when state changes.
 *
 * Idempotent.
 */
function billing_sync_feature_flags(int $clientId): void
{
    // Build the set of feature flags this tenant is currently
    // entitled to, across all active subs + comps.
    $granted = [];
    foreach (billing_plans() as $code => $plan) {
        if (!billing_plan_active_for($clientId, $code)) continue;
        foreach (($plan['features'] ?? []) as $f) $granted[$f] = true;
    }

    // Managed flags = every distinct feature listed in the registry.
    $managed = [];
    foreach (billing_plans() as $plan) {
        foreach (($plan['features'] ?? []) as $f) $managed[$f] = true;
    }

    $pdo = db();
    foreach (array_keys($managed) as $flag) {
        $shouldBe = isset($granted[$flag]) ? 1 : 0;
        try {
            $st = $pdo->prepare(
                "UPDATE client_settings SET $flag = ? WHERE client_id = ?"
            );
            $st->execute([$shouldBe, $clientId]);
        } catch (Throwable $e) {
            // Column missing — skip silently.
        }
    }
}

/**
 * Same as billing_sync_feature_flags() but bypasses the per-request
 * cache by re-reading subs + comps directly from the DB. Use this
 * inside a single POST handler that's just inserted/deleted a comp
 * or subscription row — otherwise the cache would return the pre-
 * mutation state.
 */
function billing_sync_feature_flags_force(int $clientId): void
{
    $pdo = db();

    // Fresh comps + active trials for this client. Trials past their
    // expires_at are excluded so the flags accurately reflect "right
    // now". expires_at may not exist on older schemas — fall back.
    try {
        $st = $pdo->prepare(
            "SELECT plan_code FROM client_plan_overrides
              WHERE client_id = ? AND active = 1
                AND (expires_at IS NULL OR expires_at >= CURDATE())"
        );
        $st->execute([$clientId]);
        $compCodes = $st->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        $st = $pdo->prepare(
            "SELECT plan_code FROM client_plan_overrides
              WHERE client_id = ? AND active = 1"
        );
        $st->execute([$clientId]);
        $compCodes = $st->fetchAll(PDO::FETCH_COLUMN);
    }

    // Fresh subs for this client.
    $st = $pdo->prepare(
        'SELECT plan_code, status, current_period_end
           FROM tenant_subscriptions WHERE client_id = ?'
    );
    $st->execute([$clientId]);
    $subs = $st->fetchAll();

    $granted = [];
    foreach ($compCodes as $code) {
        $p = billing_plan((string) $code);
        if ($p) foreach (($p['features'] ?? []) as $f) $granted[$f] = true;
    }
    foreach ($subs as $sub) {
        if (!billing_subscription_is_active($sub)) continue;
        $p = billing_plan((string) $sub['plan_code']);
        if ($p) foreach (($p['features'] ?? []) as $f) $granted[$f] = true;
    }

    $managed = [];
    foreach (billing_plans() as $plan) {
        foreach (($plan['features'] ?? []) as $f) $managed[$f] = true;
    }

    foreach (array_keys($managed) as $flag) {
        try {
            $pdo->prepare("UPDATE client_settings SET $flag = ? WHERE client_id = ?")
                ->execute([isset($granted[$flag]) ? 1 : 0, $clientId]);
        } catch (Throwable $e) { /* column missing — ignore */ }
    }
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
