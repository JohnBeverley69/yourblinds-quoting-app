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
function billing_plans(bool $refresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$refresh) return $cache;

    $registry = require __DIR__ . '/billing_plans.php';
    $registry = billing_apply_feature_layout($registry, billing_feature_layout());

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

/** app_settings key holding the drag-and-drop tier layout (Master Admin → Plan features). */
const BILLING_FEATURE_LAYOUT_KEY = 'plan_feature_tiers';

/**
 * The saved tier layout: feature flag => the LOWEST plan code that includes it
 * ('' = in no plan; switched on by hand on Master Admin). Flags missing from it
 * keep the placement in billing_plans.php. Read straight from the DB (not
 * app_setting_get's per-request cache) so a save can re-read it at once.
 */
function billing_feature_layout(): array
{
    try {
        $st = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $st->execute([BILLING_FEATURE_LAYOUT_KEY]);
        $map = json_decode((string) ($st->fetchColumn() ?: ''), true);
    } catch (Throwable $e) {
        return [];
    }
    if (!is_array($map)) return [];
    $out = [];
    foreach ($map as $flag => $code) {
        if (is_string($flag) && is_string($code)) $out[$flag] = $code;
    }
    return $out;
}

/**
 * Re-cut each plan's 'features' from a layout. A feature placed on a tier goes
 * on that tier AND every tier above it (the ladder stays cumulative); placed on
 * '' it is in no plan. Unknown flags and unknown plan codes are ignored. Once a
 * layout is saved the descriptions are rebuilt from the result, so the Billing
 * and welcome pages can never list a feature the tier doesn't give.
 */
function billing_apply_feature_layout(array $plans, array $layout): array
{
    $known = require __DIR__ . '/feature_flags.php';
    foreach ($layout as $flag => $code) {
        if (!isset($known[$flag])) continue;
        if ($code !== '' && !isset($plans[$code])) continue;
        $from = $code === '' ? PHP_INT_MAX : (int) ($plans[$code]['tier'] ?? 0);
        foreach ($plans as &$p) {
            $p['features'] = array_values(array_diff($p['features'] ?? [], [$flag]));
            if ((int) ($p['tier'] ?? 0) >= $from) $p['features'][] = $flag;
        }
        unset($p);
    }
    if ($layout) $plans = billing_describe_plans($plans);
    return $plans;
}

/** app_settings key holding where each LISTED feature sits (Master Admin → Plan features). */
const BILLING_CATALOGUE_KEY = 'plan_feature_catalogue';

/**
 * Every listed feature (plan_feature_catalogue.php) with the tier it sits on now:
 * key => ['label', 'desc', 'code'] — 'code' is the LOWEST plan that includes it,
 * or '' for "not shown on any plan". The saved board layout overrides the
 * standard tier; a saved code for a plan that no longer exists falls back.
 * These only DESCRIBE the plans — nothing is switched on or off by them.
 */
function billing_catalogue(): array
{
    $cat   = require __DIR__ . '/plan_feature_catalogue.php';
    $plans = billing_plans();
    $saved = [];
    try {
        $st = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $st->execute([BILLING_CATALOGUE_KEY]);
        $saved = json_decode((string) ($st->fetchColumn() ?: ''), true) ?: [];
    } catch (Throwable $e) { $saved = []; }
    $out = [];
    foreach ($cat as $key => [$label, $desc, $std]) {
        $code = array_key_exists($key, $saved) && is_string($saved[$key]) ? $saved[$key] : $std;
        if ($code !== '' && !isset($plans[$code])) $code = isset($plans[$std]) ? $std : '';
        $out[$key] = ['label' => $label, 'desc' => $desc, 'code' => $code];
    }
    // The order John left the boxes in on the board (unsaved ones keep their place after).
    $pos = billing_feature_order();
    $nat = array_flip(array_keys($out));
    uksort($out, static fn ($a, $b) => ($pos['c:' . $a] ?? 100000 + $nat[$a]) <=> ($pos['c:' . $b] ?? 100000 + $nat[$b]));
    return $out;
}

/** app_settings key holding the order of the boxes on the Plan features board. */
const BILLING_FEATURE_ORDER_KEY = 'plan_feature_order';

