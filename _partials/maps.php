<?php
declare(strict_types=1);

/**
 * Navigation-app helpers. A tenant chooses Google Maps or Waze in Settings
 * (client_settings.map_provider); every "open in maps" address link routes
 * through here so the choice applies everywhere consistently.
 *
 * Only the clickable address deep-links honour the choice. The embedded
 * route map on the run-planner stays Google — Waze has no embeddable map.
 */

/**
 * The tenant's chosen navigation app: 'google' (default) or 'waze'.
 * Defensive against the migration not having run — falls back to 'google'.
 */
function map_provider_for(int $clientId): string
{
    static $cache = [];
    if (isset($cache[$clientId])) return $cache[$clientId];

    $provider = 'google';
    try {
        $st = db()->prepare(
            'SELECT map_provider FROM client_settings WHERE client_id = ? LIMIT 1'
        );
        $st->execute([$clientId]);
        $val = (string) ($st->fetchColumn() ?: '');
        if ($val === 'waze') $provider = 'waze';
    } catch (Throwable $e) {
        // column absent (migration not run) — keep Google
    }

    return $cache[$clientId] = $provider;
}

/**
 * Build a deep-link that opens the given address in the chosen app.
 * Returns '' for a blank address so callers can skip the icon.
 *
 *   Google: universal cross-platform search URL (iOS / Android / desktop).
 *   Waze:   universal ?q= link with navigate=yes to start guidance.
 */
function map_nav_url(string $address, string $provider = 'google'): string
{
    $address = trim($address);
    if ($address === '') return '';

    if ($provider === 'waze') {
        return 'https://waze.com/ul?q=' . rawurlencode($address) . '&navigate=yes';
    }

    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address);
}
