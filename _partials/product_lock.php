<?php
declare(strict_types=1);

/**
 * Factory-catalogue pricing lock.
 *
 * When a trade account orders a factory product, the factory's wholesale
 * invoice bills from the account's OWN mirrored copy of that product (grid
 * price → trade_price_per_blind, option prices → trade_amount). Trade portal
 * logins are tenant admins, so they could edit those copies — type £1 into the
 * grid, drop a fabric's band, zero an option — and cut what the factory bills
 * them. (The next catalogue push put it back, but only after the order.)
 *
 * So on a product that came from a factory catalogue, everything that feeds
 * the price is read-only for non-super-admins: price grids and their imports /
 * Adjust % / undo, systems, fabrics and bands, options/choices and their
 * prices, and the product's own pricing fields. What the account still
 * controls: its own markup and discount (per product/system — those don't
 * reduce the factory's bill, they only change what THEIR customer pays),
 * active on/off, category and sort order. Duplicating the product makes an
 * unlinked copy of their own, which they can edit freely (it's then no longer
 * a factory product, so it isn't billed by the factory).
 *
 * Super-admins (the platform owner) and the factory's own master products are
 * never locked.
 */

if (!defined('PRODUCT_LOCK_MSG')) {
    define('PRODUCT_LOCK_MSG',
        'This product comes from the factory catalogue, so its prices, options and fabrics are set by the '
        . 'factory and can\'t be changed here. You can still set your own markup and discount.');
}

/** True if the product's pricing is locked for the current user. */
function pl_product_locked(int $productId): bool
{
    static $cache = [];
    if ($productId <= 0) return false;
    if (function_exists('is_super_admin') && is_super_admin()) return false;
    if (array_key_exists($productId, $cache)) return $cache[$productId];
    try {
        $st = db()->prepare('SELECT client_id, source_client_id FROM products WHERE id = ? LIMIT 1');
        $st->execute([$productId]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return $cache[$productId] = false;   // no source columns yet
    }
    $src = (int) ($p['source_client_id'] ?? 0);
    $locked = $p
        && $src > 0
        && $src !== (int) $p['client_id']
        && function_exists('is_factory_client') && is_factory_client($src);
    return $cache[$productId] = (bool) $locked;
}

/** Product id owning a price table / system / option (fabric) / extra / choice. */
function pl_product_of(string $kind, int $id): int
{
    if ($id <= 0) return 0;
    $sql = [
        'table'  => 'SELECT product_id FROM price_tables WHERE id = ?',
        'system' => 'SELECT product_id FROM product_systems WHERE id = ?',
        'option' => 'SELECT product_id FROM product_options WHERE id = ?',
        'extra'  => 'SELECT product_id FROM product_extras WHERE id = ?',
        'choice' => 'SELECT e.product_id FROM product_extra_choices c JOIN product_extras e ON e.id = c.extra_id WHERE c.id = ?',
    ][$kind] ?? null;
    if ($sql === null) return 0;
    try {
        $st = db()->prepare($sql . ' LIMIT 1');
        $st->execute([$id]);
        return (int) ($st->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Stop a write on a locked product. For page handlers: flash + redirect to
 * $back. For JSON endpoints pass $json = true.
 */
function pl_require_unlocked(int $productId, string $back, bool $json = false): void
{
    if (!pl_product_locked($productId)) return;
    if ($json) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => PRODUCT_LOCK_MSG]);
        exit;
    }
    $_SESSION['flash_error'] = PRODUCT_LOCK_MSG;
    header('Location: ' . $back);
    exit;
}

/** pl_require_unlocked() for several products at once (bulk actions, and a
 *  posted product_id checked alongside the product its child row really has). */
function pl_require_unlocked_any(array $productIds, string $back, bool $json = false): void
{
    foreach (array_unique(array_map('intval', $productIds)) as $pid) {
        if ($pid > 0) pl_require_unlocked($pid, $back, $json);
    }
}
