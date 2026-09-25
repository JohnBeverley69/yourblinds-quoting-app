<?php
declare(strict_types=1);

/**
 * Quote acceptance window.
 *
 * A quote's public link used to stay acceptable for ever, so a customer could
 * accept a months-old quote at the old price. A sent quote can now be accepted
 * for QUOTE_ACCEPT_DAYS after it was sent (matching the default Terms, "valid
 * for 30 days"). The link still opens after that — the customer sees the quote
 * and an "expired, please contact us" note instead of the Accept button.
 *
 * The business renews it by re-sending the quote by email, or with "Renew for
 * 30 days" on the quote page — both restart the window from today.
 */

// Defined up front: PHP hoists this file's functions, so a function_exists()
// guard would return before any const below it ran. Include with require_once.
if (!defined('QUOTE_ACCEPT_DAYS')) {
    define('QUOTE_ACCEPT_DAYS', 30);
}

/** Unix time the quote stops being acceptable, or null if it has no send date. */
function quote_accept_deadline(array $quote): ?int
{
    $base = (string) ($quote['sent_at'] ?? '') ?: (string) ($quote['created_at'] ?? '');
    $ts   = $base !== '' ? strtotime($base) : false;
    return $ts ? $ts + QUOTE_ACCEPT_DAYS * 86400 : null;
}

/** True for a SENT quote whose acceptance window has passed. */
function quote_is_expired(array $quote): bool
{
    if ((string) ($quote['status'] ?? '') !== 'sent') return false;
    $deadline = quote_accept_deadline($quote);
    return $deadline !== null && time() > $deadline;
}
