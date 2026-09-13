<?php
declare(strict_types=1);

/**
 * Factory "new order received" email.
 *
 * When a tenant's order is PLACED and lands in a factory's manufacturing queue,
 * email the factory so they know work has come in — the way Blind Matrix does
 * ("A new Order … has been received. Please review the order."). Distinct from
 * the customer-acceptance notification (which tells the SELLING business their
 * customer accepted); this tells the MAKING factory a job has arrived.
 *
 * Recipient: the factory's own notification address (client_settings.
 * order_notify_email of the factory that owns the products). Only fires for a
 * genuine tenant → factory hand-off (the order's owning client is a DIFFERENT
 * client than the factory) — a factory's own orders are covered by the accept
 * notification, so we don't double-email.
 *
 * Idempotent: stamps quotes.factory_notified_at so re-placing / multiple
 * placement paths don't re-send. Best-effort — never throws to the caller.
 */

function factory_notify_new_order(PDO $pdo, int $quoteId): void
{
    if ($quoteId <= 0) return;
    try {
        if (!function_exists('mailer_send')) { require_once __DIR__ . '/../mailer.php'; }

        // Quote + owning business. factory_notified_at is guarded (may be pre-migration).
        $hasStamp = false;
        try { $pdo->query('SELECT `factory_notified_at` FROM quotes LIMIT 0'); $hasStamp = true; } catch (Throwable $e) {}
        $sel = 'SELECT q.id, q.quote_number, q.end_customer_name, q.client_id, q.account_client_id'
             . ($hasStamp ? ', q.factory_notified_at' : '')
             . ', c.company_name AS owner_company
                 FROM quotes q JOIN clients c ON c.id = q.client_id
                WHERE q.id = ? LIMIT 1';
        $st = $pdo->prepare($sel);
        $st->execute([$quoteId]);
        $q = $st->fetch(PDO::FETCH_ASSOC);
        if (!$q) return;
        if ($hasStamp && !empty($q['factory_notified_at'])) return;   // already told

        // The factory that MAKES this order = owner of its products
        // (COALESCE(source_client_id, client_id)). Take the dominant one.
        $fs = $pdo->prepare(
            "SELECT COALESCE(NULLIF(p.source_client_id,0), p.client_id) AS fac, COUNT(*) AS n
               FROM quote_items qi JOIN products p ON p.id = qi.product_id
              WHERE qi.quote_id = ?
           GROUP BY fac ORDER BY n DESC LIMIT 1"
        );
        $fs->execute([$quoteId]);
        $factoryId = (int) ($fs->fetchColumn() ?: 0);

        // Only a genuine tenant → factory hand-off. A factory making its OWN
        // order already gets the accept notification, so skip to avoid a double.
        if ($factoryId <= 0 || $factoryId === (int) $q['client_id']) return;

        // The factory's notification address.
        $notifyTo = '';
        try {
            $ns = $pdo->prepare('SELECT order_notify_email FROM client_settings WHERE client_id = ? LIMIT 1');
            $ns->execute([$factoryId]);
            $notifyTo = trim((string) ($ns->fetchColumn() ?: ''));
        } catch (Throwable $e) { return; }   // column absent — nothing to send to
        if ($notifyTo === '' || !filter_var($notifyTo, FILTER_VALIDATE_EMAIL)) return;

        // "from <business>" — the trade account/tenant who placed it, plus the
        // end customer where there is one.
        $business = trim((string) ($q['owner_company'] ?? '')) ?: 'a trade account';
        $endCust  = trim((string) ($q['end_customer_name'] ?? ''));
        $fromWho  = $endCust !== '' ? ($business . ' (' . $endCust . ')') : $business;
        $qNum     = (string) $q['quote_number'];

        $appUrl = trim((string) (function_exists('env') ? (env('APP_URL', '') ?? '') : ''));
        $link   = ($appUrl !== '' ? rtrim($appUrl, '/') : '') . '/factory/incoming-orders.php';

        $subject = sprintf('New order — %s from %s', $qNum, $business);
        $lines = [
            'Hello,',
            '',
            'A new order ' . $qNum . ' from ' . $fromWho . ' has been placed and is in your factory queue.',
        ];
        if ($link !== '/factory/incoming-orders.php') {
            $lines[] = '';
            $lines[] = 'Review it: ' . $link;
        }
        mailer_send($notifyTo, $subject, implode("\n", $lines));

        if ($hasStamp) {
            try { $pdo->prepare('UPDATE quotes SET factory_notified_at = NOW() WHERE id = ?')->execute([$quoteId]); }
            catch (Throwable $e) { /* stamp best-effort */ }
        }
    } catch (Throwable $e) {
        error_log('factory_notify_new_order skipped for quote ' . $quoteId . ': ' . $e->getMessage());
    }
}
