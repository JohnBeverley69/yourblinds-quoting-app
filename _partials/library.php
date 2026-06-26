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
 * The suppliers in the library, keyed by supplier_key. Each entry:
 *   key, name, prefix (master-side product-name prefix to copy), free, blurb,
 *   active.
 *
 * DB-backed (library_suppliers table) via migrate_library_registry.php. If that
 * table isn't there yet we fall back to the built-in default so the site keeps
 * working before the migration runs.
 *
 * @param bool $activeOnly true = only suppliers shown in the library (default);
 *                         false = every row, for the management screen.
 */
function library_suppliers(bool $activeOnly = true): array
{
    try {
        $sql = 'SELECT supplier_key, name, prefix, is_free, blurb, active
                  FROM library_suppliers';
        if ($activeOnly) $sql .= ' WHERE active = 1';
        $sql .= ' ORDER BY name';

        $out = [];
        foreach (db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $key = (string) $r['supplier_key'];
            $out[$key] = [
                'key'    => $key,
                'name'   => (string) $r['name'],
                'prefix' => (string) $r['prefix'],
                'free'   => ((int) $r['is_free']) === 1,
                'blurb'  => (string) ($r['blurb'] ?? ''),
                'active' => ((int) $r['active']) === 1,
            ];
        }
        return $out;   // table present — its contents are authoritative (may be empty)
    } catch (Throwable $e) {
        // table absent (migration not run) — use the built-in default
        return library_suppliers_default();
    }
}

/** The pre-registry default — one free supplier. Used only before the migration runs. */
function library_suppliers_default(): array
{
    return [
        'beverley' => [
            'key'    => 'beverley',
            'name'   => 'Beverley Blinds Trade',
            'prefix' => 'Bev',
            'free'   => true,
            'blurb'  => 'Our own trade range — free to every account. The quickest way to a full, priced catalogue.',
            'active' => true,
        ],
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
