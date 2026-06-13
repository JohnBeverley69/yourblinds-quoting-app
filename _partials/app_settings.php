<?php
declare(strict_types=1);

/**
 * Global (installation-wide) key/value settings — see migrate_app_settings.php.
 *
 * These are NOT per-tenant; they apply to the whole site. Keep the set small.
 * Every accessor is defensive: if the app_settings table doesn't exist yet
 * (migration not run), reads return the default and writes are a no-op, so the
 * app never 500s on a missing table.
 */

if (function_exists('app_setting_get')) {
    return;
}

/**
 * Read a global setting. Cached per-request (settings are read on hot paths
 * like the sidebar + mailer). Returns $default if the key or table is absent.
 */
function app_setting_get(string $key, ?string $default = null): ?string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $st = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $st->execute([$key]);
        $val = $st->fetchColumn();
        $cache[$key] = ($val === false) ? $default : (string) $val;
    } catch (Throwable $e) {
        // Table missing (pre-migration) or DB hiccup — fall back to default.
        $cache[$key] = $default;
    }
    return $cache[$key];
}

/** True if the global setting equals "1" (a ticked checkbox). Defensive. */
function app_setting_on(string $key): bool
{
    return app_setting_get($key, '0') === '1';
}

/**
 * Write a global setting (upsert). Returns true on success. Best-effort: a
 * missing table or DB error returns false rather than throwing.
 */
function app_setting_set(string $key, ?string $value): bool
{
    try {
        $st = db()->prepare(
            'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $st->execute([$key, $value]);
        return true;
    } catch (Throwable $e) {
        error_log('[YourBlinds] app_setting_set(' . $key . ') failed: ' . $e->getMessage());
        return false;
    }
}
