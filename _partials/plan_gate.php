<?php
declare(strict_types=1);

/**
 * Plan features as REAL switches (John, 2026-10-17): "make them real switches".
 *
 * Every LISTED feature (plan_feature_catalogue.php) sits on a tier on Master
 * Admin → Plan features. Once "Enforce listed features" is switched on there, a
 * company below that tier can't use it: its menu item hides and its page says
 * "<Feature> is part of the Gold plan" with a link to Billing.
 *
 * Rules:
 *  - OFF until John switches it on (app_settings[plan_features_enforced]), so the
 *    trade accounts can be put on the right plans first. Off = everything allowed.
 *  - Never locked: the main factory (Beverley) and the super-admin.
 *  - Factory-tier features stay governed by the factory side (is_factory), which
 *    the Factory plan switches on; they are not re-checked here.
 *  - A feature on no plan ('') or on Bronze is open to everyone.
 *  - Access counts comps, trials and paid-through grace (billing_plan_active_for),
 *    exactly like the ⚡ switches.
 *  - Locks stop NEW use. What a company already has keeps working: existing
 *    staff and fitters can still sign in; their quotes, payments and history stay.
 */

require_once __DIR__ . '/app_settings.php';
require_once __DIR__ . '/billing_helpers.php';

const PLAN_ENFORCE_KEY = 'plan_features_enforced';

function plan_enforcing(): bool
{
    // Read straight from the DB (not app_setting_get's per-request cache), so a
    // switch flipped earlier in the same request is seen at once.
    try {
        $st = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $st->execute([PLAN_ENFORCE_KEY]);
        return (string) $st->fetchColumn() === '1';
    } catch (Throwable $e) {
        return false;   // no settings table — nothing enforced
    }
}

/** May this company use a listed feature right now? */
function plan_feature_allowed(int $clientId, string $key): bool
{
    if (!plan_enforcing()) return true;
    if (function_exists('is_super_admin') && is_super_admin()) return true;
    if (function_exists('factory_client_id') && $clientId === (int) factory_client_id()) return true;

    $cat = billing_catalogue();
    if (!isset($cat[$key])) return true;
    $code = $cat[$key]['code'];
    if ($code === '' || $code === 'free' || $code === 'factory') return true;

    $plans = billing_plans();
    $need  = (int) ($plans[$code]['tier'] ?? 0);
    foreach ($plans as $pc => $p) {
        if ((int) ($p['tier'] ?? 0) < $need) continue;
        if (billing_plan_active_for($clientId, (string) $pc)) return true;
    }
    return false;
}

/** "Sales pipeline is part of the Gold plan." (the lowest plan that has it) */
function plan_feature_message(string $key): string
{
    $cat   = billing_catalogue();
    $plans = billing_plans();
    $label = (string) ($cat[$key]['label'] ?? 'This feature');
    $name  = (string) ($plans[$cat[$key]['code'] ?? '']['name'] ?? 'a higher');
    return $label . ' is part of the ' . $name . ' plan.';
}

/**
 * Top of a page: stop here when the company's plan doesn't include $key.
 * $fallback (a URL) sends them somewhere useful instead of the notice page —
 * e.g. the Dashboard, which is where people land after signing in.
 */
function plan_require_feature(int $clientId, string $key, ?string $fallback = null): void
{
    if (plan_feature_allowed($clientId, $key)) return;
    if ($fallback !== null) {
        $_SESSION['flash_error'] = plan_feature_message($key) . ' Upgrade on the Billing page to use it.';
        header('Location: ' . $fallback);
        exit;
    }
    $msg = plan_feature_message($key);
    // Billing is admin-only (requireAdmin), so a non-admin is sent to the public
    // plans list instead — never to a page that would say 403 (John, 2026-10-17).
    $u = function_exists('current_user') ? current_user() : null;
    $isAdmin = is_array($u) && ($u['role'] ?? '') === 'admin';
    $how  = $isAdmin
        ? 'You can upgrade on the <strong>Billing</strong> page, and it switches on straight away.'
        : 'Ask your company\'s admin to upgrade on the <strong>Billing</strong> page; it switches on straight away.';
    $href = $isAdmin ? '/billing/index.php' : '/welcome.php#plans';
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Upgrade to use this</title>'
       . '<body style="font-family:system-ui,sans-serif;max-width:560px;margin:4rem auto;padding:0 16px;color:#1f2937">'
       . '<h1 style="font-size:1.35rem">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</h1>'
       . '<p style="color:#4b5563;line-height:1.5">Your company\'s plan doesn\'t include it yet. ' . $how . '</p>'
       . '<p><a href="' . $href . '" style="display:inline-block;background:#1f3b5b;color:#fff;padding:.6rem 1rem;border-radius:8px;text-decoration:none;font-weight:600">See the plans</a>'
       . ' &nbsp; <a href="javascript:history.back()">Go back</a></p></body>';
    exit;
}

/**
 * "Trade catalogue": is this product the company's copy of a FACTORY's product
 * (pushed to them, source_client_id = that factory)? Their own products and
 * duplicates have no factory source and are never caught by this.
 */
function plan_is_trade_catalogue_product(int $clientId, int $productId): bool
{
    if ($productId <= 0) return false;
    try {
        $st = db()->prepare('SELECT COALESCE(source_client_id, 0) FROM products WHERE id = ? AND client_id = ? LIMIT 1');
        $st->execute([$productId, $clientId]);
        $src = (int) $st->fetchColumn();
        return $src > 0 && $src !== $clientId;
    } catch (Throwable $e) {
        return false;   // no source marker column — nothing to tell apart
    }
}

/** For JSON / AJAX endpoints: answer with an error instead of a page. */
function plan_require_feature_json(int $clientId, string $key): void
{
    if (plan_feature_allowed($clientId, $key)) return;
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => plan_feature_message($key) . ' Upgrade on the Billing page to use it.']);
    exit;
}