/** Box id ('f:<flag>' switch, 'c:<key>' listed) => position, from the saved board order. */
function billing_feature_order(): array
{
    try {
        $st = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $st->execute([BILLING_FEATURE_ORDER_KEY]);
        $list = json_decode((string) ($st->fetchColumn() ?: ''), true);
    } catch (Throwable $e) { return []; }
    return is_array($list) ? array_flip(array_values(array_filter($list, 'is_string'))) : [];
}

/**
 * The listed features a plan includes, in catalogue order: every one whose
 * tier is at or below this plan's (the ladder is cumulative). With $newOnly,
 * just the ones that START at this tier — for "Everything in Silver, plus…".
 *
 * @return list<array{label:string, desc:string}>
 */
function billing_plan_listed(string $planCode, bool $newOnly = false): array
{
    $plans = billing_plans();
    if (!isset($plans[$planCode])) return [];
    $tier = (int) ($plans[$planCode]['tier'] ?? 0);

    // Every box on the Plan features board — the five original switches AND the
    // listed features — so a plan card lists exactly what the board shows for
    // that tier (John, 2026-10-17: they "don't marry up" otherwise).
    $byTier = $plans;
    uasort($byTier, static fn ($a, $b) => (int) ($a['tier'] ?? 0) <=> (int) ($b['tier'] ?? 0));
    $flagLabels = require __DIR__ . '/feature_flags.php';
    $blurbs     = billing_feature_blurbs();
    $boxes = [];   // id => [label, desc, tier]
    foreach ($flagLabels as $flag => $label) {
        $at = PHP_INT_MAX;
        foreach ($byTier as $p) {
            if (in_array($flag, $p['features'] ?? [], true)) { $at = (int) ($p['tier'] ?? 0); break; }
        }
        $boxes['f:' . $flag] = [$label, ucfirst((string) ($blurbs[$flag] ?? $label)), $at];
    }
    foreach (billing_catalogue() as $key => $f) {
        $boxes['c:' . $key] = [$f['label'], $f['desc'], $f['code'] === '' ? PHP_INT_MAX : (int) ($plans[$f['code']]['tier'] ?? PHP_INT_MAX)];
    }
    // The board's saved order (unsaved boxes keep their natural place after it).
    $pos = billing_feature_order();
    $nat = array_flip(array_keys($boxes));
    uksort($boxes, static fn ($a, $b) => ($pos[$a] ?? 100000 + $nat[$a]) <=> ($pos[$b] ?? 100000 + $nat[$b]));

    $out = [];
    foreach ($boxes as [$label, $desc, $at]) {
        if ($at === PHP_INT_MAX) continue;
        if ($newOnly ? $at === $tier : $at <= $tier) $out[] = ['label' => $label, 'desc' => $desc];
    }
    return $out;
}

/** The one-line heading over a plan card's list: "Everything in Gold, plus:" (Bronze: free to start). */
function billing_plan_heading(string $planCode): string
{
    $plans = billing_plans();
    if (!isset($plans[$planCode])) return '';
    $tier = (int) ($plans[$planCode]['tier'] ?? 0);
    if ($tier === 0) return 'Free to start. Everything in the list below is included.';
    $prev = null;
    foreach ($plans as $p) {
        $t = (int) ($p['tier'] ?? 0);
        if ($t < $tier && ($prev === null || $t > (int) ($prev['tier'] ?? 0))) $prev = $p;
    }
    return 'Everything in ' . (string) ($prev['name'] ?? 'the plan below') . ', plus:';
}

/**
 * A fold-out "What's included" list for a plan card (Billing and welcome pages):
 * <details><summary>$summary (N)</summary><ul>…</ul></details>, or '' when there
 * is nothing to list. Each feature shows its one-line description on hover.
 */
function billing_listed_html(string $planCode, bool $newOnly, string $summary = "What's included", bool $open = false): string
{
    $items = billing_plan_listed($planCode, $newOnly);
    if (!$items) return '';
    $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $li = '';
    foreach ($items as $i) {
        $li .= '<li title="' . $h($i['desc']) . '"><strong>' . $h($i['label']) . '</strong>'
             . '<span class="bl-desc"> — ' . $h($i['desc']) . '</span></li>';
    }
    return '<details class="billing-listed"' . ($open ? ' open' : '') . '><summary>' . $h($summary) . ' (' . count($items) . ')</summary>'
         . '<ul>' . $li . '</ul></details>';
}

