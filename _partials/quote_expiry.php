<?php
declare(strict_types=1);

/**
 * Quote acceptance window.
 *
 * A quote's public link used to stay acceptable for ever, so a customer could
 * accept a months-old quote at the old price. A sent quote can now be accepted
 * for the company's own number of days after it was sent (Settings → Quoting →
 * Quote defaults, client_settings.quote_valid_days; default QUOTE_ACCEPT_DAYS,
 * 30, matching the default Terms). The link still opens after that — the
 * customer sees the quote and an "expired, please contact us" note instead of
 * the Accept button.
 *
 * The business renews it by re-sending the quote by email, or with "Renew for
 * N days" on the quote page — both restart the window from today.
 */

// Defined up front: PHP hoists this file's functions, so a function_exists()
// guard would return before any const below it ran. Include with require_once.
if (!defined('QUOTE_ACCEPT_DAYS')) {
    define('QUOTE_ACCEPT_DAYS', 30);   // the default, and the fallback before the migration
}

/** How many days this company's quotes can be accepted for (1–365, default 30). */
function quote_valid_days(int $clientId): int
{
    static $cache = [];
    if ($clientId <= 0) return QUOTE_ACCEPT_DAYS;
    if (!isset($cache[$clientId])) {
        $days = QUOTE_ACCEPT_DAYS;
        try {
            $s = db()->prepare('SELECT quote_valid_days FROM client_settings WHERE client_id = ? LIMIT 1');
            $s->execute([$clientId]);
            $v = $s->fetchColumn();
            if ($v !== false && $v !== null && (int) $v > 0) $days = min(365, (int) $v);
        } catch (Throwable $e) { /* not migrated yet — 30 days */ }
        $cache[$clientId] = $days;
    }
    return $cache[$clientId];
}

/** Unix time the quote stops being acceptable, or null if it has no send date. */
function quote_accept_deadline(array $quote): ?int
{
    $base = (string) ($quote['sent_at'] ?? '') ?: (string) ($quote['created_at'] ?? '');
    $ts   = $base !== '' ? strtotime($base) : false;
    return $ts ? $ts + quote_valid_days((int) ($quote['client_id'] ?? 0)) * 86400 : null;
}

/** True for a SENT quote whose acceptance window has passed. */
function quote_is_expired(array $quote): bool
{
    if ((string) ($quote['status'] ?? '') !== 'sent') return false;
    $deadline = quote_accept_deadline($quote);
    return $deadline !== null && time() > $deadline;
}
