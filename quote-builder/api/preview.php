<?php
declare(strict_types=1);

/**
 * Live-price preview endpoint for the quote-builder line-item form.
 *
 * Wraps the pricing engine (`pe_calculate_item`) over a JSON layer.
 * Accepts free-text width/drop and parses them through ptp_parse_dimension
 * so the user can type "1500", "150cm", "1.5m", "60in", etc.
 *
 * Tenant-scoped via the logged-in user's client_id.
 *
 * GET /quote-builder/api/preview.php
 *   ?product_id=N
 *   &system_id=N         (optional)
 *   &option_id=N         (the fabric)
 *   &width=...           (free-text — mm/cm/m/inches accepted)
 *   &drop=...
 *   &quantity=N          (defaults to 1)
 *   &round_up=1          (1 to enable round-up to next cell)
 *   &extras[N][extra_id]=X
 *   &extras[N][choice_id]=Y
 *
 * Response: JSON. On success, the engine's full breakdown.
 *           On failure, {"error": "...", "stage": "input"|"engine"}.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';
require __DIR__ . '/../../_partials/pricing_engine.php';
require __DIR__ . '/../../_partials/price_table_parser.php';
require __DIR__ . '/../../_partials/units.php';
require __DIR__ . '/../../_partials/multi_fascia_pricer.php';   // qb_price_multi_fascia (shared multi-blind pricer)
require __DIR__ . '/../_preview_core.php';              // qb_preview_response (shared with the offline tablet engine)
require_once __DIR__ . '/../../_partials/instaprice_public.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Read-only price probe: served to logged-in tenants (their catalogue) AND to
// anonymous public InstaPrice visitors with ?public=1 (the showcase catalogue).
[$clientId, $ipPublic] = instaprice_api_client();
$user = current_user();   // null in public mode

// The account this quote is raised FOR, so the live preview prices the line the
// same way the save will. It is read from the stored quote, not taken from the
// request: the builder sends quote_id and the account comes out of the same
// column add_item.php / update_item.php use ($quote['account_client_id']).
//
// It used to be `is_super_admin() ? (int) $_GET['account_id'] : 0` while the
// SAVE path applied the account unconditionally, so for anyone who isn't a
// super-admin the two disagreed. The engine's paths differ materially, not
// cosmetically — $forAccountId > 0 resolves the discount with
// pe_account_trade_discount_for_master() and takes it off at step 8 — so a
// factory office login with Can-create-orders saw one price in the panel and
// a different one on the saved line, with nothing on screen explaining it.
//
// Scoped to the caller's own client_id, so this can only ever surface their own
// tenant's buying deal — which the save path applies for them regardless.
$forAccountId = 0;
$previewQuoteId = (int) ($_GET['quote_id'] ?? 0);
if ($previewQuoteId > 0 && is_array($user)) {
    try {
        $pqs = db()->prepare('SELECT account_client_id FROM quotes WHERE id = ? AND client_id = ? LIMIT 1');
        $pqs->execute([$previewQuoteId, $clientId]);
        $forAccountId = (int) ($pqs->fetchColumn() ?: 0);
    } catch (Throwable $e) { /* account_client_id not migrated — price as normal */ }
}
// No saved quote to read from (a brand-new line before the quote exists): a
// super-admin may still name the account directly, as before.
if ($forAccountId === 0 && is_super_admin()) {
    $forAccountId = (int) ($_GET['account_id'] ?? 0);
}

// Cost-viewers only: the per-line markup/discount override IS the trade
// margin, and the cost / trade-discount figures are for them alone.
$isAdmin  = is_array($user) && ($user['role'] ?? '') === 'admin';
// Public (anonymous) visitors never see the cost/margin breakdown.
$canCosts = $isAdmin || !empty(current_user_permissions()['can_view_costs']);

// `direct_order=1` zeroes BOTH the markup and the discount in _preview_core.php
// — it doesn't just tweak a figure, it IS the buying price. So it needs the
// same gate as the two overrides it sets, and it had none: $q there is $_GET,
// and this endpoint serves anonymous InstaPrice visitors (?public=1), who got
// the factory's buying price for any showcase blind by appending one parameter.
//
// Not gated on $canCosts: a trade client raising a direct order is entitled to
// the buying price (it's what they pay) without being a cost-viewer. The real
// entitlement is being able to raise one at all — new_order.php:23's rule.
$canDirect = is_array($user)
    && ($isAdmin || is_super_admin() || !empty(current_user_permissions()['can_create_orders']));
if (!$canDirect) unset($_GET['direct_order']);

// The whole answer lives in _preview_core.php, shared with the tablet's
// offline engine (offline/_device_preview.php) so both price identically.
echo json_encode(qb_preview_response(db(), $clientId, $_GET, $canCosts, (bool) $ipPublic, $forAccountId));