/** CSS for billing_listed_html(), once per page. */
function billing_listed_css(): string
{
    return '<style>
        .billing-listed { margin: 0.5rem 0 0.75rem; font-size: 0.875rem; }
        .billing-listed summary { cursor: pointer; font-weight: 600; color: var(--link, #2563eb); }
        .billing-listed ul { margin: 0.5rem 0 0; padding-left: 1.1rem; display: grid; gap: 0.3rem; }
        .billing-listed li { color: var(--text-body); line-height: 1.4; }
        .billing-listed .bl-desc { color: var(--text-muted); font-weight: 400; }
    </style>';
}

/** Short phrases used when a tier's description is built from its features. */
function billing_feature_blurbs(): array
{
    return [
        'feature_maps'            => 'Maps (day run planner with directions, one-tap Google Maps / Waze navigation)',
        'feature_postcode_lookup' => 'Postcode lookup',
        'feature_sms'             => 'customer texts (booking confirmations and reminders)',
        'feature_online_booking'  => 'online booking (customers book a visit from your website)',
        'feature_accounts'        => 'Accounts (payment tracking, outstanding balances and the account summary)',
    ];
}

/** "a, b and c" */
function billing_join_words(array $words): string
{
    if (count($words) <= 1) return (string) ($words[0] ?? '');
    $last = array_pop($words);
    return implode(', ', $words) . ' and ' . $last;
}

/**
 * Rebuild the descriptions from the features: Bronze keeps its core-platform
 * line (plus anything placed on it); each paid tier is "Everything in <the tier
 * below>, plus <what it adds>".
 */
function billing_describe_plans(array $plans): array
{
    $labels = require __DIR__ . '/feature_flags.php';
    $blurbs = billing_feature_blurbs();
    $phrase = static fn (string $f): string => $blurbs[$f] ?? ($labels[$f] ?? $f);

    $order = $plans;
    uasort($order, static fn ($a, $b) => (int) ($a['tier'] ?? 0) <=> (int) ($b['tier'] ?? 0));
    $prev = null;
    foreach (array_keys($order) as $code) {
        $feats = $plans[$code]['features'] ?? [];
        if ($prev === null) {
            $plans[$code]['description'] = 'Create and send quotes, plus the core platform — calendar, customers, orders and products.'
                . ($feats ? ' Also includes ' . billing_join_words(array_map($phrase, $feats)) . '.' : '')
                . ' Free to start.';
        } else {
            $adds     = array_map($phrase, array_values(array_diff($feats, $plans[$prev]['features'] ?? [])));
            if (!empty($plans[$code]['grants_factory']) && empty($plans[$prev]['grants_factory'])) $adds[] = 'the factory side';
            $prevName = (string) ($plans[$prev]['name'] ?? $prev);
            $plans[$code]['description'] = $adds
                ? 'Everything in ' . $prevName . ', plus ' . billing_join_words($adds) . '.'
                : 'Everything in ' . $prevName . '.';
        }
        $prev = $code;
    }
    return $plans;
}

/**
 * "Silver, Gold and Platinum", "Gold and Platinum", or '' when every tier
 * (Bronze up) has the feature — for marketing tags like the How-it-works tour.
 * Follows the live Plan features layout.
 */
function billing_feature_tier_names(string $flag): string
{
    $names = [];
    $all   = true;
    $plans = billing_plans();
    uasort($plans, static fn ($a, $b) => (int) ($a['tier'] ?? 0) <=> (int) ($b['tier'] ?? 0));
    foreach ($plans as $p) {
        if (in_array($flag, $p['features'] ?? [], true)) $names[] = (string) ($p['name'] ?? '');
        else $all = false;
    }
    return $all ? '' : billing_join_words($names);
}

/**
 * Which plans include a feature, for "… is part of X" upgrade messages:
 * "the Silver, Gold and Platinum plans", "the Platinum plan", "every plan", or
 * "a plan" when no tier has it (switched on by hand). Never hard-code tier
 * names in a message — the Plan features board can move any extra.
 */
function billing_feature_plans_phrase(string $flag): string
{
    $names = billing_feature_tier_names($flag);
    if ($names === '') {
        foreach (billing_plans() as $p) {
            if (!in_array($flag, $p['features'] ?? [], true)) return 'a plan';
        }
        return 'every plan';
    }
    return 'the ' . $names . (str_contains($names, ' and ') ? ' plans' : ' plan');
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
        $end   = strtotime((string) $sub['current_period_end']);
        $today = strtotime('today');
        if ($end !== false && $today !== false && $end < $today) return false;
    }
    return true;
}

/**
 * "Is the tenant still ENTITLED to this plan's features right now?" — being
 * billed (active/trial), OR cancelled but PAID THROUGH the current period
 * (standard grace: keep features until the end of the period already paid for,
 * even though billing has stopped).
 *
 * Deliberately DISTINCT from billing_subscription_is_active(), which is strictly
 * "currently billing" — that one drives the Billing page's ACTIVE badge and the
 * monthly total, so a cancelled plan must NOT count there (or the total double-
 * counts a plan that's no longer billed). Grace is feature-entitlement only.
 * PayPal fires no event when the paid period ends, so billing_reconcile_if_due()
 * flips the flags off on the next visit after it passes.
 */
function billing_subscription_grants_access(?array $sub): bool
{
    if (billing_subscription_is_active($sub)) return true;
    if (!$sub || (string) ($sub['status'] ?? '') !== 'cancelled') return false;
    if (empty($sub['current_period_end'])) return false;
    $end   = strtotime((string) $sub['current_period_end']);
    $today = strtotime('today');
    return $end !== false && $today !== false && $end >= $today;
}

/**
 * Lazy daily reconcile of a tenant's paid-feature flags. The flags live in
 * client_settings and are normally only re-synced on billing EVENTS — but a
 * cancelled plan's grace ends on a DATE with no event, so without this its
 * features would linger. Throttled to once per calendar day per session; the
 * sync is idempotent and respects comps/trials, so it's a no-op for healthy
 * tenants and never strips comp-granted access. Never breaks the page.
 */
function billing_reconcile_if_due(int $clientId): void
{
    if ($clientId <= 0) return;
    $today = date('Y-m-d');
    if (($_SESSION['_feat_reconcile'] ?? '') === $today) return;
    $_SESSION['_feat_reconcile'] = $today;
    try { billing_sync_feature_flags_force($clientId); } catch (Throwable $e) { /* never break the page */ }
}

/**
 * Is the tenant ENTITLED to this plan via its subscription right now — being
 * billed OR within the paid-through grace after a cancel? (Subscription only —
 * does NOT include comp.) Drives feature entitlement; use
 * billing_subscription_is_active() for "currently billing" (billing display).
 */
function billing_has_active_subscription_for(int $clientId, string $planCode): bool
{
    return billing_subscription_grants_access(billing_subscription_for_plan($clientId, $planCode));
}

/**
 * "Is this tenant entitled to this plan right now?" — i.e. active
 * subscription OR active comp. Use for UI gating where you don't
 * care which path granted access.
 */
function billing_plan_active_for(int $clientId, string $planCode): bool
{
    // Bronze is the baseline everyone is on — anything placed on it on
    // Master Admin → Plan features is everyone's, no subscription needed.
    if ($planCode === 'free') return true;
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
        // The tier a tenant is actually ON = currently BILLED (strict) OR
        // comped. Deliberately NOT billing_plan_active_for(), which counts the
        // paid-through grace on a cancelled plan — that retains features
        // transitionally, but the tenant's tier (and bill) is the lower one, so
        // grace must not inflate "Your plan" / the monthly total.
        $onIt = billing_subscription_is_active(billing_subscription_for_plan($clientId, $code))
             || billing_client_has_comp($clientId, $code);
        if ($onIt) {
            $best = $code;
            $bestOrder = $order;
        }
    }
    return $best;
}

