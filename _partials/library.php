<?php
declare(strict_types=1);

/**
 * Supplier price-list library — shared helpers (client side).
 *
 * The "library" is a curated set of supplier catalogues held on the master
 * (super-admin) tenant. A client enables the suppliers they use and those
 * suppliers' products are copied into their own account (with their own markup
 * preserved) by the existing push engine.
 *
 * v1 ships with one supplier — "Beverley Blinds Trade" — which is FREE to every
 * tenant and doubles as the live demo of "blank account -> full catalogue".
 * Other suppliers are gated behind the feature_price_library add-on.
 */

require_once __DIR__ . '/app_settings.php';

if (function_exists('library_suppliers')) {
    return;
}

/**
 * The library's master/source tenant — where the curated catalogues live.
 * An explicit app-setting wins; otherwise it's the tenant of the first
 * super-admin user. Cached per request.
 */
function library_master_client_id(): int
{
    static $id = null;
    if ($id !== null) return $id;

    $override = (int) (app_setting_get('library_master_client_id', '0') ?? '0');
    if ($override > 0) { $id = $override; return $id; }

    try {
        $st = db()->query('SELECT client_id FROM client_users WHERE is_super_admin = 1 ORDER BY id LIMIT 1');
        $id = (int) ($st->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        $id = 0;
    }
    return $id;
}

/**
 * The suppliers in the library. v1 is a curated config list (the master
 * registry / DB-backed version comes later). Each: key, name, the master-side
 * product-name prefix to copy, and whether it's free.
 */
function library_suppliers(): array
{
    return [
        'beverley' => [
            'key'    => 'beverley',
            'name'   => 'Beverley Blinds Trade',
            'prefix' => 'Bev',
            'free'   => true,
            'blurb'  => 'Our own trade range — free to every account. The quickest way to a full, priced catalogue.',
        ],
        // Further suppliers (Decora, Galaxy, …) get added here (or a DB registry)
        // once their master catalogues are loaded. They'll have 'free' => false.
    ];
}

/** Does this tenant have the paid price-list library add-on enabled? */
function library_has_addon(int $clientId): bool
{
    try {
        $st = db()->prepare('SELECT COALESCE(feature_price_library, 0) FROM client_settings WHERE client_id = ? LIMIT 1');
        $st->execute([$clientId]);
        return ((int) $st->fetchColumn()) === 1;
    } catch (Throwable $e) {
        return false;   // column not migrated yet → treat as off
    }
}

/**
 * Which library suppliers has this tenant enabled?
 * @return array<string, array{enabled_at:?string, last_imported_at:?string}>
 */
function library_client_enabled(int $clientId): array
{
    $out = [];
    try {
        $st = db()->prepare(
            'SELECT supplier_key, enabled_at, last_imported_at
               FROM client_library_suppliers WHERE client_id = ?'
        );
        $st->execute([$clientId]);
        foreach ($st->fetchAll() as $r) {
            $out[(string) $r['supplier_key']] = [
                'enabled_at'       => $r['enabled_at'] ?? null,
                'last_imported_at' => $r['last_imported_at'] ?? null,
            ];
        }
    } catch (Throwable $e) { /* table absent — none enabled */ }
    return $out;
}
