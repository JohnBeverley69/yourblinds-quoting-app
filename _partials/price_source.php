<?php
declare(strict_types=1);

/**
 * Where a product's price table came from — the one fact that says how to read
 * the numbers in it.
 *
 *   'own'      Our price list.      We manufacture it. The grid IS our selling
 *                                   (trade) price; a parallel cost grid holds
 *                                   what it costs to make. Selling is cost + a
 *                                   percentage + labour + overhead, so it can't
 *                                   be re-derived — which is why the grid is
 *                                   entered rather than calculated, and why the
 *                                   catalogue push must send it UNCHANGED.
 *
 *   'supplier' Supplier price list. We buy it in. The grid is THEIR standard
 *                                   trade list. Ours = list − our buying
 *                                   discount + our margin, so the push applies
 *                                   (1 - discount) x (1 + markup).
 *
 * Anything unknown reads as 'own': the safe default, because it pushes the
 * table through untouched rather than silently moving a tenant's prices.
 */

const PRICE_SOURCE_OWN      = 'own';
const PRICE_SOURCE_SUPPLIER = 'supplier';

/** Normalise any stored/posted value to one of the two known sources. */
function ps_normalise(?string $v): string
{
    return strtolower((string) $v) === PRICE_SOURCE_SUPPLIER ? PRICE_SOURCE_SUPPLIER : PRICE_SOURCE_OWN;
}

/** John's wording, for every screen that shows this. */
function ps_label(string $source): string
{
    return ps_normalise($source) === PRICE_SOURCE_SUPPLIER ? 'Supplier price list' : 'Our price list';
}

/** What the numbers in the grid actually are, in one line. */
function ps_hint(string $source): string
{
    return ps_normalise($source) === PRICE_SOURCE_SUPPLIER
        ? 'These are the supplier\'s list prices. Our price = list − buying discount + margin.'
        : 'These are our selling prices. Pushed to trade accounts exactly as they are.';
}

/**
 * A product's price source. Schema-tolerant: before the migration (or if the
 * product has gone) it reads 'own', which is the no-op for the push.
 * Cached per request — the push asks for the same product repeatedly.
 */
function ps_for_product(PDO $pdo, int $productId): string
{
    static $cache = [];
    if (isset($cache[$productId])) return $cache[$productId];
    try {
        $st = $pdo->prepare('SELECT price_source FROM products WHERE id = ? LIMIT 1');
        $st->execute([$productId]);
        $v = $st->fetchColumn();
        return $cache[$productId] = ps_normalise($v === false ? null : (string) $v);
    } catch (Throwable $e) {
        return $cache[$productId] = PRICE_SOURCE_OWN;   // column not migrated yet
    }
}

/** True when the push must convert this product's grid into our trade price. */
function ps_needs_trade_factor(PDO $pdo, int $productId): bool
{
    return ps_for_product($pdo, $productId) === PRICE_SOURCE_SUPPLIER;
}
