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
