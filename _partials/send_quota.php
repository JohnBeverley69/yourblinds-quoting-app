<?php
declare(strict_types=1);

/**
 * Daily cap on customer emails a tenant can send through the platform SMTP
 * (quote PDFs, invoices) — see migrate_tenant_send_log.php.
 *
 * WHY: anyone can self-sign-up for free Bronze, and the send screens take any
 * "to" address and free-text message, sent as noreply@yourblinds.uk. Without
 * a cap a throwaway account is a spam/phishing relay that wrecks the domain's
 * sending reputation for every tenant.
 *
 * Caps are per tenant per rolling 24h: free tier low, paid tiers generous.
 * The factory account and super-admins are never capped. Defensive: if the
 * log table isn't there yet, sending is allowed (the app never breaks on it).
 */

if (function_exists('send_quota_allows')) {
    return;
}

const SEND_QUOTA_FREE = 40;
const SEND_QUOTA_PAID = 300;

function send_quota_limit(int $clientId): int
{
    require_once __DIR__ . '/billing_helpers.php';
    try {
        return billing_current_tier_code($clientId) === 'free' ? SEND_QUOTA_FREE : SEND_QUOTA_PAID;
    } catch (Throwable $e) {
        return SEND_QUOTA_PAID;
    }
}

/** True if this tenant may send another customer email right now. */
function send_quota_allows(int $clientId): bool
{
    if (function_exists('is_super_admin') && is_super_admin()) return true;
    if (is_factory_client($clientId)) return true;
    try {
        $st = db()->prepare(
            'SELECT COUNT(*) FROM tenant_send_log
              WHERE client_id = ? AND created_at > (NOW() - INTERVAL 1 DAY)'
        );
        $st->execute([$clientId]);
        return (int) $st->fetchColumn() < send_quota_limit($clientId);
    } catch (Throwable $e) {
        return true;   // table not migrated yet — don't block sending
    }
}

/** Record one sent email against the tenant's daily cap. */
function send_quota_record(int $clientId, int $userId, string $kind): void
{
    try {
        db()->prepare('INSERT INTO tenant_send_log (client_id, user_id, kind) VALUES (?, ?, ?)')
            ->execute([$clientId, $userId, mb_substr($kind, 0, 30)]);
    } catch (Throwable $e) {
        // table not migrated yet — nothing to count against
    }
}
