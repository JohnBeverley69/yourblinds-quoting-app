<?php
declare(strict_types=1);

/**
 * Tiny key/value store for factory-wide settings that aren't per-tenant — the
 * scan-in secret to begin with. factory_kv(name, value). Defensive: returns the
 * default if the table isn't there yet.
 */

function fx_kv_get(PDO $pdo, string $name, ?string $default = null): ?string
{
    try {
        $s = $pdo->prepare('SELECT v FROM factory_kv WHERE k = ? LIMIT 1');
        $s->execute([$name]);
        $v = $s->fetchColumn();
        return $v === false ? $default : (string) $v;
    } catch (Throwable $e) { return $default; }
}

function fx_kv_set(PDO $pdo, string $name, string $value): void
{
    $pdo->prepare('INSERT INTO factory_kv (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)')
        ->execute([$name, $value]);
}

/** The shared secret a WiFi scanner puts in its URL (?key=…). '' if not set. */
function fx_scan_key(PDO $pdo): string
{
    return (string) fx_kv_get($pdo, 'scan_key', '');
}

/**
 * A per-area scan key identifies which production area a scanner belongs to.
 * Returns [id, client_id, name, scanner_name] for the area whose scan_key
 * matches, or null. Timing-safe compare is unnecessary here — a blank/short key
 * can't match a generated 24-char one, and the row lookup is by exact value.
 */
function fx_area_by_scan_key(PDO $pdo, string $key): ?array
{
    if (strlen($key) < 12) return null;   // real keys are long; skip cheap guesses
    try {
        // scanner_name is Phase E; if the column isn't migrated yet, fall back.
        try {
            $s = $pdo->prepare('SELECT id, client_id, name, scanner_name FROM production_areas WHERE scan_key = ? AND active = 1 LIMIT 1');
            $s->execute([$key]);
        } catch (Throwable $eCol) {
            $s = $pdo->prepare('SELECT id, client_id, name FROM production_areas WHERE scan_key = ? AND active = 1 LIMIT 1');
            $s->execute([$key]);
        }
        $r = $s->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    } catch (Throwable $e) { return null; }   // production_areas / scan_key not migrated
}
