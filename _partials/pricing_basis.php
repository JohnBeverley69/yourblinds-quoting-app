<?php
declare(strict_types=1);

/**
 * Margin ⇄ markup presentation helpers.
 *
 * The pricing ENGINE always works in MARKUP:
 *     sell = cost × (1 + markup/100)
 * This file changes only how a tenant ENTERS and SEES that number. A tenant
 * on the 'margin' basis types a margin %; we convert it to the equivalent
 * markup BEFORE anything is stored, and convert markup back to margin for
 * display. Every stored column and every price stays markup — so nothing in
 * the live pricing math can change, and switching basis is lossless.
 *
 *   markup → margin :  margin = markup / (1 + markup/100) = markup·100 / (100 + markup)
 *   margin → markup :  markup = margin / (1 − margin/100) = margin·100 / (100 − margin)
 *
 * A margin must be below 100% (a 100% margin would be infinite markup — a
 * zero-cost item). We clamp on the way in so we never divide by zero.
 *
 * The same two formulas live in JS in any client-side pricing surface
 * (InstaPrice) — keep them in lockstep with this file.
 */

if (!function_exists('pricing_basis_for')) {

/** 'markup' (default) or 'margin' for a tenant. Never throws; pre-migration → 'markup'. */
function pricing_basis_for(PDO $pdo, int $clientId): string
{
    static $cache = [];
    if (array_key_exists($clientId, $cache)) return $cache[$clientId];
    $basis = 'markup';
    try {
        $st = $pdo->prepare(
            'SELECT pricing_basis FROM client_settings WHERE client_id = ? LIMIT 1'
        );
        $st->execute([$clientId]);
        if ((string) $st->fetchColumn() === 'margin') $basis = 'margin';
    } catch (Throwable $e) {
        $basis = 'markup'; // column not migrated yet → current behaviour
    }
    return $cache[$clientId] = $basis;
}

/** "Margin" or "Markup" — for field labels / column headings. */
function pricing_basis_label(string $basis): string
{
    return $basis === 'margin' ? 'Margin' : 'Markup';
}

/** Stored MARKUP % → the number to SHOW the tenant for their basis. */
function markup_to_display(float $markup, string $basis): float
{
    if ($basis !== 'margin' || $markup <= 0) return max(0.0, $markup);
    return $markup * 100.0 / (100.0 + $markup);
}

/** A tenant-ENTERED number (margin or markup) → the MARKUP % to store. */
function display_to_markup(float $input, string $basis): float
{
    if ($basis !== 'margin' || $input <= 0) return max(0.0, $input);
    $m = min($input, 99.99); // a margin must stay under 100%
    return $m * 100.0 / (100.0 - $m);
}

} // function_exists guard
