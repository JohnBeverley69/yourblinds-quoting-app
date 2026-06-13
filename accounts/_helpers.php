<?php
declare(strict_types=1);

/**
 * Accounts module — shared helpers. Required by the /accounts/*
 * pages and the quote-builder Payments section.
 */

/**
 * Allowed payment methods. Stored as the raw value; display label
 * is mapped via acct_method_label(). Adding a new method here is
 * the only change needed to surface it everywhere.
 */
function acct_methods(): array
{
    return [
        'cash'          => 'Cash',
        'card'          => 'Card',
        'bank_transfer' => 'Bank transfer',
        'cheque'        => 'Cheque',
        'paypal'        => 'PayPal',
        'stripe'        => 'Stripe',
        'gocardless'    => 'GoCardless',
        'other'         => 'Other',
    ];
}

function acct_method_label(string $method): string
{
    $map = acct_methods();
    return $map[$method] ?? ucfirst(str_replace('_', ' ', $method));
}

/**
 * Does the payments table have the optional payer_name column?
 *
 * payer_name records who a payment came from — most useful for
 * standalone payments (no linked order/customer to name). Added by
 * migrate_payment_payer.php. This probe lets the UI + save handler
 * degrade gracefully if a tenant hasn't run the migration yet, rather
 * than 500ing on an unknown column. Cached per-request.
 */
function acct_has_payer_column(): bool
{
    static $has = null;
    if ($has !== null) return $has;
    try {
        $st = db()->query("SHOW COLUMNS FROM payments LIKE 'payer_name'");
        $has = $st->fetchColumn() !== false;
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

/**
 * Sum of explicit payments recorded against a quote.
 */
function acct_payments_total_for_quote(PDO $pdo, int $quoteId): float
{
    $st = $pdo->prepare(
        'SELECT IFNULL(SUM(amount), 0)
           FROM payments WHERE quote_id = ?'
    );
    $st->execute([$quoteId]);
    return (float) $st->fetchColumn();
}

/**
 * Total received against a quote, INCLUDING the deposit if the
 * tenant ticked the deposit-paid flag on the quote edit page.
 * Compatible with tenants who've been using the deposit flag
 * since before the payments table existed — their deposit value
 * still counts toward the running paid-total.
 */
function acct_received_total_for_quote(PDO $pdo, array $quote): float
{
    $payments = acct_payments_total_for_quote($pdo, (int) $quote['id']);
    $deposit  = !empty($quote['deposit_paid_at']) ? (float) ($quote['deposit_amount'] ?? 0) : 0.0;
    return round($payments + $deposit, 2);
}

/**
 * Outstanding balance on a quote — what's still owed after all
 * payments + the (paid) deposit. Negative would mean overpaid;
 * we clamp the display side, not the value, so over-receipts are
 * visible to anyone reading the figure.
 */
function acct_outstanding_for_quote(PDO $pdo, array $quote): float
{
    return round((float) $quote['total'] - acct_received_total_for_quote($pdo, $quote), 2);
}

/**
 * Cheap money formatter — same idiom as qb_fmt_money but pulled in
 * here so accounts pages don't have to require the quote-builder
 * helpers just for one function.
 */
function acct_fmt_money($n): string
{
    return '£' . number_format((float) $n, 2);
}

/**
 * Is the paid Accounts add-on enabled for this tenant?
 *
 * Result is cached per-request so the sidebar + page-body + handler
 * gates don't all hit the DB separately. Defensive against the
 * column not existing yet (treats as disabled).
 */
function acct_feature_enabled(int $clientId): bool
{
    static $cache = [];
    if (array_key_exists($clientId, $cache)) {
        return $cache[$clientId];
    }
    try {
        $st = db()->prepare(
            'SELECT COALESCE(feature_accounts, 0)
               FROM client_settings WHERE client_id = ? LIMIT 1'
        );
        $st->execute([$clientId]);
        $cache[$clientId] = ((int) $st->fetchColumn()) === 1;
    } catch (Throwable $e) {
        // Column not present yet (pre-migration) — treat as disabled.
        $cache[$clientId] = false;
    }
    return $cache[$clientId];
}

/**
 * Server-side gate. Use at the top of any /accounts/* page or
 * payment handler — 403s if the tenant doesn't have the add-on.
 * Keeps URL-poking attackers out, even if their sidebar UI is
 * hiding the link.
 */
function acct_require_feature(int $clientId): void
{
    if (acct_feature_enabled($clientId)) return;
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>403 Forbidden</title>'
       . '<h1>Accounts module not enabled</h1>'
       . '<p>The Accounts add-on isn\'t enabled for your account. '
       . 'Contact your supplier to enable it.</p>'
       . '<p><a href="/calendar/index.php">Back to Calendar</a></p>';
    exit;
}
