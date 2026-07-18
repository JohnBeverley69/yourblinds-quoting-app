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
