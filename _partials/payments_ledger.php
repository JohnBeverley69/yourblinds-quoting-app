<?php
declare(strict_types=1);

/**
 * Deposit-as-payment ledger helpers (migrate_deposit_to_payments.php).
 *
 * Once the deposit lives in the payments table (is_deposit = 1), every
 * received/outstanding figure is the sum of payments alone. BEFORE that
 * migration, the deposit is still counted from quotes.deposit_amount. Both
 * states are supported via payments_has_is_deposit() so the code is correct
 * whether or not the migration has run yet.
 *
 * Idempotent include.
 */
if (function_exists('payments_has_is_deposit')) return;

/**
 * Has the deposit-ledger migration run (payments.is_deposit exists)?
 * When true, a paid deposit IS a payment row, so callers must NOT add the
 * quote's deposit_amount on top (it would double-count). Cached per request.
 */
function payments_has_is_deposit(): bool
{
    static $has = null;
    if ($has !== null) return $has;
    try {
        $has = db()->query("SHOW COLUMNS FROM payments LIKE 'is_deposit'")->fetchColumn() !== false;
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

/**
 * The deposit amount to ADD to a payments sum for a quote, given that quote's
 * deposit fields. 0 once the deposit is its own payment row (post-migration),
 * else deposit_amount when the deposit is marked paid. Use this everywhere a
 * "received" figure is total deposit + payments, so it collapses to just
 * payments after the migration.
 */
function deposit_extra_for(?string $depositPaidAt, $depositAmount): float
{
    if (payments_has_is_deposit()) return 0.0;          // deposit is in payments now
    return !empty($depositPaidAt) ? (float) ($depositAmount ?? 0) : 0.0;
}

/**
 * Keep the single is_deposit payment row in sync with a quote's deposit state.
 * Called after any deposit change on the order. Creates / updates / removes the
 * row so the payments ledger always mirrors quotes.deposit_amount +
 * deposit_paid_at. No-op before the migration (column absent).
 */
function qb_sync_deposit_payment(PDO $pdo, int $quoteId, int $clientId): void
{
    if (!payments_has_is_deposit()) return;
    try {
        $q = $pdo->prepare(
            'SELECT customer_id, deposit_amount, deposit_paid_at
               FROM quotes WHERE id = ? AND client_id = ? LIMIT 1'
        );
        $q->execute([$quoteId, $clientId]);
        $r = $q->fetch();
        if (!$r) return;

        $paid = !empty($r['deposit_paid_at']);
        $amt  = round((float) ($r['deposit_amount'] ?? 0), 2);

        $ex = $pdo->prepare(
            'SELECT id FROM payments
              WHERE quote_id = ? AND client_id = ? AND is_deposit = 1 LIMIT 1'
        );
        $ex->execute([$quoteId, $clientId]);
        $depId = (int) ($ex->fetchColumn() ?: 0);

        if ($paid && $amt > 0) {
            $date = substr((string) $r['deposit_paid_at'], 0, 10) ?: date('Y-m-d');
            if ($depId > 0) {
                $pdo->prepare(
                    'UPDATE payments SET amount = ?, received_at = ?, customer_id = ?
                      WHERE id = ? AND client_id = ?'
                )->execute([$amt, $date, $r['customer_id'], $depId, $clientId]);
            } else {
                $pdo->prepare(
                    "INSERT INTO payments
                        (client_id, quote_id, customer_id, amount, received_at,
                         method, reference, is_deposit)
                     VALUES (?, ?, ?, ?, ?, 'deposit', 'Deposit', 1)"
                )->execute([$clientId, $quoteId, $r['customer_id'], $amt, $date]);
            }
        } elseif ($depId > 0) {
            // Deposit unpaid / cleared — remove its payment row.
            $pdo->prepare('DELETE FROM payments WHERE id = ? AND client_id = ?')
                ->execute([$depId, $clientId]);
        }
    } catch (Throwable $e) {
        error_log('qb_sync_deposit_payment failed: ' . $e->getMessage());
    }
}