/**
 * Run once a tier subscription becomes ACTIVE (from billing/return.php OR the
 * PayPal webhook — activation can be async, so both call this). It:
 *   1. Stamps the minimum-term commitment (for term plans) if unset.
 *   2. Supersedes any OTHER active tier — a tenant is on one tier at a time —
 *      cancelling its PayPal subscription and marking the local row cancelled.
 *
 * Safe to call repeatedly (idempotent). PayPal cancellation is attempted only
 * when paypal.php is loaded (both callers load it).
 */
function billing_on_tier_activated(int $clientId, string $planCode): void
{
    $pdo = db();

    // 1. Minimum-term commitment.
    $term = billing_plan_term_months($planCode);
    if ($term !== null) {
        $endDate = date('Y-m-d', strtotime('+' . $term . ' months'));
        $pdo->prepare(
            'UPDATE tenant_subscriptions SET commitment_end_at = ?
              WHERE client_id = ? AND plan_code = ? AND commitment_end_at IS NULL'
        )->execute([$endDate, $clientId, $planCode]);
    }

    // 2. Supersede any other tier still on the books.
    $others = $pdo->prepare(
        "SELECT plan_code, external_subscription_id
           FROM tenant_subscriptions
          WHERE client_id = ? AND plan_code <> ?
            AND status IN ('active', 'trial', 'past_due', 'pending')"
    );
    $others->execute([$clientId, $planCode]);
    foreach ($others->fetchAll(PDO::FETCH_ASSOC) as $o) {
        $oldId = (string) ($o['external_subscription_id'] ?? '');
        if ($oldId !== '' && function_exists('paypal_is_configured') && paypal_is_configured()) {
            try {
                paypal_request(
                    'POST',
                    '/v1/billing/subscriptions/' . rawurlencode($oldId) . '/cancel',
                    ['reason' => 'Superseded by ' . $planCode . ' tier']
                );
            } catch (Throwable $e) {
                error_log('PayPal cancel of superseded tier failed (continuing): ' . $e->getMessage());
            }
        }
        $pdo->prepare(
            "UPDATE tenant_subscriptions
                SET status = 'cancelled', cancelled_at = NOW()
              WHERE client_id = ? AND plan_code = ?"
        )->execute([$clientId, (string) $o['plan_code']]);
    }
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
    $factory = false;
    foreach (billing_plans() as $code => $plan) {
        if (!billing_plan_active_for($clientId, $code)) continue;
        foreach (($plan['features'] ?? []) as $f) $granted[$f] = true;
        if (!empty($plan['grants_factory']) && billing_factory_hold_is_real($clientId, $code)) $factory = true;
    }
    if ($factory) billing_grant_factory_side($clientId);

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
 * True if the client holds this factory-granting plan by a subscription or a
 * comp, not merely a trial. The factory side is only ever switched ON, so a
 * trial (e.g. the sign-up trial) must not be able to switch it on.
 */
function billing_factory_hold_is_real(int $clientId, string $planCode): bool
{
    if (billing_has_active_subscription_for($clientId, $planCode)) return true;
    try {
        $st = db()->prepare("SELECT COUNT(*) FROM client_plan_overrides
                               WHERE client_id = ? AND plan_code = ? AND active = 1
                                 AND override_type <> 'trial'
                                 AND (expires_at IS NULL OR expires_at >= CURDATE())");
        $st->execute([$clientId, $planCode]);
        return (int) $st->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * The Factory plan switches the account's factory side ON (clients.is_factory).
 * Only ever ON: leaving the plan does not switch it off, because that would hide
 * a working factory's floor, jobs and orders mid-production — the super-admin
 * does that deliberately on Master Admin → Factories. Logged when it happens.
 */
function billing_grant_factory_side(int $clientId): void
{
    if ($clientId <= 0) return;
    try {
        $st = db()->prepare('UPDATE clients SET is_factory = 1 WHERE id = ? AND is_factory = 0');
        $st->execute([$clientId]);
        if ($st->rowCount() > 0) {
            error_log('[YourBlinds] Factory plan: switched the factory side ON for client ' . $clientId);
        }
    } catch (Throwable $e) { /* is_factory not migrated — nothing to switch */ }
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

    // Bronze (free) features are everyone's.
    $granted = [];
    foreach ((billing_plan('free')['features'] ?? []) as $f) $granted[$f] = true;
    $factory = false;
    foreach ($compCodes as $code) {
        $p = billing_plan((string) $code);
        if ($p) foreach (($p['features'] ?? []) as $f) $granted[$f] = true;
        // A TRIAL of the Factory tier never switches the factory side on: the
        // switch is one-way, so a trial would leave a free factory behind.
        if ($p && !empty($p['grants_factory']) && billing_factory_hold_is_real($clientId, (string) $code)) $factory = true;
    }
    foreach ($subs as $sub) {
        if (!billing_subscription_grants_access($sub)) continue;   // incl. paid-through grace
        $p = billing_plan((string) $sub['plan_code']);
        if ($p) foreach (($p['features'] ?? []) as $f) $granted[$f] = true;
        if ($p && !empty($p['grants_factory'])) $factory = true;
    }
    if ($factory) billing_grant_factory_side($clientId);

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
        'pending'   => 'Activating',
        'past_due'  => 'Past due',
        'cancelled' => 'Cancelled',
        'expired'   => 'Expired',
    ];
}
