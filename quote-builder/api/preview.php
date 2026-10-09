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

// When a super-admin is previewing a line on a factory quote raised FOR a trade
// account, price with the account's buying discount so the live preview matches
// what gets saved. Super-admin only (read-only price probe otherwise); 0 = normal.
$forAccountId = is_super_admin() ? (int) ($_GET['account_id'] ?? 0) : 0;

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
